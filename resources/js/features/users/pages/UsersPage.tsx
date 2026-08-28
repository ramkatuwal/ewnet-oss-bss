import React, { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Button, Typography, Chip, Avatar,
    FormControl, InputLabel, Select, MenuItem,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import SecurityIcon from '@mui/icons-material/Security';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, StatusChip, type Column, type RowAction } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { useAuthStore } from '@/stores/authStore';
import { getErrorMessage, formatDateTime } from '@/utils';
import { usersApi } from '@/api/users';
import { companiesApi } from '@/api/companies';
import { UserFormDrawer } from '../components/UserFormDrawer';
import type { UserListItem } from '@/types';

export const UsersPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const { user: authUser, isSuperAdmin } = useAuthStore();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [search, setSearch] = useState('');
    const [companyFilter, setCompanyFilter] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserListItem | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<UserListItem | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['users', page, rowsPerPage, search, companyFilter, statusFilter],
        queryFn: () => usersApi.getAll({
            page: page + 1,
            per_page: rowsPerPage,
            ...(search ? { search } : {}),
            ...(companyFilter ? { company_id: companyFilter } : {}),
            ...(statusFilter !== '' ? { is_active: statusFilter } : {}),
        }),
    });

    const { data: companiesData } = useQuery({
        queryKey: ['companies', 'all'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: usersApi.create,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            showToast('User created successfully', 'success');
            setDrawerOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Record<string, unknown> }) => usersApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            showToast('User updated successfully', 'success');
            setDrawerOpen(false);
            setEditingUser(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: usersApi.delete,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['users'] });
            showToast('User deleted successfully', 'success');
            setDeleteTarget(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const handleEdit = async (user: UserListItem) => {
        try {
            const fresh = await usersApi.getById(user.id);
            setEditingUser(fresh);
            setDrawerOpen(true);
        } catch (err) {
            showToast(getErrorMessage(err), 'error');
        }
    };

    const handleSubmit = (formData: Record<string, unknown>) => {
        if (editingUser) {
            updateMutation.mutate({ id: editingUser.id, data: formData });
        } else {
            createMutation.mutate(formData);
        }
    };

    const columns: Column<UserListItem>[] = useMemo(() => [
        {
            key: 'name',
            label: 'User',
            width: '22%',
            render: (row) => (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Avatar sx={{ width: 32, height: 32, bgcolor: 'primary.main', fontSize: 14 }}>
                        {row.name?.charAt(0)}
                    </Avatar>
                    <Box>
                        <Typography variant="body2" fontWeight={600}>{row.name}</Typography>
                        <Typography variant="caption" color="text.secondary">{row.email}</Typography>
                    </Box>
                </Box>
            ),
        },
        {
            key: 'org',
            label: 'Membership',
            width: '18%',
            render: (row) => (
                <Box>
                    <Typography variant="caption" display="block">{row.company?.name || '—'}</Typography>
                    {row.branch && (
                        <Typography variant="caption" color="text.secondary" display="block">
                            {row.branch.name}
                        </Typography>
                    )}
                    {row.department && (
                        <Typography variant="caption" color="text.disabled" display="block">
                            {row.department.name}
                        </Typography>
                    )}
                </Box>
            ),
        },
        {
            key: 'scopes',
            label: 'Management Scope',
            width: '15%',
            render: (row) => (
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                    {(row.management_scopes ?? []).slice(0, 2).map((scope, idx) => (
                        <Chip
                            key={idx}
                            label={scope.scope_name || scope.scope_type}
                            size="small"
                            variant="outlined"
                            color={scope.scope_type === 'company' ? 'primary' : 'default'}
                        />
                    ))}
                    {(row.management_scopes?.length ?? 0) > 2 && (
                        <Chip label={`+${row.management_scopes!.length - 2}`} size="small" variant="outlined" />
                    )}
                    {(!row.management_scopes || row.management_scopes.length === 0) && (
                        <Typography variant="caption" color="text.secondary">Membership-based</Typography>
                    )}
                </Box>
            ),
        },
        {
            key: 'roles',
            label: 'Roles',
            width: '15%',
            render: (row) => (
                <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                    {(row.roles ?? []).map((r) => (
                        <Chip
                            key={r.id}
                            label={r.name}
                            size="small"
                            color={r.name === 'Super Admin' ? 'error' : 'default'}
                            icon={r.name === 'Super Admin' ? <SecurityIcon sx={{ fontSize: 14 }} /> : undefined}
                            variant={r.name === 'Super Admin' ? 'filled' : 'outlined'}
                        />
                    ))}
                    {(!row.roles || row.roles.length === 0) && (
                        <Typography variant="caption" color="text.secondary">No roles</Typography>
                    )}
                </Box>
            ),
        },
        {
            key: 'is_active',
            label: 'Status',
            align: 'center',
            width: '10%',
            render: (row) => <StatusChip active={row.is_active} />,
        },
        {
            key: 'last_login',
            label: 'Last Login',
            width: '12%',
            render: (row) => (
                <Typography variant="caption">
                    {row.last_login_at ? formatDateTime(row.last_login_at) : 'Never'}
                </Typography>
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            width: '10%',
            render: (row) => <Typography variant="caption">{formatDateTime(row.created_at)}</Typography>,
        },
    ], []);

    const actions: RowAction<UserListItem>[] = useMemo(() => [
        {
            icon: <EditIcon fontSize="small" />,
            label: 'Edit',
            onClick: handleEdit,
        },
        {
            icon: <DeleteIcon fontSize="small" />,
            label: 'Delete',
            color: 'error',
            onClick: (row) => setDeleteTarget(row),
            visible: (row) => !row.roles?.some(r => r.name === 'Super Admin') && row.id !== authUser?.id,
        },
    ], [authUser]);

    return (
        <>
            <PageHeader
                title="Users"
                subtitle="Manage system users, memberships, and management scopes"
                breadcrumbs={[{ label: 'Manage' }, { label: 'Users' }]}
                actions={
                    <Can permission="users.create">
                        <Button
                            variant="contained"
                            startIcon={<AddIcon />}
                            onClick={() => { setEditingUser(null); setDrawerOpen(true); }}
                        >
                            Add User
                        </Button>
                    </Can>
                }
            />

            <SearchFilterBar
                searchValue={search}
                onSearchChange={(v) => { setSearch(v); setPage(0); }}
                placeholder="Search by name or email..."
            >
                {isSuperAdmin() && (
                    <FormControl size="small" sx={{ minWidth: 180 }}>
                        <InputLabel>Company</InputLabel>
                        <Select
                            value={companyFilter}
                            onChange={(e) => { setCompanyFilter(e.target.value); setPage(0); }}
                            label="Company"
                        >
                            <MenuItem value="">All</MenuItem>
                            {(companiesData?.data ?? []).map((c) => (
                                <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                )}
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

            <DataTable<UserListItem>
                columns={columns}
                data={data?.data ?? []}
                loading={isLoading}
                page={page}
                rowsPerPage={rowsPerPage}
                total={data?.total ?? 0}
                onPageChange={setPage}
                onRowsPerPageChange={(rpp) => { setRowsPerPage(rpp); setPage(0); }}
                actions={actions}
                onRowClick={(row) => navigate(`/manage/users/${row.id}`)}
                emptyMessage="No users found."
            />

            <UserFormDrawer
                open={drawerOpen}
                onClose={() => { setDrawerOpen(false); setEditingUser(null); }}
                user={editingUser}
                onSubmit={handleSubmit}
                loading={createMutation.isPending || updateMutation.isPending}
            />

            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete User"
                message={`Are you sure you want to delete "${deleteTarget?.name}" (${deleteTarget?.email})?`}
                confirmLabel="Delete"
                loading={deleteMutation.isPending}
                onConfirm={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
                onCancel={() => setDeleteTarget(null)}
            />
        </>
    );
};
