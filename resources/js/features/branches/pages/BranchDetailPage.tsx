import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Grid, Button, Divider, CircularProgress,
    Card, CardContent, Stack,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import DeleteIcon from '@mui/icons-material/Delete';
import ListAltIcon from '@mui/icons-material/ListAlt';
import BusinessIcon from '@mui/icons-material/Business';
import StorefrontIcon from '@mui/icons-material/Storefront';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { BranchFormDrawer } from '../components/BranchFormDrawer';
import { branchesApi } from '@/api/branches';
import { regionsApi } from '@/api/regions';
import { companiesApi } from '@/api/companies';
import { formatDateTime, getErrorMessage } from '@/utils';

export const BranchDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const { data: branch, isLoading } = useQuery({
        queryKey: ['branch', id],
        queryFn: () => branchesApi.getById(Number(id)),
        enabled: !!id,
    });

    // Fetch regions and companies for the edit drawer
    const { data: regionsData } = useQuery({
        queryKey: ['regions', 'all'],
        queryFn: () => regionsApi.getAll({ per_page: 200 }),
    });

    const { data: companiesData } = useQuery({
        queryKey: ['companies', 'all'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    const updateMutation = useMutation({
        mutationFn: (data: Record<string, unknown>) => branchesApi.update(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['branch', id] });
            queryClient.invalidateQueries({ queryKey: ['branches'] });
            showToast('Branch updated successfully', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: () => branchesApi.delete(Number(id)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['branches'] });
            showToast('Branch deleted successfully', 'success');
            navigate('/manage/branches');
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) {
        return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    }

    if (!branch) {
        return (
            <Box sx={{ textAlign: 'center', py: 8 }}>
                <Typography variant="h5">Branch not found</Typography>
                <Button onClick={() => navigate('/manage/branches')} sx={{ mt: 2 }}>Back</Button>
            </Box>
        );
    }

    const b = branch as any;
    const companyName = b.region?.company?.name;
    const regionName = b.region?.name;
    const regions = regionsData?.data ?? [];
    const companies = companiesData?.data ?? [];

    return (
        <>
            <PageHeader
                title={b.name}
                breadcrumbs={[
                    { label: 'Manage' },
                    { label: 'Branches', path: '/manage/branches' },
                    ...(companyName ? [{ label: companyName }] : []),
                    ...(regionName ? [{ label: regionName }] : []),
                    { label: b.name },
                ]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/branches')}>
                            Back
                        </Button>
                        <Can permission="departments.view">
                            <Button
                                variant="outlined"
                                color="primary"
                                startIcon={<ListAltIcon />}
                                onClick={() => navigate(`/manage/branches/${id}/departments`)}
                            >
                                Departments
                            </Button>
                        </Can>
                        <Can permission="branches.update">
                            <Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>
                                Edit
                            </Button>
                        </Can>
                        <Can permission="branches.delete">
                            <Button color="error" startIcon={<DeleteIcon />} onClick={() => setDeleteOpen(true)}>
                                Delete
                            </Button>
                        </Can>
                    </Stack>
                }
            />

            <Grid container spacing={3}>
                {/* Branch Info */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom
                                sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <StorefrontIcon fontSize="small" /> BRANCH INFORMATION
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Name" value={b.name} />
                                <DetailRow label="Code" value={b.code} />
                                <DetailRow label="Address" value={b.address} />
                                <DetailRow label="City" value={b.city} />
                                <DetailRow label="Phone" value={b.phone} />
                                <DetailRow label="Email" value={b.email} />
                                <DetailRow label="Status" value={<StatusChip active={b.is_active} />} />
                                <DetailRow label="Created" value={formatDateTime(b.created_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Hierarchy + Departments Link */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom
                                sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <BusinessIcon fontSize="small" /> ORGANIZATIONAL HIERARCHY
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Company" value={companyName} />
                                <DetailRow label="Region" value={regionName} />
                                <DetailRow label="Branch" value={b.name} />
                            </Stack>

                            <Divider sx={{ my: 2 }} />
                            <Typography variant="subtitle2" color="primary" gutterBottom
                                sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <ListAltIcon fontSize="small" /> DEPARTMENTS
                            </Typography>
                            <Can permission="departments.view">
                                <Button
                                    variant="outlined"
                                    fullWidth
                                    startIcon={<ListAltIcon />}
                                    onClick={() => navigate(`/manage/branches/${id}/departments`)}
                                    sx={{ mt: 1 }}
                                >
                                    Manage Departments
                                </Button>
                            </Can>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <BranchFormDrawer
                open={editOpen}
                onClose={() => setEditOpen(false)}
                branch={b}
                regions={regions}
                companies={companies}
                onSubmit={(data) => updateMutation.mutate(data)}
                loading={updateMutation.isPending}
            />

            <ConfirmDialog
                open={deleteOpen}
                title="Delete Branch"
                message={`Are you sure you want to delete "${b.name}"?`}
                confirmLabel="Delete"
                loading={deleteMutation.isPending}
                onConfirm={() => deleteMutation.mutate()}
                onCancel={() => setDeleteOpen(false)}
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
