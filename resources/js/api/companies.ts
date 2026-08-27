import { apiClient } from './client';
import type { Company, PaginatedResponse } from '@/types';

const unwrap = <T>(response: { data: unknown }): T => {
    const d = response.data as Record<string, unknown>;
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

    create: (formData: FormData) =>
        apiClient.post('/api/v1/organization/companies', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }).then((r) => unwrap<Company>(r)),

    update: (id: number, formData: FormData) =>
        apiClient.post(`/api/v1/organization/companies/${id}?_method=PUT`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }).then((r) => unwrap<Company>(r)),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/companies/${id}`).then((r) => r.data),
};
