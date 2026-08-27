import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Typography, Grid, Button, Divider, CircularProgress, Card, CardContent, Stack, Chip, Link as MuiLink } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import StorefrontIcon from '@mui/icons-material/Storefront';
import EmailIcon from '@mui/icons-material/Email';
import PhoneIcon from '@mui/icons-material/Phone';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import NavigateNextIcon from '@mui/icons-material/NavigateNext';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { BranchFormDrawer } from '../components/BranchFormDrawer';
import { branchesApi } from '@/api/branches';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { formatDateTime, getErrorMessage } from '@/utils';
import { useToast } from '@/components/feedback/ToastProvider';
import type { Branch } from '@/types';

export const BranchDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);

    const { data: branch, isLoading } = useQuery({ queryKey: ['branch', id], queryFn: () => branchesApi.getById(Number(id)), enabled: !!id });
    const { data: companiesData } = useQuery({ queryKey: ['companies', 'all'], queryFn: () => companiesApi.getAll({ per_page: 100 }) });
    const { data: regionsData } = useQuery({ queryKey: ['regions', 'all'], queryFn: () => regionsApi.getAll({ per_page: 100 }) });

    const updateMutation = useMutation({
        mutationFn: (data: Partial<Branch>) => branchesApi.update(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['branch', id] });
            queryClient.invalidateQueries({ queryKey: ['branches'] });
            showToast('Branch updated successfully', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    if (!branch) return <Box sx={{ textAlign: 'center', py: 8 }}><Typography variant="h5">Branch not found</Typography><Button onClick={() => navigate('/manage/branches')} sx={{ mt: 2 }}>Back to Branches</Button></Box>;

    const addressParts = [branch.address, branch.city, branch.state, branch.postal_code, branch.country].filter(Boolean);

    return (
        <>
            <PageHeader title={branch.name}
                breadcrumbs={[
                    { label: 'Manage', path: '/manage/branches' },
                    { label: 'Branches', path: '/manage/branches' },
                    { label: branch.name },
                ]}
                actions={<Stack direction="row" spacing={1}><Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/branches')}>Back</Button><Can permission="branches.update"><Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>Edit</Button></Can></Stack>}
            />

            {/* Hierarchy Breadcrumb */}
            <Card elevation={0} sx={{ border: 1, borderColor: 'divider', mb: 3, p: 2 }}>
                <Stack direction="row" alignItems="center" spacing={1}>
                    <MuiLink component="button" variant="body2" onClick={() => navigate(`/manage/companies/${branch.region?.company_id}`)} sx={{ cursor: 'pointer' }}>
                        {branch.region?.company?.name || 'Company'}
                    </MuiLink>
                    <NavigateNextIcon fontSize="small" color="disabled" />
                    <MuiLink component="button" variant="body2" onClick={() => navigate(`/manage/regions/${branch.region_id}`)} sx={{ cursor: 'pointer' }}>
                        {branch.region?.name || 'Region'}
                    </MuiLink>
                    <NavigateNextIcon fontSize="small" color="disabled" />
                    <Typography variant="body2" fontWeight={600}>{branch.name}</Typography>
                </Stack>
            </Card>

            <Grid container spacing={3}>
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><StorefrontIcon color="primary" /> Branch Information</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Status" value={<StatusChip active={branch.is_active} />} />
                                <DetailRow label="Code" value={branch.code} />
                                <DetailRow label="Region" value={branch.region?.name} />
                                <DetailRow label="Company" value={branch.region?.company?.name} />
                                <DetailRow label="Created" value={formatDateTime(branch.created_at)} />
                                <DetailRow label="Last Updated" value={formatDateTime(branch.updated_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><EmailIcon color="primary" /> Contact</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Email" value={branch.email ? <MuiLink href={`mailto:${branch.email}`}>{branch.email}</MuiLink> : null} />
                                <DetailRow label="Phone" value={branch.phone ? <MuiLink href={`tel:${branch.phone}`}>{branch.phone}</MuiLink> : null} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}><LocationOnIcon color="primary" /> Address</Typography>
                            <Divider sx={{ mb: 2 }} />
                            {addressParts.length > 0 ? <Typography sx={{ whiteSpace: 'pre-line', lineHeight: 1.8 }}>{addressParts.join('\n')}</Typography> : <Typography color="text.secondary">No address on file</Typography>}
                            {(branch.latitude || branch.longitude) && (
                                <Box sx={{ mt: 2 }}>
                                    <Typography variant="caption" color="text.secondary">Coordinates: {branch.latitude}, {branch.longitude}</Typography>
                                </Box>
                            )}
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <BranchFormDrawer open={editOpen} onClose={() => setEditOpen(false)} branch={branch}
                regions={regionsData?.data ?? []} companies={companiesData?.data ?? []}
                onSubmit={(data) => updateMutation.mutate(data)} loading={updateMutation.isPending}
            />
        </>
    );
};

const DetailRow = ({ label, value }: { label: string; value: React.ReactNode }) => (
    <Box><Typography variant="caption" color="text.secondary" display="block">{label}</Typography><Typography variant="body2" fontWeight={500}>{value || <span style={{ color: '#999' }}>—</span>}</Typography></Box>
);
