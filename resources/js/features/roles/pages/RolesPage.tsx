import React, { useState, useEffect } from 'react';
import { Box, Button, Typography, Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, IconButton, Dialog, DialogTitle, DialogContent, DialogActions, TextField, Alert, CircularProgress, Grid, FormControlLabel, Checkbox } from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuthStore } from '@/stores/authStore';
import { rolesApi } from '@/api/roles';
import { permissionsApi } from '@/api/permissions';
import { Role, Permission } from '@/types';

export const RolesPage: React.FC = () => {
    const { authState, hasPermission } = useAuthStore();
    const [roles, setRoles] = useState<Role[]>([]);
    const [permissions, setPermissions] = useState<Permission[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [formData, setFormData] = useState<{ name: string; permissionIds: number[] }>({ name: '', permissionIds: [] });
    const [submitting, setSubmitting] = useState(false);

    const canView = hasPermission('roles.view');
    const canCreate = hasPermission('roles.create');
    const canUpdate = hasPermission('roles.update');
    const canDelete = hasPermission('roles.delete');

    const fetchData = async () => {
        if (!canView) return;
        setLoading(true);
        try {
            const [rolesRes, permsRes] = await Promise.all([rolesApi.getAll(), permissionsApi.getAll()]);
            setRoles(rolesRes || []);
            setPermissions(permsRes || []);
        } catch (err: any) {
            setError(err.message || 'Failed to load data');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { if (authState === 'authenticated') fetchData(); }, [authState]);

    const handleSubmit = async () => {
        setSubmitting(true);
        setError(null);
        try {
            if (editingRole) {
                await rolesApi.update(editingRole.id, { name: formData.name, permissions: formData.permissionIds });
            } else {
                await rolesApi.create({ name: formData.name, permissions: formData.permissionIds });
            }
            setDialogOpen(false);
            fetchData();
        } catch (err: any) {
            setError(err.response?.data?.message || err.message || 'Failed to save role');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Are you sure?')) return;
        try {
            await rolesApi.delete(id);
            fetchData();
        } catch (err: any) {
            setError(err.message || 'Failed to delete role');
        }
    };

    const togglePermission = (id: number) => {
        setFormData(prev => ({
            ...prev,
            permissionIds: prev.permissionIds.includes(id)
                ? prev.permissionIds.filter(pid => pid !== id)
                : [...prev.permissionIds, id]
        }));
    };

    if (authState === 'booting') return <Box display="flex" justifyContent="center" p={4}><CircularProgress /></Box>;
    if (!canView) return <Box p={3}><Alert severity="warning">No permission to view roles.</Alert></Box>;

    return (
        <Box>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Roles</Typography>
                {canCreate && <Button variant="contained" startIcon={<Add />} onClick={() => { setEditingRole(null); setFormData({ name: '', permissionIds: [] }); setDialogOpen(true); }}>Add Role</Button>}
            </Box>
            {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
            {loading ? <Box display="flex" justifyContent="center" py={4}><CircularProgress /></Box> : (
                <TableContainer component={Paper}>
                    <Table>
                        <TableHead><TableRow><TableCell>Name</TableCell><TableCell>Permissions Count</TableCell><TableCell align="right">Actions</TableCell></TableRow></TableHead>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>{role.name}</TableCell>
                                    <TableCell>{role.permissions?.length || 0}</TableCell>
                                    <TableCell align="right">
                                        {canUpdate && <IconButton size="small" onClick={() => { setEditingRole(role); setFormData({ name: role.name, permissionIds: role.permissions?.map((p: any) => p.id) || [] }); setDialogOpen(true); }}><Edit fontSize="small" /></IconButton>}
                                        {canDelete && role.name !== 'Super Admin' && <IconButton size="small" color="error" onClick={() => handleDelete(role.id)}><Delete fontSize="small" /></IconButton>}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
            )}
            <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="md" fullWidth>
                <DialogTitle>{editingRole ? 'Edit Role' : 'Add Role'}</DialogTitle>
                <DialogContent dividers>
                    <TextField fullWidth label="Role Name" value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })} margin="dense" />
                    <Typography variant="subtitle1" sx={{ mt: 2, mb: 1 }}>Assign Permissions:</Typography>
                    <Grid container spacing={1}>
                        {permissions.map((perm) => (
                            <Grid item xs={12} sm={6} md={4} key={perm.id}>
                                <FormControlLabel control={<Checkbox checked={formData.permissionIds.includes(perm.id)} onChange={() => togglePermission(perm.id)} size="small" />} label={perm.name} />
                            </Grid>
                        ))}
                    </Grid>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDialogOpen(false)} disabled={submitting}>Cancel</Button>
                    <Button variant="contained" onClick={handleSubmit} disabled={submitting || !formData.name}>{submitting ? <CircularProgress size={24} /> : 'Save'}</Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};
