import axios from 'axios';

const api = axios.create({ baseURL: '/api/v1' });

export interface ImportProvider {
  id: number;
  name: string;
  identity: string;
  status: string;
}

export interface ImportPreviewResponse {
  total?: number;
  analysis?: any[];
  summary?: any;
  devices?: any;
  sites?: any;
}

export interface ImportItem {
  record: any;
  analysis: any;
}

export const importApi = {
  getProviders: () => api.get<{ data: ImportProvider[] }>('/import/providers').then(r => r.data.data),

  preview: (integrationId: number, sourceType: 'devices' | 'sites') =>
    api.post<ImportPreviewResponse>(`/integrations/${integrationId}/import/preview`, { type: sourceType }).then(r => r.data),

  execute: (integrationId: number, items: ImportItem[]) =>
    api.post(`/integrations/${integrationId}/import/execute`, { items }).then(r => r.data),

  getHistory: (params?: { source?: string; type?: string; page?: number; per_page?: number }) =>
    api.get('/import/history', { params }).then(r => r.data),
};
