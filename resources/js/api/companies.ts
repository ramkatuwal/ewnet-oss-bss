import { apiClient } from './client';
import type { Company, PaginatedResponse } from '@/types';

// Laravel JsonResource wraps single resources in { data: {...} }
// but paginated collections are already { data: [...], meta: {...} }
const unwrap = <T>(response: { data: unknown }): T => {
    const d = response.data as Record<string, unknown>;
    // If the response has a nested 'data' key with an object (not array), unwrap it
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) {
        return d.data as T;
    }
    return d as T;
};

export const companiesApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Company>>('/api/v1/organization/companies', { params })
            .then((r) => r.data),

    getById: (id: number) =>
        apiClient.get(`/api/v1/organization/companies/${id}`)
            .then((r) => unwrap<Company>(r)),

    create: (data: Partial<Company>) =>
        apiClient.post('/api/v1/organization/companies', data)
            .then((r) => unwrap<Company>(r)),

    update: (id: number, data: Partial<Company>) =>
        apiClient.put(`/api/v1/organization/companies/${id}`, data)
            .then((r) => unwrap<Company>(r)),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/companies/${id}`).then((r) => r.data),
};
