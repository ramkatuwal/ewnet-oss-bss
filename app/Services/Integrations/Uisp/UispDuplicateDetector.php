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

        $matches = [];
        $action = 'create';
        $confidence = 'none';

        // CHECK 1: UISP External Reference
        $uispRef = AssetExternalReference::where('provider', 'uisp')
            ->where('external_type', 'device')
            ->where('external_id', $externalId)
            ->first();

        if ($uispRef && $uispRef->asset) {
            return [
                'action' => 'link',
                'confidence' => 'exact',
                'asset_id' => $uispRef->asset_id,
                'asset' => $uispRef->asset,
                'reason' => 'Exact UISP external reference exists.',
                'matches' => ['external_id' => true],
            ];
        }

        $identification = $uispDevice['identification'] ?? [];
        $serial = $this->normalizeSerial($identification['serialNumber'] ?? null);
        $mac = $this->normalizeMac($identification['mac'] ?? null);
        $name = $this->normalizeName($identification['name'] ?? null);
        $ip = $this->normalizeIp($uispDevice['overview']['ipAddress'] ?? null);

        // CHECK 2: Serial Number
        if ($serial) {
            $serialAsset = Asset::where('serial_number', $serial)->first();
            if ($serialAsset) {
                $matches[] = 'serial';
                $action = 'link';
                $confidence = 'strong';
                $asset = $serialAsset;
            }
        }

        // CHECK 3: MAC Address (in specifications)
        if ($mac && !isset($asset)) {
            $macAsset = Asset::whereJsonContains('specifications', ['mac_address' => $mac])->first();
            if ($macAsset) {
                $matches[] = 'mac';
                $action = 'link';
                $confidence = 'strong';
                $asset = $macAsset;
            }
        }

        // CHECK 4: LibreNMS Cross-Provider
        if ($mac && !isset($asset)) {
            $libreNmsRef = LibreNmsObject::where('data->mac', $mac)->first();
            if ($libreNmsRef) {
                // Look for Asset linked to this LibreNMS object via specs
                $libreAsset = Asset::whereJsonContains('specifications', ['librenms_device_id' => $libreNmsRef->external_id])->first();
                if ($libreAsset) {
                    $matches[] = 'librenms';
                    $action = 'link';
                    $confidence = 'strong';
                    $asset = $libreAsset;
                }
            }
        }

        // CHECK 5: IP Address
        if ($ip && !isset($asset)) {
            $ipAsset = Asset::whereJsonContains('specifications', ['ip_address' => $ip])->first();
            if ($ipAsset) {
                $matches[] = 'ip';
                $action = 'review';
                $confidence = 'moderate';
                $asset = $ipAsset;
            }
        }

        // CHECK 6: Name (weakest)
        if ($name && !isset($asset)) {
            $nameAsset = Asset::where('description', 'ilike', $name)->first();
            if ($nameAsset) {
                $matches[] = 'name';
                $action = 'review';
                $confidence = 'weak';
                $asset = $nameAsset;
            }
        }

        // If we have an asset, determine if it's a conflict or safe link
        if (isset($asset)) {
            // Check if serial or MAC conflicts
            if ($serial && $asset->serial_number && $asset->serial_number !== $serial) {
                return [
                    'action' => 'conflict',
                    'confidence' => 'conflict',
                    'asset_id' => $asset->id,
                    'asset' => $asset,
                    'reason' => "Serial number mismatch: UISP '{$serial}' vs Asset '{$asset->serial_number}'",
                    'matches' => array_combine($matches, array_fill(0, count($matches), true)),
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
            ];
        }

        $name = $this->normalizeName($uispSite['name'] ?? null);
        $location = $uispSite['location'] ?? [];

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
            'latitude' => $location['lat'] ?? null,
            'longitude' => $location['lon'] ?? null,
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
