<?php

namespace App\Services\Imports;

use App\Dto\Imports\NormalizedRecord;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Support\Collection;

class ReconciliationEngine
{
    protected Collection $destinationAssets;
    protected Collection $destinationSites;
    
    // Indexes
    protected array $assetByExtRef = [];
    protected array $assetByMac = [];
    protected array $assetBySerial = [];
    protected array $assetByIp = [];
    protected array $assetByName = [];
    
    protected array $siteByExtRef = [];
    protected array $siteByName = [];

    public function __construct()
    {
        $this->destinationAssets = new Collection();
        $this->destinationSites = new Collection();
        $this->loadDestinationIndexes();
    }

    protected function loadDestinationIndexes(): void
    {
        // Load Assets
        Asset::with('externalReferences')->cursor()->each(function ($asset) {
            $this->destinationAssets->push($asset);
            
            // External Ref Index
            foreach ($asset->externalReferences as $ref) {
                $key = "{$ref->provider}:{$ref->external_type}:{$ref->external_id}";
                $this->assetByExtRef[$key] = $asset->id;
            }

            // MAC Index
            $mac = $asset->specifications['mac_address'] ?? null;
            if ($mac) $this->assetByMac[strtolower($mac)] = $asset->id;

            // Serial Index
            if ($asset->serial_number) $this->assetBySerial[$asset->serial_number] = $asset->id;

            // IP Index
            $ip = $asset->specifications['ip_address'] ?? $asset->specifications['ip'] ?? null;
            if ($ip) $this->assetByIp[$ip] = $asset->id;

            // Name Index
            if ($asset->description) $this->assetByName[strtolower(trim($asset->description))] = $asset->id;
        });

        // Load Sites
        Site::with('externalReferences')->cursor()->each(function ($site) {
            $this->destinationSites->push($site);
            
            foreach ($site->externalReferences as $ref) {
                $key = "{$ref->provider}:{$ref->external_type}:{$ref->external_id}";
                $this->siteByExtRef[$key] = $site->id;
            }

            if ($site->name) $this->siteByName[strtolower(trim($site->name))] = $site->id;
        });
    }

    public function reconcile(NormalizedRecord $record): array
    {
        if ($record->sourceType === 'device') {
            return $this->reconcileAsset($record);
        } elseif ($record->sourceType === 'site') {
            return $this->reconcileSite($record);
        }
        return ['decision' => 'error', 'reason' => 'Unknown source type'];
    }

    protected function reconcileAsset(NormalizedRecord $record): array
    {
        $candidates = [];
        $evidence = [];

        // 1. External Reference (Exact)
        $extKey = "{$record->provider}:device:{$record->externalId}";
        if (isset($this->assetByExtRef[$extKey])) {
            $candidates[$this->assetByExtRef[$extKey]] = 'exact';
            $evidence[] = ['field' => 'external_id', 'strength' => 'exact', 'value' => $record->externalId];
        }

        // 2. MAC (Strong)
        if ($record->macAddress && isset($this->assetByMac[$record->macAddress])) {
            $id = $this->assetByMac[$record->macAddress];
            $candidates[$id] = 'strong';
            $evidence[] = ['field' => 'mac_address', 'strength' => 'strong', 'value' => $record->macAddress];
        }

        // 3. Serial (Strong)
        if ($record->serialNumber && isset($this->assetBySerial[$record->serialNumber])) {
            $id = $this->assetBySerial[$record->serialNumber];
            $candidates[$id] = 'strong';
            $evidence[] = ['field' => 'serial_number', 'strength' => 'strong', 'value' => $record->serialNumber];
        }

        // 4. IP (Moderate)
        if ($record->ipAddress && isset($this->assetByIp[$record->ipAddress])) {
            $id = $this->assetByIp[$record->ipAddress];
            $candidates[$id] = 'moderate';
            $evidence[] = ['field' => 'ip_address', 'strength' => 'moderate', 'value' => $record->ipAddress];
        }

        // 5. Name (Weak)
        if ($record->name) {
            $nameKey = strtolower(trim($record->name));
            if (isset($this->assetByName[$nameKey])) {
                $id = $this->assetByName[$nameKey];
                if (!isset($candidates[$id])) { 
                    $candidates[$id] = 'weak';
                    $evidence[] = ['field' => 'name', 'strength' => 'weak', 'value' => $record->name];
                }
            }
        }

        return $this->makeDecision($candidates, $evidence, $record);
    }

    protected function reconcileSite(NormalizedRecord $record): array
    {
        $candidates = [];
        $evidence = [];

        // 1. External Reference
        $extKey = "{$record->provider}:site:{$record->externalId}";
        if (isset($this->siteByExtRef[$extKey])) {
            $candidates[$this->siteByExtRef[$extKey]] = 'exact';
            $evidence[] = ['field' => 'external_id', 'strength' => 'exact'];
        }

        // 2. Name (Moderate for sites)
        if ($record->name) {
            $nameKey = strtolower(trim($record->name));
            if (isset($this->siteByName[$nameKey])) {
                $candidates[$this->siteByName[$nameKey]] = 'moderate';
                $evidence[] = ['field' => 'name', 'strength' => 'moderate'];
            }
        }

        return $this->makeDecision($candidates, $evidence, $record);
    }

    protected function makeDecision(array $candidates, array $evidence, NormalizedRecord $record): array
    {
        $uniqueIds = array_unique(array_keys($candidates));
        $count = count($uniqueIds);

        if ($count === 0) {
            return ['decision' => 'CREATE', 'evidence' => $evidence, 'destination_id' => null];
        }

        if ($count === 1) {
            $id = $uniqueIds[0];
            $strength = $candidates[$id];
            
            if ($strength === 'exact') {
                return ['decision' => 'LINK', 'destination_id' => $id, 'evidence' => $evidence];
            }
            
            if (in_array($strength, ['strong', 'moderate'])) {
                return ['decision' => 'LINK', 'destination_id' => $id, 'evidence' => $evidence];
            }
            
            return ['decision' => 'REVIEW', 'destination_id' => $id, 'evidence' => $evidence];
        }

        // Multiple candidates detected. Check for CONFLICT.
        // A conflict occurs if different STRONG/EXACT identifiers point to different assets.
        $strongMatches = [];
        foreach ($candidates as $id => $strength) {
            if (in_array($strength, ['exact', 'strong'])) {
                $strongMatches[] = $id;
            }
        }

        // If we have more than one strong/exact match pointing to different assets, it's a CONFLICT.
        if (count($strongMatches) > 1) {
            return ['decision' => 'CONFLICT', 'evidence' => $evidence, 'candidate_ids' => $strongMatches];
        }

        // If we have multiple candidates but only one is strong/exact, it's a REVIEW
        // (e.g., Strong MAC match + Weak Name match on a different asset)
        if (count($strongMatches) === 1) {
            return ['decision' => 'REVIEW', 'destination_id' => $strongMatches[0], 'evidence' => $evidence, 'reason' => 'Multiple matches, but strong evidence points to one'];
        }

        // Multiple moderate/weak matches
        return ['decision' => 'REVIEW', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds, 'reason' => 'Multiple weak/moderate matches found'];
    }
}
