import { apiClient } from './client';
import { SystemInfo, SystemConfig } from '@/features/system/types';

export const systemApi = {
  getInfo: () =>
    apiClient.get<{ data: SystemInfo }>('/api/v1/system/info').then((res) => res.data.data),

  getConfig: () =>
    apiClient.get<{ data: SystemConfig }>('/api/v1/system/configuration').then((res) => res.data.data),

  updateConfig: (data: Partial<SystemConfig>) =>
    apiClient.put<{ message: string; updated: Record<string, unknown> }>(
      '/api/v1/system/configuration',
      data
    ).then((res) => res.data),
};
