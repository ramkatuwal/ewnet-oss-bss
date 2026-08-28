import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Typography, Chip, FormControl, InputLabel, Select, MenuItem } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, StatusChip, type Column, type RowAction } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage, formatDateTime } from '@/utils';
import { regionsApi } from '@/api/regions';
import { companiesApi } from '@/api/companies';
import { RegionFormDrawer } from '../components/RegionFormDrawer';
import type { Region } from '@/types';

export const RegionsPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [search, setSearch] = useState('');
    const [companyFilter, setCompanyFilter] = useState<string>('');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingRegion, setEditingRegion] = useState<Region | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Region | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['regions', page, rowsPerPage, search, companyFilter],
        queryFn: () => regionsApi.getAll({
            page: page + 1, per_page: rowsPerPage,
            ...(search ? { search } : {}),
            ...(companyFilter ? { company_id: companyFilter } : {}),
        }),
    });

    const { data: companiesData } = useQuery({
        queryKey: ['companies', 'all'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    const createMutation = useMutation({
        mutationFn: regionsApi.create,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['regions'] }); showToast('Region created successfully', 'success'); setDrawerOpen(false); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Region> }) => regionsApi.update(id, data),
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['regions'] }); showToast('Region updated successfully', 'success'); setDrawerOpen(false); setEditingRegion(null); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: regionsApi.delete,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['regions'] }); showToast('Region deleted successfully', 'success'); setDeleteTarget(null); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const handleEdit = async (region: Region) => {
        try { const fresh = await regionsApi.getById(region.id); setEditingRegion(fresh); setDrawerOpen(true); }
        catch (err) { showToast(getErrorMessage(err), 'error'); }
    };

    const handleSubmit = (formData: Partial<Region>) => {
        if (editingRegion) updateMutation.mutate({ id: editingRegion.id, data: formData });
        else createMutation.mutate(formData);
    };

    const companies = companiesData?.data ?? [];

    const columns: Column<Region>[] = useMemo(() => [
        { key: 'name', label: 'Region', width: '20%', render: (row) => (
            <Box><Typography variant="body2" fontWeight={600}>{row.name}</Typography><Typography variant="caption" color="text.secondary">Code: {row.code}</Typography></Box>
        )},
        { key: 'company', label: 'Company', width: '18%', render: (row) => (
            <Typography variant="body2">{row.company?.name || '—'}</Typography>
        )},
        { key: 'location', label: 'Location', width: '18%', render: (row) => {
            const parts = [row.city, row.state, row.country].filter(Boolean);
            return parts.length ? <Typography variant="body2">{parts.join(', ')}</Typography> : '—';
        }},
        { key: 'branches_count', label: 'Branches', align: 'center', width: '10%', render: (row) => (
            <Chip label={row.branches_count ?? 0} size="small" variant="outlined" />
        )},
        { key: 'is_active', label: 'Status', align: 'center', width: '10%', render: (row) => <StatusChip active={row.is_active} /> },
        { key: 'created_at', label: 'Created', width: '12%', render: (row) => <Typography variant="caption">{formatDateTime(row.created_at)}</Typography> },
    ], []);

    const actions: RowAction<Region>[] = useMemo(() => [
        { icon: <EditIcon fontSize="small" />, label: 'Edit', onClick: handleEdit },
        { icon: <DeleteIcon fontSize="small" />, label: 'Delete', color: 'error', onClick: (row) => setDeleteTarget(row) },
    ], []);

    return (
        <>
            <PageHeader title="Regions" subtitle="Manage geographic regions within companies"
                breadcrumbs={[{ label: 'Manage' }, { label: 'Regions' }]}
                actions={<Can permission="regions.create"><Button variant="contained" startIcon={<AddIcon />} onClick={() => { setEditingRegion(null); setDrawerOpen(true); }}>Add Region</Button></Can>}
            />
            <SearchFilterBar searchValue={search} onSearchChange={(v) => { setSearch(v); setPage(0); }} placeholder="Search by name, code, city...">
                <FormControl size="small" sx={{ minWidth: 200 }}>
                    <InputLabel>Company</InputLabel>
                    <Select value={companyFilter} onChange={(e) => { setCompanyFilter(e.target.value); setPage(0); }} label="Company">
                        <MenuItem value="">All Companies</MenuItem>
                        {companies.map((c) => <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>)}
                    </Select>
                </FormControl>
            </SearchFilterBar>
            <DataTable<Region> columns={columns} data={data?.data ?? []} loading={isLoading}
                page={page} rowsPerPage={rowsPerPage} total={data?.total ?? 0}
                onPageChange={setPage} onRowsPerPageChange={(rpp) => { setRowsPerPage(rpp); setPage(0); }}
                actions={actions} onRowClick={(row) => navigate(`/manage/regions/${row.id}`)}
                emptyMessage="No regions found. Create your first region to get started."
            />
            <RegionFormDrawer open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditingRegion(null); }}
                region={editingRegion} companies={companies} onSubmit={handleSubmit}
                loading={createMutation.isPending || updateMutation.isPending}
            />
            <ConfirmDialog open={!!deleteTarget} title="Delete Region"
                message={`Are you sure you want to delete "${deleteTarget?.name}"? This cannot be undone if the region has branches.`}
                confirmLabel="Delete" loading={deleteMutation.isPending}
                onConfirm={() => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); }}
                onCancel={() => setDeleteTarget(null)}
            />
        </>
    );
};
