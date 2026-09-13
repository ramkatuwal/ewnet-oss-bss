export interface AssetCategory {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    assets_count?: number;
    created_at: string;
    updated_at: string;
}

export interface AssetDeviceType {
    id: number;
    code: string;
    name: string;
    category_id: number;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    category?: AssetCategory;
    assets_count?: number;
    created_at: string;
    updated_at: string;
}

export interface AssetUnit {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    assets_count?: number;
    created_at: string;
    updated_at: string;
}
