import React, { useState } from 'react';
import {
    Box, Typography, Card, CardContent, Button, Stack,
    Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
    Chip, CircularProgress, Alert, Paper, Grid,
    Dialog, DialogTitle, DialogContent, DialogActions,
    LinearProgress
} from '@mui/material';
import {
    Refresh, Download, CheckCircle, Warning,
    Error, Pending
} from '@mui/icons-material';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { useQuery, useMutation } from '@tanstack/react-query';
import axios from 'axios';
import toast from 'react-hot-toast';

interface PreviewItem {
    device_id: string;
    hostname: string;
    status: string;
    exists: boolean;
    existing_asset_id?: number;
    existing_asset_tag?: string;
    site_mapped: boolean;
    site_id?: number;
    site_message: string;
    action: string;
    device_data: any;
}

interface Integration {
    id: number;
    name: string;
    provider: string;
    type: string;
    enabled: boolean;
    status: string;
}

const LibreNMSImportPage: React.FC = () => {
    const [integrationId, setIntegrationId] = useState<number | null>(null);
    const [importResults, setImportResults] = useState<any | null>(null);
    const [isImporting, setIsImporting] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const { data: integrations, isLoading: integrationsLoading } = useQuery({
        queryKey: ['integrations'],
        queryFn: () => axios.get('/api/v1/integrations').then(res => res.data.data),
    });

    const librenmsIntegrations = integrations?.filter(
        (i: Integration) => i.provider === 'librenms'
    ) || [];

    const { data: preview, isLoading: previewLoading, refetch: refetchPreview } = useQuery({
        queryKey: ['librenms-preview', integrationId],
        queryFn: () => {
            if (!integrationId) return null;
            return axios.get(`/api/v1/integrations/librenms/${integrationId}/preview`)
                .then(res => res.data);
        },
        enabled: !!integrationId,
    });

    const importMutation = useMutation({
        mutationFn: () => {
            if (!integrationId) return Promise.reject('No integration selected');
            return axios.post(`/api/v1/integrations/librenms/${integrationId}/import`);
        },
        onSuccess: (response) => {
            setImportResults(response.data.results);
            toast.success('Import completed successfully');
            setConfirmOpen(false);
            setIsImporting(false);
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Import failed');
            setIsImporting(false);
        },
    });

    const handlePreview = () => {
        if (integrationId) {
            refetchPreview();
        }
    };

    const handleConfirmImport = () => {
        setConfirmOpen(false);
        setIsImporting(true);
        importMutation.mutate();
    };

    const getStatusChip = (status: string) => {
        const statusMap: Record<string, { label: string; color: any }> = {
            '1': { label: 'Up', color: 'success' },
            '0': { label: 'Down', color: 'error' },
            '2': { label: 'Warning', color: 'warning' },
        };
        const s = statusMap[status] || { label: status, color: 'default' };
        return <Chip label={s.label} size="small" color={s.color} />;
    };

    const getActionChip = (action: string) => {
        const actionMap: Record<string, { label: string; color: any; icon: any }> = {
            'create': { label: 'New Asset', color: 'success', icon: <CheckCircle fontSize="small" /> },
            'update': { label: 'Update Asset', color: 'info', icon: <Refresh fontSize="small" /> },
            'skip_unmapped': { label: 'No Site', color: 'warning', icon: <Warning fontSize="small" /> },
            'skip_duplicate': { label: 'Duplicate', color: 'error', icon: <Error fontSize="small" /> },
        };
        const a = actionMap[action] || { label: action, color: 'default', icon: <Pending fontSize="small" /> };
        return <Chip label={a.label} size="small" color={a.color} icon={a.icon} />;
    };

    return (
        <Box sx={{ p: 3 }}>
            <PageHeader
                title="LibreNMS Import"
                breadcrumbs={[
                    { label: 'System', path: '/system' },
                    { label: 'Integrations', path: '/system/integrations' },
                    { label: 'LibreNMS Import' }
                ]}
            />

            <Can permission="librenms.import">
                <Stack spacing={3}>
                    <Card>
                        <CardContent>
                            <Typography variant="h6" gutterBottom>
                                Select LibreNMS Integration
                            </Typography>
                            {integrationsLoading ? (
                                <CircularProgress size={24} />
                            ) : librenmsIntegrations.length === 0 ? (
                                <Alert severity="info">
                                    No LibreNMS integrations found. Please configure one first.
                                </Alert>
                            ) : (
                                <Stack direction="row" spacing={2} alignItems="center">
                                    <Box sx={{ minWidth: 200 }}>
                                        <select
                                            className="form-select"
                                            style={{
                                                width: '100%',
                                                padding: '8px 12px',
                                                borderRadius: '4px',
                                                border: '1px solid #ccc',
                                            }}
                                            value={integrationId || ''}
                                            onChange={(e) => setIntegrationId(Number(e.target.value))}
                                        >
                                            <option value="">Select Integration...</option>
                                            {librenmsIntegrations.map((integration: Integration) => (
                                                <option key={integration.id} value={integration.id}>
                                                    {integration.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Box>
                                    <Button
                                        variant="contained"
                                        startIcon={<Refresh />}
                                        onClick={handlePreview}
                                        disabled={!integrationId || previewLoading}
                                    >
                                        Fetch Devices
                                    </Button>
                                    {previewLoading && <CircularProgress size={24} />}
                                </Stack>
                            )}
                        </CardContent>
                    </Card>

                    {preview && (
                        <>
                            <Card>
                                <CardContent>
                                    <Typography variant="h6" gutterBottom>
                                        Preview Summary
                                    </Typography>
                                    <Grid container spacing={2}>
                                        <Grid item xs={6} sm={2}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'success.light' }}>
                                                <Typography variant="h6">{preview.summary.create}</Typography>
                                                <Typography variant="caption">New</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={2}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'info.light' }}>
                                                <Typography variant="h6">{preview.summary.update}</Typography>
                                                <Typography variant="caption">Update</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={2}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'warning.light' }}>
                                                <Typography variant="h6">{preview.summary.skip_unmapped}</Typography>
                                                <Typography variant="caption">Unmapped</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={2}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'error.light' }}>
                                                <Typography variant="h6">{preview.summary.skip_duplicate || 0}</Typography>
                                                <Typography variant="caption">Duplicate</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={2}>
                                            <Paper sx={{ p: 2, textAlign: 'center' }}>
                                                <Typography variant="h6">{preview.total}</Typography>
                                                <Typography variant="caption">Total</Typography>
                                            </Paper>
                                        </Grid>
                                    </Grid>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <Typography variant="h6" gutterBottom>
                                        Device Preview
                                    </Typography>
                                    <TableContainer component={Paper} variant="outlined">
                                        <Table size="small">
                                            <TableHead>
                                                <TableRow>
                                                    <TableCell>Device ID</TableCell>
                                                    <TableCell>Hostname</TableCell>
                                                    <TableCell>Status</TableCell>
                                                    <TableCell>Action</TableCell>
                                                    <TableCell>Site Status</TableCell>
                                                </TableRow>
                                            </TableHead>
                                            <TableBody>
                                                {preview.preview.map((item: PreviewItem) => (
                                                    <TableRow key={item.device_id}>
                                                        <TableCell>{item.device_id}</TableCell>
                                                        <TableCell>{item.hostname}</TableCell>
                                                        <TableCell>{getStatusChip(item.status)}</TableCell>
                                                        <TableCell>{getActionChip(item.action)}</TableCell>
                                                        <TableCell>
                                                            <Chip
                                                                label={item.site_mapped ? 'Mapped' : item.site_message}
                                                                size="small"
                                                                color={item.site_mapped ? 'success' : 'warning'}
                                                            />
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </TableContainer>

                                    {preview.summary.create > 0 && (
                                        <Box sx={{ mt: 2 }}>
                                            <Button
                                                variant="contained"
                                                color="primary"
                                                onClick={() => setConfirmOpen(true)}
                                                startIcon={<Download />}
                                            >
                                                Import {preview.summary.create} New Devices
                                            </Button>
                                        </Box>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    )}

                    {importResults && (
                        <Card>
                            <CardContent>
                                <Typography variant="h6" gutterBottom>
                                    Import Results
                                </Typography>
                                <Grid container spacing={2}>
                                    <Grid item xs={6} sm={2}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'success.light' }}>
                                            <Typography variant="h6">{importResults.created || 0}</Typography>
                                            <Typography variant="caption">Created</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={2}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'info.light' }}>
                                            <Typography variant="h6">{importResults.updated || 0}</Typography>
                                            <Typography variant="caption">Updated</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={2}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'warning.light' }}>
                                            <Typography variant="h6">{importResults.unmapped || 0}</Typography>
                                            <Typography variant="caption">Unmapped</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={2}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'error.light' }}>
                                            <Typography variant="h6">{importResults.failed || 0}</Typography>
                                            <Typography variant="caption">Failed</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={2}>
                                        <Paper sx={{ p: 2, textAlign: 'center' }}>
                                            <Typography variant="h6">{importResults.skipped || 0}</Typography>
                                            <Typography variant="caption">Skipped</Typography>
                                        </Paper>
                                    </Grid>
                                </Grid>
                            </CardContent>
                        </Card>
                    )}

                    {isImporting && (
                        <Box sx={{ width: '100%' }}>
                            <LinearProgress />
                            <Typography variant="body2" sx={{ mt: 1 }}>
                                Importing devices...
                            </Typography>
                        </Box>
                    )}
                </Stack>
            </Can>

            <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
                <DialogTitle>Confirm Import</DialogTitle>
                <DialogContent>
                    <Typography>
                        This will import {preview?.summary.create || 0} new devices as Assets.
                        Existing devices will be updated.
                        Unmapped devices will be skipped.
                    </Typography>
                    <Alert severity="warning" sx={{ mt: 2 }}>
                        This action cannot be undone. Please review the preview before confirming.
                    </Alert>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setConfirmOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        color="primary"
                        onClick={handleConfirmImport}
                        disabled={isImporting}
                    >
                        Confirm Import
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default LibreNMSImportPage;
