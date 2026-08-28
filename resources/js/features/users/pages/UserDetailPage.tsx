import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Grid, Button, Divider, Avatar, CircularProgress,
    Card, CardContent, Stack, Chip, Paper,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import SecurityIcon from '@mui/icons-material/Security';
import BusinessIcon from '@mui/icons-material/Business';
import MapIcon from '@mui/icons-material/Map';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { UserFormDrawer } from '../components/UserFormDrawer';
import { usersApi } from '@/api/users';
import { scopesApi } from '@/api/scopes';
import { formatDateTime, getErrorMessage } from '@/utils';
import type { ManagementScope } from '@/types';

export const UserDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);
    const [revokeTarget, setRevokeTarget] = useState<ManagementScope | null>(null);

    const { data: user, isLoading } = useQuery({
        queryKey: ['user', id],
        queryFn: () => usersApi.getById(Number(id)),
        enabled: !!id,
    });

    const { data: userScopes } = useQuery({
        queryKey: ['userScopes', id],
        queryFn: () => scopesApi.getUserScopes(Number(id)),
        enabled: !!id,
    });

    const updateMutation = useMutation({
        mutationFn: (data: Record<string, unknown>) => usersApi.update(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['user', id] });
            queryClient.invalidateQueries({ queryKey: ['users'] });
            showToast('User updated successfully', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const revokeMutation = useMutation({
        mutationFn: (scopeId: number) => scopesApi.revokeScope(Number(id), scopeId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['userScopes', id] });
            queryClient.invalidateQueries({ queryKey: ['user', id] });
            showToast('Scope revoked successfully', 'success');
            setRevokeTarget(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (!user) {
        return (
            <Box sx={{ textAlign: 'center', py: 8 }}>
                <Typography variant="h5">User not found</Typography>
                <Button onClick={() => navigate('/manage/users')} sx={{ mt: 2 }}>Back</Button>
            </Box>
        );
    }

    return (
        <>
            <PageHeader
                title={user.name}
                breadcrumbs={[
                    { label: 'Manage', path: '/manage/users' },
                    { label: 'Users', path: '/manage/users' },
                    { label: user.name },
                ]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/users')}>
                            Back
                        </Button>
                        <Can permission="users.update">
                            <Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>
                                Edit
                            </Button>
                        </Can>
                    </Stack>
                }
            />

            <Grid container spacing={3}>
                {/* ── ACCOUNT ─────────────────────────────────────── */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                                <Avatar sx={{ width: 56, height: 56, bgcolor: 'primary.main', fontSize: 24 }}>
                                    {user.name?.charAt(0)}
                                </Avatar>
                                <Box>
                                    <Typography variant="h6" fontWeight={600}>{user.name}</Typography>
                                    <StatusChip active={user.is_active} />
                                </Box>
                            </Box>
                            <Divider sx={{ mb: 2 }} />
                            <Typography variant="subtitle2" color="primary" gutterBottom>
                                ACCOUNT
                            </Typography>
                            <Stack spacing={1.5}>
                                <DetailRow label="Name" value={user.name} />
                                <DetailRow label="Email" value={user.email} />
                                <DetailRow label="Phone" value={user.phone_number} />
                                <DetailRow label="Last Login" value={user.last_login_at ? formatDateTime(user.last_login_at) : 'Never'} />
                                <DetailRow label="Created" value={formatDateTime(user.created_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* ── MEMBERSHIP ──────────────────────────────────── */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <BusinessIcon fontSize="small" /> MEMBERSHIP (Where the user belongs)
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Company" value={user.company?.name} />
                                <DetailRow label="Branch" value={user.branch?.name} />
                                <DetailRow label="Department" value={user.department?.name} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* ── ROLES ───────────────────────────────────────── */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <SecurityIcon fontSize="small" /> ROLES
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                                {(user.roles ?? []).map((r) => (
                                    <Chip
                                        key={r.id}
                                        label={r.name}
                                        color={r.name === 'Super Admin' ? 'error' : 'primary'}
                                        icon={r.name === 'Super Admin' ? <SecurityIcon sx={{ fontSize: 16 }} /> : undefined}
                                    />
                                ))}
                                {(!user.roles || user.roles.length === 0) && (
                                    <Typography color="text.secondary">No roles assigned</Typography>
                                )}
                            </Box>
                        </CardContent>
                    </Card>
                </Grid>

                {/* ── MANAGEMENT SCOPE ────────────────────────────── */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <MapIcon fontSize="small" /> MANAGEMENT SCOPE (Where the user can manage)
                            </Typography>
                            <Divider sx={{ mb: 2 }} />

                            {user.roles?.some(r => r.name === 'Super Admin') ? (
                                <Chip label="Global (Super Admin)" color="error" icon={<SecurityIcon />} />
                            ) : (userScopes?.length ?? 0) > 0 ? (
                                <Stack spacing={1}>
                                    {userScopes!.map((scope) => (
                                        <Box
                                            key={scope.id}
                                            sx={{
                                                display: 'flex',
                                                justifyContent: 'space-between',
                                                alignItems: 'center',
                                                p: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                                borderRadius: 1,
                                            }}
                                        >
                                            <Box>
                                                <Typography variant="body2" fontWeight={600}>
                                                    {scope.scope_name || scope.scope_id}
                                                </Typography>
                                                <Typography variant="caption" color="text.secondary">
                                                    Type: {scope.scope_type}
                                                    {(scope as any).granted_by && ` • Granted by: ${(scope as any).granted_by}`}
                                                </Typography>
                                            </Box>
                                            <Can permission="users.update">
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    startIcon={<DeleteOutlineIcon />}
                                                    onClick={() => setRevokeTarget(scope)}
                                                >
                                                    Revoke
                                                </Button>
                                            </Can>
                                        </Box>
                                    ))}
                                </Stack>
                            ) : (
                                <Paper variant="outlined" sx={{ p: 2 }}>
                                    <Typography variant="body2" color="text.secondary">
                                        No explicit scopes assigned. Using membership-based fallback:
                                    </Typography>
                                    <Stack spacing={0.5} sx={{ mt: 1 }}>
                                        {user.department && <Typography variant="caption">• Department: {user.department.name}</Typography>}
                                        {user.branch && <Typography variant="caption">• Branch: {user.branch.name}</Typography>}
                                        {user.company && <Typography variant="caption">• Company: {user.company.name}</Typography>}
                                    </Stack>
                                </Paper>
                            )}
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <UserFormDrawer
                open={editOpen}
                onClose={() => setEditOpen(false)}
                user={user}
                onSubmit={(data) => updateMutation.mutate(data)}
                loading={updateMutation.isPending}
            />

            <ConfirmDialog
                open={!!revokeTarget}
                title="Revoke Management Scope"
                message={`Are you sure you want to revoke the "${revokeTarget?.scope_type}" scope from this user?`}
                confirmLabel="Revoke"
                loading={revokeMutation.isPending}
                onConfirm={() => { if (revokeTarget?.id) revokeMutation.mutate(revokeTarget.id); }}
                onCancel={() => setRevokeTarget(null)}
            />
        </>
    );
};

const DetailRow = ({ label, value }: { label: string; value: React.ReactNode }) => (
    <Box>
        <Typography variant="caption" color="text.secondary" display="block">{label}</Typography>
        <Typography variant="body2" fontWeight={500}>
            {value || <span style={{ color: '#999' }}>—</span>}
        </Typography>
    </Box>
);
