import { apiClient } from './client';
import { Department } from '@/types';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const departmentsApi = {
    getAll: (params?: any) => apiClient.get<PaginatedResponse<Department>>('/api/v1/organization/departments', { params }).then(res => res.data),
    getById: (id: number) => apiClient.get<Department>(`/api/v1/organization/departments/${id}`).then(res => res.data),
    create: (data: Partial<Department>) => apiClient.post<Department>('/api/v1/organization/departments', data).then(res => res.data),
    update: (id: number, data: Partial<Department>) => apiClient.put<Department>(`/api/v1/organization/departments/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/organization/departments/${id}`).then(res => res.data),
};
