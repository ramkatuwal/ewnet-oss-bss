import React, { useState, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Button, Typography, Chip, FormControl, InputLabel, Select, MenuItem,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import PeopleIcon from '@mui/icons-material/People';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, StatusChip, type Column, type RowAction } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage, formatDateTime } from '@/utils';
import { departmentsApi } from '@/api/departments';
import { branchesApi } from '@/api/branches';
import { DepartmentFormDrawer } from '../components/DepartmentFormDrawer';
import type { Department } from '@/types';

export const DepartmentsPage = () => {
    const { branchId } = useParams<{ branchId: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingDept, setEditingDept] = useState<Department | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Department | null>(null);

    // Fetch branch context for breadcrumb
    const { data: branchData } = useQuery({
        queryKey: ['branch', branchId],
        queryFn: () => branchesApi.getById(Number(branchId)),
        enabled: !!branchId,
    });

    const { data, isLoading } = useQuery({
        queryKey: ['departments', branchId, page, rowsPerPage, search, statusFilter],
        queryFn: () => departmentsApi.getAll({
            page: page + 1,
            per_page: rowsPerPage,
            ...(branchId ? { branch_id: branchId } : {}),
            ...(search ? { search } : {}),
            ...(statusFilter !== '' ? { is_active: statusFilter } : {}),
        }),
    });

    const createMutation = useMutation({
        mutationFn: departmentsApi.create,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['departments'] });
            showToast('Department created successfully', 'success');
            setDrawerOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Department> }) =>
            departmentsApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['departments'] });
            showToast('Department updated successfully', 'success');
            setDrawerOpen(false);
            setEditingDept(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: departmentsApi.delete,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['departments'] });
            showToast('Department deleted successfully', 'success');
            setDeleteTarget(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const handleEdit = async (dept: Department) => {
        try {
            const fresh = await departmentsApi.getById(dept.id);
            setEditingDept(fresh);
            setDrawerOpen(true);
        } catch (err) {
            showToast(getErrorMessage(err), 'error');
        }
    };

    const handleSubmit = (formData: Partial<Department>) => {
        if (editingDept) {
            updateMutation.mutate({ id: editingDept.id, data: formData });
        } else {
            createMutation.mutate(formData);
        }
    };

    const branchName = (branchData as any)?.name;
    const regionName = (branchData as any)?.region?.name;
    const companyName = (branchData as any)?.region?.company?.name;

    const columns: Column<Department>[] = useMemo(() => [
        {
            key: 'name',
            label: 'Department',
            width: '25%',
            render: (row) => (
                <Box>
                    <Typography variant="body2" fontWeight={600}>{row.name}</Typography>
                    <Typography variant="caption" color="text.secondary">{row.code}</Typography>
                </Box>
            ),
        },
        {
            key: 'description',
            label: 'Description',
            width: '25%',
            render: (row) => (
                <Typography variant="body2" noWrap sx={{ maxWidth: 250 }}>
                    {row.description || '—'}
                </Typography>
            ),
        },
        {
            key: 'user_count',
            label: 'Users',
            align: 'center',
            width: '10%',
            render: (row) => (
                <Chip
                    icon={<PeopleIcon sx={{ fontSize: 14 }} />}
                    label={row.user_count ?? 0}
                    size="small"
                    variant="outlined"
                />
            ),
        },
        {
            key: 'is_active',
            label: 'Status',
            align: 'center',
            width: '12%',
            render: (row) => <StatusChip active={row.is_active} />,
        },
        {
            key: 'created_at',
            label: 'Created',
            width: '15%',
            render: (row) => (
                <Typography variant="caption">{formatDateTime(row.created_at)}</Typography>
            ),
        },
    ], []);

    const actions: RowAction<Department>[] = useMemo(() => [
        { icon: <EditIcon fontSize="small" />, label: 'Edit', onClick: handleEdit },
        {
            icon: <DeleteIcon fontSize="small" />,
            label: 'Delete',
            color: 'error',
            onClick: (row) => setDeleteTarget(row),
        },
    ], []);

    return (
        <>
            <PageHeader
                title="Departments"
                subtitle={branchId ? `Managing departments under ${branchName}` : 'All departments across organization'}
                breadcrumbs={[
                    { label: 'Manage' },
                    ...(branchId ? [
                        { label: 'Branches', path: '/manage/branches' },
                        ...(companyName ? [{ label: companyName }] : []),
                        ...(regionName ? [{ label: regionName }] : []),
                        { label: branchName ?? 'Branch' },
                    ] : []),
                    { label: 'Departments' },
                ]}
                actions={
                    <Can permission="departments.create">
                        <Button
                            variant="contained"
                            startIcon={<AddIcon />}
                            onClick={() => { setEditingDept(null); setDrawerOpen(true); }}
                        >
                            Add Department
                        </Button>
                    </Can>
                }
            />

            <SearchFilterBar
                searchValue={search}
                onSearchChange={(v) => { setSearch(v); setPage(0); }}
                placeholder="Search by name, code, or description..."
            >
                <FormControl size="small" sx={{ minWidth: 120 }}>
                    <InputLabel>Status</InputLabel>
                    <Select
                        value={statusFilter}
                        onChange={(e) => { setStatusFilter(e.target.value); setPage(0); }}
                        label="Status"
                    >
                        <MenuItem value="">All</MenuItem>
                        <MenuItem value="true">Active</MenuItem>
                        <MenuItem value="false">Inactive</MenuItem>
                    </Select>
                </FormControl>
            </SearchFilterBar>

            <DataTable<Department>
                columns={columns}
                data={data?.data ?? []}
                loading={isLoading}
                page={page}
                rowsPerPage={rowsPerPage}
                total={data?.total ?? 0}
                onPageChange={setPage}
                onRowsPerPageChange={(rpp) => { setRowsPerPage(rpp); setPage(0); }}
                actions={actions}
                onRowClick={(row) => navigate(`/manage/branches/${branchId}/departments/${row.id}`)}
                emptyMessage="No departments found for this branch."
            />

            <DepartmentFormDrawer
                open={drawerOpen}
                onClose={() => { setDrawerOpen(false); setEditingDept(null); }}
                department={editingDept}
                branchId={branchId ? Number(branchId) : undefined}
                onSubmit={handleSubmit}
                loading={createMutation.isPending || updateMutation.isPending}
            />

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Department"
                message={`Are you sure you want to delete "${deleteTarget?.name}"? This action cannot be undone.`}
                confirmLabel="Delete"
                loading={deleteMutation.isPending}
                onConfirm={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
                onCancel={() => setDeleteTarget(null)}
            />
        </>
    );
};
