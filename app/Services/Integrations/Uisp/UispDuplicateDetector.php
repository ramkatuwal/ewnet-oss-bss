<?php

namespace App\Services\Integrations\Uisp;

use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\LibreNmsObject;
use App\Models\Site;
use App\Models\SiteExternalReference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UispDuplicateDetector
{
    protected array $matches = [];
    protected array $siteMatches = [];

    /**
     * Analyze a UISP device against existing EWNET Assets.
     */
    public function analyzeDevice(array $uispDevice): array
    {
        $externalId = $uispDevice['id'] ?? $uispDevice['identification']['id'] ?? null;
        if (!$externalId) {
            return ['action' => 'error', 'reason' => 'Missing external ID'];
        }

        // Extract device details
        $identification = $uispDevice['identification'] ?? [];
        $serial = $this->normalizeSerial($identification['serialNumber'] ?? null);
        $mac = $this->normalizeMac($identification['mac'] ?? null);
        $name = $this->normalizeName($identification['name'] ?? null);
        // FIX: IP is at root level, not in overview
        $ip = $this->normalizeIp($uispDevice['ipAddress'] ?? null);
        $model = $identification['model'] ?? $identification['modelName'] ?? null;
        $vendor = $identification['vendor'] ?? $identification['vendorName'] ?? null;

        $matches = [];
        $action = 'create';
        $confidence = 'none';
        $asset = null;
        $assetId = null;

        // CHECK 1: UISP External Reference
        $uispRef = AssetExternalReference::where('provider', 'uisp')
            ->where('external_type', 'device')
            ->where('external_id', $externalId)
            ->first();

        if ($uispRef && $uispRef->asset) {
            $asset = $uispRef->asset;
            $assetId = $asset->id;
            return [
                'action' => 'link',
                'confidence' => 'exact',
                'asset_id' => $assetId,
                'asset' => $asset,
                'reason' => 'Exact UISP external reference exists.',
                'matches' => ['external_id' => true],
                'serial' => $serial,
                'mac' => $mac,
                'name' => $name,
                'ip' => $ip,
                'model' => $model,
                'vendor' => $vendor,
                'external_id' => $externalId,
            ];
        }

        // CHECK 2: Serial Number (if valid)
        if ($serial) {
            $serialAsset = Asset::where('serial_number', $serial)->first();
            if ($serialAsset) {
                $matches[] = 'serial';
                $action = 'link';
                $confidence = 'strong';
                $asset = $serialAsset;
                $assetId = $asset->id;
            }
        }

        // CHECK 3: MAC Address (in specifications)
        if ($mac && !$asset) {
            $macAsset = Asset::whereJsonContains('specifications', ['mac_address' => $mac])->first();
            if ($macAsset) {
                $matches[] = 'mac';
                $action = 'link';
                $confidence = 'strong';
                $asset = $macAsset;
                $assetId = $asset->id;
            }
        }

        // CHECK 4: LibreNMS Cross-Provider
        if ($mac && !$asset) {
            $libreNmsRef = LibreNmsObject::where('data->mac', $mac)->first();
            if ($libreNmsRef) {
                $libreAsset = Asset::whereJsonContains('specifications', ['librenms_device_id' => $libreNmsRef->external_id])->first();
                if ($libreAsset) {
                    $matches[] = 'librenms';
                    $action = 'link';
                    $confidence = 'strong';
                    $asset = $libreAsset;
                    $assetId = $asset->id;
                }
            }
        }

        // CHECK 5: IP Address
        if ($ip && !$asset) {
            $ipAsset = Asset::whereJsonContains('specifications', ['ip_address' => $ip])->first();
            if ($ipAsset) {
                $matches[] = 'ip';
                $action = 'review';
                $confidence = 'moderate';
                $asset = $ipAsset;
                $assetId = $asset->id;
            }
        }

        // CHECK 6: Name (weakest)
        if ($name && !$asset) {
            $nameAsset = Asset::where('description', 'ilike', $name)->first();
            if ($nameAsset) {
                $matches[] = 'name';
                $action = 'review';
                $confidence = 'weak';
                $asset = $nameAsset;
                $assetId = $asset->id;
            }
        }

        // If we have an asset, determine if it's a conflict or safe link
        if ($asset) {
            // Check if serial or MAC conflicts
            if ($serial && $asset->serial_number && $asset->serial_number !== $serial) {
                return [
                    'action' => 'conflict',
                    'confidence' => 'conflict',
                    'asset_id' => $asset->id,
                    'asset' => $asset,
                    'reason' => "Serial number mismatch: UISP '{$serial}' vs Asset '{$asset->serial_number}'",
                    'matches' => array_combine($matches, array_fill(0, count($matches), true)),
                    'serial' => $serial,
                    'mac' => $mac,
                    'name' => $name,
                    'ip' => $ip,
                    'model' => $model,
                    'vendor' => $vendor,
                    'external_id' => $externalId,
                ];
            }

            if ($mac && isset($asset->specifications['mac_address']) && $asset->specifications['mac_address'] !== $mac) {
                return [
                    'action' => 'conflict',
                    'confidence' => 'conflict',
                    'asset_id' => $asset->id,
                    'asset' => $asset,
                    'reason' => "MAC address mismatch: UISP '{$mac}' vs Asset '{$asset->specifications['mac_address']}'",
                    'matches' => array_combine($matches, array_fill(0, count($matches), true)),
                    'serial' => $serial,
                    'mac' => $mac,
                    'name' => $name,
                    'ip' => $ip,
                    'model' => $model,
                    'vendor' => $vendor,
                    'external_id' => $externalId,
                ];
            }

            return [
                'action' => $action,
                'confidence' => $confidence,
                'asset_id' => $asset->id,
                'asset' => $asset,
                'reason' => $this->buildReason($matches, $serial, $mac, $name, $ip),
                'matches' => array_combine($matches, array_fill(0, count($matches), true)),
                'serial' => $serial,
                'mac' => $mac,
                'name' => $name,
                'ip' => $ip,
                'model' => $model,
                'vendor' => $vendor,
                'external_id' => $externalId,
            ];
        }

        // No matches found — safe to create
        return [
            'action' => 'create',
            'confidence' => 'none',
            'asset_id' => null,
            'asset' => null,
            'reason' => 'No matching Asset found. Safe to create.',
            'matches' => [],
            'serial' => $serial,
            'mac' => $mac,
            'name' => $name,
            'ip' => $ip,
            'model' => $model,
            'vendor' => $vendor,
            'external_id' => $externalId,
        ];
    }

    /**
     * Analyze a UISP Site against existing EWNET Sites.
     */
    public function analyzeSite(array $uispSite): array
    {
        $externalId = $uispSite['id'] ?? null;
        if (!$externalId) {
            return ['action' => 'error', 'reason' => 'Missing external ID'];
        }

        $name = $this->normalizeName($uispSite['name'] ?? null);
        $location = $uispSite['location'] ?? [];
        $address = $uispSite['address'] ?? [];

        // CHECK 1: UISP External Reference
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
                'matches' => ['external_id' => true],
                'name' => $name,
                'address' => $address['fullAddress'] ?? null,
                'latitude' => $location['lat'] ?? null,
                'longitude' => $location['lon'] ?? null,
                'external_id' => $externalId,
            ];
        }

        // CHECK 2: Name match
        if ($name) {
            $site = Site::where('name', 'ilike', $name)->first();
            if ($site) {
                return [
                    'action' => 'link',
                    'confidence' => 'moderate',
                    'site_id' => $site->id,
                    'site' => $site,
                    'reason' => "Name match found: '{$name}'",
                    'matches' => ['name' => true],
                    'name' => $name,
                    'address' => $address['fullAddress'] ?? null,
                    'latitude' => $location['lat'] ?? null,
                    'longitude' => $location['lon'] ?? null,
                    'external_id' => $externalId,
                ];
            }
        }

        // No matches — safe to create
        return [
            'action' => 'create',
            'confidence' => 'none',
            'site_id' => null,
            'site' => null,
            'reason' => 'No matching Site found. Safe to create.',
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
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
        return null;
    }

    protected function buildReason(array $matches, ?string $serial, ?string $mac, ?string $name, ?string $ip): string
    {
        $parts = [];
        if (in_array('serial', $matches)) $parts[] = "Serial match: '{$serial}'";
        if (in_array('mac', $matches)) $parts[] = "MAC match: '{$mac}'";
        if (in_array('librenms', $matches)) $parts[] = "LibreNMS cross-provider match";
        if (in_array('ip', $matches)) $parts[] = "IP match: '{$ip}'";
        if (in_array('name', $matches)) $parts[] = "Name match: '{$name}'";
        return implode('; ', $parts);
    }
}
