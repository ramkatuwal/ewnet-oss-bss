import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Grid, Button, Divider, CircularProgress,
    Card, CardContent, Stack, Chip,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import DeleteIcon from '@mui/icons-material/Delete';
import PeopleIcon from '@mui/icons-material/People';
import BusinessIcon from '@mui/icons-material/Business';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { DepartmentFormDrawer } from '../components/DepartmentFormDrawer';
import { departmentsApi } from '@/api/departments';
import { formatDateTime, getErrorMessage } from '@/utils';
import type { Department } from '@/types';

export const DepartmentDetailPage = () => {
    const { branchId, departmentId } = useParams<{ branchId: string; departmentId: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const { data: dept, isLoading } = useQuery({
        queryKey: ['department', departmentId],
        queryFn: () => departmentsApi.getById(Number(departmentId)),
        enabled: !!departmentId,
    });

    const updateMutation = useMutation({
        mutationFn: (data: Partial<Department>) => departmentsApi.update(Number(departmentId), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['department', departmentId] });
            queryClient.invalidateQueries({ queryKey: ['departments'] });
            showToast('Department updated successfully', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: () => departmentsApi.delete(Number(departmentId)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['departments'] });
            showToast('Department deleted successfully', 'success');
            navigate(`/manage/branches/${branchId}/departments`);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) {
        return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    }

    if (!dept) {
        return (
            <Box sx={{ textAlign: 'center', py: 8 }}>
                <Typography variant="h5">Department not found</Typography>
                <Button onClick={() => navigate(`/manage/branches/${branchId}/departments`)} sx={{ mt: 2 }}>Back</Button>
            </Box>
        );
    }

    const d = dept as Department;
    const companyName = d.branch?.region?.company?.name ?? d.company?.name;
    const regionName = d.branch?.region?.name;
    const branchName = d.branch?.name;

    return (
        <>
            <PageHeader
                title={d.name}
                breadcrumbs={[
                    { label: 'Manage' },
                    { label: 'Branches', path: '/manage/branches' },
                    ...(companyName ? [{ label: companyName }] : []),
                    ...(regionName ? [{ label: regionName }] : []),
                    ...(branchName ? [{ label: branchName, path: `/manage/branches/${branchId}/departments` }] : []),
                    { label: d.name },
                ]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Button startIcon={<ArrowBackIcon />}
                            onClick={() => navigate(`/manage/branches/${branchId}/departments`)}>
                            Back
                        </Button>
                        <Can permission="departments.update">
                            <Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>
                                Edit
                            </Button>
                        </Can>
                        <Can permission="departments.delete">
                            <Button color="error" startIcon={<DeleteIcon />} onClick={() => setDeleteOpen(true)}>
                                Delete
                            </Button>
                        </Can>
                    </Stack>
                }
            />

            <Grid container spacing={3}>
                {/* Department Info */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="subtitle2" color="primary" gutterBottom>DEPARTMENT</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Name" value={d.name} />
                                <DetailRow label="Code" value={d.code} />
                                <DetailRow label="Description" value={d.description} />
                                <DetailRow label="Status" value={<StatusChip active={d.is_active} />} />
                                <DetailRow label="Users" value={
                                    <Chip icon={<PeopleIcon sx={{ fontSize: 14 }} />}
                                        label={d.user_count ?? 0} size="small" variant="outlined" />
                                } />
                                <DetailRow label="Created" value={formatDateTime(d.created_at)} />
                                <DetailRow label="Updated" value={formatDateTime(d.updated_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Hierarchy */}
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
                                <DetailRow label="Branch" value={branchName} />
                                <DetailRow label="Department" value={d.name} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <DepartmentFormDrawer
                open={editOpen}
                onClose={() => setEditOpen(false)}
                department={d}
                branchId={Number(branchId)}
                onSubmit={(data) => updateMutation.mutate(data)}
                loading={updateMutation.isPending}
            />

            <ConfirmDialog
                open={deleteOpen}
                title="Delete Department"
                message={`Are you sure you want to delete "${d.name}"? This action cannot be undone.`}
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
