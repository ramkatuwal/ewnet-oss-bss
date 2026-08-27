import { apiClient } from './client';
import type { Branch, PaginatedResponse } from '@/types';

const unwrap = <T>(response: { data: unknown }): T => {
    const d = response.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) return d.data as T;
    return d as T;
};

export const branchesApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Branch>>('/api/v1/organization/branches', { params }).then((r) => r.data),
    getById: (id: number) =>
        apiClient.get(`/api/v1/organization/branches/${id}`).then((r) => unwrap<Branch>(r)),
    create: (data: Partial<Branch>) =>
        apiClient.post('/api/v1/organization/branches', data).then((r) => unwrap<Branch>(r)),
    update: (id: number, data: Partial<Branch>) =>
        apiClient.put(`/api/v1/organization/branches/${id}`, data).then((r) => unwrap<Branch>(r)),
    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/branches/${id}`).then((r) => r.data),
};
