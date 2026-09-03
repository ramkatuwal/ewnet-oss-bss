<?php

namespace App\Services\Integrations\Uisp;

use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\LibreNmsObject;
use App\Models\Site;
use App\Models\SiteExternalReference;
use Illuminate\Support\Facades\Log;

/**
 * UISP Device → EWNET Asset Reconciliation
 *
 * SOURCE: ONE UISP device from the API
 * DESTINATION: Existing EWNET assets in PostgreSQL
 * NEVER: Compare UISP devices against each other
 */
class UispDuplicateDetector
{
    /**
     * Analyze a UISP device against existing EWNET Assets.
     *
     * @param array $uispDevice ONE UISP device from API (SOURCE)
     * @return array Reconciliation result with destination asset(s)
     */
    public function analyzeDevice(array $uispDevice): array
    {
        // === STEP 1: Extract SOURCE device data ===
        $externalId = $uispDevice['id'] ?? $uispDevice['identification']['id'] ?? null;
        if (!$externalId) {
            return ['action' => 'error', 'reason' => 'Missing external ID'];
        }

        $identification = $uispDevice['identification'] ?? [];
        $serial = $this->normalizeSerial($identification['serialNumber'] ?? null);
        $mac = $this->normalizeMac($identification['mac'] ?? null);
        $name = $this->normalizeName($identification['name'] ?? null);
        $ip = $this->normalizeIp($uispDevice['ipAddress'] ?? null);
        $model = $identification['model'] ?? $identification['modelName'] ?? null;
        $vendor = $identification['vendor'] ?? $identification['vendorName'] ?? null;

        // === STEP 2: Search DESTINATION (EWNET assets) ===
        $candidates = [];
        $evidence = [];

        // 2a: Check External Reference (already linked)
        $uispRef = AssetExternalReference::where('provider', 'uisp')
            ->where('external_type', 'device')
            ->where('external_id', $externalId)
            ->first();

        if ($uispRef && $uispRef->asset) {
            $candidates[$uispRef->asset_id] = $uispRef->asset;
            $evidence[$uispRef->asset_id][] = [
                'field' => 'external_id',
                'strength' => 'exact',
                'value' => $externalId,
            ];
        }

        // 2b: Check MAC Address (STRONG)
        if ($mac && $mac !== '00:00:00:00:00:00') {
            $macAsset = Asset::whereJsonContains('specifications', ['mac_address' => $mac])->first();
            if ($macAsset) {
                $candidates[$macAsset->id] = $macAsset;
                $evidence[$macAsset->id][] = [
                    'field' => 'mac',
                    'strength' => 'strong',
                    'value' => $mac,
                ];
            }
        }

        // 2c: Check Serial Number (STRONG)
        if ($serial) {
            $serialAsset = Asset::where('serial_number', $serial)->first();
            if ($serialAsset) {
                $candidates[$serialAsset->id] = $serialAsset;
                $evidence[$serialAsset->id][] = [
                    'field' => 'serial',
                    'strength' => 'strong',
                    'value' => $serial,
                ];
            }
        }

        // 2d: Check IP Address (MODERATE)
        if ($ip) {
            $ipAsset = Asset::where(function($query) use ($ip) {
                $query->whereJsonContains('specifications', ['ip_address' => $ip])
                      ->orWhereJsonContains('specifications', ['ip' => $ip]);
            })->first();

            if ($ipAsset) {
                $candidates[$ipAsset->id] = $ipAsset;
                $evidence[$ipAsset->id][] = [
                    'field' => 'ip',
                    'strength' => 'moderate',
                    'value' => $ip,
                ];
            }
        }

        // 2e: Check Name (WEAK - EXACT match only)
        if ($name) {
            $nameAsset = Asset::where('description', $name)->first();
            if ($nameAsset) {
                $candidates[$nameAsset->id] = $nameAsset;
                $evidence[$nameAsset->id][] = [
                    'field' => 'name',
                    'strength' => 'weak',
                    'value' => $name,
                ];
            }
        }

        // 2f: Check LibreNMS Cross-Provider (STRONG)
        if ($mac && $mac !== '00:00:00:00:00:00') {
            $libreNmsRef = LibreNmsObject::where('data->mac', $mac)->first();
            if ($libreNmsRef) {
                $libreAsset = Asset::whereJsonContains('specifications', ['librenms_device_id' => $libreNmsRef->external_id])->first();
                if ($libreAsset) {
                    $candidates[$libreAsset->id] = $libreAsset;
                    $evidence[$libreAsset->id][] = [
                        'field' => 'librenms',
                        'strength' => 'strong',
                        'value' => $libreNmsRef->external_id,
                    ];
                }
            }
        }

        // === STEP 3: Analyze DESTINATION candidates ===
        $candidateIds = array_keys($candidates);
        $uniqueCandidateIds = array_unique($candidateIds);
        $candidateCount = count($uniqueCandidateIds);

        // Helper: Extract match field names from evidence
        $extractMatchFields = function($evidenceList) {
            return array_map(function($ev) {
                return $ev['field'];
            }, $evidenceList);
        };

        // 3a: NO DESTINATION → CREATE
        if ($candidateCount === 0) {
            return [
                'action' => 'create',
                'confidence' => 'none',
                'asset_id' => null,
                'asset' => null,
                'reason' => 'No matching EWNET asset found. Safe to create.',
                'evidence' => [],
                'matches' => [],
                'candidates' => [],
                'serial' => $serial,
                'mac' => $mac,
                'name' => $name,
                'ip' => $ip,
                'model' => $model,
                'vendor' => $vendor,
                'external_id' => $externalId,
            ];
        }

        // 3b: ONE DESTINATION → LINK
        if ($candidateCount === 1) {
            $assetId = $uniqueCandidateIds[0];
            $asset = $candidates[$assetId];
            $assetEvidence = $evidence[$assetId] ?? [];

            $matchFields = $extractMatchFields($assetEvidence);

            $ipChanged = false;
            $assetIp = $asset->specifications['ip_address'] ?? $asset->specifications['ip'] ?? null;
            if ($ip && $assetIp && $assetIp !== $ip) {
                $ipChanged = true;
            }

            return [
                'action' => 'link',
                'confidence' => $this->calculateConfidence($assetEvidence),
                'asset_id' => $assetId,
                'asset' => $asset,
                'reason' => $this->buildReason($assetEvidence, $ipChanged),
                'evidence' => $assetEvidence,
                'matches' => $matchFields,  // ← FIXED: Now populated!
                'candidates' => [$assetId => $asset],
                'ip_changed' => $ipChanged,
                'serial' => $serial,
                'mac' => $mac,
                'name' => $name,
                'ip' => $ip,
                'model' => $model,
                'vendor' => $vendor,
                'external_id' => $externalId,
            ];
        }

        // 3c: MULTIPLE DESTINATION CANDIDATES → REVIEW / CONFLICT
        $strongMatchFound = false;
        $strongMatchAssetId = null;
        foreach ($evidence as $assetId => $evidences) {
            foreach ($evidences as $ev) {
                if (in_array($ev['strength'], ['exact', 'strong'])) {
                    $strongMatchFound = true;
                    $strongMatchAssetId = $assetId;
                    break 2;
                }
            }
        }

        if ($strongMatchFound && $candidateCount === 2) {
            $asset = $candidates[$strongMatchAssetId];
            $assetEvidence = $evidence[$strongMatchAssetId] ?? [];
            $matchFields = $extractMatchFields($assetEvidence);

            return [
                'action' => 'link',
                'confidence' => 'strong',
                'asset_id' => $strongMatchAssetId,
                'asset' => $strongMatchAssetId,
                'reason' => 'Strong evidence (MAC/serial) identifies one asset. Weak name match reviewed.',
                'evidence' => $assetEvidence,
                'matches' => $matchFields,  // ← FIXED: Now populated!
                'candidates' => $candidates,
                'weak_matches' => array_filter($evidence, function($evs, $id) use ($strongMatchAssetId) {
                    return $id !== $strongMatchAssetId;
                }, ARRAY_FILTER_USE_BOTH),
                'serial' => $serial,
                'mac' => $mac,
                'name' => $name,
                'ip' => $ip,
                'model' => $model,
                'vendor' => $vendor,
                'external_id' => $externalId,
            ];
        }

        // Multiple candidates with conflicting strong evidence → CONFLICT
        return [
            'action' => 'conflict',
            'confidence' => 'conflict',
            'asset_id' => null,
            'asset' => null,
            'reason' => 'Multiple EWNET assets match this device. Manual review required.',
            'evidence' => $evidence,
            'matches' => [],  // No single match
            'candidates' => $candidates,
            'candidate_ids' => $uniqueCandidateIds,
            'serial' => $serial,
            'mac' => $mac,
            'name' => $name,
            'ip' => $ip,
            'model' => $model,
            'vendor' => $vendor,
            'external_id' => $externalId,
        ];
    }

    protected function calculateConfidence(array $evidence): string
    {
        foreach ($evidence as $ev) {
            if ($ev['strength'] === 'exact') return 'exact';
            if ($ev['strength'] === 'strong') return 'strong';
            if ($ev['strength'] === 'moderate') return 'moderate';
        }
        return 'weak';
    }

    protected function buildReason(array $evidence, bool $ipChanged = false): string
    {
        $parts = [];
        foreach ($evidence as $ev) {
            $parts[] = ucfirst($ev['field']) . ' match';
        }
        $reason = implode('; ', $parts);
        if ($ipChanged) {
            $reason .= '; IP changed (will update)';
        }
        return $reason ?: 'No evidence';
    }

    public function analyzeSite(array $uispSite): array
    {
        $externalId = $uispSite['id'] ?? null;
        if (!$externalId) {
            return ['action' => 'error', 'reason' => 'Missing external ID'];
        }

        $name = $this->normalizeName($uispSite['name'] ?? null);
        $location = $uispSite['location'] ?? [];
        $address = $uispSite['address'] ?? [];

        $uispRef = SiteExternalReference::where('provider', 'uisp')
            ->where('external_type', 'site')
            ->where('external_id', $externalId)
            ->first();

        if ($uispRef && $uispRef->site) {
            return [
                'action' => 'link',
                'confidence' => 'exact',
                'site_id' => $uispRef->site_id,
                'site' => $uispRef->site,
                'reason' => 'Exact UISP external reference exists.',
                'evidence' => [['field' => 'external_id', 'strength' => 'exact']],
                'matches' => ['external_id'],
                'name' => $name,
                'address' => $address['fullAddress'] ?? null,
                'latitude' => $location['lat'] ?? null,
                'longitude' => $location['lon'] ?? null,
                'external_id' => $externalId,
            ];
        }

        if ($name) {
            $site = Site::where('name', $name)->first();
            if ($site) {
                return [
                    'action' => 'link',
                    'confidence' => 'moderate',
                    'site_id' => $site->id,
                    'site' => $site,
                    'reason' => "Name match found: '{$name}'",
                    'evidence' => [['field' => 'name', 'strength' => 'moderate']],
                    'matches' => ['name'],
                    'name' => $name,
                    'address' => $address['fullAddress'] ?? null,
                    'latitude' => $location['lat'] ?? null,
                    'longitude' => $location['lon'] ?? null,
                    'external_id' => $externalId,
                ];
            }
        }

        return [
            'action' => 'create',
            'confidence' => 'none',
            'site_id' => null,
            'site' => null,
            'reason' => 'No matching Site found. Safe to create.',
            'evidence' => [],
            'matches' => [],
            'name' => $name,
            'address' => $address['fullAddress'] ?? null,
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lon'] ?? null,
            'external_id' => $externalId,
        ];
    }

    protected function normalizeSerial(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        if (in_array(strtoupper($value), ['N/A', 'NA', 'UNKNOWN', 'NONE', '-', ''])) {
            return null;
        }
        return $value;
    }

    protected function normalizeMac(?string $value): ?string
    {
        if (!$value) return null;
        $value = preg_replace('/[^a-fA-F0-9]/', '', $value);
        if (strlen($value) !== 12) return null;
        if ($value === '000000000000') return null;
        return strtolower(implode(':', str_split($value, 2)));
    }

    protected function normalizeName(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if (strlen($value) < 2) return null;
        return $value;
    }

    protected function normalizeIp(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);
        $value = preg_replace('/\/\d+$/', '', $value);
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
        return null;
    }
}
