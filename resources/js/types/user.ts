export interface UserListItem {
    id: number;
    name: string;
    email: string;
    phone_number?: string | null;
    is_active: boolean;
    company_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    company?: { id: number; name: string } | null;
    branch?: { id: number; name: string; region?: { id: number; name: string } | null } | null;
    department?: { id: number; name: string } | null;
    roles?: { id: number; name: string }[];
    management_scopes?: ManagementScope[];
    last_login_at?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface UserRole {
    id: number;
    name: string;
}

export interface ManagementScope {
    id?: number;
    scope_type: ScopeType;
    scope_id: number;
    scope_name?: string | null;
    granted_by?: string | null;
    granted_at?: string;
}

export type ScopeType = 'global' | 'company' | 'region' | 'branch' | 'department';

export interface ScopeAssignment {
    scope_type: ScopeType;
    scope_id: number;
}
