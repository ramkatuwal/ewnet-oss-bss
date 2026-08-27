import React, { useState, useEffect } from 'react';
import {
    Box,
    Button,
    Typography,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    IconButton,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    TextField,
    Alert,
    CircularProgress,
    Chip,
    Grid,
    MenuItem,
} from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuthStore } from '@/stores/authStore';
import { regionsApi } from '@/api/regions';
import { companiesApi } from '@/api/companies';
import { Region, Company } from '@/types';

export const RegionsPage: React.FC = () => {
    const { authState, hasPermission } = useAuthStore();
    const [regions, setRegions] = useState<Region[]>([]);
    const [companies, setCompanies] = useState<Company[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRegion, setEditingRegion] = useState<Region | null>(null);
    const [formData, setFormData] = useState<Partial<Region>>({});
    const [submitting, setSubmitting] = useState(false);

    const canView = hasPermission('regions.view');
    const canCreate = hasPermission('regions.create');
    const canUpdate = hasPermission('regions.update');
    const canDelete = hasPermission('regions.delete');

    const fetchRegions = async () => {
        if (!canView) return;
        setLoading(true);
        setError(null);
        try {
            const response = await regionsApi.getAll();
            setRegions(response.data || []);
        } catch (err: any) {
            setError(err.message || 'Failed to load regions');
        } finally {
            setLoading(false);
        }
    };

    const fetchCompanies = async () => {
        try {
            const response = await companiesApi.getAll();
            setCompanies(response.data || []);
        } catch (err) {
            console.error('Failed to load companies for dropdown', err);
        }
    };

    useEffect(() => {
        if (authState === 'authenticated') {
            fetchRegions();
            fetchCompanies();
        }
    }, [authState]);

    const handleSubmit = async () => {
        setSubmitting(true);
        setError(null);
        try {
            if (editingRegion) {
                await regionsApi.update(editingRegion.id, formData);
            } else {
                await regionsApi.create(formData);
            }
            setDialogOpen(false);
            fetchRegions();
        } catch (err: any) {
            setError(err.response?.data?.message || err.message || 'Failed to save region');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Are you sure you want to delete this region?')) return;
        try {
            await regionsApi.delete(id);
            fetchRegions();
        } catch (err: any) {
            setError(err.message || 'Failed to delete region');
        }
    };

    if (authState === 'booting') {
        return (
            <Box display="flex" justifyContent="center" alignItems="center" minHeight="200px">
                <CircularProgress />
            </Box>
        );
    }

    if (!canView) {
        return (
            <Box p={3}>
                <Alert severity="warning">You do not have permission to view regions.</Alert>
            </Box>
        );
    }

    return (
        <Box>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Regions</Typography>
                {canCreate && (
                    <Button variant="contained" startIcon={<Add />} onClick={() => { setEditingRegion(null); setFormData({ country: 'Nepal', is_active: true }); setDialogOpen(true); }}>
                        Add Region
                    </Button>
                )}
            </Box>

            {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

            {loading ? (
                <Box display="flex" justifyContent="center" py={4}>
                    <CircularProgress />
                </Box>
            ) : (
                <TableContainer component={Paper}>
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>Name</TableCell>
                                <TableCell>Code</TableCell>
                                <TableCell>Company</TableCell>
                                <TableCell>City</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {regions.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} align="center">No regions found</TableCell>
                                </TableRow>
                            ) : (
                                regions.map((region) => (
                                    <TableRow key={region.id}>
                                        <TableCell>{region.name}</TableCell>
                                        <TableCell>{region.code}</TableCell>
                                        <TableCell>{region.company?.name || 'N/A'}</TableCell>
                                        <TableCell>{region.city || '-'}</TableCell>
                                        <TableCell>
                                            <Chip label={region.is_active ? 'Active' : 'Inactive'} color={region.is_active ? 'success' : 'default'} size="small" />
                                        </TableCell>
                                        <TableCell align="right">
                                            {canUpdate && (
                                                <IconButton size="small" onClick={() => { setEditingRegion(region); setFormData(region); setDialogOpen(true); }}>
                                                    <Edit fontSize="small" />
                                                </IconButton>
                                            )}
                                            {canDelete && (
                                                <IconButton size="small" color="error" onClick={() => handleDelete(region.id)}>
                                                    <Delete fontSize="small" />
                                                </IconButton>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </TableContainer>
            )}

            <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="md" fullWidth>
                <DialogTitle>{editingRegion ? 'Edit Region' : 'Add Region'}</DialogTitle>
                <DialogContent dividers>
                    <Grid container spacing={2}>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                select
                                fullWidth
                                label="Company"
                                required
                                value={formData.company_id || ''}
                                onChange={(e) => setFormData({ ...formData, company_id: Number(e.target.value) })}
                            >
                                {companies.map((c) => (
                                    <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                                ))}
                            </TextField>
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="Name"
                                required
                                value={formData.name || ''}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="Code"
                                required
                                value={formData.code || ''}
                                onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="City"
                                value={formData.city || ''}
                                onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="State"
                                value={formData.state || ''}
                                onChange={(e) => setFormData({ ...formData, state: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="Country"
                                value={formData.country || 'Nepal'}
                                onChange={(e) => setFormData({ ...formData, country: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                label="Description"
                                multiline
                                rows={2}
                                value={formData.description || ''}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                            />
                        </Grid>
                    </Grid>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDialogOpen(false)} disabled={submitting}>Cancel</Button>
                    <Button variant="contained" onClick={handleSubmit} disabled={submitting}>
                        {submitting ? <CircularProgress size={24} /> : (editingRegion ? 'Update' : 'Create')}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};
