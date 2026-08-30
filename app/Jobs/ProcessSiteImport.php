<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SiteImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ProcessSiteImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Don't retry entire batch on row failure
    public int $timeout = 600;

    public function __construct(
        private string $filePath,
        private int $userId
    ) {}

    public function handle(SiteImportService $importService): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            \Log::error("ProcessSiteImport: User {$this->userId} not found.");
            return;
        }

        if (!Storage::disk('local')->exists($this->filePath)) {
            \Log::error("ProcessSiteImport: File {$this->filePath} not found.");
            return;
        }

        $csv = Reader::createFromPath(Storage::disk('local')->path($this->filePath), 'r');
        $csv->setHeaderOffset(0);

        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($csv as $offset => $record) {
            $results['total']++;
            
            // Normalize keys to lowercase snake_case
            $normalized = [];
            foreach ($record as $key => $value) {
                $normalized[strtolower(str_replace(' ', '_', trim($key)))] = trim($value);
            }

            $result = $importService->processRow($normalized, $user);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $offset + 2, // +2 for 1-based index + header
                    'site_code' => $normalized['site_code'] ?? 'N/A',
                    'error' => $result['message'],
                ];
            }
        }

        // Store result report
        $reportPath = str_replace('.csv', '_report.json', $this->filePath);
        Storage::disk('local')->put($reportPath, json_encode($results, JSON_PRETTY_PRINT));

        // Cleanup original file after processing (optional, keeping for audit trail for now)
        // Storage::disk('local')->delete($this->filePath);

        \Log::info("Site Import Complete", $results);
    }
}
