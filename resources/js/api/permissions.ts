import { apiClient } from './client';
import { Permission } from '@/types';

export const permissionsApi = {
    getAll: () => apiClient.get<Permission[]>('/api/v1/security/permissions').then(res => res.data),
    getById: (id: number) => apiClient.get<Permission>(`/api/v1/security/permissions/${id}`).then(res => res.data),
    create: (data: Partial<Permission>) => apiClient.post<Permission>('/api/v1/security/permissions', data).then(res => res.data),
    update: (id: number, data: Partial<Permission>) => apiClient.put<Permission>(`/api/v1/security/permissions/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/security/permissions/${id}`).then(res => res.data),
};
