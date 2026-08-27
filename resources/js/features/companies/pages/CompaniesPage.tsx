import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, StatusChip, type Column, type RowAction } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage } from '@/utils';
import { companiesApi } from '@/api/companies';
import { CompanyFormDrawer } from '../components/CompanyFormDrawer';
import type { Company } from '@/types';

export const CompaniesPage = () => {
    const queryClient = useQueryClient();
    const { showToast } = useToast();

    // List state
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const [search, setSearch] = useState('');

    // Drawer state
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [editingCompany, setEditingCompany] = useState<Company | null>(null);

    // Delete confirmation
    const [deleteTarget, setDeleteTarget] = useState<Company | null>(null);

    // Fetch companies
    const { data, isLoading } = useQuery({
        queryKey: ['companies', page, rowsPerPage, search],
        queryFn: () =>
            companiesApi.getAll({
                page: page + 1,
                per_page: rowsPerPage,
                ...(search ? { search } : {}),
            }),
    });

    // Create mutation
    const createMutation = useMutation({
        mutationFn: companiesApi.create,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] });
            showToast('Company created successfully', 'success');
            setDrawerOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    // Update mutation
    const updateMutation = useMutation({
        mutationFn: ({ id, data }: { id: number; data: Partial<Company> }) => companiesApi.update(id, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] });
            showToast('Company updated successfully', 'success');
            setDrawerOpen(false);
            setEditingCompany(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    // Delete mutation
    const deleteMutation = useMutation({
        mutationFn: companiesApi.delete,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['companies'] });
            showToast('Company deleted successfully', 'success');
            setDeleteTarget(null);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    // Handlers
    const handleCreate = () => {
        setEditingCompany(null);
        setDrawerOpen(true);
    };

    const handleEdit = (company: Company) => {
        setEditingCompany(company);
        setDrawerOpen(true);
    };

    const handleSubmit = (formData: Partial<Company>) => {
        if (editingCompany) {
            updateMutation.mutate({ id: editingCompany.id, data: formData });
        } else {
            createMutation.mutate(formData);
        }
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            deleteMutation.mutate(deleteTarget.id);
        }
    };

    // Table columns
    const columns: Column<Company>[] = useMemo(
        () => [
            { key: 'name', label: 'Company Name', render: (row) => <strong>{row.name}</strong> },
            { key: 'code', label: 'Code' },
            {
                key: 'is_active',
                label: 'Status',
                align: 'center',
                render: (row) => <StatusChip active={row.is_active} />,
            },
            {
                key: 'created_at',
                label: 'Created',
                render: (row) =>
                    row.created_at
                        ? new Date(row.created_at).toLocaleDateString('en-NP')
                        : '—',
            },
        ],
        []
    );

    // Row actions
    const actions: RowAction<Company>[] = useMemo(
        () => [
            {
                icon: <EditIcon fontSize="small" />,
                label: 'Edit',
                onClick: handleEdit,
                visible: () => true, // Backend enforces authorization
            },
            {
                icon: <DeleteIcon fontSize="small" />,
                label: 'Delete',
                color: 'error',
                onClick: (row) => setDeleteTarget(row),
                visible: (row) => row.is_active, // Only allow deleting active companies
            },
        ],
        []
    );

    return (
        <>
            <PageHeader
                title="Companies"
                subtitle="Manage organizational entities"
                breadcrumbs={[
                    { label: 'Manage' },
                    { label: 'Companies' },
                ]}
                actions={
                    <Can permission="companies.create">
                        <Button variant="contained" startIcon={<AddIcon />} onClick={handleCreate}>
                            Add Company
                        </Button>
                    </Can>
                }
            />

            <SearchFilterBar
                searchValue={search}
                onSearchChange={(v) => {
                    setSearch(v);
                    setPage(0);
                }}
                placeholder="Search companies..."
            />

            <DataTable<Company>
                columns={columns}
                data={data?.data ?? []}
                loading={isLoading}
                page={page}
                rowsPerPage={rowsPerPage}
                total={data?.total ?? 0}
                onPageChange={setPage}
                onRowsPerPageChange={(rpp) => {
                    setRowsPerPage(rpp);
                    setPage(0);
                }}
                actions={actions}
                emptyMessage="No companies found. Create your first company to get started."
            />

            {/* Create/Edit Drawer */}
            <CompanyFormDrawer
                open={drawerOpen}
                onClose={() => {
                    setDrawerOpen(false);
                    setEditingCompany(null);
                }}
                company={editingCompany}
                onSubmit={handleSubmit}
                loading={createMutation.isPending || updateMutation.isPending}
            />

            {/* Delete Confirmation */}
            <ConfirmDialog
                open={!!deleteTarget}
                title="Delete Company"
                message={`Are you sure you want to delete "${deleteTarget?.name}"? This action will deactivate the company and cannot be easily undone.`}
                confirmLabel="Delete"
                loading={deleteMutation.isPending}
                onConfirm={handleConfirmDelete}
                onCancel={() => setDeleteTarget(null)}
            />
        </>
    );
};
