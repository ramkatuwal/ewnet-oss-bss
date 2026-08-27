import { apiClient } from './client';
import type { Region, PaginatedResponse } from '@/types';

const unwrap = <T>(response: { data: unknown }): T => {
    const d = response.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) return d.data as T;
    return d as T;
};

export const regionsApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Region>>('/api/v1/organization/regions', { params }).then((r) => r.data),
    getById: (id: number) =>
        apiClient.get(`/api/v1/organization/regions/${id}`).then((r) => unwrap<Region>(r)),
    create: (data: Partial<Region>) =>
        apiClient.post('/api/v1/organization/regions', data).then((r) => unwrap<Region>(r)),
    update: (id: number, data: Partial<Region>) =>
        apiClient.put(`/api/v1/organization/regions/${id}`, data).then((r) => unwrap<Region>(r)),
    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/regions/${id}`).then((r) => r.data),
};
