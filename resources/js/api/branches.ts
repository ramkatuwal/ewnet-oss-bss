import { apiClient } from './client';
import { Branch } from '@/types';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const branchesApi = {
    getAll: (params?: any) => apiClient.get<PaginatedResponse<Branch>>('/api/v1/organization/branches', { params }).then(res => res.data),
    getById: (id: number) => apiClient.get<Branch>(`/api/v1/organization/branches/${id}`).then(res => res.data),
    create: (data: Partial<Branch>) => apiClient.post<Branch>('/api/v1/organization/branches', data).then(res => res.data),
    update: (id: number, data: Partial<Branch>) => apiClient.put<Branch>(`/api/v1/organization/branches/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/organization/branches/${id}`).then(res => res.data),
};
