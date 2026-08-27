import { apiClient } from './client';
import { Role } from '@/types';

export const rolesApi = {
    getAll: () => apiClient.get<Role[]>('/api/v1/security/roles').then(res => res.data),
    getById: (id: number) => apiClient.get<Role>(`/api/v1/security/roles/${id}`).then(res => res.data),
    create: (data: Partial<Role>) => apiClient.post<Role>('/api/v1/security/roles', data).then(res => res.data),
    update: (id: number, data: Partial<Role>) => apiClient.put<Role>(`/api/v1/security/roles/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/security/roles/${id}`).then(res => res.data),
    assignPermissions: (id: number, permissionIds: number[]) => apiClient.post(`/api/v1/security/roles/${id}/permissions`, { permissions: permissionIds }).then(res => res.data),
};
