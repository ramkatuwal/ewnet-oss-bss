import { useEffect, useState } from 'react';
import { Alert, Box, Button, Dialog, DialogActions, DialogContent, DialogTitle, Typography } from '@mui/material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api/client';
import { normalizeApiError } from '@/api/errors';
import ImportDataTable, { Column } from '@/components/import/ImportDataTable';
import toast from 'react-hot-toast';

interface SiteLibreNMSImportDialogProps {
    open: boolean;
    onClose: () => void;
    siteId: number;
    siteName: string;
    integrationId: number;
    onSuccess: () => void;
}

interface DeviceItem {
    external_id: string;
    name: string | null;
    ip: string | null;
    os: string | null;
    model: string | null;
    serial: string | null;
    status: 'UP' | 'DOWN' | 'UNKNOWN';
    type: string | null;
    site_id: number | null;
    site_name: string | null;
    action: 'create' | 'update' | 'skip_unmapped';
}

interface ImportResult {
    created: number;
    updated: number;
    skipped: number;
    failed: number;
}

const columns: Column[] = [
    { id: 'name', label: 'Display Name', sortable: true },
    { id: 'ip', label: 'Management IP', sortable: true },
    { id: 'os', label: 'OS/Platform', sortable: true },
    { id: 'model', label: 'Hardware / Model', sortable: true },
    { id: 'serial', label: 'Serial', sortable: true },
    { id: 'status', label: 'Provider Status', sortable: true },
    { id: 'type', label: 'Provider Type', sortable: true },
    { id: 'site_name', label: 'Mapped Site', sortable: true },
];

export const SiteLibreNMSImportDialog = ({ open, onClose, siteId, siteName, integrationId, onSuccess }: SiteLibreNMSImportDialogProps) => {
    const queryClient = useQueryClient();
    const [selectedDevices, setSelectedDevices] = useState<Set<string>>(new Set());
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [result, setResult] = useState<ImportResult | null>(null);

    useEffect(() => {
        setSelectedDevices(new Set());
        setConfirmOpen(false);
        setResult(null);
    }, [open, integrationId, siteId]);

    const { data: preview, isLoading, isError, refetch } = useQuery({
        queryKey: ['librenms-preview', integrationId, siteId],
        queryFn: () => apiClient.get<{ analysis: DeviceItem[] }>(`/api/v1/integrations/librenms/${integrationId}/preview`).then(res => res.data),
        enabled: open && !!integrationId,
    });

    // Site placement is resolved again by the backend, never supplied by this dialog.
    const importableDevices = (preview?.analysis ?? []).filter(item =>
        item.site_id === siteId && (item.action === 'create' || item.action === 'update'));
    const selected = importableDevices.filter(item => selectedDevices.has(item.external_id));

    const mutation = useMutation({
        mutationFn: () => apiClient.post<{ data: ImportResult }>(`/api/v1/integrations/librenms/${integrationId}/import`, {
            devices: selected.map(item => ({ external_id: item.external_id })),
        }).then(res => res.data.data),
        onSuccess: (data) => {
            setResult(data);
            setSelectedDevices(new Set());
            setConfirmOpen(false);
            queryClient.invalidateQueries({ queryKey: ['librenms-preview'] });
            queryClient.invalidateQueries({ queryKey: ['nms-device-preview'] });
            queryClient.invalidateQueries({ queryKey: ['import-history'] });
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'asset'] });
            onSuccess();
            if (data.failed === 0 && data.skipped === 0) {
                toast.success(`Imported ${data.created + data.updated} devices successfully`);
                onClose();
            }
        },
        onError: error => toast.error(normalizeApiError(error).message),
    });

    const cannotImport = isLoading || isError || mutation.isPending || selected.length === 0 || selected.length > 1000;

    return <>
        <Dialog open={open} onClose={mutation.isPending ? undefined : onClose} maxWidth="lg" fullWidth>
            <DialogTitle>Import from LibreNMS - {siteName}</DialogTitle>
            <DialogContent>
                {result && <Alert severity={result.failed > 0 || result.skipped > 0 ? 'warning' : 'success'} sx={{ mb: 1 }}>
                    Created: {result.created}; Updated: {result.updated}; Skipped: {result.skipped}; Failed: {result.failed}.
                </Alert>}
                {isError ? <Alert severity="error" action={<Button onClick={() => void refetch()}>Retry</Button>}>
                    Unable to load LibreNMS device preview.
                </Alert> : <>
                    <Typography variant="body2" sx={{ mb: 1 }}>Only devices mapped to {siteName} are available here.</Typography>
                    {!isLoading && importableDevices.length === 0 && <Alert severity="info">No devices mapped to this site are available for import.</Alert>}
                    <Box sx={{ pointerEvents: mutation.isPending ? 'none' : undefined }}>
                        <ImportDataTable columns={columns} rows={importableDevices} loading={isLoading}
                            getRowId={(item: DeviceItem) => item.external_id} selectedIds={selectedDevices} onRowSelect={setSelectedDevices}
                            searchFields={['name', 'ip', 'serial']} />
                    </Box>
                </>}
                {selected.length > 1000 && <Alert severity="warning">Select at most 1000 devices per import.</Alert>}
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose} disabled={mutation.isPending}>Cancel</Button>
                <Button variant="contained" disabled={cannotImport} onClick={() => setConfirmOpen(true)}>Import Selected ({selected.length})</Button>
            </DialogActions>
        </Dialog>
        <Dialog open={confirmOpen} onClose={mutation.isPending ? undefined : () => setConfirmOpen(false)}>
            <DialogTitle>Confirm Import</DialogTitle>
            <DialogContent>
                <Typography>Import {selected.length} device(s) currently mapped to {siteName}? Existing assets will be synchronized.</Typography>
                <Alert severity="info" sx={{ mt: 1 }}>The server rechecks provider data and permitted site mappings before import.</Alert>
            </DialogContent>
            <DialogActions>
                <Button disabled={mutation.isPending} onClick={() => setConfirmOpen(false)}>Cancel</Button>
                <Button variant="contained" disabled={cannotImport} onClick={() => mutation.mutate()}>{mutation.isPending ? 'Importing...' : 'Confirm Import'}</Button>
            </DialogActions>
        </Dialog>
    </>;
};
