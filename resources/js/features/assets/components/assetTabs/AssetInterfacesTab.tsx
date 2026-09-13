import { useState } from 'react';
import { Box, Chip, Typography } from '@mui/material';
import LinkIcon from '@mui/icons-material/Link';
import LinkOffIcon from '@mui/icons-material/LinkOff';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { AssetInterface } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';
import { getAssetInterfaces, reconcileAssetInterface, unreconcileAssetInterface } from '../../api/assets';
import { infrastructureKeys } from '@/api/queryKeys';
import { useAuthStore } from '@/stores/authStore';

const cols: Column<AssetInterface>[] = [
    { key: 'name', label: 'Interface', render: (itf) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{itf.name}</Box> },
    { key: 'type', label: 'Type', render: (itf) => itf.type ?? '—' },
    { key: 'mac_address', label: 'MAC', render: (itf) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{itf.mac_address ?? '—'}</Box> },
    { key: 'speed', label: 'Speed', render: (itf) => (itf.speed ? `${itf.speed}` : '—') },
    { key: 'status', label: 'Status', render: (itf) => (itf.status ? <Chip label={itf.status} size="small" /> : '—') },
    {
        key: 'observation_status',
        label: 'Observation',
        render: (itf) => {
            const isStale = itf.observation_status === 'stale';
            return (
                <Chip
                    label={isStale ? 'stale' : 'observed'}
                    size="small"
                    color={isStale ? 'warning' : 'success'}
                    variant={isStale ? 'outlined' : 'filled'}
                    sx={{ fontSize: '0.7rem', height: 22 }}
                />
            );
        },
    },
    {
        key: 'reconciled_port',
        label: 'Reconciled Port',
        render: (itf) =>
            itf.reconciled_port ? (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                    <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>
                        {itf.reconciled_port.port_key ?? itf.reconciled_port.name}
                    </Box>
                    <AuthorityBadge authoritative />
                </Box>
            ) : (
                <span>—</span>
            ),
    },
    {
        key: 'provider',
        label: 'Provider',
        render: (itf) => (itf.provider ? <Chip label={itf.provider} size="small" variant="outlined" /> : '—'),
    },
    { key: 'ip_count', label: 'IPs', render: (itf) => itf.ip_addresses?.length ?? 0 },
];

export const AssetInterfacesTab = ({ assetId }: { assetId: number }) => {
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const queryClient = useQueryClient();
    const canReconcile = useAuthStore((state) => state.hasPermission('assets.observations.sync'));
    const reconcile = useMutation({
        mutationFn: ({ interfaceId, networkPortId }: { interfaceId: number; networkPortId: number }) => reconcileAssetInterface(assetId, interfaceId, networkPortId),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: infrastructureKeys.assetInterfaces(assetId) }),
    });
    const unreconcile = useMutation({
        mutationFn: (interfaceId: number) => unreconcileAssetInterface(assetId, interfaceId),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: infrastructureKeys.assetInterfaces(assetId) }),
    });

    const { data, isLoading, isError } = useQuery({
        queryKey: [...infrastructureKeys.assetInterfaces(assetId), page, rowsPerPage],
        queryFn: () => getAssetInterfaces(assetId, { page: page + 1, per_page: rowsPerPage }),
        enabled: !!assetId,
    });

    if (isError) {
        return <Typography variant="body2" color="text.secondary">Observed interface data is unavailable.</Typography>;
    }

    return (
        <Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                <Typography variant="body2" color="text.secondary">
                    Observed interfaces from providers/discovery. Read-only. Reconciliation links an observation to an authoritative network port.
                </Typography>
                <AuthorityBadge authoritative={false} />
            </Box>
            <DataTable<AssetInterface>
                columns={cols}
                data={data?.data ?? []}
                loading={isLoading}
                total={data?.total ?? 0}
                page={page}
                rowsPerPage={data?.per_page ?? rowsPerPage}
                onPageChange={setPage}
                onRowsPerPageChange={(perPage) => { setRowsPerPage(perPage); setPage(0); }}
                actions={canReconcile ? [
                    {
                        icon: <LinkIcon fontSize="small" />,
                        label: 'Link to authoritative port',
                        onClick: (itf) => {
                            const value = window.prompt('Authoritative NetworkPort ID');
                            if (value && Number.isInteger(Number(value)) && Number(value) > 0) reconcile.mutate({ interfaceId: itf.id, networkPortId: Number(value) });
                        },
                        visible: (itf) => !itf.reconciled_port,
                    },
                    {
                        icon: <LinkOffIcon fontSize="small" />,
                        label: 'Remove authoritative port link',
                        onClick: (itf) => unreconcile.mutate(itf.id),
                        color: 'error',
                        visible: (itf) => !!itf.reconciled_port,
                    },
                ] : undefined}
                emptyMessage="No observed interfaces."
            />
        </Box>
    );
};
