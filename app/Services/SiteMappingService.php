<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\LibreNmsObject;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;

class SiteMappingService
{
    /**
     * Process a single LibreNMS device for Site mapping.
     * Returns ['status' => 'mapped'|'unmapped'|'error', 'message' => string, 'site_id' => ?int]
     */
    public function mapDevice(array $deviceData, Integration $integration): array
    {
        $deviceId = (string) ($deviceData['device_id'] ?? '');
        $hostname = $deviceData['hostname'] ?? '';
        $location = $deviceData['location'] ?? '';
        $lat = $deviceData['lat'] ?? null;
        $lng = $deviceData['lng'] ?? null;

        if (empty($deviceId)) {
            return ['status' => 'error', 'message' => 'Missing device_id', 'site_id' => null];
        }

        // 1. Check for Explicit Mapping via SiteExternalReference
        $explicitRef = SiteExternalReference::where('provider', 'librenms')
            ->where('external_type', 'device')
            ->where('external_id', $deviceId)
            ->first();

        if ($explicitRef) {
            $site = $explicitRef->site;
            if ($site) {
                // Enrich GPS if available and Site allows
                $this->enrichGps($site, $lat, $lng);
                return ['status' => 'mapped', 'message' => 'Explicitly mapped', 'site_id' => $site->id];
            }
        }

        // 2. Heuristic Matching: By Hostname/Site Code
        $siteByCode = Site::where('site_code', strtoupper($hostname))->first();
        if ($siteByCode) {
            $this->createOrUpdateReference($siteByCode, 'librenms', 'device', $deviceId, $deviceData);
            $this->enrichGps($siteByCode, $lat, $lng);
            return ['status' => 'mapped', 'message' => 'Matched by hostname/code', 'site_id' => $siteByCode->id];
        }

        // 3. Heuristic Matching: By Location Name
        if (!empty($location)) {
            $siteByName = Site::where('name', $location)->first();
            if ($siteByName) {
                $this->createOrUpdateReference($siteByName, 'librenms', 'location', $location, $deviceData);
                $this->enrichGps($siteByName, $lat, $lng);
                return ['status' => 'mapped', 'message' => 'Matched by location name', 'site_id' => $siteByName->id];
            }
        }

        // 4. Heuristic Matching: By GPS Proximity (Threshold: 0.0005 degrees ~ 50m)
        if ($lat && $lng) {
            $nearbySite = Site::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereBetween('latitude', [$lat - 0.0005, $lat + 0.0005])
                ->whereBetween('longitude', [$lng - 0.0005, $lng + 0.0005])
                ->first();
            
            if ($nearbySite) {
                // Only map if not already mapped to another device to avoid ambiguity
                $existingRef = $nearbySite->externalReferences()->where('provider', 'librenms')->exists();
                if (!$existingRef) {
                    $this->createOrUpdateReference($nearbySite, 'librenms', 'device', $deviceId, $deviceData);
                    return ['status' => 'mapped', 'message' => 'Matched by GPS proximity', 'site_id' => $nearbySite->id];
                }
            }
        }

        return ['status' => 'unmapped', 'message' => 'No matching Site found', 'site_id' => null];
    }

    private function createOrUpdateReference(Site $site, string $provider, string $type, string $externalId, array $metadata): void
    {
        SiteExternalReference::updateOrCreate(
            [
                'site_id' => $site->id,
                'provider' => $provider,
                'external_type' => $type,
                'external_id' => $externalId,
            ],
            [
                'metadata' => $metadata,
            ]
        );
    }

    private function enrichGps(Site $site, $lat, $lng): void
    {
        // Conservative enrichment: only update if Site has no GPS or if explicitly configured
        // For now, we only update if the Site's GPS is null to avoid overwriting manual entries
        if (($site->latitude === null || $site->longitude === null) && $lat && $lng) {
            $site->update([
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }
    }
}
