export interface Branch {
    id: number;
    region_id: number;
    region?: {
        id: number;
        name: string;
        code: string;
        company_id: number;
        company?: { id: number; name: string } | null;
    } | null;
    name: string;
    code: string;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country: string;
    phone?: string | null;
    email?: string | null;
    latitude?: string | null;
    longitude?: string | null;
    settings?: Record<string, unknown> | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}
