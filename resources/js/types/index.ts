export interface User {
    id: number;
    name: string;
    email: string;
    roles?: string[];
    permissions?: string[];
    [key: string]: any;
}

export interface AuthUser extends User {}

export interface LoginResponse {
    user: User;
    token?: string;
}

export interface Company {
    id: number;
    name: string;
    [key: string]: any;
}

export interface Region {
    id: number;
    name: string;
    code: string;
    company?: Company;
    [key: string]: any;
}

export interface Branch {
    id: number;
    name: string;
    code: string;
    region?: Region;
    [key: string]: any;
}

export interface Department {
    id: number;
    name: string;
    code: string;
    branch?: Branch;
    manager?: any; // Retained for backward compatibility with legacy data
    [key: string]: any;
}

export interface Role {
    id: number;
    name: string;
    permissions?: any[];
    [key: string]: any;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    [key: string]: any;
}
