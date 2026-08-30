<?php

namespace App\Jobs;

use App\Services\AssetImportService;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessAssetImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $userId;

    public function __construct(string $filePath, int $userId)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(AssetImportService $importService): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            Log::error("Asset Import: User not found", ['user_id' => $this->userId]);
            return;
        }

        try {
            $results = $importService->processImport($this->filePath, $user);
            
            AuditService::log('assets.imported', 'success', null, [
                'total' => $results['total'],
                'created' => $results['created'],
                'updated' => $results['updated'],
                'failed' => $results['failed'],
            ]);

            Log::info("Asset Import Completed", [
                'user_id' => $user->id,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error("Asset Import Failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            // Clean up file on failure
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
            }
        }
    }
}
