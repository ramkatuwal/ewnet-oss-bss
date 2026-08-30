import { useState } from 'react';
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
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import { sitesApi, Site } from '@/api/sites';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { Can } from '@/components/auth/Can';
import { SiteFormDrawer } from '../components/SiteFormDrawer';

export const SitesPage = () => {
    const queryClient = useQueryClient();
    const [search, setSearch] = useState('');
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | undefined>();

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

    const handleAdd = () => {
        setEditingId(undefined);
        setFormOpen(true);
    };

    if (isLoading) return <CircularProgress />;
    if (error) return <Alert severity="error">Failed to load sites</Alert>;

    return (
        <Box sx={{ p: 3 }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
                <Typography variant="h4">Sites</Typography>
                <Can permission="sites.create">
                    <Button
                        variant="contained"
                        startIcon={<AddIcon />}
                        onClick={handleAdd}
                    >
                        Add Site
                    </Button>
                </Can>
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
                            <TableRow key={site.id} hover>
                                <TableCell>{site.site_code}</TableCell>
                                <TableCell>{site.name}</TableCell>
                                <TableCell>{site.type}</TableCell>
                                <TableCell>
                                    <Chip label={site.status} color={getStatusColor(site.status) as any} size="small" />
                                </TableCell>
                                <TableCell>
                                    {site.latitude && site.longitude ? `${site.latitude.toFixed(4)}, ${site.longitude.toFixed(4)}` : '-'}
                                </TableCell>
                                <TableCell align="right">
                                    <Stack direction="row" justifyContent="flex-end" spacing={1}>
                                        <Can permission="sites.update">
                                            <IconButton size="small" onClick={() => handleEdit(site.id)}>
                                                <EditIcon />
                                            </IconButton>
                                        </Can>
                                        <Can permission="sites.delete">
                                            <IconButton size="small" color="error" onClick={() => setDeleteId(site.id)}>
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
