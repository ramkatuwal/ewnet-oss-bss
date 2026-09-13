import { apiClient } from '@/api/client';
import type { AssetCategory, AssetDeviceType, AssetUnit } from '../types/assetModelSettings';

export const assetModelSettingsApi = {
    // Categories
    getCategories: () =>
        apiClient.get<{ data: AssetCategory[] }>('/api/v1/settings/model-settings/asset/categories'),
    createCategory: (data: Partial<AssetCategory>) =>
        apiClient.post<{ data: AssetCategory }>('/api/v1/settings/model-settings/asset/categories', data),
    updateCategory: (id: number, data: Partial<AssetCategory>) =>
        apiClient.put<{ data: AssetCategory }>(`/api/v1/settings/model-settings/asset/categories/${id}`, data),
    deleteCategory: (id: number) =>
        apiClient.delete(`/api/v1/settings/model-settings/asset/categories/${id}`),

    // Device Types
    getDeviceTypes: (categoryId?: number) =>
        apiClient.get<{ data: AssetDeviceType[] }>('/api/v1/settings/model-settings/asset/device-types', {
            params: categoryId ? { category_id: categoryId } : {},
        }),
    createDeviceType: (data: Partial<AssetDeviceType>) =>
        apiClient.post<{ data: AssetDeviceType }>('/api/v1/settings/model-settings/asset/device-types', data),
    updateDeviceType: (id: number, data: Partial<AssetDeviceType>) =>
        apiClient.put<{ data: AssetDeviceType }>(`/api/v1/settings/model-settings/asset/device-types/${id}`, data),
    deleteDeviceType: (id: number) =>
        apiClient.delete(`/api/v1/settings/model-settings/asset/device-types/${id}`),

    // Units
    getUnits: () =>
        apiClient.get<{ data: AssetUnit[] }>('/api/v1/settings/model-settings/asset/units'),
    createUnit: (data: Partial<AssetUnit>) =>
        apiClient.post<{ data: AssetUnit }>('/api/v1/settings/model-settings/asset/units', data),
    updateUnit: (id: number, data: Partial<AssetUnit>) =>
        apiClient.put<{ data: AssetUnit }>(`/api/v1/settings/model-settings/asset/units/${id}`, data),
    deleteUnit: (id: number) =>
        apiClient.delete(`/api/v1/settings/model-settings/asset/units/${id}`),
};
