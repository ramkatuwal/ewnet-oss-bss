import type { Asset, AssetInterface, AssetOperational, AssetVlanMembership, IpAddress, NetworkPort, PaginatedResponse, PonMembership } from '@/types';
import { apiClient } from '@/api/client';

export type { NetworkPort };

export interface AssetListParams {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    category?: string;
    type?: string;
    site_id?: number;
    company_id?: number;
    region_id?: number;
    branch_id?: number;
    manufacturer?: string;
    condition?: string;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
}

export const getAssets = (params?: AssetListParams) =>
    apiClient.get<PaginatedResponse<Asset>>('/api/v1/assets', { params }).then(res => res.data);

export const getAsset = (id: number) =>
    apiClient.get<{ data: AssetOperational }>(`/api/v1/assets/${id}`).then(res => res.data.data);

export const createAsset = (data: Partial<Asset>) =>
    apiClient.post<{ data: AssetOperational }>('/api/v1/assets', data).then(res => res.data.data);

export const updateAsset = (id: number, data: Partial<Asset>) =>
    apiClient.put<{ data: AssetOperational }>(`/api/v1/assets/${id}`, data).then(res => res.data.data);

export const deleteAsset = (id: number) =>
    apiClient.delete(`/api/v1/assets/${id}`);

export interface AssetDashboardData {
    sites_with_assets: number;
    total_records: number;
    total_units: number;
    by_status: {
        operational: number;
        maintenance: number;
        faulty: number;
        retired: number;
    };
}

export interface AssetLifecycleEvent {
    id: number;
    asset_id: number;
    event_type: string;
    status_before: string | null;
    status_after: string | null;
    from_site_id: number | null;
    to_site_id: number | null;
    from_site?: { id: number; site_code: string; name: string };
    to_site?: { id: number; site_code: string; name: string };
    notes: string | null;
    created_by: number;
    created_by_user?: { id: number; name: string; email: string };
    event_date: string;
    created_at: string;
    updated_at: string;
}

export const getAssetDashboard = () =>
    apiClient.get<{ data: AssetDashboardData }>('/api/v1/assets/dashboard').then(res => res.data.data);

export const exportAssets = (format: string = 'csv', params?: Record<string, any>) =>
    apiClient.get('/api/v1/assets/export', {
        params: { ...params, format },
        responseType: 'blob',
    });

export const importAssets = (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post('/api/v1/assets/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
};

export const getSiteAssets = (siteId: number, params?: AssetListParams) =>
    apiClient.get<PaginatedResponse<Asset>>(`/api/v1/sites/${siteId}/assets`, { params }).then(res => res.data);

// Asset Lifecycle
export const getAssetLifecycle = (assetId: number) =>
    apiClient.get<{ data: AssetLifecycleEvent[] }>(`/api/v1/assets/${assetId}/lifecycle`).then(res => res.data);

export const createAssetLifecycleEvent = (assetId: number, data: Record<string, unknown>) =>
    apiClient.post(`/api/v1/assets/${assetId}/lifecycle`, data).then(res => res.data);

export const transferAsset = (assetId: number, data: { to_site_id: number; notes?: string }) =>
    apiClient.post(`/api/v1/assets/${assetId}/transfer`, data).then(res => res.data);

export const retireAsset = (assetId: number, data?: { notes?: string }) =>
    apiClient.post(`/api/v1/assets/${assetId}/retire`, data || {}).then(res => res.data);

export const disposeAsset = (assetId: number, data?: { notes?: string }) =>
    apiClient.post(`/api/v1/assets/${assetId}/dispose`, data || {}).then(res => res.data);

// Asset Photos
export const getAssetPhotos = (assetId: number) =>
    apiClient.get(`/api/v1/assets/${assetId}/photos`).then(res => res.data);

export const uploadAssetPhoto = (assetId: number, data: FormData) =>
    apiClient.post(`/api/v1/assets/${assetId}/photos`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then(res => res.data);

export const deleteAssetPhoto = (assetId: number, photoId: number) =>
    apiClient.delete(`/api/v1/assets/${assetId}/photos/${photoId}`).then(res => res.data);

export const getNetworkPorts = (assetId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<NetworkPort>>(`/api/v1/assets/${assetId}/network-ports`, { params }).then((res) => res.data);

// Observed/authoritative operational sub-resources (read-only detail views)
export const getAssetInterfaces = (assetId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<AssetInterface>>(`/api/v1/assets/${assetId}/interfaces`, { params }).then((res) => res.data);

export const getAssetIpAddresses = (assetId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<IpAddress>>(`/api/v1/assets/${assetId}/ip-addresses`, { params }).then((res) => res.data);

export const getAssetPonMemberships = (assetId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<PonMembership>>(`/api/v1/assets/${assetId}/pon-memberships`, { params }).then((res) => res.data);

export const getAssetVlanMemberships = (assetId: number, params?: { page?: number; per_page?: number }) =>
    apiClient.get<PaginatedResponse<AssetVlanMembership>>(`/api/v1/assets/${assetId}/vlan-memberships`, { params }).then((res) => res.data);
