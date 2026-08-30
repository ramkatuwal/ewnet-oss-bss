<?php

namespace App\Services;

use App\Models\Site;
use App\Models\User;
use League\Csv\Writer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class SiteExportService
{
    public function exportCsv(User $user, array $filters = []): StreamedResponse
    {
        $sites = $this->getSites($user, $filters);

        $csv = Writer::createFromString('');
        $csv->insertOne([
            'Site Code', 'Name', 'Type', 'Status',
            'Company', 'Region', 'Branch',
            'Latitude', 'Longitude', 'Altitude',
            'Address', 'Municipality', 'District', 'Province'
        ]);

        foreach ($sites as $site) {
            $csv->insertOne([
                $site->site_code,
                $site->name,
                $site->type,
                $site->status,
                $site->company?->name ?? '',
                $site->region?->name ?? '',
                $site->branch?->name ?? '',
                $site->latitude,
                $site->longitude,
                $site->altitude,
                $site->address,
                $site->municipality,
                $site->district,
                $site->province,
            ]);
        }

        return new StreamedResponse(function () use ($csv) {
            echo $csv->toString();
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="sites_export_' . date('Y-m-d_His') . '.csv"',
        ]);
    }

    public function exportXlsx(User $user, array $filters = []): StreamedResponse
    {
        $sites = $this->getSites($user, $filters);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Site Code', 'Name', 'Type', 'Status',
            'Company', 'Region', 'Branch',
            'Latitude', 'Longitude', 'Altitude',
            'Address', 'Municipality', 'District', 'Province'
        ];

        $row = 1;
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }

        foreach ($sites as $site) {
            $row++;
            $sheet->setCellValueByColumnAndRow(1, $row, $site->site_code);
            $sheet->setCellValueByColumnAndRow(2, $row, $site->name);
            $sheet->setCellValueByColumnAndRow(3, $row, $site->type);
            $sheet->setCellValueByColumnAndRow(4, $row, $site->status);
            $sheet->setCellValueByColumnAndRow(5, $row, $site->company?->name ?? '');
            $sheet->setCellValueByColumnAndRow(6, $row, $site->region?->name ?? '');
            $sheet->setCellValueByColumnAndRow(7, $row, $site->branch?->name ?? '');
            $sheet->setCellValueByColumnAndRow(8, $row, $site->latitude);
            $sheet->setCellValueByColumnAndRow(9, $row, $site->longitude);
            $sheet->setCellValueByColumnAndRow(10, $row, $site->altitude);
            $sheet->setCellValueByColumnAndRow(11, $row, $site->address);
            $sheet->setCellValueByColumnAndRow(12, $row, $site->municipality);
            $sheet->setCellValueByColumnAndRow(13, $row, $site->district);
            $sheet->setCellValueByColumnAndRow(14, $row, $site->province);
        }

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        $writer->save($tempFile);

        return new StreamedResponse(function () use ($tempFile) {
            readfile($tempFile);
            unlink($tempFile);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="sites_export_' . date('Y-m-d_His') . '.xlsx"',
        ]);
    }

    private function getSites(User $user, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Site::with(['company', 'region', 'branch']);
        $query = ManagementScopeService::applyScopeToQuery($query, $user, Site::class);

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('site_code', 'like', "%{$filters['search']}%");
        }

        return $query->get();
    }
}
