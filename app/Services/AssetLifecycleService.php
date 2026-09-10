<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $sites = Site::whereIn('id', [$asset->site_id, $toSite->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $fromSite = $sites->get($asset->site_id) ?? throw new \LogicException('Asset site no longer exists.');
            $toSite = $sites->get($toSite->id) ?? throw new \LogicException('Destination site no longer exists.');
            $this->ensureSameCompany($asset, $fromSite, $toSite);
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
        return $this->transition($asset, $newStatus, $user, $notes);
    }

    private function transition(Asset $asset, string $newStatus, User $user, ?string $notes, ?callable $validate = null): AssetLifecycleEvent
    {
        return DB::transaction(function () use ($asset, $newStatus, $user, $notes, $validate) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            $oldStatus = $asset->status;
            if ($oldStatus === $newStatus) {
                throw new \InvalidArgumentException('Status is already set to '.$newStatus);
            }
            if ($validate !== null) {
                $validate($asset);
            }
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
        return $this->transition($asset, 'RETIRED', $user, $notes, function (Asset $lockedAsset): void {
            if ($lockedAsset->status === 'DISPOSED') {
                throw new \InvalidArgumentException('Cannot retire a disposed asset.');
            }
        });
    }

    public function dispose(Asset $asset, User $user, ?string $notes = null): AssetLifecycleEvent
    {
        return $this->transition($asset, 'DISPOSED', $user, $notes, function (Asset $lockedAsset): void {
            if ($lockedAsset->status !== 'RETIRED') {
                throw new \InvalidArgumentException('Asset must be retired before disposal.');
            }
        });
    }

    public function getHistory(Asset $asset): Collection
    {
        return $asset->lifecycleEvents()
            ->with(['fromSite', 'toSite', 'createdBy'])
            ->orderBy('event_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    private function ensureSameCompany(Asset $asset, Site $fromSite, Site $toSite): void
    {
        // Legacy company IDs may be null; a source site's company remains a
        // reliable boundary when the denormalized asset value is absent.
        $assetCompanyId = $asset->company_id ?? $fromSite->company_id;
        if ($assetCompanyId !== null && $toSite->company_id !== null
            && (int) $assetCompanyId !== (int) $toSite->company_id) {
            throw new \InvalidArgumentException('Assets cannot be transferred across companies.');
        }
    }
}
