import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Grid, Button, Divider, CircularProgress,
    Card, CardContent, Stack, Chip, Table, TableHead, TableRow, TableCell, TableBody, Avatar,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import DeleteIcon from '@mui/icons-material/Delete';
import ShieldIcon from '@mui/icons-material/Shield';
import PeopleIcon from '@mui/icons-material/People';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { useAuthStore } from '@/stores/authStore';
import { RoleFormDrawer } from '../components/RoleFormDrawer';
import { rolesApi } from '@/api/roles';
import { permissionsApi } from '@/api/permissions';
import { getErrorMessage } from '@/utils';
import type { Role, Permission } from '@/types';

export const RoleDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const { isSuperAdmin } = useAuthStore();
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const { data: role, isLoading } = useQuery({
        queryKey: ['role', id],
        queryFn: () => rolesApi.getById(Number(id)),
        enabled: !!id,
    });

    const { data: allPermissions } = useQuery({
        queryKey: ['permissions', 'all'],
        queryFn: () => permissionsApi.getAll({ per_page: 100 }),
    });

    const { data: roleUsers } = useQuery({
        queryKey: ['roleUsers', id],
        queryFn: () => rolesApi.getUsers(Number(id), { per_page: 50 }),
        enabled: !!id,
    });

    const updateMutation = useMutation({
        mutationFn: (data: any) => rolesApi.update(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['role', id] });
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            showToast('Role updated', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: () => rolesApi.delete(Number(id)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            showToast('Role deleted', 'success');
            navigate('/manage/roles');
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    if (!role) return <Box sx={{ textAlign: 'center', py: 8 }}><Typography variant="h5">Role not found</Typography><Button onClick={() => navigate('/manage/roles')} sx={{ mt: 2 }}>Back</Button></Box>;

    const r = role as Role;
    const perms = (allPermissions?.data ?? []) as Permission[];
    const users = roleUsers?.data ?? [];

    // Group permissions by domain
    const groupedPerms: Record<string, Permission[]> = {};
    (r.permissions ?? []).forEach(p => {
        const domain = p.domain || 'other';
        if (!groupedPerms[domain]) groupedPerms[domain] = [];
        groupedPerms[domain].push(p);
    });

    return (
        <>
            <PageHeader title={r.name} breadcrumbs={[{ label: 'Manage' }, { label: 'Roles', path: '/manage/roles' }, { label: r.name }]}
                actions={<Stack direction="row" spacing={1}>
                    <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/roles')}>Back</Button>
                    {(!r.is_protected || isSuperAdmin()) && <Can permission="roles.update"><Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>Edit</Button></Can>}
                    {!r.is_protected && <Can permission="roles.delete"><Button color="error" startIcon={<DeleteIcon />} onClick={() => setDeleteOpen(true)}>Delete</Button></Can>}
                </Stack>} />

            <Grid container spacing={3}>
                {/* Overview */}
                <Grid item xs={12} md={4}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom>OVERVIEW</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <Box><Typography variant="caption" color="text.secondary">Name</Typography><Typography variant="body2" fontWeight={600}>{r.name}</Typography></Box>
                                <Box><Typography variant="caption" color="text.secondary">Guard</Typography><Typography variant="body2">{r.guard_name}</Typography></Box>
                                <Box><Typography variant="caption" color="text.secondary">Permissions</Typography><Chip icon={<VpnKeyIcon sx={{ fontSize: 14 }} />} label={r.permission_count ?? 0} size="small" /></Box>
                                <Box><Typography variant="caption" color="text.secondary">Assigned Users</Typography><Chip icon={<PeopleIcon sx={{ fontSize: 14 }} />} label={r.user_count ?? 0} size="small" /></Box>
                                {r.is_protected && <Box><Chip icon={<ShieldIcon />} label="Protected System Role" color="error" size="small" /></Box>}
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Permissions Matrix */}
                <Grid item xs={12} md={8}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom>PERMISSIONS</Typography>
                            <Divider sx={{ mb: 2 }} />
                            {Object.entries(groupedPerms).sort(([a], [b]) => a.localeCompare(b)).map(([domain, domainPerms]) => (
                                <Box key={domain} sx={{ mb: 2 }}>
                                    <Typography variant="caption" fontWeight={700} textTransform="uppercase" color="text.secondary">{domain}</Typography>
                                    <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5, mt: 0.5 }}>
                                        {domainPerms.map(p => <Chip key={p.id} label={p.action} size="small" color="primary" variant="outlined" />)}
                                    </Box>
                                </Box>
                            ))}
                            {(r.permissions?.length ?? 0) === 0 && <Typography color="text.secondary">No permissions assigned.</Typography>}
                        </CardContent>
                    </Card>
                </Grid>

                {/* Assigned Users */}
                <Grid item xs={12}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom>ASSIGNED USERS ({users.length})</Typography>
                            <Divider sx={{ mb: 2 }} />
                            {users.length > 0 ? (
                                <Table size="small">
                                    <TableHead><TableRow>
                                        <TableCell>User</TableCell><TableCell>Email</TableCell>
                                        <TableCell>Membership</TableCell><TableCell>Status</TableCell>
                                    </TableRow></TableHead>
                                    <TableBody>{users.map((u: any) => (
                                        <TableRow key={u.id} hover sx={{ cursor: 'pointer' }} onClick={() => navigate(`/manage/users/${u.id}`)}>
                                            <TableCell><Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                <Avatar sx={{ width: 28, height: 28, fontSize: 12 }}>{u.name?.charAt(0)}</Avatar>
                                                <Typography variant="body2">{u.name}</Typography>
                                            </Box></TableCell>
                                            <TableCell><Typography variant="body2">{u.email}</Typography></TableCell>
                                            <TableCell><Typography variant="caption">{u.company?.name ?? '—'}</Typography></TableCell>
                                            <TableCell><StatusChip active={u.is_active} /></TableCell>
                                        </TableRow>
                                    ))}</TableBody>
                                </Table>
                            ) : <Typography color="text.secondary">No users assigned to this role.</Typography>}
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <RoleFormDrawer open={editOpen} onClose={() => setEditOpen(false)} role={r} allPermissions={perms}
                onSubmit={(data) => updateMutation.mutate(data)} loading={updateMutation.isPending} />
            <ConfirmDialog open={deleteOpen} title="Delete Role" message={`Delete "${r.name}"?`}
                confirmLabel="Delete" loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate()} onCancel={() => setDeleteOpen(false)} />
        </>
    );
};
