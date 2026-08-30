import React, { useState, useRef } from 'react';
import {
    Box, Typography, Card, CardContent, Button, Stack,
    Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
    Chip, CircularProgress, Alert, Paper, Grid,
    Dialog, DialogTitle, DialogContent, DialogActions,
    LinearProgress, FormControl, InputLabel, Select, MenuItem
} from '@mui/material';
import { Refresh, CheckCircle, Warning, Sync } from '@mui/icons-material';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import toast from 'react-hot-toast';

interface Integration {
    id: number;
    name: string;
    provider: string;
    type: string;
    enabled: boolean;
    status: string;
}

interface LocationPreview {
    location_name: string;
    device_count: number;
    devices: any[];
    mapped: boolean;
    site_id?: number;
    site_name?: string;
    site_code?: string;
    action: string;
}

const LibreNMSSiteImportPage: React.FC = () => {
    const queryClient = useQueryClient();
    const [integrationId, setIntegrationId] = useState<number | null>(null);
    const [importResults, setImportResults] = useState<any | null>(null);
    const [isImporting, setIsImporting] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [selectedLocations, setSelectedLocations] = useState<Set<string>>(new Set());
    const selectedLocationsRef = useRef<Set<string>>(new Set());

    const { data: integrations, isLoading: integrationsLoading } = useQuery({
        queryKey: ['integrations'],
        queryFn: () => axios.get('/api/v1/integrations').then(res => res.data.data),
    });

    const librenmsIntegrations = (integrations || []).filter(
        (i: Integration) => i.provider === 'librenms'
    );

    const { data: previewData, isLoading: previewLoading, refetch: refetchPreview } = useQuery({
        queryKey: ['librenms-sites-preview', integrationId],
        queryFn: () => {
            if (!integrationId) return null;
            return axios.get(`/api/v1/integrations/librenms/${integrationId}/sites/preview`)
                .then(res => res.data);
        },
        enabled: !!integrationId,
    });

    // Safely extract preview array
    const preview = previewData?.preview || [];

    const importMutation = useMutation({
        mutationFn: () => {
            if (!integrationId) return Promise.reject('No integration selected');
            const locations = Array.from(selectedLocationsRef.current);
            console.log('Sending import request with locations:', locations);
            return axios.post(`/api/v1/integrations/librenms/${integrationId}/sites/import`, {
                locations: locations.length > 0 ? locations : undefined,
            });
        },
        onSuccess: (response) => {
            console.log('Import response:', response.data);
            setImportResults(response.data.results);
            const results = response.data.results;
            let message = `Import completed: ${results.created || 0} created, ${results.updated || 0} updated`;
            if (results.skipped > 0) {
                message += `, ${results.skipped} skipped`;
            }
            if (results.failed > 0) {
                message += `, ${results.failed} failed`;
            }
            toast.success(message, { duration: 5000 });
            setConfirmOpen(false);
            setIsImporting(false);
            setSelectedLocations(new Set());
            selectedLocationsRef.current = new Set();
            queryClient.invalidateQueries({ queryKey: ['librenms-sites-preview'] });
            queryClient.invalidateQueries({ queryKey: ['sites'] });
        },
        onError: (err: any) => {
            console.error('Import error:', err);
            toast.error(err.response?.data?.error || 'Import failed');
            setIsImporting(false);
        },
    });

    const handlePreview = () => {
        if (integrationId) {
            refetchPreview();
        }
    };

    const handleConfirmImport = () => {
        const locations = Array.from(selectedLocationsRef.current);
        console.log('Confirming import with selected locations:', locations);
        if (locations.length === 0) {
            toast.error('Please select at least one location to import');
            setConfirmOpen(false);
            return;
        }
        setConfirmOpen(false);
        setIsImporting(true);
        importMutation.mutate();
    };

    const handleToggleSelect = (locationName: string) => {
        const newSet = new Set(selectedLocations);
        if (newSet.has(locationName)) {
            newSet.delete(locationName);
        } else {
            newSet.add(locationName);
        }
        setSelectedLocations(newSet);
        selectedLocationsRef.current = newSet;
        console.log('Selected locations:', Array.from(newSet));
    };

    const handleSelectAll = () => {
        if (!preview || preview.length === 0) {
             toast.success('No locations available to select');
            return;
        }
        let newSet: Set<string>;
        if (selectedLocations.size === preview.length) {
            newSet = new Set();
        } else {
            newSet = new Set(preview.map((p: LocationPreview) => p.location_name));
        }
        setSelectedLocations(newSet);
        selectedLocationsRef.current = newSet;
        console.log('Selected all locations:', Array.from(newSet));
    };

    const getStatusChip = (mapped: boolean) => {
        return mapped 
            ? <Chip label="Mapped" size="small" color="success" icon={<CheckCircle />} />
            : <Chip label="Unmapped" size="small" color="warning" icon={<Warning />} />;
    };

    const selectedCount = selectedLocations.size;

    return (
        <Box sx={{ p: 3 }}>
            <PageHeader
                title="LibreNMS Site Import"
                breadcrumbs={[
                    { label: 'System', path: '/system' },
                    { label: 'Integrations', path: '/system/integrations' },
                    { label: 'LibreNMS Site Import' }
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
                                        <FormControl fullWidth size="small">
                                            <InputLabel>Integration</InputLabel>
                                            <Select
                                                value={integrationId || ''}
                                                label="Integration"
                                                onChange={(e) => setIntegrationId(Number(e.target.value))}
                                            >
                                                <MenuItem value="">Select Integration...</MenuItem>
                                                {librenmsIntegrations.map((integration: Integration) => (
                                                    <MenuItem key={integration.id} value={integration.id}>
                                                        {integration.name}
                                                    </MenuItem>
                                                ))}
                                            </Select>
                                        </FormControl>
                                    </Box>
                                    <Button
                                        variant="contained"
                                        startIcon={<Refresh />}
                                        onClick={handlePreview}
                                        disabled={!integrationId || previewLoading}
                                    >
                                        Fetch Sites
                                    </Button>
                                    {previewLoading && <CircularProgress size={24} />}
                                </Stack>
                            )}
                        </CardContent>
                    </Card>

                    {previewData && (
                        <>
                            <Card>
                                <CardContent>
                                    <Typography variant="h6" gutterBottom>
                                        Site Preview Summary
                                    </Typography>
                                    <Grid container spacing={2}>
                                        <Grid item xs={6} sm={3}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'info.light' }}>
                                                <Typography variant="h6">{previewData.total || preview.length}</Typography>
                                                <Typography variant="caption">Total Locations</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={3}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'success.light' }}>
                                                <Typography variant="h6">{previewData.summary?.mapped || 0}</Typography>
                                                <Typography variant="caption">Mapped</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={3}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'warning.light' }}>
                                                <Typography variant="h6">{previewData.summary?.unmapped || 0}</Typography>
                                                <Typography variant="caption">Unmapped</Typography>
                                            </Paper>
                                        </Grid>
                                        <Grid item xs={6} sm={3}>
                                            <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'primary.light' }}>
                                                <Typography variant="h6">{previewData.total || preview.length}</Typography>
                                                <Typography variant="caption">Total</Typography>
                                            </Paper>
                                        </Grid>
                                    </Grid>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent>
                                    <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
                                        <Typography variant="h6">Locations</Typography>
                                        <Stack direction="row" spacing={1}>
                                            <Button
                                                size="small"
                                                onClick={handleSelectAll}
                                            >
                                                {selectedCount === preview.length ? 'Deselect All' : 'Select All'}
                                            </Button>
                                            {selectedCount > 0 && (
                                                <Button
                                                    variant="contained"
                                                    size="small"
                                                    startIcon={<Sync />}
                                                    onClick={() => setConfirmOpen(true)}
                                                >
                                                    Import {selectedCount} Sites
                                                </Button>
                                            )}
                                        </Stack>
                                    </Stack>
                                    <TableContainer component={Paper} variant="outlined">
                                        <Table size="small">
                                            <TableHead>
                                                <TableRow>
                                                    <TableCell padding="checkbox">
                                                        <input type="checkbox" />
                                                    </TableCell>
                                                    <TableCell>Location Name</TableCell>
                                                    <TableCell>Devices</TableCell>
                                                    <TableCell>Status</TableCell>
                                                    <TableCell>Mapped Site</TableCell>
                                                    <TableCell>Action</TableCell>
                                                </TableRow>
                                            </TableHead>
                                            <TableBody>
                                                {preview.map((item: LocationPreview) => (
                                                    <TableRow key={item.location_name}>
                                                        <TableCell padding="checkbox">
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedLocations.has(item.location_name)}
                                                                onChange={() => handleToggleSelect(item.location_name)}
                                                                disabled={item.mapped}
                                                            />
                                                        </TableCell>
                                                        <TableCell>{item.location_name}</TableCell>
                                                        <TableCell>{item.device_count}</TableCell>
                                                        <TableCell>{getStatusChip(item.mapped)}</TableCell>
                                                        <TableCell>
                                                            {item.site_name || item.site_code || '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Chip
                                                                label={item.mapped ? 'Update' : 'Create'}
                                                                size="small"
                                                                color={item.mapped ? 'info' : 'primary'}
                                                            />
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </TableContainer>
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
                                    <Grid item xs={6} sm={3}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'success.light' }}>
                                            <Typography variant="h6">{importResults.created || 0}</Typography>
                                            <Typography variant="caption">Created</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={3}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'info.light' }}>
                                            <Typography variant="h6">{importResults.updated || 0}</Typography>
                                            <Typography variant="caption">Updated</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={3}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'warning.light' }}>
                                            <Typography variant="h6">{importResults.skipped || 0}</Typography>
                                            <Typography variant="caption">Skipped</Typography>
                                        </Paper>
                                    </Grid>
                                    <Grid item xs={6} sm={3}>
                                        <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'error.light' }}>
                                            <Typography variant="h6">{importResults.failed || 0}</Typography>
                                            <Typography variant="caption">Failed</Typography>
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
                                Importing sites...
                            </Typography>
                        </Box>
                    )}
                </Stack>
            </Can>

            <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
                <DialogTitle>Confirm Site Import</DialogTitle>
                <DialogContent>
                    <Typography>
                        This will import {selectedCount} location(s) as EWNET Sites.
                    </Typography>
                    {selectedCount > 0 && (
                        <Box sx={{ mt: 1, maxHeight: 200, overflow: 'auto' }}>
                            {Array.from(selectedLocations).slice(0, 10).map((loc) => (
                                <Typography key={loc} variant="body2">• {loc}</Typography>
                            ))}
                            {selectedCount > 10 && (
                                <Typography variant="body2" color="text.secondary">
                                    ... and {selectedCount - 10} more
                                </Typography>
                            )}
                        </Box>
                    )}
                    <Alert severity="warning" sx={{ mt: 2 }}>
                        Sites will be created with default settings. You can edit them later.
                    </Alert>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setConfirmOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        color="primary"
                        onClick={handleConfirmImport}
                        disabled={isImporting || selectedCount === 0}
                    >
                        Confirm Import
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default LibreNMSSiteImportPage;
