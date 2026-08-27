import { apiClient } from './client';
import { Region } from '@/types';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export const regionsApi = {
    getAll: (params?: any) => apiClient.get<PaginatedResponse<Region>>('/api/v1/organization/regions', { params }).then(res => res.data),
    getById: (id: number) => apiClient.get<Region>(`/api/v1/organization/regions/${id}`).then(res => res.data),
    create: (data: Partial<Region>) => apiClient.post<Region>('/api/v1/organization/regions', data).then(res => res.data),
    update: (id: number, data: Partial<Region>) => apiClient.put<Region>(`/api/v1/organization/regions/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/organization/regions/${id}`).then(res => res.data),
};
