import { useState, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Typography } from '@mui/material';
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
import { companiesApi } from '@/api/companies';
import { CompanyFormDrawer } from '../components/CompanyFormDrawer';
import type { Company } from '@/types';

export const CompaniesPage = () => {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [search, setSearch] = useState('');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingCompany, setEditingCompany] = useState<Company | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Company | null>(null);

    const { data, isLoading } = useQuery({
        queryKey: ['companies', page, rowsPerPage, search],
        queryFn: () => companiesApi.getAll({ page: page + 1, per_page: rowsPerPage, ...(search ? { search } : {}) }),
    });

    const createMutation = useMutation({
        mutationFn: companiesApi.create,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['companies'] }); showToast('Company created successfully', 'success'); setDrawerOpen(false); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Company> }) => companiesApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] });
            if (editingCompany) queryClient.invalidateQueries({ queryKey: ['company', editingCompany.id] });
            showToast('Company updated successfully', 'success');
            setDrawerOpen(false);
            setEditingCompany(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const deleteMutation = useMutation({
        mutationFn: companiesApi.delete,
        onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['companies'] }); showToast('Company deleted successfully', 'success'); setDeleteTarget(null); },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    const handleCreate = () => { setEditingCompany(null); setDrawerOpen(true); };

    // Edit: fetch fresh full data from API before opening drawer
    const handleEdit = async (company: Company) => {
        try {
            const fresh = await companiesApi.getById(company.id);
            setEditingCompany(fresh);
            setDrawerOpen(true);
        } catch (err) {
            showToast(getErrorMessage(err), 'error');
        }
    };

    // Click row → navigate to detail page
    const handleRowClick = (company: Company) => {
        navigate(`/manage/companies/${company.id}`);
    };

    const handleSubmit = (formData: FormData) => {
        if (editingCompany) updateMutation.mutate({ id: editingCompany.id, data: formData });
        else createMutation.mutate(formData);
    };

    const handleConfirmDelete = () => { if (deleteTarget) deleteMutation.mutate(deleteTarget.id); };

    const columns: Column<Company>[] = useMemo(() => [
        {
            key: 'name', label: 'Company', width: '20%',
            render: (row) => (
                <Box>
                    <Typography variant="body2" fontWeight={600}>{row.name}</Typography>
                    {row.registration_number && (
                        <Typography variant="caption" color="text.secondary">Reg: {row.registration_number}</Typography>
                    )}
                </Box>
            ),
        },
        { key: 'pan_number', label: 'PAN', width: '10%', render: (row) => row.pan_number || '—' },
        {
            key: 'contact', label: 'Contact', width: '20%',
            render: (row) => (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.25 }}>
                    {row.email && <Typography variant="caption" sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}><EmailIcon sx={{ fontSize: 14 }} /> {row.email}</Typography>}
                    {row.phone && <Typography variant="caption" sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}><PhoneIcon sx={{ fontSize: 14 }} /> {row.phone}</Typography>}
                    {!row.email && !row.phone && '—'}
                </Box>
            ),
        },
        {
            key: 'location', label: 'Location', width: '18%',
            render: (row) => { const p = [row.city, row.state, row.country].filter(Boolean); return p.length ? <Typography variant="body2">{p.join(', ')}</Typography> : '—'; },
        },
        { key: 'is_active', label: 'Status', align: 'center', width: '10%', render: (row) => <StatusChip active={row.is_active} /> },
        { key: 'created_at', label: 'Created', width: '12%', render: (row) => <Typography variant="caption">{formatDateTime(row.created_at)}</Typography> },
    ], []);

    const actions: RowAction<Company>[] = useMemo(() => [
        { icon: <EditIcon fontSize="small" />, label: 'Edit', onClick: handleEdit },
        { icon: <DeleteIcon fontSize="small" />, label: 'Delete', color: 'error', onClick: (row) => setDeleteTarget(row), visible: (row) => row.is_active },
    ], []);

    return (
        <>
            <PageHeader
                title="Companies"
                subtitle="Manage organizational entities"
                breadcrumbs={[{ label: 'Manage' }, { label: 'Companies' }]}
                actions={<Can permission="companies.create"><Button variant="contained" startIcon={<AddIcon />} onClick={handleCreate}>Add Company</Button></Can>}
            />
            <SearchFilterBar searchValue={search} onSearchChange={(v) => { setSearch(v); setPage(0); }} placeholder="Search by name, PAN, email..." />
            <DataTable<Company>
                columns={columns} data={data?.data ?? []} loading={isLoading}
                page={page} rowsPerPage={rowsPerPage} total={data?.total ?? 0}
                onPageChange={setPage} onRowsPerPageChange={(rpp) => { setRowsPerPage(rpp); setPage(0); }}
                actions={actions} onRowClick={handleRowClick}
                emptyMessage="No companies found. Create your first company to get started."
            />
            <CompanyFormDrawer open={drawerOpen} onClose={() => { setDrawerOpen(false); setEditingCompany(null); }} company={editingCompany} onSubmit={(data: FormData) => handleSubmit(data)} loading={createMutation.isPending || updateMutation.isPending} />
            <ConfirmDialog open={!!deleteTarget} title="Delete Company" message={`Are you sure you want to delete "${deleteTarget?.name}"? This will deactivate the company.`} confirmLabel="Delete" loading={deleteMutation.isPending} onConfirm={handleConfirmDelete} onCancel={() => setDeleteTarget(null)} />
        </>
    );
};
