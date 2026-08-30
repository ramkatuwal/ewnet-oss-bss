import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
    Box, Button, IconButton, Tooltip, Chip, 
    Dialog, DialogTitle, DialogContent, DialogActions,
    TextField, Grid, Card, CardContent, Typography
} from '@mui/material';
import { Add, Delete, Edit, UploadFile, Download } from '@mui/icons-material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { PageHeader } from '@/components/layout/PageHeader';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { Can } from '@/components/auth/Can';
import AssetFormDrawer from '../components/AssetFormDrawer';
import { getAssets, getAssetDashboard, deleteAsset, exportAssets, importAssets } from '../api/assets';
import toast from 'react-hot-toast';
import type { Asset } from '@/types';

const AssetsPage: React.FC = () => {
    const queryClient = useQueryClient();
    const [openForm, setOpenForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [filters, setFilters] = useState<any>({});
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(15);
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);

    const { data: dashboard } = useQuery({
        queryKey: ['asset-dashboard'],
        queryFn: getAssetDashboard,
    });

    const { data, isLoading } = useQuery({
        queryKey: ['assets', page, pageSize, filters],
        queryFn: () => getAssets({ 
            page: page + 1, 
            per_page: pageSize, 
            ...filters 
        }),
    });

    const deleteMutation = useMutation({
        mutationFn: deleteAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['asset-dashboard'] });
            setDeleteId(null);
            toast.success('Asset deleted successfully');
        },
        onError: () => toast.error('Failed to delete asset'),
    });

    const handleExport = async (format: string) => {
        try {
            const response = await exportAssets(format, filters);
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `assets_export.${format}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (error) {
            toast.error('Export failed');
        }
    };

    const handleImport = async () => {
        if (!importFile) return;
        try {
            await importAssets(importFile);
            toast.success('Import queued successfully');
            setImportOpen(false);
            setImportFile(null);
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Import failed');
        }
    };

    const columns: GridColDef[] = [
        { field: 'asset_tag', headerName: 'Asset Tag', flex: 1 },
        { field: 'type', headerName: 'Type', flex: 1 },
        { field: 'manufacturer', headerName: 'Manufacturer', flex: 1 },
        { field: 'model', headerName: 'Model', flex: 1 },
        { field: 'serial_number', headerName: 'Serial', flex: 1 },
        { field: 'quantity', headerName: 'Qty', width: 80 },
        { 
            field: 'status', 
            headerName: 'Status', 
            width: 120,
            renderCell: (params: GridRenderCellParams) => (
                <Chip 
                    label={params.value} 
                    size="small" 
                    color={params.value === 'OPERATIONAL' ? 'success' : 'default'} 
                />
            )
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
            )
        },
    ];

    return (
        <Box sx={{ p: 3 }}>
            <PageHeader title="Asset Management" breadcrumbs={['Network', 'Assets']} />
            
            {/* Dashboard Cards */}
            {dashboard && (
                <Grid container spacing={2} sx={{ mb: 3 }}>
                    <Grid item xs={6} sm={3}>
                        <Card>
                            <CardContent>
                                <Typography variant="h6">{dashboard.data.total_records}</Typography>
                                <Typography variant="body2" color="textSecondary">Total Records</Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid item xs={6} sm={3}>
                        <Card>
                            <CardContent>
                                <Typography variant="h6">{dashboard.data.total_units}</Typography>
                                <Typography variant="body2" color="textSecondary">Total Units</Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid item xs={6} sm={3}>
                        <Card>
                            <CardContent>
                                <Typography variant="h6">{dashboard.data.by_status.operational}</Typography>
                                <Typography variant="body2" color="textSecondary">Operational</Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                    <Grid item xs={6} sm={3}>
                        <Card>
                            <CardContent>
                                <Typography variant="h6">{dashboard.data.by_status.maintenance}</Typography>
                                <Typography variant="body2" color="textSecondary">Maintenance</Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                </Grid>
            )}

            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <SearchFilterBar 
                    onSearch={(value: string) => setFilters({ ...filters, search: value })}
                    onFilterChange={(key: string, value: string) => setFilters({ ...filters, [key]: value })}
                    filters={[
                        { key: 'category', label: 'Category', options: ['POWER', 'NETWORK', 'INFRASTRUCTURE', 'OTHER'] },
                        { key: 'status', label: 'Status', options: ['OPERATIONAL', 'SPARE', 'MAINTENANCE', 'FAULTY', 'RETIRED'] },
                    ]}
                />
                <Box>
                    <Can permission="assets.import">
                        <Button startIcon={<UploadFile />} onClick={() => setImportOpen(true)} sx={{ mr: 1 }}>Import</Button>
                    </Can>
                    <Can permission="assets.export">
                        <Button startIcon={<Download />} onClick={() => handleExport('csv')} sx={{ mr: 1 }}>CSV</Button>
                        <Button startIcon={<Download />} onClick={() => handleExport('xlsx')}>XLSX</Button>
                    </Can>
                    <Can permission="assets.create">
                        <Button 
                            variant="contained" 
                            startIcon={<Add />} 
                            onClick={() => { setEditingId(null); setOpenForm(true); }} 
                            sx={{ ml: 2 }}
                        >
                            Add Asset
                        </Button>
                    </Can>
                </Box>
            </Box>

            <DataGrid
                rows={data?.data.data || []}
                columns={columns}
                loading={isLoading}
                paginationMode="server"
                rowCount={data?.data.meta.total || 0}
                paginationModel={{ page, pageSize }}
                onPaginationModelChange={(model) => {
                    setPage(model.page);
                    setPageSize(model.pageSize);
                }}
                autoHeight
            />

            <AssetFormDrawer 
                open={openForm} 
                onClose={() => { setOpenForm(false); setEditingId(null); }} 
                assetId={editingId} 
            />

            <ConfirmDialog
                open={!!deleteId}
                title="Delete Asset"
                content="Are you sure you want to delete this asset?"
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />

            <Dialog open={importOpen} onClose={() => setImportOpen(false)}>
                <DialogTitle>Import Assets</DialogTitle>
                <DialogContent>
                    <TextField 
                        type="file" 
                        fullWidth 
                        onChange={(e: any) => setImportFile(e.target.files?.[0] || null)} 
                        sx={{ mt: 2 }}
                    />
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setImportOpen(false)}>Cancel</Button>
                    <Button onClick={handleImport} variant="contained" disabled={!importFile}>Upload</Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default AssetsPage;
