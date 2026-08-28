import { apiClient } from './client';
import type { PaginatedResponse } from '@/types';

export interface AuditLog {
    id: number;
    action: string;
    result: string;
    actor: {
        type: string;
        id: number | null;
        name: string | null;
        email: string | null;
    };
    target: {
        type: string | null;
        id: number | null;
        name: string | null;
    };
    organization_context: {
        company_id?: number | null;
        branch_id?: number | null;
        department_id?: number | null;
    } | null;
    ip_address: string | null;
    user_agent: string | null;
    correlation_id: string;
    metadata: Record<string, any> | null;
    created_at: string;
}

export interface AuditLogFilters {
    page?: number;
    per_page?: number;
    search?: string;
    action?: string;
    result?: string;
    actor_id?: number;
    target_type?: string;
    date_from?: string;
    date_to?: string;
}

const unwrap = <T>(res: { data: unknown }): T => {
    const d = res.data as Record<string, unknown>;
    if (d && typeof d === 'object' && 'data' in d && !Array.isArray(d.data)) return d.data as T;
    return d as T;
};

export const auditApi = {
    getAll: (params?: AuditLogFilters) =>
        apiClient.get<PaginatedResponse<AuditLog>>('/api/v1/security/audit-logs', { params })
            .then(r => r.data),

    getById: (id: number) =>
        apiClient.get(`/api/v1/security/audit-logs/${id}`)
            .then(r => unwrap<AuditLog>(r)),
};
