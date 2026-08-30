<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SplTempFileObject;

class AssetImportService
{
    public function processImport(string $filePath, User $user): array
    {
        $results = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Determine file type and read rows
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $rows = [];

        if ($extension === 'csv') {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            foreach ($csv as $record) {
                $rows[] = $record;
            }
        } else {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $header = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $header[] = $cell->getValue();
                }
            }
            
            foreach ($sheet->getRowIterator(2) as $row) {
                $rowData = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $i = 0;
                foreach ($cellIterator as $cell) {
                    if (isset($header[$i])) {
                        $rowData[$header[$i]] = $cell->getValue();
                    }
                    $i++;
                }
                $rows[] = $rowData;
            }
        }

        $results['total'] = count($rows);

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // Excel row number (1-based header + 1-based data)
            try {
                $this->processRow($row, $user, $results);
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $rowNum,
                    'message' => $e->getMessage(),
                    'data' => $row,
                ];
            }
        }

        // Clean up temp file
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return $results;
    }

    private function processRow(array $row, User $user, array &$results): void
    {
        // 1. Validate Site
        $siteCode = $row['site'] ?? $row['site_code'] ?? null;
        if (!$siteCode) {
            throw new \Exception("Missing site or site_code");
        }

        $site = Site::where('site_code', $siteCode)->first();
        if (!$site) {
            throw new \Exception("Site '{$siteCode}' not found");
        }

        // Scope Check
        if (!ManagementScopeService::isInScope($user, $site)) {
            throw new \Exception("Unauthorized Site '{$siteCode}'");
        }

        // 2. Validate Required Fields
        $assetTag = $row['asset_tag'] ?? null;
        if (!$assetTag) {
            throw new \Exception("Missing asset_tag");
        }

        $category = strtoupper($row['category'] ?? '');
        if (!in_array($category, Asset::CATEGORIES)) {
            throw new \Exception("Invalid category '{$category}'");
        }

        $type = $row['type'] ?? null;
        if (!$type) {
            throw new \Exception("Missing type");
        }

        $status = strtoupper($row['status'] ?? 'OPERATIONAL');
        if (!in_array($status, Asset::STATUSES)) {
            throw new \Exception("Invalid status '{$status}'");
        }

        $quantity = (int) ($row['quantity'] ?? 1);
        if ($quantity < 1) {
            throw new \Exception("Quantity must be at least 1");
        }

        $serialNumber = $row['serial_number'] ?? null;
        if ($serialNumber && $quantity > 1) {
             // In this phase, bulk items shouldn't have a single serial number unless it's a batch ID.
             // We'll allow it but treat it as a batch identifier if needed, or reject if strict.
             // For now, let's allow it but ensure uniqueness.
        }

        // 3. Upsert Logic
        $asset = Asset::where('asset_tag', $assetTag)->first();

        $data = [
            'site_id' => $site->id,
            'asset_tag' => $assetTag,
            'serial_number' => $serialNumber,
            'category' => $category,
            'type' => $type,
            'manufacturer' => $row['manufacturer'] ?? null,
            'model' => $row['model'] ?? null,
            'quantity' => $quantity,
            'unit' => $row['unit'] ?? 'pcs',
            'status' => $status,
            'condition' => strtoupper($row['condition'] ?? '') ?: null,
            'purchase_date' => !empty($row['purchase_date']) ? date('Y-m-d', strtotime($row['purchase_date'])) : null,
            'installation_date' => !empty($row['installation_date']) ? date('Y-m-d', strtotime($row['installation_date'])) : null,
            'warranty_expiry' => !empty($row['warranty_expiry']) ? date('Y-m-d', strtotime($row['warranty_expiry'])) : null,
            'description' => $row['description'] ?? null,
            'notes' => $row['notes'] ?? null,
            'updated_by' => $user->id,
        ];

        // Handle JSON specifications if present
        if (!empty($row['specifications'])) {
            $specs = json_decode($row['specifications'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['specifications'] = $specs;
            }
        }

        if ($asset) {
            // Check scope again for update
            if (!ManagementScopeService::isInScope($user, $asset->site)) {
                throw new \Exception("Unauthorized to update existing asset '{$assetTag}'");
            }
            $asset->update($data);
            $results['updated']++;
        } else {
            $data['created_by'] = $user->id;
            Asset::create($data);
            $results['created']++;
        }
    }
}
