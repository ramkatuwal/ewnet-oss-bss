import { apiClient } from './client';
import { PaginatedResponse } from '@/types';

export interface Site {
    id: number;
    site_code: string;
    name: string;
    type: string;
    status: string;
    description?: string;
    notes?: string;
    latitude?: number;
    longitude?: number;
    altitude?: number;
    province?: string;
    district?: string;
    municipality?: string;
    ward?: string;
    tole?: string;
    address?: string;
    postal_code?: string;
    company_id?: number;
    region_id?: number;
    branch_id?: number;
    created_at: string;
    updated_at: string;
}

export const sitesApi = {
    list: (params?: Record<string, any>) =>
        apiClient.get<PaginatedResponse<Site>>('/api/v1/sites', { params }).then((res) => res.data),

    create: (data: Partial<Site>) =>
        apiClient.post<{ data: Site }>('/api/v1/sites', data).then((res) => res.data.data),

    get: (id: number) =>
        apiClient.get<{ data: Site }>(`/api/v1/sites/${id}`).then((res) => res.data.data),

    update: (id: number, data: Partial<Site>) =>
        apiClient.put<{ data: Site }>(`/api/v1/sites/${id}`, data).then((res) => res.data.data),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/sites/${id}`),
};
