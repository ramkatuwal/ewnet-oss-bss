
export interface SiteDashboardMetrics {
    total_sites: number;
    active_sites: number;
    planned_sites: number;
    maintenance_sites: number;
    inactive_sites: number;
    sites_with_assets: number;
    sites_without_coordinates: number;
    total_assets: number;
    top_site_by_assets: {
        id: number;
        site_code: string;
        name: string;
        asset_count: number;
    } | null;
    by_type: Record<string, number>;
}
