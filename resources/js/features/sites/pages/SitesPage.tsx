import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box,
    Button,
    Chip,
    Typography,
    Stack,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    TextField,
    CircularProgress,
    Alert
} from '@mui/material';
import { DataGrid, GridColDef, GridRenderCellParams, GridRowParams } from '@mui/x-data-grid';
import AddIcon from '@mui/icons-material/Add';
import UploadIcon from '@mui/icons-material/Upload';
import DownloadIcon from '@mui/icons-material/Download';
import { sitesApi } from '@/api/sites';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { Can } from '@/components/auth/Can';
import { SiteFormDrawer } from '../components/SiteFormDrawer';
import { formatCoordinates } from '@/utils/format';
import { PageHeader } from '@/components/layout/PageHeader';
import axios from 'axios';
import toast from 'react-hot-toast';

export const SitesPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | undefined>();
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importStatus, setImportStatus] = useState<string>('');
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(15);

    const { data, isLoading, error } = useQuery({
        queryKey: ['sites', search, page, pageSize],
        queryFn: () => sitesApi.list({ search, page: page + 1, per_page: pageSize }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => sitesApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['sites'] });
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
            }, 3000);
        } catch (err) {
            setImportStatus('Import failed. Please check file format.');
        }
    };

    const handleExport = (format: 'csv' | 'xlsx') => {
        window.location.href = `/api/v1/sites/export?format=${format}&search=${search}`;
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': return 'success';
            case 'planned': return 'info';
            case 'maintenance': return 'warning';
            case 'inactive': return 'default';
            case 'decommissioned': return 'error';
            default: return 'default';
        }
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

    const columns: GridColDef[] = [
        { field: 'site_code', headerName: 'Code', flex: 0.8 },
        { field: 'name', headerName: 'Name', flex: 1 },
        { field: 'type', headerName: 'Type', flex: 0.6 },
        {
            field: 'status',
            headerName: 'Status',
            flex: 0.6,
            renderCell: (params: GridRenderCellParams) => (
                <Chip label={params.value} color={getStatusColor(params.value) as any} size="small" />
            )
        },
        {
            field: 'location',
            headerName: 'Location',
            flex: 0.8,
            renderCell: (params: GridRenderCellParams) => {
                const { latitude, longitude } = params.row;
                return formatCoordinates(latitude, longitude);
            }
        },
        {
            field: 'actions',
            headerName: 'Actions',
            flex: 0.6,
            renderCell: (params: GridRenderCellParams) => (
                <Stack direction="row" spacing={0}>
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

    if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', p: 4 }}><CircularProgress /></Box>;
    if (error) return <Alert severity="error">Failed to load sites</Alert>;

    const rows = data?.data || [];

    return (
        <Box sx={{ p: 3 }}>
            <PageHeader
                title="Sites"
                actions={
                    <Stack direction="row" spacing={1}>
                        <Can permission="sites.import">
                            <Button
                                variant="outlined"
                                startIcon={<UploadIcon />}
                                onClick={() => setImportOpen(true)}
                            >
                                Import
                            </Button>
                        </Can>
                        <Can permission="sites.export">
                            <Button
                                variant="outlined"
                                startIcon={<DownloadIcon />}
                                onClick={() => handleExport('csv')}
                            >
                                Export CSV
                            </Button>
                            <Button
                                variant="outlined"
                                startIcon={<DownloadIcon />}
                                onClick={() => handleExport('xlsx')}
                            >
                                Export XLSX
                            </Button>
                        </Can>
                        <Can permission="sites.create">
                            <Button
                                variant="contained"
                                startIcon={<AddIcon />}
                                onClick={handleAdd}
                            >
                                Add Site
                            </Button>
                        </Can>
                    </Stack>
                }
            />

            <SearchFilterBar
                searchValue={search}
                onSearchChange={setSearch}
                placeholder="Search sites by code or name..."
            />

            <Box sx={{ mt: 2, height: 500, width: '100%' }}>
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
                    sx={{
                        cursor: 'pointer',
                        '& .MuiDataGrid-row:hover': {
                            backgroundColor: 'action.hover',
                        },
                    }}
                />
            </Box>

            <Dialog open={importOpen} onClose={() => setImportOpen(false)}>
                <DialogTitle>Import Sites</DialogTitle>
                <DialogContent>
                    <TextField
                        type="file"
                        fullWidth
                        inputProps={{ accept: '.csv,.xlsx' }}
                        onChange={(e: React.ChangeEvent<HTMLInputElement>) => setImportFile(e.target.files?.[0] || null)}
                        sx={{ mt: 2 }}
                    />
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
                onClose={() => {
                    setFormOpen(false);
                    setEditingId(undefined);
                }}
                onSuccess={() => {
                    setFormOpen(false);
                    setEditingId(undefined);
                    queryClient.invalidateQueries({ queryKey: ['sites'] });
                }}
            />
        </Box>
    );
};
