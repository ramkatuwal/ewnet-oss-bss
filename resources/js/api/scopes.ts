import { apiClient } from './client';
import type { ManagementScope, ScopeAssignment } from '@/types';

export const scopesApi = {
    /**
     * Get all management scopes for a user
     */
    getUserScopes: (userId: number): Promise<ManagementScope[]> =>
        apiClient.get(`/api/v1/organization/users/${userId}/management-scopes`)
            .then((r) => Array.isArray(r.data) ? r.data : []),

    /**
     * Assign a management scope to a user
     */
    assignScope: (userId: number, scope: ScopeAssignment): Promise<ManagementScope> =>
        apiClient.post(`/api/v1/organization/users/${userId}/management-scopes`, scope)
            .then((r) => r.data.scope),

    /**
     * Revoke a management scope from a user
     */
    revokeScope: (userId: number, scopeId: number): Promise<void> =>
        apiClient.delete(`/api/v1/organization/users/${userId}/management-scopes/${scopeId}`)
            .then((r) => r.data),
};
