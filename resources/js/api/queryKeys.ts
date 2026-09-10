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

export const networkKeys = {
    ports: (assetId: number) => ['network', 'ports', assetId] as const,
    switching: (portId: number) => ['network', 'ports', portId, 'switching'] as const,
    ponDomains: (portId: number) => ['network', 'ports', portId, 'pon-domains'] as const,
    ponMemberships: (domainId: number) => ['network', 'pon-domains', domainId, 'memberships'] as const,
    vlans: () => ['network', 'vlans'] as const,
    routingInstances: (assetId: number) => ['network', 'routing-instances', assetId] as const,
    interfaces: (instanceId: number) => ['network', 'routing-instances', instanceId, 'interfaces'] as const,
    addresses: (interfaceId: number) => ['network', 'interfaces', interfaceId, 'addresses'] as const,
    staticRoutes: (instanceId: number) => ['network', 'routing-instances', instanceId, 'static-routes'] as const,
};

export const fimKeys = {
    map: (filters: Record<string, unknown>) => ['fim', 'map', filters] as const,
    cables: (filters: Record<string, unknown> = {}) => ['fim', 'cables', filters] as const,
    points: (filters: Record<string, unknown> = {}) => ['fim', 'connection-points', filters] as const,
    segments: (filters: Record<string, unknown> = {}) => ['fim', 'segments', filters] as const,
    cores: (filters: Record<string, unknown> = {}) => ['fim', 'cores', filters] as const,
    terminations: (coreId: number, filters: Record<string, unknown> = {}) => ['fim', 'cores', coreId, 'terminations', filters] as const,
    connections: (filters: Record<string, unknown> = {}) => ['fim', 'physical-connections', filters] as const,
    passivePorts: (assetId: number, filters: Record<string, unknown> = {}) => ['fim', 'assets', assetId, 'passive-ports', filters] as const,
    capacity: (kind: 'cable' | 'segment' | 'splitter', id: number) => ['fim', 'capacity', kind, id] as const,
    continuity: (coreId: number) => ['fim', 'continuity', coreId] as const,
};
