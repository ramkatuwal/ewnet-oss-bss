<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class SiteDashboardService
{
    protected ManagementScopeService $scopeService;

    public function __construct(ManagementScopeService $scopeService)
    {
        $this->scopeService = $scopeService;
    }

    public function getDashboardMetrics($user): array
    {
        // Start with a scoped query for Sites
        $scopedQuery = Site::query();
        $this->scopeService->applyScopeToQuery($scopedQuery, $user, Site::class);

        // 1. Basic Counts by Status
        $statusCounts = $scopedQuery->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalSites = $scopedQuery->count();
        $activeSites = $statusCounts['active'] ?? 0;
        $plannedSites = $statusCounts['planned'] ?? 0;
        $maintenanceSites = $statusCounts['maintenance'] ?? 0;
        $inactiveSites = ($statusCounts['inactive'] ?? 0) + ($statusCounts['decommissioned'] ?? 0);

        // 2. Asset Metrics (using whereHas for scope integrity)
        $sitesWithAssets = (clone $scopedQuery)->has('assets')->count();
        
        $totalAssets = Asset::whereHas('site', function ($q) use ($user) {
            $this->scopeService->applyScopeToQuery($q, $user, Site::class);
        })->count();

        $sitesWithoutCoordinates = (clone $scopedQuery)
            ->whereNull('latitude')
            ->orWhereNull('longitude')
            ->count();

        // 3. Top Site by Assets
        $topSite = (clone $scopedQuery)
            ->withCount('assets')
            ->orderBy('assets_count', 'desc')
            ->orderBy('name', 'asc')
            ->first(['id', 'site_code', 'name', 'assets_count']);

        $topSiteData = null;
        if ($topSite && $topSite->assets_count > 0) {
            $topSiteData = [
                'id' => $topSite->id,
                'site_code' => $topSite->site_code,
                'name' => $topSite->name,
                'asset_count' => $topSite->assets_count,
            ];
        }

        // 4. Breakdown by Type
        $typeCounts = $scopedQuery->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total_sites' => $totalSites,
            'active_sites' => $activeSites,
            'planned_sites' => $plannedSites,
            'maintenance_sites' => $maintenanceSites,
            'inactive_sites' => $inactiveSites,
            'sites_with_assets' => $sitesWithAssets,
            'sites_without_coordinates' => $sitesWithoutCoordinates,
            'total_assets' => $totalAssets,
            'top_site_by_assets' => $topSiteData,
            'by_type' => $typeCounts,
        ];
    }
}
