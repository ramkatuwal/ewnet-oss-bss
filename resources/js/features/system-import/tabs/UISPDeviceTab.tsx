import React, { useState, useMemo, useEffect } from 'react';
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
import { uispImportApi } from '@/api/integrations';
import { importApi, ImportProvider } from '@/api/import';




interface Integration {
  id: number;
  name: string;
  provider: string;
  status: string;
  enabled: boolean;
}

const UISPDeviceTab: React.FC = () => {
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

  const uispProvider = useMemo(() => {
    if (!providers) return null;
    return providers.find((p: ImportProvider) => p.provider === 'uisp') || null;
  }, [providers]);

  const integrationForCard = useMemo((): Integration | null => {
    if (!uispProvider) return null;
    return { id: uispProvider.id, name: uispProvider.name, provider: uispProvider.provider, status: 'connected', enabled: true };
  }, [uispProvider]);

  useEffect(() => {
    if (uispProvider && !selectedIntegration) setSelectedIntegration(uispProvider.id);
  }, [uispProvider, selectedIntegration]);

  const { data: previewData, isLoading: isPreviewing, refetch: runPreview } = useQuery({
    queryKey: ['uisp-device-preview', selectedIntegration],
    queryFn: async () => {
      if (!selectedIntegration) throw new Error('No integration selected');
      const response = await uispImportApi.preview();
      return response.data;
    },
    enabled: !!selectedIntegration,
    retry: false,
  });

  const importMutation = useMutation({
    mutationFn: async (items: any[]) => {
      if (!selectedIntegration) throw new Error('No integration selected');
      const response = await uispImportApi.execute({
        devices: items.map(i => i.record),
        sites: [],
        
      });
      return response.data;
    },
    onSuccess: (data) => {
      setImportResult(data);
      setShowResultDialog(true);
      queryClient.invalidateQueries({ queryKey: ['uisp-device-preview'] });
      queryClient.invalidateQueries({ queryKey: ['import-history'] });
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.error || 'Import failed');
    },
  });

  const importItems = useMemo(() => {
    if (!previewData) return [];
    const devices = previewData.devices?.analysis || [];
    return devices.map((item: any) => ({
      record: {
        id: item.external_id || item.id || '',
        name: item.name || 'Unknown Device',
        external_id: item.external_id || '',
        hostname: item.name || '',
        ip_address: item.ip || '',
        mac_address: item.mac || '',
        serial_number: item.serial || '',
        vendor: item.vendor || '',
        model: item.model || '',
        site_name: item.site || '',
        status: item.status || 'unknown',
      },
      analysis: {
        decision: item.action === 'link' ? 'LINK' : item.action === 'create' ? 'CREATE' : item.action === 'conflict' ? 'CONFLICT' : 'REVIEW',
        destination_id: item.asset_id || null,
        evidence: [],
      },
    }));
  }, [previewData]);

  const columns: Column[] = [
    { id: 'name', label: 'Name', sortable: true },
    { id: 'hostname', label: 'Hostname', sortable: true },
    { id: 'ip_address', label: 'IP Address', sortable: true },
    { id: 'mac_address', label: 'MAC', sortable: true },
    { id: 'vendor', label: 'Vendor', sortable: true },
    { id: 'model', label: 'Model', sortable: true },
    { id: 'site_name', label: 'Site', sortable: true },
  ];

  return (
    <Box sx={{ p: 2 }}>
      <ImportSourceCard
        title="UISP Device Import"
        source="uisp"
        integration={integrationForCard}
        integrations={[]}
        onIntegrationSelect={setSelectedIntegration}
        onRefresh={() => runPreview()}
        isLoading={isPreviewing}
        connectionStatus={uispProvider ? 'connected' : 'disconnected'}
        recordsCount={importItems.length}
        recordsLabel="devices"
      />
      
      <Box sx={{ mt: 3 }}>
        <ImportDataTable
          columns={columns}
          rows={importItems.map((item: any) => item.record)}
          loading={isPreviewing}
          selectedIds={selectedItems}
          onRowSelect={(ids) => setSelectedItems(ids)}
          searchable
          searchFields={['name', 'hostname', 'ip_address', 'mac_address']}
        />
      </Box>

      <Box sx={{ mt: 2, display: 'flex', gap: 2 }}>
        <Button 
          variant="contained" 
          onClick={() => setShowConfirmDialog(true)} 
          disabled={selectedItems.size === 0 || importMutation.isPending}
        >
          {importMutation.isPending ? <CircularProgress size={24} /> : `Import Selected (${selectedItems.size})`}
        </Button>
        <Button variant="outlined" onClick={() => setShowHistory(!showHistory)}>
          {showHistory ? 'Hide History' : 'Show History'}
        </Button>
      </Box>

      {showHistory && (
        <Box sx={{ mt: 3 }}>
          <ImportHistoryPanel source="uisp" type="device" />
        </Box>
      )}

      <ImportConfirmationDialog
        open={showConfirmDialog}
        onClose={() => setShowConfirmDialog(false)}
        onConfirm={() => {
          const itemsToImport = importItems.filter((item: any) => selectedItems.has(item.record.id));
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

export default UISPDeviceTab;
