import type { Company } from './company';

export interface Region {
    id: number;
    company_id: number;
    company?: Pick<Company, 'id' | 'name' | 'logo_url'> | null;
    name: string;
    code: string;
    description?: string | null;
    city?: string | null;
    state?: string | null;
    country: string;
    settings?: Record<string, unknown> | null;
    is_active: boolean;
    branches_count?: number;
    created_at?: string;
    updated_at?: string;
}
