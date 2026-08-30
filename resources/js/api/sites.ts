import { apiClient } from './client';
import { PaginatedResponse } from '@/types';

export interface Company {
    id: number;
    name: string;
    registration_number?: string;
    pan_number?: string;
    email?: string;
    phone?: string;
    address?: string;
    city?: string;
    state?: string;
    country?: string;
    website?: string;
    is_active?: boolean;
}

export interface Region {
    id: number;
    company_id: number;
    name: string;
    code?: string;
    description?: string;
    city?: string;
    state?: string;
    country?: string;
    is_active?: boolean;
}

export interface Branch {
    id: number;
    region_id: number;
    company_id: number;
    name: string;
    code?: string;
    description?: string;
    city?: string;
    state?: string;
    country?: string;
    is_active?: boolean;
}

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
    // Eager-loaded relationships
    company?: Company;
    region?: Region;
    branch?: Branch;
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

// Site Photos
export const getSitePhotos = (siteId: number) =>
    apiClient.get(`/api/v1/sites/${siteId}/photos`).then(res => res.data);

export const uploadSitePhoto = (siteId: number, data: FormData) =>
    apiClient.post(`/api/v1/sites/${siteId}/photos`, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }).then(res => res.data);

export const deleteSitePhoto = (siteId: number, photoId: number) =>
    apiClient.delete(`/api/v1/sites/${siteId}/photos/${photoId}`).then(res => res.data);
