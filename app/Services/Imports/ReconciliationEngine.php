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

    // Indexes (now storing arrays of IDs to handle duplicates)
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
                if (!isset($this->assetByExtRef[$key])) $this->assetByExtRef[$key] = [];
                $this->assetByExtRef[$key][] = $asset->id;
            }

            // MAC Index
            $mac = $asset->specifications['mac_address'] ?? null;
            if ($mac) {
                $macKey = strtolower($mac);
                if (!isset($this->assetByMac[$macKey])) $this->assetByMac[$macKey] = [];
                $this->assetByMac[$macKey][] = $asset->id;
            }

            // Serial Index
            if ($asset->serial_number) {
                if (!isset($this->assetBySerial[$asset->serial_number])) $this->assetBySerial[$asset->serial_number] = [];
                $this->assetBySerial[$asset->serial_number][] = $asset->id;
            }

            // IP Index
            $ip = $asset->specifications['ip_address'] ?? $asset->specifications['ip'] ?? null;
            if ($ip) {
                if (!isset($this->assetByIp[$ip])) $this->assetByIp[$ip] = [];
                $this->assetByIp[$ip][] = $asset->id;
            }

            // Name Index
            if ($asset->description) {
                $nameKey = strtolower(trim($asset->description));
                if (!isset($this->assetByName[$nameKey])) $this->assetByName[$nameKey] = [];
                $this->assetByName[$nameKey][] = $asset->id;
            }
        });

        // Load Sites
        Site::with('externalReferences')->cursor()->each(function ($site) {
            $this->destinationSites->push($site);

            foreach ($site->externalReferences as $ref) {
                $key = "{$ref->provider}:{$ref->external_type}:{$ref->external_id}";
                if (!isset($this->siteByExtRef[$key])) $this->siteByExtRef[$key] = [];
                $this->siteByExtRef[$key][] = $site->id;
            }

            if ($site->name) {
                $nameKey = strtolower(trim($site->name));
                if (!isset($this->siteByName[$nameKey])) $this->siteByName[$nameKey] = [];
                $this->siteByName[$nameKey][] = $site->id;
            }
        });
    }

    public function reconcile(NormalizedRecord $record): array
    {
        if ($record->sourceType === 'device') {
            return $this->reconcileAsset($record);
        } elseif ($record->sourceType === 'site') {
            return $this->reconcileSite($record);
        }
        return ['decision' => 'ERROR', 'reason' => 'Unknown source type'];
    }

    protected function reconcileAsset(NormalizedRecord $record): array
    {
        $candidates = [];
        $evidence = [];

        // 1. External Reference (Exact)
        $extKey = "{$record->provider}:device:{$record->externalId}";
        if (isset($this->assetByExtRef[$extKey])) {
            foreach ($this->assetByExtRef[$extKey] as $id) {
                $candidates[$id] = 'exact';
            }
            $evidence[] = ['field' => 'external_id', 'strength' => 'exact', 'value' => $record->externalId];
        }

        // 2. MAC (Strong)
        if ($record->macAddress && isset($this->assetByMac[strtolower($record->macAddress)])) {
            foreach ($this->assetByMac[strtolower($record->macAddress)] as $id) {
                $candidates[$id] = 'strong';
            }
            $evidence[] = ['field' => 'mac_address', 'strength' => 'strong', 'value' => $record->macAddress];
        }

        // 3. Serial (Strong)
        if ($record->serialNumber && isset($this->assetBySerial[$record->serialNumber])) {
            foreach ($this->assetBySerial[$record->serialNumber] as $id) {
                $candidates[$id] = 'strong';
            }
            $evidence[] = ['field' => 'serial_number', 'strength' => 'strong', 'value' => $record->serialNumber];
        }

        // 4. IP (Moderate)
        if ($record->ipAddress && isset($this->assetByIp[$record->ipAddress])) {
            foreach ($this->assetByIp[$record->ipAddress] as $id) {
                // Only set if not already matched by stronger evidence
                if (!isset($candidates[$id]) || $candidates[$id] !== 'exact') {
                    $candidates[$id] = 'moderate';
                }
            }
            $evidence[] = ['field' => 'ip_address', 'strength' => 'moderate', 'value' => $record->ipAddress];
        }

        // 5. Name (Weak)
        if ($record->name) {
            $nameKey = strtolower(trim($record->name));
            if (isset($this->assetByName[$nameKey])) {
                foreach ($this->assetByName[$nameKey] as $id) {
                    if (!isset($candidates[$id])) {
                        $candidates[$id] = 'weak';
                    }
                }
                $evidence[] = ['field' => 'name', 'strength' => 'weak', 'value' => $record->name];
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
            foreach ($this->siteByExtRef[$extKey] as $id) {
                $candidates[$id] = 'exact';
            }
            $evidence[] = ['field' => 'external_id', 'strength' => 'exact'];
        }

        // 2. Name (Moderate for sites)
        if ($record->name) {
            $nameKey = strtolower(trim($record->name));
            if (isset($this->siteByName[$nameKey])) {
                foreach ($this->siteByName[$nameKey] as $id) {
                    if (!isset($candidates[$id])) {
                        $candidates[$id] = 'moderate';
                    }
                }
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

        // If we have multiple moderate matches (e.g. same IP on different assets), it's also a CONFLICT
        // because we don't know which one is the "correct" destination without human review.
        if ($count > 1) {
             // Check if any are moderate
             $hasModerate = false;
             foreach ($candidates as $strength) {
                 if ($strength === 'moderate') $hasModerate = true;
             }
             
             if ($hasModerate || count($strongMatches) === 0) {
                 return ['decision' => 'CONFLICT', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds, 'reason' => 'Multiple potential destinations found'];
             }
        }

        // If we have multiple candidates but only one is strong/exact, it's a REVIEW
        if (count($strongMatches) === 1) {
            return ['decision' => 'REVIEW', 'destination_id' => $strongMatches[0], 'evidence' => $evidence, 'reason' => 'Multiple matches, but strong evidence points to one'];
        }

        return ['decision' => 'REVIEW', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds, 'reason' => 'Multiple weak/moderate matches found'];
    }
}
