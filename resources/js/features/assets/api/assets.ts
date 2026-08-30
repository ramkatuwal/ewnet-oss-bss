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
