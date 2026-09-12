export const infrastructureKeys = {
    sites: (filters: Record<string, unknown> = {}) => ['infrastructure', 'sites', filters] as const,
    site: (id: number | string) => ['infrastructure', 'site', id] as const,
    siteDashboard: () => ['infrastructure', 'sites', 'dashboard'] as const,
    siteAssets: (siteId: number, filters: Record<string, unknown> = {}) => ['infrastructure', 'site-assets', siteId, filters] as const,
    assets: (filters: Record<string, unknown> = {}) => ['infrastructure', 'assets', filters] as const,
    asset: (id: number | string) => ['infrastructure', 'asset', id] as const,
    assetDashboard: () => ['infrastructure', 'assets', 'dashboard'] as const,
    networkPorts: (assetId: number) => ['infrastructure', 'asset', assetId, 'network-ports'] as const,
    assetInterfaces: (assetId: number) => ['infrastructure', 'asset', assetId, 'interfaces'] as const,
    assetIpAddresses: (assetId: number) => ['infrastructure', 'asset', assetId, 'ip-addresses'] as const,
    assetPonMemberships: (assetId: number) => ['infrastructure', 'asset', assetId, 'pon-memberships'] as const,
    assetVlanMemberships: (assetId: number) => ['infrastructure', 'asset', assetId, 'vlan-memberships'] as const,
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

export const bssKeys = {
    customers: (filters: Record<string, unknown> = {}) => ['bss', 'customers', filters] as const,
    customer: (id: number) => ['bss', 'customer', id] as const,
    services: (filters: Record<string, unknown> = {}) => ['bss', 'services', filters] as const,
    service: (id: number) => ['bss', 'service', id] as const,
    customerServices: (customerId: number, filters: Record<string, unknown> = {}) => ['bss', 'customer-services', customerId, filters] as const,
    leads: (filters: Record<string, unknown> = {}) => ['bss', 'leads', filters] as const,
    lead: (id: number) => ['bss', 'lead', id] as const,
    customer360: (id: number) => ['bss', 'customer-360', id] as const,
    leadFeasibility: (leadId: number) => ['bss', 'lead', leadId, 'feasibility'] as const,
    feasibilityChecks: (filters: Record<string, unknown> = {}) => ['bss', 'feasibility-checks', filters] as const,
    feasibilityCheck: (id: number) => ['bss', 'feasibility-check', id] as const,
    feasibilityEvidence: (id: number) => ['bss', 'feasibility-check', id, 'evidence'] as const,
    feasibilityConditions: (id: number) => ['bss', 'feasibility-check', id, 'conditions'] as const,
    feasibilityConfirmations: (id: number) => ['bss', 'feasibility-check', id, 'confirmations'] as const,
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
