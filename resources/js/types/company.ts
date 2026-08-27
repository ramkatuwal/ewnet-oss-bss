export interface Company {
    id: number;
    name: string;
    registration_number?: string | null;
    pan_number?: string | null;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country: string;
    website?: string | null;
    settings?: Record<string, unknown> | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}
