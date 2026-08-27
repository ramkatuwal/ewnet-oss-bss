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
    FormControlLabel,
    Checkbox,
} from '@mui/material';
import { Add, Edit, Delete } from '@mui/icons-material';
import { useAuthStore } from '@/stores/authStore';
import { usersApi } from '@/api/users';
import { rolesApi } from '@/api/roles';
import { Role } from '@/types';

export const UsersPage: React.FC = () => {
    const { authState, hasPermission, user: currentUser } = useAuthStore();
    const [users, setUsers] = useState<any[]>([]);
    const [roles, setRoles] = useState<Role[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<any | null>(null);
    const [formData, setFormData] = useState<{ name: string; email: string; password: string; roleIds: number[] }>({
        name: '',
        email: '',
        password: '',
        roleIds: [],
    });
    const [submitting, setSubmitting] = useState(false);

    const canView = hasPermission('users.view');
    const canCreate = hasPermission('users.create');
    const canUpdate = hasPermission('users.update');
    const canDelete = hasPermission('users.delete');

    const fetchData = async () => {
        if (!canView) return;
        setLoading(true);
        setError(null);
        try {
            const [usersRes, rolesRes] = await Promise.all([
                usersApi.getAll(),
                rolesApi.getAll(),
            ]);
            setUsers(Array.isArray(usersRes) ? usersRes : []);
            setRoles(Array.isArray(rolesRes) ? rolesRes : []);
        } catch (err: any) {
            setError(err.message || 'Failed to load data');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (authState === 'authenticated') {
            fetchData();
        }
    }, [authState]);

    const handleSubmit = async () => {
        setSubmitting(true);
        setError(null);
        try {
            const payload: any = {
                name: formData.name,
                email: formData.email,
                roles: formData.roleIds,
            };
            if (formData.password) {
                payload.password = formData.password;
            }

            if (editingUser) {
                await usersApi.update(editingUser.id, payload);
            } else {
                await usersApi.create(payload);
            }
            setDialogOpen(false);
            fetchData();
        } catch (err: any) {
            const msg = err.response?.data?.message || err.message || 'Failed to save user';
            setError(msg);
        } finally {
            setSubmitting(false);
        }
    };

    const handleDelete = async (id: number) => {
        if (!window.confirm('Are you sure you want to delete this user?')) return;
        try {
            await usersApi.delete(id);
            fetchData();
        } catch (err: any) {
            setError(err.response?.data?.message || err.message || 'Failed to delete user');
        }
    };

    const openDialog = (user: any | null) => {
        if (user) {
            setEditingUser(user);
            setFormData({
                name: user.name,
                email: user.email,
                password: '',
                roleIds: user.roles?.map((r: any) => r.id) || [],
            });
        } else {
            setEditingUser(null);
            setFormData({ name: '', email: '', password: '', roleIds: [] });
        }
        setDialogOpen(true);
    };

    const toggleRole = (roleId: number) => {
        setFormData((prev) => ({
            ...prev,
            roleIds: prev.roleIds.includes(roleId)
                ? prev.roleIds.filter((id) => id !== roleId)
                : [...prev.roleIds, roleId],
        }));
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
                <Alert severity="warning">You do not have permission to view users.</Alert>
            </Box>
        );
    }

    return (
        <Box>
            <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
                <Typography variant="h5">Users</Typography>
                {canCreate && (
                    <Button variant="contained" startIcon={<Add />} onClick={() => openDialog(null)}>
                        Add User
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
                                <TableCell>Email</TableCell>
                                <TableCell>Roles</TableCell>
                                <TableCell align="right">Actions</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {users.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} align="center">No users found</TableCell>
                                </TableRow>
                            ) : (
                                users.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell>{user.name}</TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            {user.roles?.map((role: any) => (
                                                <Chip key={role.id} label={role.name} size="small" sx={{ mr: 0.5 }} />
                                            )) || '-'}
                                        </TableCell>
                                        <TableCell align="right">
                                            {canUpdate && (
                                                <IconButton size="small" onClick={() => openDialog(user)}>
                                                    <Edit fontSize="small" />
                                                </IconButton>
                                            )}
                                            {canDelete && user.id !== currentUser?.id && (
                                                <IconButton size="small" color="error" onClick={() => handleDelete(user.id)}>
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

            <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
                <DialogTitle>{editingUser ? 'Edit User' : 'Add User'}</DialogTitle>
                <DialogContent dividers>
                    <Grid container spacing={2}>
                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                label="Name"
                                required
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                label="Email"
                                type="email"
                                required
                                value={formData.email}
                                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                label="Password"
                                type="password"
                                required={!editingUser}
                                helperText={editingUser ? 'Leave blank to keep current password' : ''}
                                value={formData.password}
                                onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                            />
                        </Grid>
                        <Grid item xs={12}>
                            <Typography variant="subtitle2" sx={{ mb: 1 }}>Assign Roles:</Typography>
                            <Box display="flex" flexWrap="wrap" gap={1}>
                                {roles.map((role) => (
                                    <FormControlLabel
                                        key={role.id}
                                        control={
                                            <Checkbox
                                                checked={formData.roleIds.includes(role.id)}
                                                onChange={() => toggleRole(role.id)}
                                                size="small"
                                            />
                                        }
                                        label={role.name}
                                    />
                                ))}
                            </Box>
                        </Grid>
                    </Grid>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDialogOpen(false)} disabled={submitting}>Cancel</Button>
                    <Button variant="contained" onClick={handleSubmit} disabled={submitting || !formData.name || !formData.email}>
                        {submitting ? <CircularProgress size={24} /> : (editingUser ? 'Update' : 'Create')}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};
