export const infrastructureKeys = {
    sites: (filters: Record<string, unknown> = {}) => ['infrastructure', 'sites', filters] as const,
    site: (id: number | string) => ['infrastructure', 'site', id] as const,
    siteDashboard: () => ['infrastructure', 'sites', 'dashboard'] as const,
    siteAssets: (siteId: number, filters: Record<string, unknown> = {}) => ['infrastructure', 'site-assets', siteId, filters] as const,
    assets: (filters: Record<string, unknown> = {}) => ['infrastructure', 'assets', filters] as const,
    asset: (id: number | string) => ['infrastructure', 'asset', id] as const,
    assetDashboard: () => ['infrastructure', 'assets', 'dashboard'] as const,
    networkPorts: (assetId: number) => ['infrastructure', 'asset', assetId, 'network-ports'] as const,
};
