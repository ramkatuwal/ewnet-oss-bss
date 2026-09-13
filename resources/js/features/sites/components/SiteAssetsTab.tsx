import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Button, IconButton, Tooltip, Chip, Typography,
    Grid, Card, CardContent, TextField, MenuItem, FormControl, InputLabel, Select
} from '@mui/material';
import { Add, Delete, Edit, Sync } from '@mui/icons-material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { getSiteAssets, deleteAsset } from '../../assets/api/assets';
import AssetFormDrawer from '../../assets/components/AssetFormDrawer';
import { SiteLibreNMSImportDialog } from './SiteLibreNMSImportDialog';
import toast from 'react-hot-toast';
import { apiClient } from '@/api/client';
import { infrastructureKeys } from '@/api/queryKeys';
import { PageErrorState } from '@/components/feedback/PageStates';
import { assetModelSettingsApi } from '@/features/settings/api/assetModelSettings';

interface SiteAssetsTabProps {
    siteId: number;
}

export const SiteAssetsTab: React.FC<SiteAssetsTabProps> = ({ siteId }) => {
    const queryClient = useQueryClient();
    const [openForm, setOpenForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(10);
    const [searchValue, setSearchValue] = useState('');
    const [filters, setFilters] = useState<{ category?: string; status?: string }>({});
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    useEffect(() => { setPage(0); }, [siteId, searchValue, filters]);
    const { data: categories = [] } = useQuery({
        queryKey: ['settings', 'asset-categories'],
        queryFn: () => assetModelSettingsApi.getCategories().then(r => r.data.data),
    });

    // Fetch LibreNMS integrations
    const { data: integrations } = useQuery({
        queryKey: ['integrations'],
        queryFn: () => apiClient.get('/api/v1/integrations').then(res => res.data.data),
    });

    const librenmsIntegration = integrations?.find(
        (i: any) => i.provider === 'librenms' && i.enabled
    );

    const { data, isLoading, isError, refetch } = useQuery({
        queryKey: infrastructureKeys.siteAssets(siteId, { page, pageSize, searchValue, ...filters }),
        queryFn: () => getSiteAssets(siteId, {
            page: page + 1,
            per_page: pageSize,
            search: searchValue || undefined,
            ...filters
        }),
        enabled: !!siteId,
    });

    const deleteMutation = useMutation({
        mutationFn: deleteAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'site-assets', siteId] });
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'assets'] });
            setDeleteId(null);
            toast.success('Asset deleted successfully');
        },
        onError: () => toast.error('Failed to delete asset'),
    });

    const columns: GridColDef[] = [
        { field: 'asset_tag', headerName: 'Asset Code', flex: 1, minWidth: 120 },
        {
            field: 'device_name',
            headerName: 'Device Name',
            flex: 1,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2" noWrap>{params.value || '\u2014'}</Typography>
            ),
        },
        { field: 'type', headerName: 'Type', flex: 0.7 },
        { field: 'manufacturer', headerName: 'Manufacturer', flex: 0.7 },
        { field: 'model', headerName: 'Model', flex: 0.7 },
        {
            field: 'management_ip',
            headerName: 'Management IP',
            flex: 0.8,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>
                    {params.value || '\u2014'}
                </Typography>
            ),
        },
        { field: 'serial_number', headerName: 'Serial', flex: 0.7 },
        { field: 'mac_address', headerName: 'MAC / Identifier', minWidth: 150, flex: 0.9, sortable: false },
        {
            field: 'status',
            headerName: 'Asset Status',
            width: 110,
            renderCell: (params: GridRenderCellParams) => (
                <Chip
                    label={params.value}
                    size="small"
                    color={params.value === 'OPERATIONAL' ? 'success' : 'default'}
                />
            ),
        },
        {
            field: 'condition',
            headerName: 'Condition',
            width: 100,
        },
        {
            field: 'actions',
            headerName: 'Actions',
            width: 120,
            renderCell: (params: GridRenderCellParams) => (
                <Box>
                    <Can permission="assets.update">
                        <Tooltip title="Edit">
                            <IconButton size="small" onClick={() => {
                                setEditingId(params.row.id);
                                setOpenForm(true);
                            }}>
                                <Edit fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    </Can>
                    <Can permission="assets.delete">
                        <Tooltip title="Delete">
                            <IconButton size="small" color="error" onClick={() => setDeleteId(params.row.id)}>
                                <Delete fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    </Can>
                </Box>
            ),
        },
    ];

    const handleImportSuccess = () => {
        queryClient.invalidateQueries({ queryKey: ['infrastructure', 'site-assets', siteId] });
        queryClient.invalidateQueries({ queryKey: ['infrastructure', 'assets'] });
    };

    if (isError) return <PageErrorState message="Unable to load site assets." onRetry={() => void refetch()} />;

    return (
        <Box>
            {/* Summary Cards */}
            <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid item xs={3}>
                    <Card>
                        <CardContent>
                            <Typography variant="h6">{data?.total || 0}</Typography>
                            <Typography variant="body2" color="text.secondary">Total Assets</Typography>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={3}>
                    <Card>
                        <CardContent>
                            <Typography variant="h6">
                                {data?.data?.reduce((sum: number, a: any) => sum + (a.quantity || 0), 0) || 0}
                            </Typography>
                            <Typography variant="body2" color="text.secondary">Units on This Page</Typography>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            {/* Toolbar */}
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, flexWrap: 'wrap', gap: 1 }}>
                <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                    <TextField
                        size="small"
                        placeholder="Search assets..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        sx={{ width: 200 }}
                    />
                    <FormControl size="small" sx={{ minWidth: 120 }}>
                        <InputLabel id={`site-assets-category-${siteId}`}>Category</InputLabel>
                        <Select
                            labelId={`site-assets-category-${siteId}`}
                            value={filters.category || ''}
                            label="Category"
                            onChange={(e) => setFilters({ ...filters, category: e.target.value })}
                        >
                            <MenuItem value="">All</MenuItem>
                            {categories.map(c => <MenuItem key={c.id} value={c.code}>{c.name}{c.is_active ? '' : ' (inactive)'}</MenuItem>)}
                        </Select>
                    </FormControl>
                    <FormControl size="small" sx={{ minWidth: 120 }}>
                        <InputLabel id={`site-assets-status-${siteId}`}>Status</InputLabel>
                        <Select
                            labelId={`site-assets-status-${siteId}`}
                            value={filters.status || ''}
                            label="Status"
                            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                        >
                            <MenuItem value="">All</MenuItem>
                            <MenuItem value="OPERATIONAL">Operational</MenuItem>
                            <MenuItem value="SPARE">Spare</MenuItem>
                            <MenuItem value="MAINTENANCE">Maintenance</MenuItem>
                            <MenuItem value="FAULTY">Faulty</MenuItem>
                            <MenuItem value="RETIRED">Retired</MenuItem>
                        </Select>
                    </FormControl>
                </Box>
                <Box>
                    {librenmsIntegration && (
                        <Can permission="librenms.import">
                            <Button
                                variant="outlined"
                                startIcon={<Sync />}
                                onClick={() => setImportDialogOpen(true)}
                                sx={{ mr: 1 }}
                            >
                                Import from LibreNMS
                            </Button>
                        </Can>
                    )}
                    <Can permission="assets.create">
                        <Button
                            variant="contained"
                            startIcon={<Add />}
                            onClick={() => { setEditingId(null); setOpenForm(true); }}
                        >
                            Add Asset
                        </Button>
                    </Can>
                </Box>
            </Box>

            {/* Data Grid */}
            <DataGrid
                density="compact"
                rows={data?.data || []}
                columns={columns.map(column => ({ ...column, sortable: false }))}
                loading={isLoading}
                paginationMode="server"
                rowCount={data?.total ?? 0}
                pageSizeOptions={[10, 25, 50, 100]}
                paginationModel={{ page, pageSize }}
                onPaginationModelChange={(model) => {
                    setPage(model.page);
                    setPageSize(model.pageSize);
                }}
                autoHeight
                sx={{ minHeight: 300 }}
            />

            {/* Asset Form Drawer */}
            <AssetFormDrawer
                open={openForm}
                onClose={() => { setOpenForm(false); setEditingId(null); }}
                assetId={editingId}
                siteId={siteId}
            />

            {/* Delete Confirmation */}
            <ConfirmDialog
                open={!!deleteId}
                title="Delete Asset"
                message="Are you sure you want to delete this asset?"
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />

            {/* LibreNMS Import Dialog */}
            {librenmsIntegration && (
                <SiteLibreNMSImportDialog
                    open={importDialogOpen}
                    onClose={() => setImportDialogOpen(false)}
                    siteId={siteId}
                    siteName={data?.data?.[0]?.site?.name || 'Site'}
                    integrationId={librenmsIntegration.id}
                    onSuccess={handleImportSuccess}
                />
            )}
        </Box>
    );
};
