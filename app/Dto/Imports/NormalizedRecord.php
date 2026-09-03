<?php

namespace App\Dto\Imports;

class NormalizedRecord
{
    public function __construct(
        public readonly string $provider,
        public readonly string $sourceType, // 'device' or 'site'
        public readonly string $externalId,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $macAddress = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $serialNumber = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $model = null,
        public readonly ?string $firmwareVersion = null,
        public readonly ?string $status = null,
        public readonly ?string $sourceSiteId = null,
        public readonly ?string $sourceSiteName = null,
        public readonly array $metadata = [],
        public readonly ?array $rawSource = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'source_type' => $this->sourceType,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'description' => $this->description,
            'mac_address' => $this->macAddress,
            'ip_address' => $this->ipAddress,
            'serial_number' => $this->serialNumber,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'firmware_version' => $this->firmwareVersion,
            'status' => $this->status,
            'source_site_id' => $this->sourceSiteId,
            'source_site_name' => $this->sourceSiteName,
            'metadata' => $this->metadata,
        ];
    }
}
