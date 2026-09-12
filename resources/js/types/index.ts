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

// Management Scope types
export interface ManagementScope {
    id: number;
    scope_type: 'company' | 'region' | 'branch' | 'department';
    scope_id: number;
    scope_name?: string;
}

export interface ScopeAssignment {
    id: number;
    user_id: number;
    scope_type: string;
    scope_id: number;
    granted_by?: number;
    created_at: string;
    updated_at: string;
}

export interface Asset {
    id: number;
    site_id: number;
    asset_tag: string;
    device_name: string | null;
    serial_number: string | null;
    category: string;
    type: string;
    manufacturer: string | null;
    model: string | null;
    quantity: number;
    unit: string;
    status: string;
    condition: string | null;
    purchase_date: string | null;
    installation_date: string | null;
    warranty_expiry: string | null;
    specifications: Record<string, unknown> | null;
    description: string | null;
    notes: string | null;
    ip_address: string | null;
    mac_address: string | null;
    created_by: number | null;
    updated_by: number | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    site?: {
        id: number;
        site_code: string;
        name: string;
        company_id?: number;
        region_id?: number;
        branch_id?: number;
        company?: { id: number; name: string };
        region?: { id: number; name: string };
        branch?: { id: number; name: string };
    };
    provider_observations?: {
        provider: string;
        external_id: string | null;
        provider_status: string | null;
        observed_hostname: string | null;
        observed_os: string | null;
        observed_hardware: string | null;
        observed_version: string | null;
        observed_uptime: number | null;
        ip_address: string | null;
        serial_number: string | null;
        mac_address: string | null;
        last_observed_at: string | null;
        last_synced: string | null;
    } | null;
}

export interface IpAddress {
    id: number;
    asset_interface_id: number;
    ip_address: string;
    family: string | null;
    prefix_length: number;
    is_primary: boolean;
    is_management: boolean;
    provider: string | null;
    external_type: string | null;
    external_id: string | null;
    first_seen_at: string | null;
    last_seen_at: string | null;
    created_at?: string;
    updated_at?: string;
    interface?: AssetInterface;
}

export interface AssetInterface {
    id: number;
    asset_id: number;
    name: string;
    display_name: string | null;
    description: string | null;
    type: string | null;
    mac_address: string | null;
    speed: number | null;
    status: string | null;
    is_management: boolean;
    provider: string | null;
    external_type: string | null;
    external_id: string | null;
    metadata: Record<string, unknown> | null;
    first_seen_at: string | null;
    last_seen_at: string | null;
    created_at?: string;
    updated_at?: string;
    ip_addresses?: IpAddress[];
}

export interface Vlan {
    id: number;
    company_id: number;
    vid: number;
    name: string | null;
    description: string | null;
    reserved: boolean;
    created_at?: string;
    updated_at?: string;
}

export interface NetworkPort {
    id: number;
    asset_id: number;
    company_id: number;
    port_key: string;
    name: string | null;
    slot: string | null;
    card: string | null;
    port_number: number | null;
    connector_type: string | null;
    port_direction: string | null;
    technology: string | null;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
    pon_domain?: PonDomain | null;
}

export interface PonDomain {
    id: number;
    olt_port_id: number;
    company_id: number;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
    olt_port?: NetworkPort;
    memberships?: PonMembership[];
}

export interface PonMembership {
    id: number;
    pon_domain_id: number;
    onu_asset_id: number;
    onu_id: string | null;
    company_id: number;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
    pon_domain?: PonDomain;
    onu_asset?: Asset;
}

export interface AssetVlanMembership {
    id: number;
    network_port_id: number | null;
    port_key: string | null;
    port_name: string | null;
    port_technology: string | null;
    mode: string | null;
    tagging: string;
    vlan_id: number;
    vlan?: Vlan;
    updated_at?: string;
}

export interface RoutingL3InterfaceAddress {
    id: number;
    routing_l3_interface_id: number;
    routing_instance_id: number | null;
    asset_id: number | null;
    company_id: number | null;
    address: string;
    prefix_length: number;
    address_role: string | null;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
}

export interface RoutingL3Interface {
    id: number;
    routing_instance_id: number;
    asset_id: number;
    company_id: number;
    name: string | null;
    kind: string;
    network_port_id: number | null;
    vlan_id: number | null;
    parent_routing_l3_interface_id: number | null;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
    addresses?: RoutingL3InterfaceAddress[];
}

export interface RoutingStaticRoute {
    id: number;
    routing_instance_id: number;
    routing_l3_interface_id: number | null;
    asset_id: number;
    company_id: number;
    destination: string;
    gateway: string;
    route_type: string | null;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
}

export interface RoutingInstance {
    id: number;
    asset_id: number;
    company_id: number;
    name: string;
    kind: string;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
    deleted_at?: string | null;
    l3_interfaces?: RoutingL3Interface[];
    static_routes?: RoutingStaticRoute[];
}

export interface PassiveOpticalPort {
    id: number;
    asset_id: number;
    network_connection_point_id: number | null;
    company_id: number;
    port_number: string | null;
    connector_type: string | null;
    port_role: string | null;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

export interface SplitterProfile {
    id: number;
    asset_id: number;
    company_id: number;
    input_port_count: number;
    output_port_count: number;
    split_ratio: string;
    metadata: Record<string, unknown> | null;
    created_at?: string;
    updated_at?: string;
}

export interface AssetOperational extends Asset {
    company_id?: number;
    interfaces?: AssetInterface[];
    ip_addresses?: IpAddress[];
    network_ports?: NetworkPort[];
    routing_instances?: RoutingInstance[];
    passive_optical_ports?: PassiveOpticalPort[];
    splitter_profile?: SplitterProfile | null;
    pon_memberships?: PonMembership[];
}
