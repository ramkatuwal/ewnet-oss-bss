<?php

namespace App\Services\Imports;

use App\Contracts\ImportSourceInterface;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\AssetInterface;
use App\Models\IpAddress;
use App\Services\ManagementScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenericImportService
{
    protected ImportSourceInterface $source;
    protected ReconciliationEngine $engine;
    protected $user;

    public function __construct($source)
    {
        if (!$source instanceof ImportSourceInterface) {
            throw new \InvalidArgumentException(
                'Source must implement ImportSourceInterface. Got: ' . get_class($source)
            );
        }

        $this->source = $source;
        $this->engine = new ReconciliationEngine();
        $this->user = auth()->user();
    }

    public function preview(string $type): array
    {
        $records = $type === 'devices' ? $this->source->fetchDevices() : $this->source->fetchSites();
        $normalized = [];
        $decisions = [];

        foreach ($records as $raw) {
            try {
                $norm = $type === 'devices' ? $this->source->normalizeDevice($raw) : $this->source->normalizeSite($raw);
                $normalized[] = $norm;
                $decisions[] = [
                    'record' => $norm->toArray(),
                    'analysis' => $this->engine->reconcile($norm)
                ];
            } catch (\Exception $e) {
                Log::error('Import normalization failed', ['error' => $e->getMessage(), 'raw' => $raw]);
            }
        }

        return [
            'source' => $this->source->getIdentity(),
            'fetched_at' => now()->toISOString(),
            'total' => count($normalized),
            'records' => $decisions
        ];
    }

    public function execute(array $selectedItems): array
    {
        $results = [
            'success' => true,
            'processed' => 0,
            'created' => 0,
            'linked' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => []
        ];

        DB::transaction(function () use ($selectedItems, &$results) {
            foreach ($selectedItems as $item) {
                $results['processed']++;
                $detail = [
                    'source_id' => $item['record']['external_id'] ?? 'unknown',
                    'name' => $item['record']['name'] ?? 'unknown',
                    'status' => 'pending',
                    'message' => ''
                ];

                try {
                    $this->processItem($item, $results);
                    $detail['status'] = 'success';
                    $detail['message'] = "Successfully processed.";
                } catch (\Exception $e) {
                    $results['failed']++;
                    $detail['status'] = 'failed';
                    $detail['message'] = $e->getMessage();
                    Log::error('Import item failed', [
                        'source_id' => $detail['source_id'],
                        'error' => $e->getMessage()
                    ]);
                }
                $results['details'][] = $detail;
            }
        });

        if ($results['failed'] > 0) {
            $results['success'] = false;
        }

        return $results;
    }

    protected function processItem(array $item, array &$results): void
    {
        $recordData = $item['record'];
        $analysis = $item['analysis'];

        if ($analysis['decision'] === 'CONFLICT') {
            $results['skipped']++;
            throw new \Exception('Record has unresolved conflicts.');
        }

        if ($recordData['source_type'] === 'device') {
            $this->processAsset($recordData, $analysis, $results);
        } else {
            $this->processSite($recordData, $analysis, $results);
        }
    }

    protected function processAsset(array $data, array $analysis, array &$results): void
    {
        $destId = $analysis['destination_id'];

        if ($destId) {
            // EXISTING ASSET — verify user has scope
            $asset = Asset::find($destId);
            if (!$asset) throw new \Exception("Destination asset #{$destId} not found.");
            
            // Verify management scope
            if (!$this->isAssetInScope($asset)) {
                throw new \Exception("You do not have permission to update asset #{$destId}");
            }

            $results['linked']++;
            AssetExternalReference::updateOrCreate(
                ['provider' => $data['provider'], 'external_type' => 'device', 'external_id' => $data['external_id']],
                ['asset_id' => $asset->id]
            );
        } else {
            // NEW ASSET — determine destination site first
            $siteId = $this->resolveSiteId($data);
            
            // Verify the site is in scope
            if (!$this->isSiteInScope($siteId)) {
                throw new \Exception("You do not have permission to create assets at site #{$siteId}");
            }

            $assetTag = $this->generateAssetTag($data['external_id']);
            $assetTag = $this->ensureUniqueAssetTag($assetTag);

            $asset = Asset::create([
                'site_id' => $siteId,
                'asset_tag' => $assetTag,
                'serial_number' => $data['serial_number'] ?? null,
                'category' => 'NETWORK',
                'type' => $data['type'] ?? 'device',
                'manufacturer' => $data['manufacturer'] ?? null,
                'model' => $data['model'] ?? null,
                'status' => $data['status'] ?? 'OPERATIONAL',
                'condition' => 'GOOD',
                'quantity' => 1,
                'unit' => 'pcs',
                'specifications' => $data['metadata'] ?? [],
                'description' => $data['name'] ?? null,
            ]);

            AssetExternalReference::create([
                'asset_id' => $asset->id,
                'provider' => $data['provider'],
                'external_type' => 'device',
                'external_id' => $data['external_id'],
                'metadata' => ['imported_at' => now()],
            ]);

            $results['created']++;
        }

        // Process interfaces and IPs for this asset
        $this->processInterfacesAndIps($data, $asset->id, $results);
    }

    protected function processSite(array $data, array $analysis, array &$results): void
    {
        // Site processing with scope enforcement
        $destId = $analysis['destination_id'];

        if ($destId) {
            // EXISTING SITE — verify user has scope
            $site = Site::find($destId);
            if (!$site) throw new \Exception("Destination site #{$destId} not found.");
            
            if (!$this->isSiteInScope($site->id)) {
                throw new \Exception("You do not have permission to update site #{$destId}");
            }
            $results['linked']++;
        } else {
            // NEW SITE — verify scope based on organization context
            $orgContext = $this->resolveSiteOrganization($data);
            if (!$this->isOrganizationInScope($orgContext)) {
                throw new \Exception("You do not have permission to create sites in this organization");
            }

            $site = Site::create([
                'site_code' => $this->generateSiteCode($data['name']),
                'name' => $data['name'] ?? 'Unknown Site',
                'type' => 'pop',
                'status' => 'active',
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'company_id' => $orgContext['company_id'] ?? null,
                'region_id' => $orgContext['region_id'] ?? null,
                'branch_id' => $orgContext['branch_id'] ?? null,
            ]);

            SiteExternalReference::create([
                'site_id' => $site->id,
                'provider' => $data['provider'],
                'external_type' => 'site',
                'external_id' => $data['external_id'],
                'metadata' => ['imported_at' => now()],
            ]);

            $results['created']++;
        }
    }

    protected function isAssetInScope(Asset $asset): bool
    {
        if ($this->user->hasRole('Super Admin')) return true;
        return ManagementScopeService::isInScope($this->user, $asset);
    }

    protected function isSiteInScope(int $siteId): bool
    {
        if ($this->user->hasRole('Super Admin')) return true;
        $site = Site::find($siteId);
        if (!$site) return false;
        return ManagementScopeService::isInScope($this->user, $site);
    }

    protected function isOrganizationInScope(array $orgContext): bool
    {
        if ($this->user->hasRole('Super Admin')) return true;
        
        // Check if user has scope for this organization level
        if (!empty($orgContext['company_id'])) {
            $company = \App\Models\Company::find($orgContext['company_id']);
            if ($company && ManagementScopeService::isInScope($this->user, $company)) {
                return true;
            }
        }
        // Additional org-level checks can be added here
        return false;
    }

    protected function resolveSiteId(array $data): int
    {
        // Try to resolve site from metadata
        if (isset($data['metadata']['site_id'])) {
            return (int) $data['metadata']['site_id'];
        }
        // Default to first site or throw
        $site = Site::first();
        if (!$site) {
            throw new \Exception('No site available for asset creation');
        }
        return $site->id;
    }

    protected function resolveSiteOrganization(array $data): array
    {
        // Try to resolve organization context from metadata
        return [
            'company_id' => $data['metadata']['company_id'] ?? null,
            'region_id' => $data['metadata']['region_id'] ?? null,
            'branch_id' => $data['metadata']['branch_id'] ?? null,
        ];
    }

    protected function generateAssetTag(string $externalId): string
    {
        return 'IMP-' . substr($externalId, 0, 8);
    }

    protected function ensureUniqueAssetTag(string $assetTag): string
    {
        $baseTag = $assetTag;
        $counter = 1;
        while (Asset::where('asset_tag', $assetTag)->exists()) {
            $assetTag = $baseTag . '-' . $counter++;
        }
        return $assetTag;
    }

    protected function generateSiteCode(string $name): string
    {
        return 'SITE-' . strtoupper(substr(md5($name . time()), 0, 8));
    }

    protected function processInterfacesAndIps(array $data, int $assetId, array &$results): void
    {
        $interfaces = $data['interfaces'] ?? [];

        foreach ($interfaces as $interfaceData) {
            $this->processSingleInterface($interfaceData, $assetId, $results);
        }
    }

    protected function processSingleInterface(array $interfaceData, int $assetId, array &$results): void
    {
        // Verify asset is in scope before creating/updating interface
        $asset = Asset::find($assetId);
        if (!$asset || !$this->isAssetInScope($asset)) {
            $results['failed']++;
            return;
        }

        $decision = $this->engine->reconcileInterface($interfaceData, $assetId);

        if ($decision['decision'] === 'CONFLICT') {
            $results['skipped']++;
            return;
        }

        $interface = null;

        if ($decision['decision'] === 'LINK' && !empty($decision['destination_id'])) {
            $interface = AssetInterface::find($decision['destination_id']);
            if ($interface) {
                $interface->update($this->prepareInterfaceData($interfaceData, $assetId));
                $results['updated']++;
            }
        } else {
            $interface = AssetInterface::create($this->prepareInterfaceData($interfaceData, $assetId));
            $results['created']++;
        }

        if (!$interface) {
            $results['failed']++;
            return;
        }

        $ips = $interfaceData['ip_addresses'] ?? [];
        foreach ($ips as $ipData) {
            $this->processSingleIp($ipData, $interface->id, $assetId, $results);
        }
    }

    protected function processSingleIp(array $ipData, int $interfaceId, int $assetId, array &$results): void
    {
        // IP authorization inherits from Asset
        $asset = Asset::find($assetId);
        if (!$asset || !$this->isAssetInScope($asset)) {
            $results['failed']++;
            return;
        }

        $decision = $this->engine->reconcileIpAddress($ipData, $interfaceId, $assetId);

        if ($decision['decision'] === 'CONFLICT') {
            $results['skipped']++;
            return;
        }

        if ($decision['decision'] === 'LINK' && !empty($decision['destination_id'])) {
            $ip = IpAddress::find($decision['destination_id']);
            if ($ip) {
                $ip->update($this->prepareIpData($ipData, $interfaceId));
                $results['updated']++;
            }
        } else {
            IpAddress::create($this->prepareIpData($ipData, $interfaceId));
            $results['created']++;
        }
    }

    protected function prepareInterfaceData(array $data, int $assetId): array
    {
        return [
            'asset_id' => $assetId,
            'name' => $data['name'] ?? 'unknown',
            'display_name' => $data['display_name'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'speed' => $data['speed'] ?? null,
            'status' => $data['status'] ?? 'active',
            'is_management' => $data['is_management'] ?? false,
            'provider' => $data['provider'] ?? null,
            'external_type' => $data['external_type'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    protected function prepareIpData(array $data, int $interfaceId): array
    {
        return [
            'asset_interface_id' => $interfaceId,
            'ip_address' => $data['ip'] ?? null,
            'family' => $data['family'] ?? null,
            'prefix_length' => $data['prefix'] ?? null,
            'is_primary' => $data['is_primary'] ?? false,
            'is_management' => $data['is_management'] ?? false,
            'provider' => $data['provider'] ?? null,
            'external_type' => $data['external_type'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
