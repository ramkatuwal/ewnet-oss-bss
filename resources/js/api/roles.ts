import { apiClient } from './client';
import type { Role, PaginatedResponse } from '@/types';

const unwrap = <T>(res: { data: unknown }): T => {
    const d = res.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) return d.data as T;
    return d as T;
};

export const rolesApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Role>>('/api/v1/security/roles', { params }).then(r => r.data),

    getById: (id: number) =>
        apiClient.get(`/api/v1/security/roles/${id}`).then(r => unwrap<Role>(r)),

    create: (data: { name: string; permissions?: number[] }) =>
        apiClient.post('/api/v1/security/roles', data).then(r => unwrap<Role>(r)),

    update: (id: number, data: { name?: string; permissions?: number[] }) =>
        apiClient.put(`/api/v1/security/roles/${id}`, data).then(r => unwrap<Role>(r)),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/security/roles/${id}`).then(r => r.data),

    getUsers: (roleId: number, params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<any>>(`/api/v1/security/roles/${roleId}/users`, { params }).then(r => r.data),
};
