import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Typography, FormControl, InputLabel, Select, MenuItem } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import EmailIcon from '@mui/icons-material/Email';
import PhoneIcon from '@mui/icons-material/Phone';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, StatusChip, type Column, type RowAction } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage, formatDateTime } from '@/utils';
import { branchesApi } from '@/api/branches';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { BranchFormDrawer } from '../components/BranchFormDrawer';
import type { Branch } from '@/types';

export const BranchesPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [search, setSearch] = useState('');
    const [companyFilter, setCompanyFilter] = useState<string>('');
    const [regionFilter, setRegionFilter] = useState<string>('');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Branch | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['branches', page, rowsPerPage, search, companyFilter, regionFilter],
        queryFn: () => branchesApi.getAll({
            page: page + 1, per_page: rowsPerPage,
            ...(search ? { search } : {}),
            ...(companyFilter ? { company_id: companyFilter } : {}),
            ...(regionFilter ? { region_id: regionFilter } : {}),
        }),
    });

    const { data: companiesData } = useQuery({ queryKey: ['companies', 'all'], queryFn: () => companiesApi.getAll({ per_page: 100 }) });
    const { data: regionsData } = useQuery({
        queryKey: ['regions', 'filter', companyFilter],
        queryFn: () => regionsApi.getAll({ per_page: 100, ...(companyFilter ? { company_id: companyFilter } : {}) }),
    });

    const createMutation = useMutation({
        mutationFn: branchesApi.create,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['branches'] }); showToast('Branch created successfully', 'success'); setDrawerOpen(false); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Branch> }) => branchesApi.update(id, data),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['branches'] }); showToast('Branch updated successfully', 'success'); setDrawerOpen(false); setEditingBranch(null); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: branchesApi.delete,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['branches'] }); showToast('Branch deleted successfully', 'success'); setDeleteTarget(null); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const handleEdit = async (branch: Branch) => {
        try { const fresh = await branchesApi.getById(branch.id); setEditingBranch(fresh); setDrawerOpen(true); }
        catch (err) { showToast(getErrorMessage(err), 'error'); }
    };

    const handleSubmit = (formData: Partial<Branch>) => {
        if (editingBranch) updateMutation.mutate({ id: editingBranch.id, data: formData });
        else createMutation.mutate(formData);
    };

    const columns: Column<Branch>[] = useMemo(() => [
        { key: 'name', label: 'Branch', width: '18%', render: (row) => (
            <Box><Typography variant="body2" fontWeight={600}>{row.name}</Typography><Typography variant="caption" color="text.secondary">Code: {row.code}</Typography></Box>
        )},
        { key: 'region', label: 'Region', width: '14%', render: (row) => <Typography variant="body2">{row.region?.name || '—'}</Typography> },
        { key: 'company', label: 'Company', width: '14%', render: (row) => <Typography variant="body2">{row.region?.company?.name || '—'}</Typography> },
        { key: 'contact', label: 'Contact', width: '18%', render: (row) => (
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.25 }}>
                {row.email && <Typography variant="caption" sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}><EmailIcon sx={{ fontSize: 14 }} /> {row.email}</Typography>}
                {row.phone && <Typography variant="caption" sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}><PhoneIcon sx={{ fontSize: 14 }} /> {row.phone}</Typography>}
                {!row.email && !row.phone && '—'}
            </Box>
        )},
        { key: 'location', label: 'Location', width: '14%', render: (row) => {
            const parts = [row.city, row.state].filter(Boolean);
            return parts.length ? <Typography variant="body2">{parts.join(', ')}</Typography> : '—';
        }},
        { key: 'is_active', label: 'Status', align: 'center', width: '10%', render: (row) => <StatusChip active={row.is_active} /> },
        { key: 'created_at', label: 'Created', width: '12%', render: (row) => <Typography variant="caption">{formatDateTime(row.created_at)}</Typography> },
    ], []);

    const actions: RowAction<Branch>[] = useMemo(() => [
        { icon: <EditIcon fontSize="small" />, label: 'Edit', onClick: handleEdit },
        { icon: <DeleteIcon fontSize="small" />, label: 'Delete', color: 'error', onClick: (row) => setDeleteTarget(row) },
    ], []);

    return (
        <>
            <PageHeader title="Branches" subtitle="Manage branch offices within regions"
                breadcrumbs={[{ label: 'Manage' }, { label: 'Branches' }]}
                actions={<Can permission="branches.create"><Button variant="contained" startIcon={<AddIcon />} onClick={() => { setEditingBranch(null); setDrawerOpen(true); }}>Add Branch</Button></Can>}
            />
            <SearchFilterBar searchValue={search} onSearchChange={(v) => { setSearch(v); setPage(0); }} placeholder="Search by name, code, city, email...">
                <FormControl size="small" sx={{ minWidth: 180 }}>
                    <InputLabel>Company</InputLabel>
                    <Select value={companyFilter} onChange={(e) => { setCompanyFilter(e.target.value); setRegionFilter(''); setPage(0); }} label="Company">
                        <MenuItem value="">All</MenuItem>
                        {(companiesData?.data ?? []).map((c) => <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>)}
                    </Select>
                </FormControl>
                <FormControl size="small" sx={{ minWidth: 180 }}>
                    <InputLabel>Region</InputLabel>
                    <Select value={regionFilter} onChange={(e) => { setRegionFilter(e.target.value); setPage(0); }} label="Region">
                        <MenuItem value="">All</MenuItem>
                        {(regionsData?.data ?? []).map((r) => <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>)}
                    </Select>
                </FormControl>
            </SearchFilterBar>
            <DataTable<Branch> columns={columns} data={data?.data ?? []} loading={isLoading}
                page={page} rowsPerPage={rowsPerPage} total={data?.total ?? 0}
                onPageChange={setPage} onRowsPerPageChange={(rpp) => { setRowsPerPage(rpp); setPage(0); }}
                actions={actions} onRowClick={(row) => navigate(`/manage/branches/${row.id}`)}
                emptyMessage="No branches found. Create your first branch to get started."
            />
            <BranchFormDrawer open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditingBranch(null); }}
                branch={editingBranch} regions={regionsData?.data ?? []} companies={companiesData?.data ?? []}
                onSubmit={handleSubmit} loading={createMutation.isPending || updateMutation.isPending}
            />
            <ConfirmDialog open={!!deleteTarget} title="Delete Branch"
                message={`Are you sure you want to delete "${deleteTarget?.name}"?`}
                confirmLabel="Delete" loading={deleteMutation.isPending}
                onConfirm={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
                onCancel={() => setDeleteTarget(null)}
            />
        </>
    );
};
