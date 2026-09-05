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

const UISPSiteTab: React.FC = () => {
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
    queryKey: ['uisp-site-preview', selectedIntegration],
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
        sites: items.map(i => i.record),
        devices: [],
      });
      return response.data;
    },
    onSuccess: (data) => {
      setImportResult(data);
      setShowResultDialog(true);
      queryClient.invalidateQueries({ queryKey: ['uisp-site-preview'] });
      queryClient.invalidateQueries({ queryKey: ['import-history'] });
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.error || 'Site import failed');
    },
  });

  const sites = useMemo(() => {
    if (!previewData) return [];
    return (previewData.sites?.analysis || []).map((item: any) => ({
      record: {
        id: item.external_id || '',
        name: item.record?.identification?.name || 'Unknown Site',
        external_id: item.external_id || '',
        address: item.record?.identification?.address || '',
        status: item.record?.identification?.status || 'unknown',
      },
      analysis: {
        decision: item.action === 'link' ? 'LINK' : item.action === 'create' ? 'CREATE' : 'REVIEW',
        destination_id: item.site_id || null,
        evidence: [],
      },
    }));
  }, [previewData]);

  const columns: Column[] = [
    { id: 'name', label: 'Site Name', sortable: true },
    { id: 'address', label: 'Address', sortable: true },
    { id: 'status', label: 'Status', sortable: true },
  ];

  return (
    <Box sx={{ p: 2 }}>
      <ImportSourceCard
        title="UISP Site Import"
        source="uisp"
        integration={integrationForCard}
        integrations={[]}
        onIntegrationSelect={setSelectedIntegration}
        onRefresh={() => runPreview()}
        isLoading={isPreviewing}
        connectionStatus={uispProvider ? 'connected' : 'disconnected'}
        recordsCount={sites.length}
        recordsLabel="sites"
      />

      <Box sx={{ mt: 3 }}>
        <ImportDataTable
          columns={columns}
          rows={sites.map((s: any) => s.record)}
          loading={isPreviewing}
          selectedIds={selectedItems}
          onRowSelect={setSelectedItems}
          searchable
          searchFields={['name', 'address']}
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

      {showHistory && <Box sx={{ mt: 3 }}><ImportHistoryPanel source="uisp" type="site" /></Box>}

      <ImportConfirmationDialog
        open={showConfirmDialog}
        onClose={() => setShowConfirmDialog(false)}
        onConfirm={() => {
          const itemsToImport = sites.filter((s: any) => selectedItems.has(s.record.id));
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

export default UISPSiteTab;
