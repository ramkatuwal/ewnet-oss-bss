<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteExportService
{
    public function exportCsv(User $user, array $filters = []): StreamedResponse
    {
        $query = Site::query();
        $query = ManagementScopeService::applyScopeToQuery($query, $user, Site::class);

        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('site_code', 'like', "%{$filters['search']}%")
                  ->orWhere('name', 'like', "%{$filters['search']}%");
            });
        }

        $sites = $query->get();

        $csv = Writer::createFromString('');
        $csv->insertOne([
            'site_code', 'name', 'type', 'status', 
            'latitude', 'longitude', 'altitude',
            'province', 'district', 'municipality', 'ward', 'tole', 'address', 'postal_code',
            'company_id', 'region_id', 'branch_id',
            'created_at', 'updated_at'
        ]);

        foreach ($sites as $site) {
            $csv->insertOne([
                $site->site_code, $site->name, $site->type, $site->status,
                $site->latitude, $site->longitude, $site->altitude,
                $site->province, $site->district, $site->municipality, $site->ward, $site->tole, $site->address, $site->postal_code,
                $site->company_id, $site->region_id, $site->branch_id,
                $site->created_at?->toIso8601String(), $site->updated_at?->toIso8601String()
            ]);
        }

        return new StreamedResponse(function () use ($csv) {
            echo $csv->toString();
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sites_export_' . date('Y-m-d_His') . '.csv"',
        ]);
    }

    public function exportXlsx(User $user, array $filters = []): StreamedResponse
    {
        $query = Site::query();
        $query = ManagementScopeService::applyScopeToQuery($query, $user, Site::class);
        
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('site_code', 'like', "%{$filters['search']}%")
                  ->orWhere('name', 'like', "%{$filters['search']}%");
            });
        }

        $sites = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $headers = [
            'Site Code', 'Name', 'Type', 'Status', 
            'Latitude', 'Longitude', 'Altitude',
            'Province', 'District', 'Municipality', 'Ward', 'Tole', 'Address', 'Postal Code',
            'Company ID', 'Region ID', 'Branch ID',
            'Created At', 'Updated At'
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $rowData = [];
        foreach ($sites as $site) {
            $rowData[] = [
                $site->site_code, $site->name, $site->type, $site->status,
                $site->latitude, $site->longitude, $site->altitude,
                $site->province, $site->district, $site->municipality, $site->ward, $site->tole, $site->address, $site->postal_code,
                $site->company_id, $site->region_id, $site->branch_id,
                $site->created_at?->toIso8601String(), $site->updated_at?->toIso8601String()
            ];
        }
        $sheet->fromArray($rowData, null, 'A2');

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="sites_export_' . date('Y-m-d_His') . '.xlsx"',
        ]);
    }
}
