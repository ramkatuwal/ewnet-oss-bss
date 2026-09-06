import { apiClient } from './client';
import { SystemInfo, SystemConfig, SystemLogsResponse } from '@/features/system/types';

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

  uploadBranding: (type: 'logo' | 'favicon', file: File) => {
    const formData = new FormData();
    formData.append('type', type);
    formData.append('file', file);

    return apiClient
      .post<{ message: string; type: 'logo' | 'favicon'; path: string; url: string }>(
        '/api/v1/system/branding',
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      )
      .then((res) => res.data);
  },

  getLogs: (params: {
    type: 'nginx' | 'laravel';
    page: number;
    perPage: number;
    search?: string;
    level?: string;
    statusClass?: string;
    dateFrom?: string;
    dateTo?: string;
  }) =>
    apiClient
      .get<SystemLogsResponse>('/api/v1/debug/logs', {
        params: {
          type: params.type,
          page: params.page,
          per_page: params.perPage,
          window: 3000,
          search: params.search || undefined,
          level: params.level || undefined,
          status_class: params.statusClass || undefined,
          date_from: params.dateFrom || undefined,
          date_to: params.dateTo || undefined,
        },
      })
      .then((res) => res.data),
};
