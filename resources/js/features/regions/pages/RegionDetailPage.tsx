import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Typography, Grid, Button, Divider, Avatar, CircularProgress, Card, CardContent, Stack, Chip, Link as MuiLink } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import BusinessIcon from '@mui/icons-material/Business';
import AccountTreeIcon from '@mui/icons-material/AccountTree';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { RegionFormDrawer } from '../components/RegionFormDrawer';
import { regionsApi } from '@/api/regions';
import { companiesApi } from '@/api/companies';
import { branchesApi } from '@/api/branches';
import { formatDateTime, getErrorMessage } from '@/utils';
import { useToast } from '@/components/feedback/ToastProvider';
import type { Region } from '@/types';

export const RegionDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);

    const { data: region, isLoading } = useQuery({
        queryKey: ['region', id],
        queryFn: () => regionsApi.getById(Number(id)),
        enabled: !!id,
    });

    const { data: companiesData } = useQuery({
        queryKey: ['companies', 'all'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    const { data: branchesData } = useQuery({
        queryKey: ['branches', 'byRegion', id],
        queryFn: () => branchesApi.getAll({ region_id: Number(id), per_page: 100 }),
        enabled: !!id,
    });

    const updateMutation = useMutation({
        mutationFn: (data: Partial<Region>) => regionsApi.update(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['region', id] });
            queryClient.invalidateQueries({ queryKey: ['regions'] });
            showToast('Region updated successfully', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    if (!region) return <Box sx={{ textAlign: 'center', py: 8 }}><Typography variant="h5">Region not found</Typography><Button onClick={() => navigate('/manage/regions')} sx={{ mt: 2 }}>Back to Regions</Button></Box>;

    const branches = branchesData?.data ?? [];

    return (
        <>
            <PageHeader title={region.name}
                breadcrumbs={[
                    { label: 'Manage', path: '/manage/regions' },
                    { label: 'Regions', path: '/manage/regions' },
                    { label: region.name },
                ]}
                actions={<Stack direction="row" spacing={1}><Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/regions')}>Back</Button><Can permission="regions.update"><Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>Edit</Button></Can></Stack>}
            />
            <Grid container spacing={3}>
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><AccountTreeIcon color="primary" /> Region Information</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Status" value={<StatusChip active={region.is_active} />} />
                                <DetailRow label="Code" value={region.code} />
                                <DetailRow label="Description" value={region.description} />
                                <DetailRow label="Created" value={formatDateTime(region.created_at)} />
                                <DetailRow label="Last Updated" value={formatDateTime(region.updated_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><BusinessIcon color="primary" /> Company & Location</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Company" value={region.company?.name ? <MuiLink component="button" onClick={() => navigate(`/manage/companies/${region.company?.id}`)}>{region.company.name}</MuiLink> : null} />
                                <DetailRow label="City" value={region.city} />
                                <DetailRow label="State" value={region.state} />
                                <DetailRow label="Country" value={region.country} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid item xs={12}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider' }}>
                        <CardContent>
                            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                                <Typography variant="h6" sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><LocationOnIcon color="primary" /> Branches <Chip label={branches.length} size="small" sx={{ ml: 1 }} /></Typography>
                                <Can permission="branches.create"><Button size="small" variant="outlined" onClick={() => navigate('/manage/branches')}>Manage Branches</Button></Can>
                            </Box>
                            <Divider sx={{ mb: 2 }} />
                            {branches.length > 0 ? (
                                <Stack spacing={1}>{branches.map((b) => (
                                    <Box key={b.id} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', p: 1, border: 1, borderColor: 'divider', borderRadius: 1, cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }} onClick={() => navigate(`/manage/branches/${b.id}`)}>
                                        <Box><Typography variant="body2" fontWeight={600}>{b.name}</Typography><Typography variant="caption" color="text.secondary">Code: {b.code} {b.city ? `• ${b.city}` : ''}</Typography></Box>
                                        <StatusChip active={b.is_active} />
                                    </Box>
                                ))}</Stack>
                            ) : <Typography variant="body2" color="text.secondary">No branches in this region</Typography>}
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>
            <RegionFormDrawer open={editOpen} onClose={() => setEditOpen(false)} region={region} companies={companiesData?.data ?? []} onSubmit={(data) => updateMutation.mutate(data)} loading={updateMutation.isPending} />
        </>
    );
};

const DetailRow = ({ label, value }: { label: string; value: React.ReactNode }) => (
    <Box><Typography variant="caption" color="text.secondary" display="block">{label}</Typography><Typography variant="body2" fontWeight={500}>{value || <span style={{ color: '#999' }}>—</span>}</Typography></Box>
);
