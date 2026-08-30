import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box,
    Button,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Typography,
    Chip,
    IconButton,
    CircularProgress,
    Alert,
    Stack,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    TextField,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import UploadIcon from '@mui/icons-material/Upload';
import DownloadIcon from '@mui/icons-material/Download';
import { sitesApi, Site } from '@/api/sites';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { Can } from '@/components/auth/Can';
import { SiteFormDrawer } from '../components/SiteFormDrawer';
import { formatCoordinates } from '@/utils/format';
import axios from 'axios';

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

    const { data, isLoading, error } = useQuery({
        queryKey: ['sites', search],
        queryFn: () => sitesApi.list({ search }),
    });

    const deleteMutation = useMutation({
        mutationFn: (id: number) => sitesApi.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['sites'] });
            setDeleteId(null);
        },
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

    const handleEdit = (id: number, e: React.MouseEvent) => {
        e.stopPropagation();
        setEditingId(id);
        setFormOpen(true);
    };

    const handleDelete = (id: number, e: React.MouseEvent) => {
        e.stopPropagation();
        setDeleteId(id);
    };

    const handleAdd = () => {
        setEditingId(undefined);
        setFormOpen(true);
    };

    const handleRowClick = (id: number) => {
        navigate(`/network/sites/${id}`);
    };

    if (isLoading) return <CircularProgress />;
    if (error) return <Alert severity="error">Failed to load sites</Alert>;

    return (
        <Box sx={{ p: 3 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h4">Sites</Typography>
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
            </Box>

            <SearchFilterBar
                searchValue={search}
                onSearchChange={setSearch}
                placeholder="Search sites by code or name..."
            />

            <TableContainer component={Paper} sx={{ mt: 2 }}>
                <Table>
                    <TableHead>
                        <TableRow>
                            <TableCell>Code</TableCell>
                            <TableCell>Name</TableCell>
                            <TableCell>Type</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Location</TableCell>
                            <TableCell align="right">Actions</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {data?.data.map((site: Site) => (
                            <TableRow 
                                key={site.id} 
                                hover 
                                onClick={() => handleRowClick(site.id)}
                                sx={{ 
                                    cursor: 'pointer',
                                    '&:hover': { backgroundColor: 'action.hover' }
                                }}
                            >
                                <TableCell>{site.site_code}</TableCell>
                                <TableCell>{site.name}</TableCell>
                                <TableCell>{site.type}</TableCell>
                                <TableCell>
                                    <Chip label={site.status} color={getStatusColor(site.status) as any} size="small" />
                                </TableCell>
                                <TableCell>
                                    {formatCoordinates(site.latitude, site.longitude)}
                                </TableCell>
                                <TableCell align="right">
                                    <Stack direction="row" justifyContent="flex-end" spacing={1}>
                                        <Can permission="sites.update">
                                            <IconButton size="small" onClick={(e) => handleEdit(site.id, e)}>
                                                <EditIcon />
                                            </IconButton>
                                        </Can>
                                        <Can permission="sites.delete">
                                            <IconButton size="small" color="error" onClick={(e) => handleDelete(site.id, e)}>
                                                <DeleteIcon />
                                            </IconButton>
                                        </Can>
                                    </Stack>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>

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
