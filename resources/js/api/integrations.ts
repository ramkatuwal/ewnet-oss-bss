import axios from 'axios';

const api = axios.create({ baseURL: '/api/v1' });

export interface Integration {
  id: number;
  name: string;
  provider: string;
  status: string;
  enabled: boolean;
  configuration?: any;
  type?: string;
  last_sync_at?: string | null;
}

export interface IntegrationSync {
  id: number;
  integration_id: number;
  status: string;
  operation: string;
  started_at: string;
  completed_at: string | null;
  finished_at: string | null;
  records_processed: number;
  records_created: number;
  records_updated: number;
  records_failed: number;
  error_summary?: string | null;
}

export interface IntegrationCredential {
  id: number;
  integration_id: number;
  key: string;
  value: string;
  credential_type: string;
  label?: string | null;
  masked_hint?: string | null;
  is_encrypted: boolean;
  is_active: boolean;
}

export const integrationApi = {
  list: () => api.get<{ data: Integration[] }>('/integrations').then(r => r.data.data),
  get: (id: number) => api.get<{ data: Integration }>(`/integrations/${id}`).then(r => r.data.data),
  create: (data: any) => api.post('/integrations', data).then(r => r.data.data),
  update: (id: number, data: any) => api.put(`/integrations/${id}`, data).then(r => r.data.data),
  delete: (id: number) => api.delete(`/integrations/${id}`),
  testConnection: (id: number) => api.post(`/integrations/${id}/test`).then(r => r.data),
  healthCheck: (id: number) => api.get(`/integrations/${id}/health`).then(r => r.data),
  sync: (id: number) => api.post(`/integrations/${id}/sync`).then(r => r.data),
  getSyncs: (id: number) => api.get<{ data: IntegrationSync[] }>(`/integrations/${id}/syncs`).then(r => r.data.data),
  getCredentials: (id: number) => api.get<{ data: IntegrationCredential[] }>(`/integrations/${id}/credentials`).then(r => r.data.data),
  createCredential: (id: number, data: any) => api.post(`/integrations/${id}/credentials`, data).then(r => r.data.data),
  deleteCredential: (id: number, credentialId: number) => api.delete(`/integrations/${id}/credentials/${credentialId}`),
  // Canonical preview endpoint
  preview: (id: number, resourceType: 'device' | 'site') =>
    api.post<{ data: any }>(`/integrations/${id}/import/preview`, { resource_type: resourceType }).then(r => r.data),
};

// Legacy aliases for backward compatibility during transition
export const uispImportApi = {
  preview: (integrationId: number) => integrationApi.preview(integrationId, 'device'), // Default to device for legacy calls
  execute: (integrationId: number, selected: { sites?: any[]; devices?: any[] }) =>
    api.post(`/integrations/${integrationId}/uisp/import/execute`, selected).then(r => r.data),
  analyzeSingle: (integrationId: number, type: 'site' | 'device', data: any) =>
    api.post(`/integrations/${integrationId}/uisp/import/analyze`, { type, data }).then(r => r.data),
};

export const integrationImportApi = {
  preview: (integrationId: number) => integrationApi.preview(integrationId, 'device'), // Default to device for legacy calls
  execute: (integrationId: number, data: { devices?: any[]; sites?: any[] }) =>
    api.post<{ data: any }>(`/integrations/${integrationId}/import`, data).then(r => r.data),
};
