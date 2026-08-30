<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssetLifecycleService
{
    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    public function createEvent(Asset $asset, string $eventType, array $data = []): AssetLifecycleEvent
    {
        $event = AssetLifecycleEvent::create([
            'asset_id' => $asset->id,
            'event_type' => $eventType,
            'status_before' => $data['status_before'] ?? $asset->status,
            'status_after' => $data['status_after'] ?? null,
            'from_site_id' => $data['from_site_id'] ?? null,
            'to_site_id' => $data['to_site_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'created_by' => $data['created_by'] ?? auth()->id(),
            'event_date' => $data['event_date'] ?? now(),
        ]);

        $this->audit->log('asset.lifecycle.event', 'success', $asset, [
            'event_type' => $eventType,
            'event_id' => $event->id,
        ]);

        return $event;
    }

    public function transfer(Asset $asset, Site $toSite, User $user, ?string $notes = null): AssetLifecycleEvent
    {
        return DB::transaction(function () use ($asset, $toSite, $user, $notes) {
            $fromSiteId = $asset->site_id;

            $event = $this->createEvent($asset, 'TRANSFERRED', [
                'from_site_id' => $fromSiteId,
                'to_site_id' => $toSite->id,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $asset->site_id = $toSite->id;
            $asset->save();

            $this->audit->log('asset.transferred', 'success', $asset, [
                'from_site_id' => $fromSiteId,
                'to_site_id' => $toSite->id,
                'event_id' => $event->id,
            ]);

            return $event;
        });
    }

    public function changeStatus(Asset $asset, string $newStatus, User $user, ?string $notes = null): AssetLifecycleEvent
    {
        $oldStatus = $asset->status;

        if ($oldStatus === $newStatus) {
            throw new \InvalidArgumentException('Status is already set to ' . $newStatus);
        }

        return DB::transaction(function () use ($asset, $newStatus, $user, $notes, $oldStatus) {
            $event = $this->createEvent($asset, 'STATUS_CHANGED', [
                'status_before' => $oldStatus,
                'status_after' => $newStatus,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $asset->status = $newStatus;
            $asset->save();

            $this->audit->log('asset.status_changed', 'success', $asset, [
                'from' => $oldStatus,
                'to' => $newStatus,
                'event_id' => $event->id,
            ]);

            return $event;
        });
    }

    public function retire(Asset $asset, User $user, ?string $notes = null): AssetLifecycleEvent
    {
        if ($asset->status === 'RETIRED') {
            throw new \InvalidArgumentException('Asset is already retired.');
        }

        if ($asset->status === 'DISPOSED') {
            throw new \InvalidArgumentException('Cannot retire a disposed asset.');
        }

        return $this->changeStatus($asset, 'RETIRED', $user, $notes);
    }

    public function dispose(Asset $asset, User $user, ?string $notes = null): AssetLifecycleEvent
    {
        if ($asset->status !== 'RETIRED') {
            throw new \InvalidArgumentException('Asset must be retired before disposal.');
        }

        return $this->changeStatus($asset, 'DISPOSED', $user, $notes);
    }

    public function getHistory(Asset $asset): \Illuminate\Database\Eloquent\Collection
    {
        return $asset->lifecycleEvents()
            ->with(['fromSite', 'toSite', 'createdBy'])
            ->orderBy('event_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
