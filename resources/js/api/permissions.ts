import { apiClient } from './client';
import type { Permission, PaginatedResponse } from '@/types';

const unwrap = <T>(res: { data: unknown }): T => {
    const d = res.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) return d.data as T;
    return d as T;
};

export const permissionsApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Permission>>('/api/v1/security/permissions', { params }).then(r => r.data),

    getById: (id: number) =>
        apiClient.get(`/api/v1/security/permissions/${id}`).then(r => unwrap<Permission>(r)),

    create: (data: { name: string }) =>
        apiClient.post('/api/v1/security/permissions', data).then(r => unwrap<Permission>(r)),

    update: (id: number, data: { name: string }) =>
        apiClient.put(`/api/v1/security/permissions/${id}`, data).then(r => unwrap<Permission>(r)),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/security/permissions/${id}`).then(r => r.data),
};
