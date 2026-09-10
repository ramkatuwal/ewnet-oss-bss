import { apiClient } from '@/api/client';
import type { PaginatedResponse } from '@/types';

export interface NetworkPort { id: number; asset_id: number; company_id: number; port_key: string; name: string | null; slot: string | null; card: string | null; port_number: string | null; connector_type: string | null; port_direction: string | null; technology: string | null; metadata: Record<string, unknown> | null; }
export interface Vlan { id: number; company_id: number; vid: number; name: string; description: string | null; reserved: boolean; metadata: Record<string, unknown> | null; }
export interface VlanMembership { id: number; vlan_id: number; tagging: 'tagged' | 'untagged'; vlan?: Vlan; metadata: Record<string, unknown> | null; }
export interface SwitchingConfig { id: number; network_port_id: number; company_id: number; mode: 'access' | 'trunk'; memberships: VlanMembership[]; metadata: Record<string, unknown> | null; }
export interface SwitchingConfigInput { mode: SwitchingConfig['mode']; memberships: Array<Pick<VlanMembership, 'vlan_id' | 'tagging'> & { metadata?: Record<string, unknown> | null }>; }
export interface PonDomain { id: number; olt_port_id: number; company_id: number; metadata: Record<string, unknown> | null; }
export interface PonMembership { id: number; pon_domain_id: number; onu_asset_id: number; onu_id: string | null; company_id: number; metadata: Record<string, unknown> | null; }
export interface RoutingInstance { id: number; asset_id: number; company_id: number; name: string; kind: 'default' | 'vrf'; metadata: Record<string, unknown> | null; }
export interface RoutingL3Interface { id: number; routing_instance_id: number; asset_id: number; company_id: number; name: string; kind: 'physical' | 'svi' | 'subinterface' | 'loopback'; network_port_id: number | null; vlan_id: number | null; parent_routing_l3_interface_id: number | null; metadata: Record<string, unknown> | null; }
export interface RoutingAddress { id: number; routing_l3_interface_id: number; address: string; prefix_length: number; address_role: 'primary' | 'secondary'; }
export interface StaticRoute { id: number; routing_instance_id: number; routing_l3_interface_id: number | null; destination: string; gateway: string | null; route_type: 'forward' | 'discard' | 'reject'; }

const collection = <T>(path: string, params?: Record<string, unknown>) => apiClient.get<PaginatedResponse<T>>(path, { params }).then(response => response.data);
const resource = <T>(path: string, body?: unknown) => apiClient.post<{ data: T }>(path, body).then(response => response.data.data);
const remove = (path: string) => apiClient.delete(path).then(response => response.data);

export const networkApi = {
    ports: (assetId: number) => collection<NetworkPort>(`/api/v1/assets/${assetId}/network-ports`, { per_page: 100 }),
    createPort: (assetId: number, body: Record<string, unknown>) => resource<NetworkPort>(`/api/v1/assets/${assetId}/network-ports`, body),
    switching: (portId: number) => apiClient.get<{ data: SwitchingConfig }>(`/api/v1/network-ports/${portId}/switching-configuration`).then(response => response.data.data),
    replaceSwitching: (portId: number, body: SwitchingConfigInput) => apiClient.put<{ data: SwitchingConfig }>(`/api/v1/network-ports/${portId}/switching-configuration`, body).then(response => response.data.data),
    ponDomains: (portId: number) => collection<PonDomain>(`/api/v1/network-ports/${portId}/pon-domains`, { per_page: 100 }),
    createPonDomain: (portId: number) => resource<PonDomain>(`/api/v1/network-ports/${portId}/pon-domains`, {}),
    ponMemberships: (domainId: number) => collection<PonMembership>(`/api/v1/pon-domains/${domainId}/memberships`, { per_page: 100 }),
    createPonMembership: (domainId: number, body: { onu_asset_id: number; onu_id?: string }) => resource<PonMembership>(`/api/v1/pon-domains/${domainId}/memberships`, body),
    vlans: () => collection<Vlan>('/api/v1/vlans', { per_page: 100 }),
    createVlan: (body: Record<string, unknown>) => resource<Vlan>('/api/v1/vlans', body),
    updateVlan: (id: number, body: Record<string, unknown>) => apiClient.put<{ data: Vlan }>(`/api/v1/vlans/${id}`, body).then(response => response.data.data),
    routingInstances: (assetId: number) => apiClient.get<{ data: RoutingInstance[] }>(`/api/v1/assets/${assetId}/routing-instances`).then(response => response.data.data),
    createRoutingInstance: (assetId: number, body: { name: string; kind: RoutingInstance['kind'] }) => resource<RoutingInstance>(`/api/v1/assets/${assetId}/routing-instances`, body),
    interfaces: (instanceId: number) => apiClient.get<{ data: RoutingL3Interface[] }>(`/api/v1/routing-instances/${instanceId}/l3-interfaces`).then(response => response.data.data),
    createInterface: (instanceId: number, body: Record<string, unknown>) => resource<RoutingL3Interface>(`/api/v1/routing-instances/${instanceId}/l3-interfaces`, body),
    addresses: (interfaceId: number) => apiClient.get<{ data: RoutingAddress[] }>(`/api/v1/routing-l3-interfaces/${interfaceId}/addresses`).then(response => response.data.data),
    createAddress: (interfaceId: number, body: { address: string; address_role: RoutingAddress['address_role'] }) => resource<RoutingAddress>(`/api/v1/routing-l3-interfaces/${interfaceId}/addresses`, body),
    staticRoutes: (instanceId: number) => apiClient.get<{ data: StaticRoute[] }>(`/api/v1/routing-instances/${instanceId}/static-routes`).then(response => response.data.data),
    createStaticRoute: (instanceId: number, body: Record<string, unknown>) => resource<StaticRoute>(`/api/v1/routing-instances/${instanceId}/static-routes`, body),
    remove,
};
