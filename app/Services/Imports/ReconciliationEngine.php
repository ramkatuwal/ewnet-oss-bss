<?php

namespace App\Services\Imports;

use App\Dto\Imports\NormalizedRecord;
use App\Models\Asset;
use App\Models\Site;
use App\Models\AssetInterface;
use App\Models\IpAddress;
use Illuminate\Support\Collection;

class ReconciliationEngine
{
    protected Collection $destinationAssets;
    protected Collection $destinationSites;

    // Asset indexes
    protected array $assetByExtRef = [];
    protected array $assetByMac = [];
    protected array $assetBySerial = [];
    protected array $assetByIp = [];
    protected array $assetByName = [];

    // Site indexes
    protected array $siteByExtRef = [];
    protected array $siteByName = [];

    // Interface indexes
    protected array $interfaceByExtRef = [];
    protected array $interfaceByAssetAndName = [];
    protected array $interfaceByAssetAndMac = [];

    // IP indexes
    protected array $ipByExtRef = [];
    protected array $ipByInterfaceAndIp = [];
    protected array $ipByAssetAndIp = [];

    public function __construct()
    {
        $this->destinationAssets = new Collection();
        $this->destinationSites = new Collection();
        $this->loadDestinationIndexes();
        $this->loadInterfaceIndexes();
        $this->loadIpIndexes();
    }

    protected function loadDestinationIndexes(): void
    {
        Asset::with('externalReferences')->cursor()->each(function ($asset) {
            $this->destinationAssets->push($asset);

            foreach ($asset->externalReferences as $ref) {
                $key = "{$ref->provider}:{$ref->external_type}:{$ref->external_id}";
                if (!isset($this->assetByExtRef[$key])) $this->assetByExtRef[$key] = [];
                $this->assetByExtRef[$key][] = $asset->id;
            }

            $mac = $asset->specifications['mac_address'] ?? null;
            if ($mac) {
                $macKey = strtolower($mac);
                if (!isset($this->assetByMac[$macKey])) $this->assetByMac[$macKey] = [];
                $this->assetByMac[$macKey][] = $asset->id;
            }

            if ($asset->serial_number) {
                if (!isset($this->assetBySerial[$asset->serial_number])) $this->assetBySerial[$asset->serial_number] = [];
                $this->assetBySerial[$asset->serial_number][] = $asset->id;
            }

            $ip = $asset->specifications['ip_address'] ?? $asset->specifications['ip'] ?? null;
            if ($ip) {
                if (!isset($this->assetByIp[$ip])) $this->assetByIp[$ip] = [];
                $this->assetByIp[$ip][] = $asset->id;
            }

            if ($asset->description) {
                $nameKey = strtolower(trim($asset->description));
                if (!isset($this->assetByName[$nameKey])) $this->assetByName[$nameKey] = [];
                $this->assetByName[$nameKey][] = $asset->id;
            }
        });

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

    protected function loadInterfaceIndexes(): void
    {
        AssetInterface::with(['asset'])->cursor()->each(function ($interface) {
            if ($interface->provider && $interface->external_type && $interface->external_id) {
                $key = "{$interface->provider}:{$interface->external_type}:{$interface->external_id}";
                if (!isset($this->interfaceByExtRef[$key])) $this->interfaceByExtRef[$key] = [];
                $this->interfaceByExtRef[$key][] = $interface->id;
            }

            if ($interface->asset_id && $interface->name) {
                $key = "{$interface->asset_id}:{$interface->name}";
                if (!isset($this->interfaceByAssetAndName[$key])) $this->interfaceByAssetAndName[$key] = [];
                $this->interfaceByAssetAndName[$key][] = $interface->id;
            }

            if ($interface->asset_id && $interface->mac_address) {
                $key = "{$interface->asset_id}:" . strtolower($interface->mac_address);
                if (!isset($this->interfaceByAssetAndMac[$key])) $this->interfaceByAssetAndMac[$key] = [];
                $this->interfaceByAssetAndMac[$key][] = $interface->id;
            }
        });
    }

    protected function loadIpIndexes(): void
    {
        IpAddress::with(['interface'])->cursor()->each(function ($ip) {
            if ($ip->provider && $ip->external_type && $ip->external_id) {
                $key = "{$ip->provider}:{$ip->external_type}:{$ip->external_id}";
                if (!isset($this->ipByExtRef[$key])) $this->ipByExtRef[$key] = [];
                $this->ipByExtRef[$key][] = $ip->id;
            }

            if ($ip->asset_interface_id && $ip->ip_address) {
                $key = "{$ip->asset_interface_id}:{$ip->ip_address}";
                if (!isset($this->ipByInterfaceAndIp[$key])) $this->ipByInterfaceAndIp[$key] = [];
                $this->ipByInterfaceAndIp[$key][] = $ip->id;
            }

            if ($ip->interface && $ip->interface->asset_id && $ip->ip_address) {
                $key = "{$ip->interface->asset_id}:{$ip->ip_address}";
                if (!isset($this->ipByAssetAndIp[$key])) $this->ipByAssetAndIp[$key] = [];
                $this->ipByAssetAndIp[$key][] = $ip->id;
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

        $extKey = "{$record->provider}:device:{$record->externalId}";
        if (isset($this->assetByExtRef[$extKey])) {
            foreach ($this->assetByExtRef[$extKey] as $id) {
                $candidates[$id] = 'exact';
            }
            $evidence[] = ['field' => 'external_id', 'strength' => 'exact', 'value' => $record->externalId];
        }

        if ($record->macAddress && isset($this->assetByMac[strtolower($record->macAddress)])) {
            foreach ($this->assetByMac[strtolower($record->macAddress)] as $id) {
                $candidates[$id] = 'strong';
            }
            $evidence[] = ['field' => 'mac_address', 'strength' => 'strong', 'value' => $record->macAddress];
        }

        if ($record->serialNumber && isset($this->assetBySerial[$record->serialNumber])) {
            foreach ($this->assetBySerial[$record->serialNumber] as $id) {
                $candidates[$id] = 'strong';
            }
            $evidence[] = ['field' => 'serial_number', 'strength' => 'strong', 'value' => $record->serialNumber];
        }

        if ($record->ipAddress && isset($this->assetByIp[$record->ipAddress])) {
            foreach ($this->assetByIp[$record->ipAddress] as $id) {
                if (!isset($candidates[$id]) || $candidates[$id] !== 'exact') {
                    $candidates[$id] = 'moderate';
                }
            }
            $evidence[] = ['field' => 'ip_address', 'strength' => 'moderate', 'value' => $record->ipAddress];
        }

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

        $extKey = "{$record->provider}:site:{$record->externalId}";
        if (isset($this->siteByExtRef[$extKey])) {
            foreach ($this->siteByExtRef[$extKey] as $id) {
                $candidates[$id] = 'exact';
            }
            $evidence[] = ['field' => 'external_id', 'strength' => 'exact'];
        }

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

        $strongMatches = [];
        foreach ($candidates as $id => $strength) {
            if (in_array($strength, ['exact', 'strong'])) {
                $strongMatches[] = $id;
            }
        }

        if (count($strongMatches) > 1) {
            return ['decision' => 'CONFLICT', 'evidence' => $evidence, 'candidate_ids' => $strongMatches];
        }

        if ($count > 1) {
            $hasModerate = false;
            foreach ($candidates as $strength) {
                if ($strength === 'moderate') $hasModerate = true;
            }

            if ($hasModerate || count($strongMatches) === 0) {
                return ['decision' => 'CONFLICT', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds, 'reason' => 'Multiple potential destinations found'];
            }
        }

        if (count($strongMatches) === 1) {
            return ['decision' => 'REVIEW', 'destination_id' => $strongMatches[0], 'evidence' => $evidence, 'reason' => 'Multiple matches, but strong evidence points to one'];
        }

        return ['decision' => 'REVIEW', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds, 'reason' => 'Multiple weak/moderate matches found'];
    }

    // ============================================================
    // INTERFACE RECONCILIATION
    // ============================================================

    public function reconcileInterface(array $interfaceData, int $assetId): array
    {
        $candidates = [];
        $evidence = [];

        if (!empty($interfaceData['external_id']) && !empty($interfaceData['provider'])) {
            $key = "{$interfaceData['provider']}:interface:{$interfaceData['external_id']}";
            if (isset($this->interfaceByExtRef[$key])) {
                foreach ($this->interfaceByExtRef[$key] as $id) {
                    $candidates[$id] = 'exact';
                }
                $evidence[] = ['field' => 'external_id', 'strength' => 'exact', 'value' => $interfaceData['external_id']];
            }
        }

        if ($assetId && !empty($interfaceData['name'])) {
            $key = "{$assetId}:{$interfaceData['name']}";
            if (isset($this->interfaceByAssetAndName[$key])) {
                foreach ($this->interfaceByAssetAndName[$key] as $id) {
                    if (!isset($candidates[$id]) || $candidates[$id] !== 'exact') {
                        $candidates[$id] = 'strong';
                    }
                }
                $evidence[] = ['field' => 'asset_id:name', 'strength' => 'strong', 'value' => $interfaceData['name']];
            }
        }

        if ($assetId && !empty($interfaceData['mac_address'])) {
            $key = "{$assetId}:" . strtolower($interfaceData['mac_address']);
            if (isset($this->interfaceByAssetAndMac[$key])) {
                foreach ($this->interfaceByAssetAndMac[$key] as $id) {
                    if (!isset($candidates[$id]) || $candidates[$id] !== 'exact') {
                        $candidates[$id] = 'strong';
                    }
                }
                $evidence[] = ['field' => 'asset_id:mac', 'strength' => 'strong', 'value' => $interfaceData['mac_address']];
            }
        }

        return $this->makeInterfaceDecision($candidates, $evidence, $interfaceData);
    }

    protected function makeInterfaceDecision(array $candidates, array $evidence, array $interfaceData): array
    {
        $uniqueIds = array_unique(array_keys($candidates));
        $count = count($uniqueIds);

        if ($count === 0) {
            return ['decision' => 'CREATE', 'evidence' => $evidence, 'destination_id' => null];
        }

        if ($count === 1) {
            $id = $uniqueIds[0];
            $strength = $candidates[$id];
            if (in_array($strength, ['exact', 'strong'])) {
                return ['decision' => 'LINK', 'destination_id' => $id, 'evidence' => $evidence];
            }
            return ['decision' => 'REVIEW', 'destination_id' => $id, 'evidence' => $evidence];
        }

        $strongMatches = array_filter($candidates, fn($s) => in_array($s, ['exact', 'strong']));
        if (count($strongMatches) > 1) {
            return ['decision' => 'CONFLICT', 'evidence' => $evidence, 'candidate_ids' => array_keys($strongMatches)];
        }

        return ['decision' => 'REVIEW', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds];
    }

    // ============================================================
    // IP ADDRESS RECONCILIATION
    // ============================================================

    public function reconcileIpAddress(array $ipData, int $interfaceId, int $assetId): array
    {
        $candidates = [];
        $evidence = [];

        if (!empty($ipData['external_id']) && !empty($ipData['provider'])) {
            $key = "{$ipData['provider']}:ip:{$ipData['external_id']}";
            if (isset($this->ipByExtRef[$key])) {
                foreach ($this->ipByExtRef[$key] as $id) {
                    $candidates[$id] = 'exact';
                }
                $evidence[] = ['field' => 'external_id', 'strength' => 'exact', 'value' => $ipData['external_id']];
            }
        }

        if ($interfaceId && !empty($ipData['ip'])) {
            $key = "{$interfaceId}:{$ipData['ip']}";
            if (isset($this->ipByInterfaceAndIp[$key])) {
                foreach ($this->ipByInterfaceAndIp[$key] as $id) {
                    if (!isset($candidates[$id]) || $candidates[$id] !== 'exact') {
                        $candidates[$id] = 'strong';
                    }
                }
                $evidence[] = ['field' => 'interface_id:ip', 'strength' => 'strong', 'value' => $ipData['ip']];
            }
        }

        if ($assetId && !empty($ipData['ip'])) {
            $key = "{$assetId}:{$ipData['ip']}";
            if (isset($this->ipByAssetAndIp[$key])) {
                foreach ($this->ipByAssetAndIp[$key] as $id) {
                    if (!isset($candidates[$id])) {
                        $candidates[$id] = 'moderate';
                    }
                }
                $evidence[] = ['field' => 'asset_id:ip', 'strength' => 'moderate', 'value' => $ipData['ip']];
            }
        }

        return $this->makeIpDecision($candidates, $evidence, $ipData);
    }

    protected function makeIpDecision(array $candidates, array $evidence, array $ipData): array
    {
        $uniqueIds = array_unique(array_keys($candidates));
        $count = count($uniqueIds);

        if ($count === 0) {
            return ['decision' => 'CREATE', 'evidence' => $evidence, 'destination_id' => null];
        }

        if ($count === 1) {
            $id = $uniqueIds[0];
            $strength = $candidates[$id];
            if (in_array($strength, ['exact', 'strong'])) {
                return ['decision' => 'LINK', 'destination_id' => $id, 'evidence' => $evidence];
            }
            if ($strength === 'moderate') {
                return ['decision' => 'REVIEW', 'destination_id' => $id, 'evidence' => $evidence, 'reason' => 'Same IP on different interface'];
            }
            return ['decision' => 'REVIEW', 'destination_id' => $id, 'evidence' => $evidence];
        }

        $strongMatches = array_filter($candidates, fn($s) => in_array($s, ['exact', 'strong']));
        if (count($strongMatches) > 1) {
            return ['decision' => 'CONFLICT', 'evidence' => $evidence, 'candidate_ids' => array_keys($strongMatches)];
        }

        return ['decision' => 'REVIEW', 'evidence' => $evidence, 'candidate_ids' => $uniqueIds];
    }
}
