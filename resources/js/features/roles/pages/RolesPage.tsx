import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Typography, Chip } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import ShieldIcon from '@mui/icons-material/Shield';
import PeopleIcon from '@mui/icons-material/People';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, type Column, type RowAction } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { useAuthStore } from '@/stores/authStore';
import { getErrorMessage } from '@/utils';
import { rolesApi } from '@/api/roles';
import { RoleFormDrawer } from '../components/RoleFormDrawer';
import type { Role } from '@/types';

export const RolesPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const { isSuperAdmin } = useAuthStore();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(25);
    const [search, setSearch] = useState('');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Role | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['roles', page, rowsPerPage, search],
        queryFn: () => rolesApi.getAll({ page: page + 1, per_page: rowsPerPage, ...(search ? { search } : {}) }),
    });

    const { data: allPermissions } = useQuery({
        queryKey: ['permissions', 'all'],
        queryFn: () => import('@/api/permissions').then(m => m.permissionsApi.getAll({ per_page: 100 })),
    });

    const createMutation = useMutation({
        mutationFn: rolesApi.create,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            showToast('Role created successfully', 'success');
            setDrawerOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: any }) => rolesApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            showToast('Role updated successfully', 'success');
            setDrawerOpen(false);
            setEditingRole(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: rolesApi.delete,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['roles'] });
            showToast('Role deleted successfully', 'success');
            setDeleteTarget(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const handleSubmit = (formData: { name: string; permissions: number[] }) => {
        if (editingRole) {
            updateMutation.mutate({ id: editingRole.id, data: formData });
        } else {
            createMutation.mutate(formData);
        }
    };

    const columns: Column<Role>[] = useMemo(() => [
        {
            key: 'name', label: 'Role', width: '30%',
            render: (row) => (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    {row.is_protected && <ShieldIcon color="error" fontSize="small" />}
                    <Typography variant="body2" fontWeight={600}>{row.name}</Typography>
                    {row.is_protected && <Chip label="Protected" size="small" color="error" variant="outlined" />}
                </Box>
            ),
        },
        {
            key: 'permissions', label: 'Permissions', width: '20%', align: 'center',
            render: (row) => (
                <Chip icon={<VpnKeyIcon sx={{ fontSize: 14 }} />} label={row.permission_count ?? 0} size="small" variant="outlined" />
            ),
        },
        {
            key: 'users', label: 'Users', width: '15%', align: 'center',
            render: (row) => (
                <Chip icon={<PeopleIcon sx={{ fontSize: 14 }} />} label={row.user_count ?? 0} size="small" variant="outlined" />
            ),
        },
        {
            key: 'created_at', label: 'Created', width: '20%',
            render: (row) => <Typography variant="caption">{row.created_at ? new Date(row.created_at).toLocaleDateString() : '—'}</Typography>,
        },
    ], []);

    const actions: RowAction<Role>[] = useMemo(() => [
        { icon: <EditIcon fontSize="small" />, label: 'Edit', onClick: (row) => { setEditingRole(row); setDrawerOpen(true); },
          visible: (row) => !row.is_protected || isSuperAdmin() },
        { icon: <DeleteIcon fontSize="small" />, label: 'Delete', color: 'error',
          onClick: (row) => setDeleteTarget(row),
          visible: (row) => !row.is_protected },
    ], [isSuperAdmin]);

    const perms = allPermissions?.data ?? [];

    return (
        <>
            <PageHeader title="Roles" subtitle="Manage roles and their permissions"
                breadcrumbs={[{ label: 'Manage' }, { label: 'Roles' }]}
                actions={<Can permission="roles.create"><Button variant="contained" startIcon={<AddIcon />} onClick={() => { setEditingRole(null); setDrawerOpen(true); }}>Create Role</Button></Can>}
            />
            <SearchFilterBar searchValue={search} onSearchChange={(v) => { setSearch(v); setPage(0); }} placeholder="Search roles..." />
            <DataTable<Role> columns={columns} data={data?.data ?? []} loading={isLoading}
                page={page} rowsPerPage={rowsPerPage} total={data?.total ?? 0}
                onPageChange={setPage} onRowsPerPageChange={(rpp) => { setRowsPerPage(rpp); setPage(0); }}
                actions={actions} onRowClick={(row) => navigate(`/manage/roles/${row.id}`)}
                emptyMessage="No roles found." />
            <RoleFormDrawer open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditingRole(null); }}
                role={editingRole} allPermissions={perms} onSubmit={handleSubmit}
                loading={createMutation.isPending || updateMutation.isPending} />
            <ConfirmDialog open={!!deleteTarget} title="Delete Role"
                message={`Are you sure you want to delete "${deleteTarget?.name}"?`}
                confirmLabel="Delete" loading={deleteMutation.isPending}
                onConfirm={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
                onCancel={() => setDeleteTarget(null)} />
        </>
    );
};
