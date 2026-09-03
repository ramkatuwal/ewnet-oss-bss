import axios from 'axios';

const api = axios.create({ baseURL: '/api/v1' });

export interface ImportProvider {
  id: number;
  name: string;
  identity: string;
  capabilities: string[];
}

export interface NormalizedRecord {
  provider: string;
  source_type: string;
  external_id: string;
  name: string | null;
  description: string | null;
  mac_address: string | null;
  ip_address: string | null;
  serial_number: string | null;
  manufacturer: string | null;
  model: string | null;
  firmware_version: string | null;
  status: string | null;
  source_site_id: string | null;
  source_site_name: string | null;
  metadata: Record<string, unknown>;
}

export interface ImportAnalysis {
  decision: 'CREATE' | 'LINK' | 'REVIEW' | 'CONFLICT' | 'ERROR';
  destination_id: number | null;
  evidence: Array<{ field: string; strength: string; value: string }>;
  candidate_ids?: number[];
  reason?: string;
}

export interface ImportItem {
  record: NormalizedRecord;
  analysis: ImportAnalysis;
}

export interface ImportPreviewResponse {
  source: string;
  fetched_at: string;
  total: number;
  records: ImportItem[];
}

export const importApi = {
  getProviders: () =>
    api.get<{ data: ImportProvider[] }>('/import/providers').then(r => r.data.data),

  preview: (integrationId: number, sourceType: 'devices' | 'sites') =>
    api.post<{ data: ImportPreviewResponse }>('/import/preview', {
      integration_id: integrationId,
      source_type: sourceType,
    }).then(r => r.data.data),

  execute: (integrationId: number, items: ImportItem[]) =>
    api.post<{ data: any }>('/import/execute', {
      integration_id: integrationId,
      items: items.map(item => ({
        record: item.record,
        analysis: item.analysis,
      })),
    }).then(r => r.data.data),
};
