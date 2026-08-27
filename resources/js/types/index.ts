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


export interface Region {
    id: number;
    name: string;
    code?: string;
    company_id: number;
    company?: Company;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface Branch {
    id: number;
    name: string;
    code?: string;
    region_id: number;
    region?: Region;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface Department {
    id: number;
    name: string;
    code?: string;
    branch_id: number;
    branch?: Branch;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
    created_at?: string;
    updated_at?: string;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
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
