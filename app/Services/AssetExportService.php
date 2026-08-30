<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\ManagementScopeService;

class AssetExportService
{
    public function exportCsv(User $user, array $filters = []): StreamedResponse
    {
        $query = Asset::with(['site.company', 'site.region', 'site.branch']);
        $query = ManagementScopeService::applyScopeToQuery($query, $user, Asset::class);

        // Apply filters (simplified for brevity, should match controller logic)
        if (!empty($filters['search'])) {
            $query->where('asset_tag', 'like', "%{$filters['search']}%");
        }

        $assets = $query->get();

        $csv = Writer::createFromString('');
        $csv->insertOne([
            'Asset Tag', 'Serial', 'Category', 'Type', 'Manufacturer', 'Model', 
            'Qty', 'Unit', 'Status', 'Condition', 'Site Code', 'Company', 'Region', 'Branch'
        ]);

        foreach ($assets as $asset) {
            $csv->insertOne([
                $asset->asset_tag, $asset->serial_number, $asset->category, $asset->type,
                $asset->manufacturer, $asset->model, $asset->quantity, $asset->unit,
                $asset->status, $asset->condition, 
                $asset->site?->site_code, $asset->site?->company?->name, 
                $asset->site?->region?->name, $asset->site?->branch?->name
            ]);
        }

        return new StreamedResponse(function () use ($csv) {
            echo $csv->toString();
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="assets_export_' . date('Y-m-d_His') . '.csv"',
        ]);
    }

    public function exportXlsx(User $user, array $filters = []): StreamedResponse
    {
        // Similar to CSV but using PhpSpreadsheet
        $query = Asset::with(['site.company', 'site.region', 'site.branch']);
        $query = ManagementScopeService::applyScopeToQuery($query, $user, Asset::class);
        
        if (!empty($filters['search'])) {
            $query->where('asset_tag', 'like', "%{$filters['search']}%");
        }

        $assets = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $headers = [
            'Asset Tag', 'Serial', 'Category', 'Type', 'Manufacturer', 'Model', 
            'Qty', 'Unit', 'Status', 'Condition', 'Site Code', 'Company', 'Region', 'Branch'
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $rowData = [];
        foreach ($assets as $asset) {
            $rowData[] = [
                $asset->asset_tag, $asset->serial_number, $asset->category, $asset->type,
                $asset->manufacturer, $asset->model, $asset->quantity, $asset->unit,
                $asset->status, $asset->condition, 
                $asset->site?->site_code, $asset->site?->company?->name, 
                $asset->site?->region?->name, $asset->site?->branch?->name
            ];
        }
        $sheet->fromArray($rowData, null, 'A2');

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="assets_export_' . date('Y-m-d_His') . '.xlsx"',
        ]);
    }
}
