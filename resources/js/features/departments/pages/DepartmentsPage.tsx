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
import { departmentsApi } from '@/api/departments';
import { branchesApi } from '@/api/branches';
import { Department, Branch } from '@/types';

export const DepartmentsPage: React.FC = () => {
    const { authState, hasPermission } = useAuthStore();
    const [departments, setDepartments] = useState<Department[]>([]);
    const [branches, setBranches] = useState<Branch[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState<Department | null>(null);
    const [formData, setFormData] = useState<Partial<Department>>({});
    const [submitting, setSubmitting] = useState(false);

    const canView = hasPermission('departments.view');
    const canCreate = hasPermission('departments.create');
    const canUpdate = hasPermission('departments.update');
    const canDelete = hasPermission('departments.delete');

    const fetchDepartments = async () => {
        if (!canView) return;
        setLoading(true);
        setError(null);
        try {
            const response = await departmentsApi.getAll();
            setDepartments(response.data || []);
        } catch (err: any) {
            setError(err.message || 'Failed to load departments');
        } finally {
            setLoading(false);
        }
    };

    const fetchBranches = async () => {
        try {
            const response = await branchesApi.getAll();
            setBranches(response.data || []);
        } catch (err) {
            console.error('Failed to load branches for dropdown', err);
        }
    };


    useEffect(() => {
        if (authState === 'authenticated') {
            fetchDepartments();
            fetchBranches();
        }
    }, [authState]);

    const handleSubmit = async () => {
        setSubmitting(true);
        setError(null);
        try {
            if (editingDepartment) {
                await departmentsApi.update(editingDepartment.id, formData);
            } else {
                await departmentsApi.create(formData);
            }
            setDialogOpen(false);
            fetchDepartments();
        } catch (err: any) {
            setError(err.response?.data?.message || err.message || 'Failed to save department');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Are you sure you want to delete this department?')) return;
        try {
            await departmentsApi.delete(id);
            fetchDepartments();
        } catch (err: any) {
            setError(err.message || 'Failed to delete department');
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
                <Alert severity="warning">You do not have permission to view departments.</Alert>
            </Box>
        );
    }

    return (
        <Box>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Departments</Typography>
                {canCreate && (
                    <Button variant="contained" startIcon={<Add />} onClick={() => { setEditingDepartment(null); setFormData({ is_active: true }); setDialogOpen(true); }}>
                        Add Department
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
                                <TableCell>Branch</TableCell>
                                <TableCell>Manager</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {departments.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} align="center">No departments found</TableCell>
                                </TableRow>
                            ) : (
                                departments.map((department) => (
                                    <TableRow key={department.id}>
                                        <TableCell>{department.name}</TableCell>
                                        <TableCell>{department.code}</TableCell>
                                        <TableCell>{department.branch?.name || 'N/A'}</TableCell>
                                        <TableCell>{department.manager?.name || '-'}</TableCell>
                                        <TableCell>
                                            <Chip label={department.is_active ? 'Active' : 'Inactive'} color={department.is_active ? 'success' : 'default'} size="small" />
                                        </TableCell>
                                        <TableCell align="right">
                                            {canUpdate && (
                                                <IconButton size="small" onClick={() => { setEditingDepartment(department); setFormData(department); setDialogOpen(true); }}>
                                                    <Edit fontSize="small" />
                                                </IconButton>
                                            )}
                                            {canDelete && (
                                                <IconButton size="small" color="error" onClick={() => handleDelete(department.id)}>
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
                <DialogTitle>{editingDepartment ? 'Edit Department' : 'Add Department'}</DialogTitle>
                <DialogContent dividers>
                    <Grid container spacing={2}>
                        <Grid item xs={12} sm={6}>
                            <TextField
                                select
                                fullWidth
                                label="Branch"
                                required
                                value={formData.branch_id || ''}
                                onChange={(e) => setFormData({ ...formData, branch_id: Number(e.target.value) })}
                            >
                                {branches.map((b) => (
                                    <MenuItem key={b.id} value={b.id}>{b.name}</MenuItem>
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
                        {submitting ? <CircularProgress size={24} /> : (editingDepartment ? 'Update' : 'Create')}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};
