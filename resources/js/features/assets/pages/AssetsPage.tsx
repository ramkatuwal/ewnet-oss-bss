import React, { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Button, Typography, Stack, Dialog, DialogTitle,
    DialogContent, DialogActions, TextField, MenuItem, Alert, Chip,
} from '@mui/material';
import {
    DataGrid, GridColDef, GridRenderCellParams, GridRowParams,
    GridToolbarColumnsButton, gridClasses, GridSortModel,
} from '@mui/x-data-grid';
import AddIcon from '@mui/icons-material/Add';
import UploadFileIcon from '@mui/icons-material/UploadFile';
import DownloadIcon from '@mui/icons-material/Download';
import { PageHeader } from '@/components/layout/PageHeader';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { Can } from '@/components/auth/Can';
import AssetFormDrawer from '../components/AssetFormDrawer';
import {
    getAssets, getAssetDashboard, deleteAsset, exportAssets, importAssets,
    AssetDashboardData, AssetListParams,
} from '../api/assets';
import toast from 'react-hot-toast';

const COLUMN_VISIBILITY_KEY = 'assets-table-column-visibility';

const DEFAULT_COLUMN_VISIBILITY: Record<string, boolean> = {
    asset_tag: true,
    type: true,
    status: true,
    site: true,
    manufacturer: true,
    model: true,
    serial_number: true,
    condition: false,
    quantity: true,
    category: false,
    organization: false,
    purchase_date: false,
    installation_date: false,
    warranty_expiry: false,
    created_at: false,
    updated_at: false,
    actions: true,
};

const STATUS_COLORS: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    OPERATIONAL: 'success',
    SPARE: 'info',
    MAINTENANCE: 'warning',
    FAULTY: 'error',
    RETIRED: 'default',
    MISSING: 'error',
    DISPOSED: 'default',
};

const AssetsPage: React.FC = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    // State
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(25);
    const [sortModel, setSortModel] = useState<GridSortModel>([{ field: 'created_at', sort: 'desc' }]);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);

    // Column visibility with localStorage persistence
    const [columnVisibilityModel, setColumnVisibilityModel] = useState(() => {
        try {
            const saved = localStorage.getItem(COLUMN_VISIBILITY_KEY);
            return saved ? JSON.parse(saved) : DEFAULT_COLUMN_VISIBILITY;
        } catch {
            return DEFAULT_COLUMN_VISIBILITY;
        }
    });

    useEffect(() => {
        localStorage.setItem(COLUMN_VISIBILITY_KEY, JSON.stringify(columnVisibilityModel));
    }, [columnVisibilityModel]);

    // Reset page when filters change
    useEffect(() => {
        setPage(0);
    }, [search, statusFilter, categoryFilter]);

    // Queries
    const { data: dashboard, isLoading: isDashboardLoading } = useQuery<AssetDashboardData>({
        queryKey: ['asset-dashboard'],
        queryFn: getAssetDashboard,
    });

    const queryParams: AssetListParams = useMemo(() => ({
        page: page + 1,
        per_page: pageSize,
        search: search || undefined,
        status: statusFilter || undefined,
        category: categoryFilter || undefined,
        sort_by: sortModel[0]?.field || 'created_at',
        sort_dir: sortModel[0]?.sort || 'desc',
    }), [page, pageSize, search, statusFilter, categoryFilter, sortModel]);

    const { data, isLoading, error } = useQuery({
        queryKey: ['assets', queryParams],
        queryFn: () => getAssets(queryParams),
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
            const response = await exportAssets(format, { search, status: statusFilter, category: categoryFilter });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `assets_export.${format}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch {
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
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['asset-dashboard'] });
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Import failed');
        }
    };

    const handleReset = () => {
        setSearch('');
        setStatusFilter('');
        setCategoryFilter('');
        setPage(0);
    };

    const columns: GridColDef[] = useMemo(() => [
        {
            field: 'asset_tag',
            headerName: 'Asset',
            flex: 1.2,
            minWidth: 140,
            renderCell: (params: GridRenderCellParams) => (
                <Box>
                    <Typography variant="body2" fontWeight="bold">{params.row.asset_tag}</Typography>
                    <Typography variant="caption" color="text.secondary">{params.row.category}</Typography>
                </Box>
            ),
        },
        {
            field: 'type',
            headerName: 'Type',
            flex: 0.8,
            minWidth: 100,
        },
        {
            field: 'status',
            headerName: 'Status',
            flex: 0.7,
            minWidth: 110,
            renderCell: (params: GridRenderCellParams) => (
                <Chip
                    label={params.value}
                    size="small"
                    color={STATUS_COLORS[params.value] || 'default'}
                    sx={{ fontSize: '0.7rem', height: 24 }}
                />
            ),
        },
        {
            field: 'site',
            headerName: 'Site',
            flex: 1,
            minWidth: 130,
            sortable: false,
            renderCell: (params: GridRenderCellParams) => {
                const site = params.row.site;
                if (!site) return <Typography variant="body2" color="text.secondary">—</Typography>;
                return (
                    <Box
                        component="span"
                        onClick={(e) => { e.stopPropagation(); navigate(`/network/sites/${site.id}`); }}
                        sx={{ cursor: 'pointer', '&:hover': { textDecoration: 'underline' } }}
                    >
                        <Typography variant="body2" fontWeight="medium">{site.site_code}</Typography>
                        <Typography variant="caption" color="text.secondary">{site.name}</Typography>
                    </Box>
                );
            },
        },
        {
            field: 'manufacturer',
            headerName: 'Manufacturer',
            flex: 0.8,
            minWidth: 100,
        },
        {
            field: 'model',
            headerName: 'Model',
            flex: 0.8,
            minWidth: 100,
        },
        {
            field: 'serial_number',
            headerName: 'Serial',
            flex: 0.9,
            minWidth: 120,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>
                    {params.value || '—'}
                </Typography>
            ),
        },
        {
            field: 'condition',
            headerName: 'Condition',
            flex: 0.6,
            minWidth: 80,
        },
        {
            field: 'quantity',
            headerName: 'Qty',
            flex: 0.4,
            minWidth: 50,
            align: 'center',
            headerAlign: 'center',
        },
        {
            field: 'category',
            headerName: 'Category',
            flex: 0.7,
            minWidth: 90,
        },
        {
            field: 'organization',
            headerName: 'Organization',
            flex: 1,
            minWidth: 140,
            sortable: false,
            renderCell: (params: GridRenderCellParams) => {
                const site = params.row.site;
                if (!site) return '—';
                const parts = [site.company?.name, site.region?.name, site.branch?.name].filter(Boolean);
                return (
                    <Typography variant="body2" noWrap title={parts.join(' • ')}>
                        {parts.join(' • ') || '—'}
                    </Typography>
                );
            },
        },
        {
            field: 'purchase_date',
            headerName: 'Purchased',
            flex: 0.7,
            minWidth: 100,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2">{params.value ? new Date(params.value).toLocaleDateString() : '—'}</Typography>
            ),
        },
        {
            field: 'installation_date',
            headerName: 'Installed',
            flex: 0.7,
            minWidth: 100,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2">{params.value ? new Date(params.value).toLocaleDateString() : '—'}</Typography>
            ),
        },
        {
            field: 'warranty_expiry',
            headerName: 'Warranty',
            flex: 0.7,
            minWidth: 100,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2">{params.value ? new Date(params.value).toLocaleDateString() : '—'}</Typography>
            ),
        },
        {
            field: 'created_at',
            headerName: 'Created',
            flex: 0.7,
            minWidth: 100,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2">{params.value ? new Date(params.value).toLocaleDateString() : '—'}</Typography>
            ),
        },
        {
            field: 'updated_at',
            headerName: 'Updated',
            flex: 0.7,
            minWidth: 100,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2">{params.value ? new Date(params.value).toLocaleDateString() : '—'}</Typography>
            ),
        },
        {
            field: 'actions',
            headerName: '',
            width: 100,
            sortable: false,
            filterable: false,
            renderCell: (params: GridRenderCellParams) => (
                <Stack direction="row" spacing={0.5}>
                    <Can permission="assets.update">
                        <Button
                            size="small"
                            onClick={(e) => { e.stopPropagation(); setEditingId(params.row.id); setFormOpen(true); }}
                        >
                            Edit
                        </Button>
                    </Can>
                    <Can permission="assets.delete">
                        <Button
                            size="small"
                            color="error"
                            onClick={(e) => { e.stopPropagation(); setDeleteId(params.row.id); }}
                        >
                            Del
                        </Button>
                    </Can>
                </Stack>
            ),
        },
    ], [navigate]);

    if (error) {
        return (
            <Alert severity="error" sx={{ m: 3 }}>
                Unable to load assets. <Button onClick={() => window.location.reload()}>Retry</Button>
            </Alert>
        );
    }

    const rows = data?.data || [];
    const totalRows = (data as any)?.meta?.total ?? 0;

    return (
        <Box sx={{ p: 3, maxWidth: 1600, mx: 'auto' }}>
            <PageHeader
                title="Asset Management"
                breadcrumbs={[{ label: 'Network', path: '/network' }, { label: 'Assets' }]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Can permission="assets.import">
                            <Button variant="outlined" startIcon={<UploadFileIcon />} onClick={() => setImportOpen(true)}>Import</Button>
                        </Can>
                        <Can permission="assets.export">
                            <Button variant="outlined" startIcon={<DownloadIcon />} onClick={() => handleExport('csv')}>CSV</Button>
                            <Button variant="outlined" startIcon={<DownloadIcon />} onClick={() => handleExport('xlsx')}>XLSX</Button>
                        </Can>
                        <Can permission="assets.create">
                            <Button variant="contained" startIcon={<AddIcon />} onClick={() => { setEditingId(null); setFormOpen(true); }}>Add Asset</Button>
                        </Can>
                    </Stack>
                }
            />

            {/* Dashboard Cards */}
            {dashboard && !isDashboardLoading && (
                <Stack direction="row" spacing={2} sx={{ mb: 3, flexWrap: 'wrap' }}>
                    {[
                        { label: 'Total Records', value: dashboard.total_records },
                        { label: 'Total Units', value: dashboard.total_units },
                        { label: 'Sites Covered', value: dashboard.sites_with_assets },
                        { label: 'Operational', value: dashboard.by_status.operational },
                        { label: 'Maintenance', value: dashboard.by_status.maintenance },
                        { label: 'Faulty', value: dashboard.by_status.faulty },
                        { label: 'Retired', value: dashboard.by_status.retired },
                    ].map((card) => (
                        <Box
                            key={card.label}
                            sx={{
                                p: 2, borderRadius: 1, bgcolor: 'background.paper',
                                border: '1px solid', borderColor: 'divider', minWidth: 120, flex: 1,
                            }}
                        >
                            <Typography variant="h5" fontWeight="bold">{card.value}</Typography>
                            <Typography variant="caption" color="text.secondary">{card.label}</Typography>
                        </Box>
                    ))}
                </Stack>
            )}

            {/* Filters */}
            <SearchFilterBar
                searchValue={search}
                onSearchChange={setSearch}
                placeholder="Search assets, serials, sites..."
            >
                <TextField
                    select label="Status" size="small" sx={{ minWidth: 130, mr: 1 }}
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value)}
                >
                    <MenuItem value="">All Statuses</MenuItem>
                    {['OPERATIONAL', 'SPARE', 'MAINTENANCE', 'FAULTY', 'RETIRED', 'MISSING', 'DISPOSED'].map(s => (
                        <MenuItem key={s} value={s}>{s}</MenuItem>
                    ))}
                </TextField>
                <TextField
                    select label="Category" size="small" sx={{ minWidth: 130, mr: 1 }}
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                >
                    <MenuItem value="">All Categories</MenuItem>
                    {['POWER', 'NETWORK', 'INFRASTRUCTURE', 'OTHER'].map(c => (
                        <MenuItem key={c} value={c}>{c}</MenuItem>
                    ))}
                </TextField>
                <Button variant="outlined" size="small" onClick={handleReset}>Reset</Button>
            </SearchFilterBar>

            {/* DataGrid */}
            <Box sx={{ height: 650, width: '100%', bgcolor: 'background.paper', borderRadius: 1, border: '1px solid', borderColor: 'divider', mt: 2 }}>
                <DataGrid
                    rows={rows}
                    columns={columns}
                    loading={isLoading}
                    paginationMode="server"
                    sortingMode="server"
                    rowCount={totalRows}
                    paginationModel={{ page, pageSize }}
                    onPaginationModelChange={(model) => { setPage(model.page); setPageSize(model.pageSize); }}
                    sortModel={sortModel}
                    onSortModelChange={setSortModel}
                    onRowClick={(params: GridRowParams) => navigate(`/network/assets/${params.id}`)}
                    pageSizeOptions={[10, 25, 50, 100]}
                    disableRowSelectionOnClick
                    columnVisibilityModel={columnVisibilityModel}
                    onColumnVisibilityModelChange={(m) => setColumnVisibilityModel(m)}
                    slots={{
                        toolbar: () => (
                            <Box sx={{ p: 1, borderBottom: '1px solid', borderColor: 'divider', display: 'flex', justifyContent: 'flex-end' }}>
                                <GridToolbarColumnsButton />
                            </Box>
                        ),
                    }}
                    sx={{
                        '& .MuiDataGrid-row:hover': { backgroundColor: 'action.hover', cursor: 'pointer' },
                        '& .MuiDataGrid-cell': { py: 0.75 },
                        [`& .${gridClasses.cell}`]: { outline: 'none !important' },
                    }}
                />
            </Box>

            {/* Empty States */}
            {!isLoading && rows.length === 0 && (
                <Box sx={{ textAlign: 'center', py: 6 }}>
                    <Typography variant="body1" color="text.secondary">
                        {search || statusFilter || categoryFilter
                            ? 'No assets match your search or filters.'
                            : 'No assets have been registered yet.'}
                    </Typography>
                    {(search || statusFilter || categoryFilter) && (
                        <Button onClick={handleReset} sx={{ mt: 1 }}>Clear Filters</Button>
                    )}
                </Box>
            )}

            {/* Forms & Dialogs */}
            <AssetFormDrawer
                open={formOpen}
                onClose={() => { setFormOpen(false); setEditingId(null); }}
                assetId={editingId}
            />

            <ConfirmDialog
                open={!!deleteId}
                title="Delete Asset"
                message="Are you sure you want to delete this asset? This action cannot be undone."
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />

            <Dialog open={importOpen} onClose={() => setImportOpen(false)}>
                <DialogTitle>Import Assets</DialogTitle>
                <DialogContent>
                    <TextField
                        type="file" fullWidth
                        inputProps={{ accept: '.csv,.xlsx,.xls' }}
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
