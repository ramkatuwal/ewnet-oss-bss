import { apiClient } from './client';
import { User, Role } from '@/types';

export const usersApi = {
    getAll: () => apiClient.get<{ data: User[] }>('/api/v1/organization/users').then(res => res.data.data),
    getById: (id: number) => apiClient.get<User>(`/api/v1/organization/users/${id}`).then(res => res.data),
    create: (data: Partial<User> & { password: string }) => apiClient.post<User>('/api/v1/organization/users', data).then(res => res.data),
    update: (id: number, data: Partial<User>) => apiClient.put<User>(`/api/v1/organization/users/${id}`, data).then(res => res.data),
    delete: (id: number) => apiClient.delete(`/api/v1/organization/users/${id}`).then(res => res.data),
    assignRoles: (id: number, roleIds: number[]) => apiClient.post(`/api/v1/organization/users/${id}/roles`, { roles: roleIds }).then(res => res.data),
    getRoles: (id: number) => apiClient.get<Role[]>(`/api/v1/organization/users/${id}/roles`).then(res => res.data),
};
