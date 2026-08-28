// ============================================================
// CORE TYPES
// ============================================================

export interface User {
    id: number;
    name: string;
    email: string;
    company_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    is_active: boolean;
    roles: string[];
    permissions: string[];
}

export type AuthUser = User;

export interface LoginResponse {
    message: string;
    user: User;
}




export interface Department {
    id: number;
    name: string;
    code: string;
    description?: string | null;
    company_id: number;
    branch_id: number;
    is_active: boolean;
    user_count?: number;
    branch?: {
        id: number;
        name: string;
        region?: {
            id: number;
            name: string;
            company?: {
                id: number;
                name: string;
            } | null;
        } | null;
    } | null;
    company?: {
        id: number;
        name: string;
    } | null;
    created_at?: string;
    updated_at?: string;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    is_protected: boolean;
    permissions?: Permission[];
    permission_count?: number;
    user_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    domain: string;
    action: string;
    role_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface AuditLog {
    id: number;
    actor_type?: string;
    actor_id?: number;
    action: string;
    target_type?: string;
    target_id?: number;
    organization_context?: Record<string, unknown>;
    result: string;
    ip_address?: string;
    user_agent?: string;
    correlation_id?: string;
    metadata?: Record<string, unknown>;
    created_at: string;
}

// ============================================================
// PAGINATION
// ============================================================

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

// ============================================================
// NAVIGATION
// ============================================================

export interface NavItem {
    label: string;
    path?: string;
    icon: React.ReactNode;
    permission?: string;
    children?: NavItem[];
}

// ============================================================
// API ERROR
// ============================================================

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

export type { Company } from './company';
export type { Region } from './region';
export type { Branch } from './branch';
export type { UserListItem, UserRole } from './user';
