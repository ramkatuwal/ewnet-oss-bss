import { apiClient } from './client';
import { Company } from '@/types';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const companiesApi = {
    getAll: (params?: any) => apiClient.get<PaginatedResponse<Company>>('/api/v1/organization/companies', { params }).then(res => res.data),
    getById: (id: number) => apiClient.get<Company>(`/api/v1/organization/companies/${id}`).then(res => res.data),
    create: (data: Partial<Company>) => apiClient.post<Company>('/api/v1/organization/companies', data).then(res => res.data),
    update: (id: number, data: Partial<Company>) => apiClient.put<Company>(`/api/v1/organization/companies/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/organization/companies/${id}`).then(res => res.data),
};
