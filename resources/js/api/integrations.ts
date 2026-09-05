import axios from 'axios';

const api = axios.create({ baseURL: '/api/v1' });

export interface Integration {
  id: number;
  name: string;
  provider: string;
  type: string;
  description: string | null;
  enabled: boolean;
  status: string;
  configuration: Record<string, unknown> | null;
  last_health_check_at: string | null;
  last_sync_at: string | null;
  created_by: string | null;
  updated_by: string | null;
  created_at: string;
  updated_at: string;
}

export interface IntegrationCredential {
  id: number;
  integration_id: number;
  credential_type: string;
  label: string | null;
  masked_hint: string | null;
  metadata: Record<string, unknown> | null;
  is_active: boolean;
  last_used_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface IntegrationSync {
  id: number;
  integration_id: number;
  operation: string;
  status: string;
  started_at: string | null;
  finished_at: string | null;
  records_processed: number;
  records_created: number;
  records_updated: number;
  records_unchanged: number;
  records_failed: number;
  error_summary: string | null;
  initiated_by: string | null;
  created_at: string;
}

export const integrationApi = {
  list: (params?: Record<string, string>) =>
    api.get<{ data: Integration[] }>('/integrations', { params }).then(r => r.data),

  get: (id: number) =>
    api.get<{ data: Integration }>(`/integrations/${id}`).then(r => r.data),

  create: (data: Partial<Integration> & {
    credential_type?: string;
    credential_value?: string;
    credential_label?: string;
  }) =>
    api.post<{ data: Integration }>('/integrations', data).then(r => r.data),

  update: (id: number, data: Partial<Integration> & {
    credential_type?: string;
    credential_value?: string;
    credential_label?: string;
  }) =>
    api.put<{ data: Integration }>(`/integrations/${id}`, data).then(r => r.data),

  delete: (id: number) =>
    api.delete(`/integrations/${id}`),

  testConnection: (id: number) =>
    api.post<{ success: boolean; response_time_ms?: number; error?: string }>(`/integrations/${id}/test`).then(r => r.data),

  healthCheck: (id: number) =>
    api.post<{ status: string; error?: string }>(`/integrations/${id}/health-check`).then(r => r.data),

  sync: (id: number, operation: string = 'full') =>
    api.post<{ data: IntegrationSync }>(`/integrations/${id}/sync`, { operation }).then(r => r.data),

  getSyncs: (id: number, params?: Record<string, string>) =>
    api.get<{ data: IntegrationSync[] }>(`/integrations/${id}/syncs`, { params }).then(r => r.data),

  getCredentials: (id: number) =>
    api.get<{ data: IntegrationCredential[] }>(`/integrations/${id}/credentials`).then(r => r.data),

  createCredential: (id: number, data: { credential_type: string; label?: string; value?: string; metadata?: Record<string, unknown> }) =>
    api.post<{ data: IntegrationCredential }>(`/integrations/${id}/credentials`, data).then(r => r.data),

  deleteCredential: (integrationId: number, credentialId: number) =>
    api.delete(`/integrations/${integrationId}/credentials/${credentialId}`),
};

export const uispImportApi = {
  preview: () =>
    api.post('/integrations/uisp/import/preview').then(r => r.data),

  execute: (selected: { sites?: any[]; devices?: any[] }) =>
    api.post('/integrations/uisp/import/execute', selected).then(r => r.data),

  analyzeSingle: (type: 'site' | 'device', data: any) =>
    api.post('/integrations/uisp/import/analyze', { type, data }).then(r => r.data),
};

export const integrationImportApi = {
  preview: (integrationId: number) =>
    api.post<{ data: any }>(`/integrations/${integrationId}/import/preview`).then(r => r.data),

  execute: (integrationId: number, data: { devices?: any[]; sites?: any[] }) =>
    api.post<{ data: any }>(`/integrations/${integrationId}/import`, data).then(r => r.data),
};
