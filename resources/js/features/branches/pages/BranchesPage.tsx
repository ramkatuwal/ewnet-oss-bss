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
import { branchesApi } from '@/api/branches';
import { regionsApi } from '@/api/regions';
import { Branch, Region } from '@/types';

export const BranchesPage: React.FC = () => {
    const { authState, hasPermission } = useAuthStore();
    const [branches, setBranches] = useState<Branch[]>([]);
    const [regions, setRegions] = useState<Region[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
    const [formData, setFormData] = useState<Partial<Branch>>({});
    const [submitting, setSubmitting] = useState(false);

    const canView = hasPermission('branches.view');
    const canCreate = hasPermission('branches.create');
    const canUpdate = hasPermission('branches.update');
    const canDelete = hasPermission('branches.delete');

    const fetchBranches = async () => {
        if (!canView) return;
        setLoading(true);
        setError(null);
        try {
            const response = await branchesApi.getAll();
            setBranches(response.data || []);
        } catch (err: any) {
            setError(err.message || 'Failed to load branches');
        } finally {
            setLoading(false);
        }
    };

    const fetchRegions = async () => {
        try {
            const response = await regionsApi.getAll();
            setRegions(response.data || []);
        } catch (err) {
            console.error('Failed to load regions for dropdown', err);
        }
    };

    useEffect(() => {
        if (authState === 'authenticated') {
            fetchBranches();
            fetchRegions();
        }
    }, [authState]);

    const handleSubmit = async () => {
        setSubmitting(true);
        setError(null);
        try {
            if (editingBranch) {
                await branchesApi.update(editingBranch.id, formData);
            } else {
                await branchesApi.create(formData);
            }
            setDialogOpen(false);
            fetchBranches();
        } catch (err: any) {
            setError(err.response?.data?.message || err.message || 'Failed to save branch');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Are you sure you want to delete this branch?')) return;
        try {
            await branchesApi.delete(id);
            fetchBranches();
        } catch (err: any) {
            setError(err.message || 'Failed to delete branch');
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
                <Alert severity="warning">You do not have permission to view branches.</Alert>
            </Box>
        );
    }

    return (
        <Box>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Branches</Typography>
                {canCreate && (
                    <Button variant="contained" startIcon={<Add />} onClick={() => { setEditingBranch(null); setFormData({ country: 'Nepal', is_active: true }); setDialogOpen(true); }}>
                        Add Branch
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
                                <TableCell>Region</TableCell>
                                <TableCell>City</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {branches.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} align="center">No branches found</TableCell>
                                </TableRow>
                            ) : (
                                branches.map((branch) => (
                                    <TableRow key={branch.id}>
                                        <TableCell>{branch.name}</TableCell>
                                        <TableCell>{branch.code}</TableCell>
                                        <TableCell>{branch.region?.name || 'N/A'}</TableCell>
                                        <TableCell>{branch.city || '-'}</TableCell>
                                        <TableCell>
                                            <Chip label={branch.is_active ? 'Active' : 'Inactive'} color={branch.is_active ? 'success' : 'default'} size="small" />
                                        </TableCell>
                                        <TableCell align="right">
                                            {canUpdate && (
                                                <IconButton size="small" onClick={() => { setEditingBranch(branch); setFormData(branch); setDialogOpen(true); }}>
                                                    <Edit fontSize="small" />
                                                </IconButton>
                                            )}
                                            {canDelete && (
                                                <IconButton size="small" color="error" onClick={() => handleDelete(branch.id)}>
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
                <DialogTitle>{editingBranch ? 'Edit Branch' : 'Add Branch'}</DialogTitle>
                <DialogContent dividers>
                    <Grid container spacing={2}>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                select
                                fullWidth
                                label="Region"
                                required
                                value={formData.region_id || ''}
                                onChange={(e) => setFormData({ ...formData, region_id: Number(e.target.value) })}
                            >
                                {regions.map((r) => (
                                    <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>
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
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="Phone"
                                value={formData.phone || ''}
                                onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                fullWidth
                                label="Email"
                                type="email"
                                value={formData.email || ''}
                                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                label="Address"
                                multiline
                                rows={2}
                                value={formData.address || ''}
                                onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                            />
                        </Grid>
                    </Grid>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDialogOpen(false)} disabled={submitting}>Cancel</Button>
                    <Button variant="contained" onClick={handleSubmit} disabled={submitting}>
                        {submitting ? <CircularProgress size={24} /> : (editingBranch ? 'Update' : 'Create')}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};
