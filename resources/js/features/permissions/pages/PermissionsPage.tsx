import { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Box, Typography, Chip, Paper, Accordion, AccordionSummary, AccordionDetails,
    Table, TableHead, TableRow, TableCell, TableBody,
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import SecurityIcon from '@mui/icons-material/Security';
import { PageHeader } from '@/components/layout/PageHeader';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { useAuthStore } from '@/stores/authStore';
import { permissionsApi } from '@/api/permissions';
import type { Permission } from '@/types';

export const PermissionsPage = () => {
    const [search, setSearch] = useState('');
    const { isSuperAdmin } = useAuthStore();

    const { data, isLoading } = useQuery({
        queryKey: ['permissions', search],
        queryFn: () => permissionsApi.getAll({ per_page: 100, ...(search ? { search } : {}) }),
    });

    const permissions = data?.data ?? [];

    // Group by domain
    const grouped = useMemo(() => {
        const groups: Record<string, Permission[]> = {};
        permissions.forEach(p => {
            const domain = p.domain || 'other';
            if (!groups[domain]) groups[domain] = [];
            groups[domain].push(p);
        });
        return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
    }, [permissions]);

    const domainLabels: Record<string, string> = {
        companies: 'Companies', regions: 'Regions', branches: 'Branches',
        departments: 'Departments', users: 'Users', roles: 'Roles',
        permissions: 'Permissions', system: 'System', other: 'Other',
    };

    return (
        <>
            <PageHeader title="Permissions" subtitle="System permissions grouped by domain. Read-only for non-Super Admin."
                breadcrumbs={[{ label: 'Manage' }, { label: 'Permissions' }]} />

            <SearchFilterBar searchValue={search} onSearchChange={setSearch} placeholder="Search permissions..." />

            {!isSuperAdmin() && (
                <Paper variant="outlined" sx={{ p: 2, mb: 2, bgcolor: 'action.hover' }}>
                    <Typography variant="body2" color="text.secondary">
                        <SecurityIcon sx={{ fontSize: 16, verticalAlign: 'middle', mr: 0.5 }} />
                        Permission creation, editing, and deletion are restricted to Super Admin.
                    </Typography>
                </Paper>
            )}

            {isLoading ? (
                <Typography color="text.secondary">Loading...</Typography>
            ) : grouped.length === 0 ? (
                <Typography color="text.secondary">No permissions found.</Typography>
            ) : (
                grouped.map(([domain, perms]) => (
                    <Accordion key={domain} defaultExpanded>
                        <AccordionSummary expandIcon={<ExpandMoreIcon />}>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <VpnKeyIcon color="primary" fontSize="small" />
                                <Typography fontWeight={600}>{domainLabels[domain] || domain}</Typography>
                                <Chip label={perms.length} size="small" variant="outlined" />
                            </Box>
                        </AccordionSummary>
                        <AccordionDetails sx={{ pt: 0 }}>
                            <Table size="small">
                                <TableHead><TableRow>
                                    <TableCell>Permission</TableCell>
                                    <TableCell>Action</TableCell>
                                    <TableCell align="center">Roles</TableCell>
                                </TableRow></TableHead>
                                <TableBody>{perms.map(p => (
                                    <TableRow key={p.id}>
                                        <TableCell><Typography variant="body2" fontFamily="monospace">{p.name}</Typography></TableCell>
                                        <TableCell><Typography variant="body2">{p.action}</Typography></TableCell>
                                        <TableCell align="center"><Chip label={p.role_count ?? 0} size="small" variant="outlined" /></TableCell>
                                    </TableRow>
                                ))}</TableBody>
                            </Table>
                        </AccordionDetails>
                    </Accordion>
                ))
            )}
        </>
    );
};
