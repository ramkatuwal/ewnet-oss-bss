import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Button, CircularProgress } from '@mui/material';
import toast from 'react-hot-toast';

// Shared components
import ImportSourceCard from '@/components/import/ImportSourceCard';
import ImportDataTable, { Column } from '@/components/import/ImportDataTable';
import ImportConfirmationDialog from '@/components/import/ImportConfirmationDialog';
import ImportResultDialog from '@/components/import/ImportResultDialog';
import ImportHistoryPanel from '@/components/import/ImportHistoryPanel';

// API
import { integrationImportApi } from '@/api/integrations';
import { importApi, ImportProvider } from '@/api/import';

interface Integration {
  id: number;
  name: string;
  provider: string;
  status: string;
  enabled: boolean;
}

const NMSDeviceTab: React.FC = () => {
  const queryClient = useQueryClient();
  const [selectedIntegration, setSelectedIntegration] = useState<number | null>(null);
  const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set());
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);
  const [showResultDialog, setShowResultDialog] = useState(false);
  const [importResult, setImportResult] = useState<any>(null);
  const [showHistory, setShowHistory] = useState(false);

  const { data: providers } = useQuery({
    queryKey: ['import-providers'],
    queryFn: importApi.getProviders,
  });

  const nmsProviders = useMemo(() => {
    if (!providers) return [];
    return providers.filter((p: ImportProvider) => p.identity === 'librenms');
  }, [providers]);

  const integrationForCard = useMemo((): Integration | null => {
    if (!selectedIntegration || !providers) return null;
    const p = providers.find((prov: ImportProvider) => prov.id === selectedIntegration);
    return p ? { id: p.id, name: p.name, provider: p.identity, status: 'connected', enabled: true } : null;
  }, [selectedIntegration, providers]);

  const { data: previewData, isLoading: isPreviewing, refetch: runPreview } = useQuery({
    queryKey: ['nms-device-preview', selectedIntegration],
    queryFn: async () => {
      if (!selectedIntegration) throw new Error('No integration selected');
      const response = await integrationImportApi.preview(selectedIntegration);
      return response.data;
    },
    enabled: !!selectedIntegration,
    retry: false,
  });

  const importMutation = useMutation({
    mutationFn: async (items: any[]) => {
      if (!selectedIntegration) throw new Error('No integration selected');
      const response = await integrationImportApi.execute(selectedIntegration, {
        devices: items.map(i => i.record),
      });
      return response.data;
    },
    onSuccess: (data) => {
      setImportResult(data);
      setShowResultDialog(true);
      queryClient.invalidateQueries({ queryKey: ['nms-device-preview'] });
      queryClient.invalidateQueries({ queryKey: ['import-history'] });
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.error || 'NMS Import failed');
    },
  });

  const devices = useMemo(() => {
    if (!previewData) return [];
    return (previewData.analysis || []).map((item: any) => ({
      record: {
        id: item.external_id,
        name: item.name,
        external_id: item.external_id,
        hostname: item.hostname,
        ip: item.ip,
        vendor: item.vendor,
        model: item.model,
        site_name: item.site_name,
        action: item.action,
      },
      analysis: { 
        decision: item.action === 'create' ? 'CREATE' : item.action === 'update' ? 'UPDATE' : 'REVIEW', 
        destination_id: item.asset_id, 
        evidence: item.evidence || [] 
      },
    }));
  }, [previewData]);

  const columns: Column[] = [
    { id: 'name', label: 'SysName', sortable: true },
    { id: 'hostname', label: 'Hostname', sortable: true },
    { id: 'ip', label: 'IP Address', sortable: true },
    { id: 'vendor', label: 'OS/Vendor', sortable: true },
    { id: 'model', label: 'Hardware', sortable: true },
    { id: 'site_name', label: 'Mapped Site', sortable: true },
  ];

  return (
    <Box sx={{ p: 2 }}>
      <ImportSourceCard
        title="NMS Device Import"
        source="librenms"
        integration={integrationForCard}
        integrations={nmsProviders.map((p: ImportProvider) => ({ id: p.id, name: p.name, provider: p.identity, status: 'connected', enabled: true }))}
        onIntegrationSelect={setSelectedIntegration}
        onRefresh={() => runPreview()}
        isLoading={isPreviewing}
        connectionStatus={selectedIntegration ? 'connected' : 'disconnected'}
        recordsCount={devices.length}
        recordsLabel="devices"
      />

      <Box sx={{ mt: 3 }}>
        <ImportDataTable
          columns={columns}
          rows={devices.map((d: any) => d.record)}
          loading={isPreviewing}
          selectedIds={selectedItems}
          onRowSelect={setSelectedItems}
          searchable
          searchFields={['name', 'hostname', 'ip']}
        />
      </Box>

      <Box sx={{ mt: 2, display: 'flex', gap: 2 }}>
        <Button variant="contained" onClick={() => setShowConfirmDialog(true)} disabled={selectedItems.size === 0 || importMutation.isPending}>
          {importMutation.isPending ? <CircularProgress size={24} /> : `Import Selected (${selectedItems.size})`}
        </Button>
        <Button variant="outlined" onClick={() => setShowHistory(!showHistory)}>
          {showHistory ? 'Hide History' : 'Show History'}
        </Button>
      </Box>

      {showHistory && <Box sx={{ mt: 3 }}><ImportHistoryPanel source="librenms" type="device" /></Box>}

      <ImportConfirmationDialog
        open={showConfirmDialog}
        onClose={() => setShowConfirmDialog(false)}
        onConfirm={() => {
          const itemsToImport = devices.filter((d: any) => selectedItems.has(d.record.id));
          importMutation.mutate(itemsToImport);
          setShowConfirmDialog(false);
        }}
        itemCount={selectedItems.size}
      />

      <ImportResultDialog
        open={showResultDialog}
        onClose={() => setShowResultDialog(false)}
        result={importResult || { created: 0, updated: 0, skipped: 0 }}
      />
    </Box>
  );
};

export default NMSDeviceTab;
