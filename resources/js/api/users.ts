import { apiClient } from './client';
import type { UserListItem, PaginatedResponse } from '@/types';

const unwrap = <T>(response: { data: unknown }): T => {
    const d = response.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) return d.data as T;
    return d as T;
};

export const usersApi = {
    getAll: (params?: Record<string, unknown>) =>
        apiClient.get<PaginatedResponse<UserListItem>>('/api/v1/organization/users', { params }).then((r) => r.data),
    getById: (id: number) =>
        apiClient.get(`/api/v1/organization/users/${id}`).then((r) => unwrap<UserListItem>(r)),
    create: (data: Record<string, unknown>) =>
        apiClient.post('/api/v1/organization/users', data).then((r) => unwrap<UserListItem>(r)),
    update: (id: number, data: Record<string, unknown>) =>
        apiClient.put(`/api/v1/organization/users/${id}`, data).then((r) => unwrap<UserListItem>(r)),
    delete: (id: number) =>
        apiClient.delete(`/api/v1/organization/users/${id}`).then((r) => r.data),
};
