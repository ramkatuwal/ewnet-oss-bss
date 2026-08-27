import { apiClient } from './client';
import type { Company, PaginatedResponse } from '@/types';

export const companiesApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Company>>('/api/v1/organization/companies', { params }).then((r) => r.data),

    getById: (id: number) =>
        apiClient.get<Company>(`/api/v1/organization/companies/${id}`).then((r) => r.data),

    create: (data: Partial<Company>) =>
        apiClient.post<Company>('/api/v1/organization/companies', data).then((r) => r.data),

    update: (id: number, data: Partial<Company>) =>
        apiClient.put<Company>(`/api/v1/organization/companies/${id}`, data).then((r) => r.data),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/companies/${id}`).then((r) => r.data),
};
