import axios from 'axios';
import type { Asset, PaginatedResponse } from '@/types';

export const getAssets = (params?: any) =>
    axios.get<PaginatedResponse<Asset>>('/api/v1/assets', { params }).then(res => res.data);

export const getAsset = (id: number) =>
    axios.get<{ data: Asset }>(`/api/v1/assets/${id}`).then(res => res.data.data);

export const createAsset = (data: any) =>
    axios.post<{ data: Asset }>('/api/v1/assets', data).then(res => res.data.data);

export const updateAsset = (id: number, data: any) =>
    axios.put<{ data: Asset }>(`/api/v1/assets/${id}`, data).then(res => res.data.data);

export const deleteAsset = (id: number) =>
    axios.delete(`/api/v1/assets/${id}`);

export const getAssetDashboard = () =>
    axios.get<{ data: any }>('/api/v1/assets/dashboard').then(res => res.data.data);

export const exportAssets = (format: string = 'csv', params?: any) =>
    axios.get(`/api/v1/assets/export`, {
        params: { ...params, format },
        responseType: 'blob'
    });

export const importAssets = (file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return axios.post('/api/v1/assets/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
    });
};

export const getSiteAssets = (siteId: number, params?: any) =>
    axios.get<PaginatedResponse<Asset>>(`/api/v1/sites/${siteId}/assets`, { params }).then(res => res.data);

// Asset Lifecycle
export const getAssetLifecycle = (assetId: number) =>
    axios.get<{ data: any[] }>(`/api/v1/assets/${assetId}/lifecycle`).then(res => res.data);

export const createAssetLifecycleEvent = (assetId: number, data: any) =>
    axios.post(`/api/v1/assets/${assetId}/lifecycle`, data).then(res => res.data);

export const transferAsset = (assetId: number, data: { to_site_id: number; notes?: string }) =>
    axios.post(`/api/v1/assets/${assetId}/transfer`, data).then(res => res.data);

export const retireAsset = (assetId: number, data?: { notes?: string }) =>
    axios.post(`/api/v1/assets/${assetId}/retire`, data || {}).then(res => res.data);

export const disposeAsset = (assetId: number, data?: { notes?: string }) =>
    axios.post(`/api/v1/assets/${assetId}/dispose`, data || {}).then(res => res.data);

// Asset Photos
export const getAssetPhotos = (assetId: number) =>
    axios.get(`/api/v1/assets/${assetId}/photos`).then(res => res.data);

export const uploadAssetPhoto = (assetId: number, data: FormData) =>
    axios.post(`/api/v1/assets/${assetId}/photos`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then(res => res.data);

export const deleteAssetPhoto = (assetId: number, photoId: number) =>
    axios.delete(`/api/v1/assets/${assetId}/photos/${photoId}`).then(res => res.data);
