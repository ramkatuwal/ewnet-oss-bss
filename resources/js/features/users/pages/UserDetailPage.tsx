import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Typography, Grid, Button, Divider, Avatar, CircularProgress, Card, CardContent, Stack, Chip } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import PersonIcon from '@mui/icons-material/Person';
import SecurityIcon from '@mui/icons-material/Security';
import BusinessIcon from '@mui/icons-material/Business';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { UserFormDrawer } from '../components/UserFormDrawer';
import { usersApi } from '@/api/users';
import { formatDateTime, getErrorMessage } from '@/utils';
import { useToast } from '@/components/feedback/ToastProvider';
import type { UserListItem } from '@/types';

export const UserDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);

    const { data: user, isLoading } = useQuery({ queryKey: ['user', id], queryFn: () => usersApi.getById(Number(id)), enabled: !!id });

    const updateMutation = useMutation({
        mutationFn: (data: Record<string, unknown>) => usersApi.update(Number(id), data),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['user', id] }); queryClient.invalidateQueries({ queryKey: ['users'] }); showToast('User updated successfully', 'success'); setEditOpen(false); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    if (!user) return <Box sx={{ textAlign: 'center', py: 8 }}><Typography variant="h5">User not found</Typography><Button onClick={() => navigate('/manage/users')} sx={{ mt: 2 }}>Back</Button></Box>;

    return (
        <>
            <PageHeader title={user.name}
                breadcrumbs={[{ label: 'Manage', path: '/manage/users' }, { label: 'Users', path: '/manage/users' }, { label: user.name }]}
                actions={<Stack direction="row" spacing={1}><Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/users')}>Back</Button><Can permission="users.update"><Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>Edit</Button></Can></Stack>}
            />
            <Grid container spacing={3}>
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                                <Avatar sx={{ width: 56, height: 56, bgcolor: 'primary.main', fontSize: 24 }}>{user.name?.charAt(0)}</Avatar>
                                <Box><Typography variant="h6" fontWeight={600}>{user.name}</Typography><StatusChip active={user.is_active} /></Box>
                            </Box>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Email" value={user.email} />
                                <DetailRow label="Phone" value={user.phone_number} />
                                <DetailRow label="Last Login" value={user.last_login_at ? formatDateTime(user.last_login_at) : 'Never'} />
                                <DetailRow label="Created" value={formatDateTime(user.created_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><BusinessIcon color="primary" /> Organization</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Company" value={user.company?.name} />
                                <DetailRow label="Branch" value={user.branch?.name} />
                                <DetailRow label="Department" value={user.department?.name} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={12}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><SecurityIcon color="primary" /> Roles</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                                {(user.roles ?? []).map((r) => (
                                    <Chip key={r.id} label={r.name} color={r.name === 'Super Admin' ? 'error' : 'primary'} icon={r.name === 'Super Admin' ? <SecurityIcon sx={{ fontSize: 16 }} /> : undefined} />
                                ))}
                                {(!user.roles || user.roles.length === 0) && <Typography color="text.secondary">No roles assigned</Typography>}
                            </Box>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>
            <UserFormDrawer open={editOpen} onClose={() => setEditOpen(false)} user={user} onSubmit={(data) => updateMutation.mutate(data)} loading={updateMutation.isPending} />
        </>
    );
};

const DetailRow = ({ label, value }: { label: string; value: React.ReactNode }) => (
    <Box><Typography variant="caption" color="text.secondary" display="block">{label}</Typography><Typography variant="body2" fontWeight={500}>{value || <span style={{ color: '#999' }}>—</span>}</Typography></Box>
);
