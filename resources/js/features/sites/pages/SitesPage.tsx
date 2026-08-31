import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box,
    Button,
    Typography,
    Stack,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    TextField,
    Alert
} from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams, GridRowParams } from '@mui/x-data-grid';
import AddIcon from '@mui/icons-material/Add';
import UploadIcon from '@mui/icons-material/Upload';
import DownloadIcon from '@mui/icons-material/Download';
import { Inventory, LocationOn } from '@mui/icons-material';
import { sitesApi, getSiteDashboard } from '@/api/sites';
import { SiteHeaderDashboard } from '../components/SiteHeaderDashboard';
import { SiteFilters } from '../components/SiteFilters';
import { SiteStatusBadge } from '../components/SiteStatusBadge';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { Can } from '@/components/auth/Can';
import { SiteFormDrawer } from '../components/SiteFormDrawer';
import { PageHeader } from '@/components/layout/PageHeader';
import axios from 'axios';
import toast from 'react-hot-toast';

export const SitesPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    
    // State
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | undefined>();
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importStatus, setImportStatus] = useState<string>('');
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(15);

    // Queries
    const { data: dashboardData, isLoading: isDashboardLoading } = useQuery({

        queryKey: ['sites-dashboard'],
        queryFn: getSiteDashboard,
    });

    const { data, isLoading, error } = useQuery({
        queryKey: ['sites', search, statusFilter, typeFilter, page, pageSize],
        queryFn: () => sitesApi.list({ 
            search, 
            status: statusFilter || undefined, 
            type: typeFilter || undefined,
            page: page + 1, 
            per_page: pageSize 
        }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => sitesApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['sites'] });
            queryClient.invalidateQueries({ queryKey: ['sites-dashboard'] });
            setDeleteId(null);
            toast.success('Site deleted successfully');
        },
        onError: () => toast.error('Failed to delete site'),
    });

    const handleImport = async () => {
        if (!importFile) return;
        setImportStatus('Uploading...');
        const formData = new FormData();
        formData.append('file', importFile);
        try {
            await axios.post('/api/v1/sites/import', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setImportStatus('Import queued successfully! Check Horizon for status.');
            setTimeout(() => {
                setImportOpen(false);
                setImportStatus('');
                setImportFile(null);
                queryClient.invalidateQueries({ queryKey: ['sites'] });
            }, 2000);
        } catch (err) {
            setImportStatus('Import failed. Please check file format.');
        }
    };

    const handleExport = (format: 'csv' | 'xlsx') => {
        window.location.href = `/api/v1/sites/export?format=${format}&search=${search}`;
    };

    const handleEdit = (id: number) => {
        setEditingId(id);
        setFormOpen(true);
    };

    const handleRowClick = (params: GridRowParams) => {
        navigate(`/network/sites/${params.id}`);
    };

    const handleAdd = () => {
        setEditingId(undefined);
        setFormOpen(true);
    };

    const handleReset = () => {
        setSearch('');
        setStatusFilter('');
        setTypeFilter('');
        setPage(0);
    };

    const columns: GridColDef[] = [
        { 
            field: 'site', 
            headerName: 'Site Information', 
            flex: 1.5, 
            renderCell: (params: GridRenderCellParams) => (
                <Box>
                    <Typography variant="body2" fontWeight="bold">{params.row.name}</Typography>
                    <Typography variant="caption" color="text.secondary">{params.row.site_code}</Typography>
                </Box>
            )
        },
        { 
            field: 'type', 
            headerName: 'Type', 
            flex: 0.6,
            renderCell: (params: GridRenderCellParams) => (
                <Typography variant="body2" sx={{ textTransform: 'uppercase', fontSize: '0.75rem' }}>
                    {params.row.type}
                </Typography>
            )
        },
        { 
            field: 'status', 
            headerName: 'Status', 
            flex: 0.8,
            renderCell: (params: GridRenderCellParams) => (
                <SiteStatusBadge status={params.row.status} />
            )
        },
        { 
            field: 'organization', 
            headerName: 'Organization', 
            flex: 1,
            renderCell: (params: GridRenderCellParams) => (
                <Box>
                    <Typography variant="body2">{params.row.company?.name || '-'}</Typography>
                    <Typography variant="caption" color="text.secondary">
                        {params.row.region?.name} {params.row.branch?.name ? `• ${params.row.branch.name}` : ''}
                    </Typography>
                </Box>
            )
        },
        { 
            field: 'assets_count', 
            headerName: 'Assets', 
            flex: 0.6,
            align: 'center',
            headerAlign: 'center',
            renderCell: (params: GridRenderCellParams) => (
                <Stack direction="row" alignItems="center" justifyContent="center" spacing={0.5}>
                    <Inventory fontSize="small" color="action" />
                    <Typography variant="body2">{params.row.assets_count || 0}</Typography>
                </Stack>
            )
        },
        { 
            field: 'location', 
            headerName: 'Location', 
            flex: 0.8,
            renderCell: (params: GridRenderCellParams) => {
                const hasCoords = params.row.latitude && params.row.longitude;
                return (
                    <Stack direction="row" alignItems="center" spacing={0.5}>
                        <LocationOn fontSize="small" color={hasCoords ? "primary" : "disabled"} />
                        <Typography variant="body2" color={hasCoords ? "text.primary" : "text.secondary"}>
                            {hasCoords ? 'Available' : 'Missing'}
                        </Typography>
                    </Stack>
                );
            }
        },
        {
            field: 'actions',
            headerName: '',
            flex: 0.8,
            sortable: false,
            renderCell: (params: GridRenderCellParams) => (
                <Stack direction="row" spacing={1}>
                    <Can permission="sites.update">
                        <Button size="small" onClick={(e) => { e.stopPropagation(); handleEdit(params.row.id); }}>
                            Edit
                        </Button>
                    </Can>
                    <Can permission="sites.delete">
                        <Button size="small" color="error" onClick={(e) => { e.stopPropagation(); setDeleteId(params.row.id); }}>
                            Delete
                        </Button>
                    </Can>
                </Stack>
            )
        },
    ];

    if (error) return <Alert severity="error" sx={{ m: 3 }}>Failed to load site information. <Button onClick={() => window.location.reload()}>Retry</Button></Alert>;

    const rows = data?.data || [];

    return (
        <Box sx={{ p: 3, maxWidth: 1600, mx: 'auto' }}>
            <PageHeader
                title="Network Sites"
                subtitle="Manage network locations, POPs, towers and infrastructure sites"
                actions={
                    <Stack direction="row" spacing={1}>
                        <Can permission="sites.import">
                            <Button variant="outlined" startIcon={<UploadIcon />} onClick={() => setImportOpen(true)}>Import</Button>
                        </Can>
                        <Can permission="sites.export">
                            <Button variant="outlined" startIcon={<DownloadIcon />} onClick={() => handleExport('csv')}>Export</Button>
                        </Can>
                        <Can permission="sites.create">
                            <Button variant="contained" startIcon={<AddIcon />} onClick={handleAdd}>Add Site</Button>
                        </Can>
                    </Stack>
                }
            />

            <SiteHeaderDashboard data={dashboardData} isLoading={isDashboardLoading} />

            <SiteFilters 
                search={search} onSearchChange={setSearch}
                status={statusFilter} onStatusChange={setStatusFilter}
                type={typeFilter} onTypeChange={setTypeFilter}
                onReset={handleReset}
            />

            <Box sx={{ height: 600, width: '100%', bgcolor: 'background.paper', borderRadius: 1, border: '1px solid', borderColor: 'divider' }}>
                <DataGrid
                    rows={rows}
                    columns={columns}
                    loading={isLoading}
                    paginationMode="server"
                    rowCount={data?.total || 0}
                    paginationModel={{ page, pageSize }}
                    onPaginationModelChange={(model) => {
                        setPage(model.page);
                        setPageSize(model.pageSize);
                    }}
                    onRowClick={handleRowClick}
                    pageSizeOptions={[15, 25, 50, 100]}
                    disableRowSelectionOnClick
                    sx={{
                        '& .MuiDataGrid-row:hover': { backgroundColor: 'action.hover', cursor: 'pointer' },
                        '& .MuiDataGrid-cell': { py: 1.5 },
                    }}
                />
            </Box>

            {/* Import Dialog */}
            <Dialog open={importOpen} onClose={() => setImportOpen(false)}>
                <DialogTitle>Import Sites</DialogTitle>
                <DialogContent>
                    <TextField type="file" fullWidth inputProps={{ accept: '.csv,.xlsx' }} onChange={(e) => setImportFile((e.target as HTMLInputElement).files?.[0] || null)} sx={{ mt: 2 }} />
                    {importStatus && <Typography sx={{ mt: 2, color: 'primary.main' }}>{importStatus}</Typography>}
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setImportOpen(false)}>Cancel</Button>
                    <Button onClick={handleImport} variant="contained" disabled={!importFile}>Upload</Button>
                </DialogActions>
            </Dialog>

            <ConfirmDialog
                open={!!deleteId}
                title="Delete Site"
                message="Are you sure you want to delete this site? This action cannot be undone."
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />

            <SiteFormDrawer
                open={formOpen}
                siteId={editingId}
                onClose={() => { setFormOpen(false); setEditingId(undefined); }}
                onSuccess={() => {
                    setFormOpen(false);
                    setEditingId(undefined);
                    queryClient.invalidateQueries({ queryKey: ['sites'] });
                    queryClient.invalidateQueries({ queryKey: ['sites-dashboard'] });
                }}
            />
        </Box>
    );
};
