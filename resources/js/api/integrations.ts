import axios from 'axios';

const api = axios.create({ baseURL: '/api/v1' });

export interface Integration {
  id: number;
  company_id?: number | null;
  company_scope?: 'global' | 'company';
  company?: { id: number; name: string } | null;
  name: string;
  provider: string;
  provider_type?: string;
  type: string;
  description: string | null;
  enabled: boolean;
  status: string;
  health_status?: string;
  configuration?: any;
  active_objects_count?: number;
  can?: Record<string, boolean>;
  last_health_check_at: string | null;
  last_sync_at: string | null;
  created_by?: string | null;
  updated_by?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface IntegrationSync {
  id: number;
  integration_id: number;
  status: string;
  operation: string;
  started_at: string;
  completed_at: string | null;
  finished_at: string | null;
  duration_seconds?: number | null;
  records_processed: number;
  records_created: number;
  records_updated: number;
  records_skipped?: number;
  records_failed: number;
  error_summary?: string | null;
}

export interface IntegrationStats {
  total_objects: number;
  syncs_total: number;
  syncs_completed: number;
  syncs_failed: number;
  syncs_running: number;
  success_rate: number | null;
  records_created_total: number;
  records_updated_total: number;
  records_skipped_total: number;
  records_failed_total: number;
  last_sync_at: string | null;
  last_sync_operation: string | null;
  last_sync_status: string | null;
  last_sync_duration_seconds: number | null;
  last_error_summary: string | null;
}

export interface AuditLogEntry {
  id: number;
  action: string;
  result: string;
  actor_id: number | null;
  actor_type: string | null;
  actor_name?: string | null;
  ip_address?: string | null;
  metadata?: any;
  created_at: string;
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
  last_used_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export const integrationApi = {
  list: () => api.get<{ data: Integration[] }>('/integrations').then(r => r.data.data),
  get: (id: number) => api.get<{ data: Integration }>(`/integrations/${id}`).then(r => r.data.data),
  create: (data: any) => api.post('/integrations', data).then(r => r.data.data),
  update: (id: number, data: any) => api.put(`/integrations/${id}`, data).then(r => r.data.data),
  delete: (id: number) => api.delete(`/integrations/${id}`),
  testConnection: (id: number) => api.post(`/integrations/${id}/test`).then(r => r.data),
  healthCheck: (id: number) => api.post(`/integrations/${id}/health-check`).then(r => r.data),
  sync: (id: number) => api.post(`/integrations/${id}/sync`).then(r => r.data),
  getSyncs: (id: number, params?: Record<string, unknown>) =>
    api.get(`/integrations/${id}/syncs`, { params }).then(r => r.data),
  stats: (id: number) => api.get<{ data: IntegrationStats }>(`/integrations/${id}/stats`).then(r => r.data.data),
  getCredentials: (id: number) => api.get<{ data: IntegrationCredential[] }>(`/integrations/${id}/credentials`).then(r => r.data.data),
  createCredential: (id: number, data: any) => api.post(`/integrations/${id}/credentials`, data).then(r => r.data.data),
  rotateCredential: (id: number, credentialId: number, value: string) =>
    api.post(`/integrations/${id}/credentials/${credentialId}/rotate`, { value }).then(r => r.data),
  deleteCredential: (id: number, credentialId: number) => api.delete(`/integrations/${id}/credentials/${credentialId}`),
  getAuditLogs: (id: number, params?: Record<string, unknown>) =>
    api.get(`/integrations/${id}/audit-logs`, { params }).then(r => r.data),
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
