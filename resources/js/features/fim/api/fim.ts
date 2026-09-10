import { apiClient } from '@/api/client';

export interface Page<T> { data: T[]; meta?: { current_page: number; last_page: number; per_page: number; total: number }; total?: number; current_page?: number; }
export interface FiberCable { id: number; cable_code: string; name: string | null; fiber_count: number; status: string; company_id: number; length_meters: number | null; route_geometry_authority: string; }
export interface ConnectionPoint { id: number; name: string | null; point_type: string; status: string; site_id: number | null; asset_id: number | null; company_id: number; }
export interface FiberSegment { id: number; fiber_cable_id: number; endpoint_a_id: number; endpoint_b_id: number; sequence: number; status: string; length_meters: number | null; calculated_length_meters: number | null; fiber_cable?: FiberCable; endpoint_a?: ConnectionPoint; endpoint_b?: ConnectionPoint; }
export interface FiberCore { id: number; fiber_segment_id: number; core_number: number; status: string; color_code: string | null; company_id: number; }
export interface FiberTermination { id: number; fiber_core_id: number; network_connection_point_id: number; segment_end: 'A' | 'B'; company_id: number; }
export interface PhysicalConnection { id: number; termination_a_id: number; termination_b_id: number; connection_type: string; company_id: number; }
export interface PassiveOpticalPort { id: number; asset_id: number; network_connection_point_id: number; port_number: string; connector_type: string | null; port_role: string; }
export interface TerminationPortAttachment { id: number; fiber_termination_id: number; passive_optical_port_id: number; company_id: number; }
export interface NetworkPortAttachment { id: number; network_port_id: number; fiber_termination_id: number; company_id: number; }
export interface SplitterProfile { id: number; asset_id: number; company_id: number; input_port_count: number; output_port_count: number; split_ratio: string | null; }
export interface SplitterBranch { id: number; splitter_profile_id: number; input_port_id: number; output_port_id: number; }

const page = <T>(path: string, params?: Record<string, unknown>) => apiClient.get<Page<T>>(path, { params }).then(r => r.data);
const item = <T>(path: string, body?: unknown) => apiClient.post<{ data: T }>(path, body).then(r => r.data.data);
const update = <T>(path: string, body: Record<string, unknown>) => apiClient.patch<{ data: T }>(path, body).then(r => r.data.data);
const remove = (path: string) => apiClient.delete(path).then(r => r.data);
export const fimApi = {
    cables: (params?: Record<string, unknown>) => page<FiberCable>('/api/v1/fim/fiber-cables', params),
    points: (params?: Record<string, unknown>) => page<ConnectionPoint>('/api/v1/fim/connection-points', params),
    segments: (params?: Record<string, unknown>) => page<FiberSegment>('/api/v1/fim/fiber-segments', params),
    cores: (params?: Record<string, unknown>) => page<FiberCore>('/api/v1/fim/fiber-cores', params),
    terminations: (coreId: number, params?: Record<string, unknown>) => page<FiberTermination>(`/api/v1/fim/fiber-cores/${coreId}/terminations`, params),
    connections: (params?: Record<string, unknown>) => page<PhysicalConnection>('/api/v1/fim/physical-connections', params),
    passivePorts: (assetId: number, params?: Record<string, unknown>) => page<PassiveOpticalPort>(`/api/v1/assets/${assetId}/passive-optical-ports`, params),
    terminationAttachments: (terminationId: number) => page<TerminationPortAttachment>(`/api/v1/fim/fiber-terminations/${terminationId}/port-attachments`, { per_page: 100 }),
    networkPortAttachments: (networkPortId: number) => page<NetworkPortAttachment>(`/api/v1/network-ports/${networkPortId}/fiber-attachments`, { per_page: 100 }),
    splitterProfile: (assetId: number) => apiClient.get<{ data: SplitterProfile }>(`/api/v1/assets/${assetId}/splitter-profile`).then(r => r.data.data),
    splitterBranches: (profileId: number) => page<SplitterBranch>(`/api/v1/fim/splitter-profiles/${profileId}/branches`, { per_page: 100 }),
    createPoint: (body: Record<string, unknown>) => item<ConnectionPoint>('/api/v1/fim/connection-points', body),
    createCable: (body: Record<string, unknown>) => item<FiberCable>('/api/v1/fim/fiber-cables', body),
    createSegment: (body: Record<string, unknown>) => item<FiberSegment>('/api/v1/fim/fiber-segments', body),
    createCore: (body: Record<string, unknown>) => item<FiberCore>('/api/v1/fim/fiber-cores', body),
    createTermination: (coreId: number, body: Record<string, unknown>) => item<FiberTermination>(`/api/v1/fim/fiber-cores/${coreId}/terminations`, body),
    createConnection: (body: Record<string, unknown>) => item<PhysicalConnection>('/api/v1/fim/physical-connections', body),
    createPassivePort: (assetId: number, body: Record<string, unknown>) => item<PassiveOpticalPort>(`/api/v1/assets/${assetId}/passive-optical-ports`, body),
    attachPassive: (terminationId: number, passiveOpticalPortId: number) => item<TerminationPortAttachment>(`/api/v1/fim/fiber-terminations/${terminationId}/port-attachments`, { passive_optical_port_id: passiveOpticalPortId }),
    detachPassive: (attachmentId: number) => remove(`/api/v1/fim/termination-port-attachments/${attachmentId}`),
    attachNetworkPort: (networkPortId: number, terminationId: number) => item<NetworkPortAttachment>(`/api/v1/network-ports/${networkPortId}/fiber-attachments`, { fiber_termination_id: terminationId }),
    detachNetworkPort: (attachmentId: number) => remove(`/api/v1/network-port-fiber-attachments/${attachmentId}`),
    createSplitterProfile: (assetId: number, body: Record<string, unknown>) => item<SplitterProfile>(`/api/v1/assets/${assetId}/splitter-profile`, body),
    generateSplitterPorts: (profileId: number, body: { inputs: Array<{ port_number: string; network_connection_point_id: number }>; outputs: Array<{ port_number: string; network_connection_point_id: number }> }) => item<PassiveOpticalPort[]>(`/api/v1/fim/splitter-profiles/${profileId}/ports/generate`, body),
    createSplitterBranch: (profileId: number, body: { input_port_id: number; output_port_id: number }) => item<SplitterBranch>(`/api/v1/fim/splitter-profiles/${profileId}/branches`, body),
    deleteSplitterBranch: (branchId: number) => remove(`/api/v1/fim/splitter-branches/${branchId}`),
    retireCable: (id: number) => update<FiberCable>(`/api/v1/fim/fiber-cables/${id}`, { status: 'retired' }),
    retireSegment: (id: number) => update<FiberSegment>(`/api/v1/fim/fiber-segments/${id}`, { status: 'retired' }),
    retireCore: (id: number) => update<FiberCore>(`/api/v1/fim/fiber-cores/${id}`, { status: 'decommissioned' }),
    retirePoint: (id: number) => update<ConnectionPoint>(`/api/v1/fim/connection-points/${id}`, { status: 'inactive' }),
    capacity: (kind: 'cable' | 'segment' | 'splitter', id: number) => apiClient.get(`/api/v1/fim/${kind === 'cable' ? 'cables' : kind === 'segment' ? 'fiber-segments' : 'splitter-profiles'}/${id}/capacity`).then(r => r.data.data),
    continuity: (coreId: number) => apiClient.get(`/api/v1/fim/fiber-cores/${coreId}/strand-path`, { params: { mode: 'physical-strand', max_depth: 20 } }).then(r => r.data.data),
};
