<?php

namespace App\Contracts;

use App\Dto\Imports\NormalizedRecord;

interface ImportSourceInterface
{
    public function getIdentity(): string;
    public function getDisplayName(): string;
    public function getCapabilities(): array; // ['devices', 'sites']
    public function fetchDevices(): array;
    public function fetchSites(): array;
    public function normalizeDevice(array $raw): NormalizedRecord;
    public function normalizeSite(array $raw): NormalizedRecord;
}
