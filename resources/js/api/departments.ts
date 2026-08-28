import { apiClient } from './client';
import type { Department } from '@/types';

interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const unwrap = <T>(res: { data: unknown }): T => {
    const d = res.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) {
        return d.data as T;
    }
    return d as T;
};

export const departmentsApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<Department>>('/api/v1/organization/departments', { params })
            .then(res => res.data),

    getById: (id: number) =>
        apiClient.get(`/api/v1/organization/departments/${id}`)
            .then(res => unwrap<Department>(res)),

    create: (data: Partial<Department>) =>
        apiClient.post('/api/v1/organization/departments', data)
            .then(res => unwrap<Department>(res)),

    update: (id: number, data: Partial<Department>) =>
        apiClient.put(`/api/v1/organization/departments/${id}`, data)
            .then(res => unwrap<Department>(res)),

    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/departments/${id}`)
            .then(res => res.data),
};
