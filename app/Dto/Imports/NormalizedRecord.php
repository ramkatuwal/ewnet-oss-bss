<?php

namespace App\Dto\Imports;

class NormalizedRecord
{
    public string $sourceType;
    public string $provider;
    public string $externalId;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $siteId = null;
    public ?string $serialNumber = null;
    public ?string $macAddress = null;
    public ?string $ipAddress = null;
    public ?string $model = null;
    public ?string $manufacturer = null;
    public ?string $status = null;
    public array $metadata = [];
    public array $interfaces = [];
    public array $ipAddresses = [];

    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'provider' => $this->provider,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'description' => $this->description,
            'site_id' => $this->siteId,
            'serial_number' => $this->serialNumber,
            'mac_address' => $this->macAddress,
            'ip_address' => $this->ipAddress,
            'model' => $this->model,
            'manufacturer' => $this->manufacturer,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'interfaces' => $this->interfaces,
            'ip_addresses' => $this->ipAddresses,
        ];
    }
}
