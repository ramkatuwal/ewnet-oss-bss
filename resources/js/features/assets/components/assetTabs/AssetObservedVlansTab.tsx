import { useState } from 'react';
import { Box, Chip, Typography } from '@mui/material';
import LinkIcon from '@mui/icons-material/Link';
import LinkOffIcon from '@mui/icons-material/LinkOff';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { ObservedVlan } from '@/types';
import { getAssetObservedVlans, reconcileObservedVlan, unreconcileObservedVlan } from '../../api/assets';
import { infrastructureKeys } from '@/api/queryKeys';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';
import { useAuthStore } from '@/stores/authStore';

const columns: Column<ObservedVlan>[] = [
    {
        key: 'vid',
        label: 'VID',
        render: (v) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem', fontWeight: 600 }}>{v.vid}</Box>,
    },
    { key: 'name', label: 'Name', render: (v) => v.name ?? '—' },
    { key: 'vlan_type', label: 'Type', render: (v) => (v.vlan_type ? <Chip label={v.vlan_type} size="small" variant="outlined" /> : '—') },
    {
        key: 'observation_status',
        label: 'Observation',
        render: (v) => {
            const isStale = v.observation_status === 'stale';
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
        key: 'reconciled_vlan',
        label: 'Reconciled',
        render: (v) =>
            v.reconciled_vlan ? (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.75 }}>
                    <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{v.reconciled_vlan.vid}</Box>
                    <Typography variant="caption" color="text.secondary">{v.reconciled_vlan.name ?? ''}</Typography>
                    <AuthorityBadge authoritative />
                </Box>
            ) : (
                <Chip label="Unreconciled" size="small" variant="outlined" color="default" sx={{ fontSize: '0.7rem', height: 22 }} />
            ),
    },
    {
        key: 'provider',
        label: 'Provider',
        render: (v) => (v.provider ? <Chip label={`${v.provider}${v.external_id ? ` #${v.external_id}` : ''}`} size="small" variant="outlined" /> : '—'),
    },
];

export const AssetObservedVlansTab = ({ assetId }: { assetId: number }) => {
    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(10);
    const queryClient = useQueryClient();
    const canReconcile = useAuthStore((state) => state.hasPermission('assets.observations.sync'));
    const reconcile = useMutation({
        mutationFn: ({ observedVlanId, vlanId }: { observedVlanId: number; vlanId: number }) => reconcileObservedVlan(assetId, observedVlanId, vlanId),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: infrastructureKeys.assetObservedVlans(assetId) }),
    });
    const unreconcile = useMutation({
        mutationFn: (observedVlanId: number) => unreconcileObservedVlan(assetId, observedVlanId),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: infrastructureKeys.assetObservedVlans(assetId) }),
    });

    const { data, isLoading, isError } = useQuery({
        queryKey: [...infrastructureKeys.assetObservedVlans(assetId), page, rowsPerPage],
        queryFn: () => getAssetObservedVlans(assetId, { page: page + 1, per_page: rowsPerPage }),
        enabled: !!assetId,
    });

    if (isError) {
        return <Typography variant="body2" color="text.secondary">Observed VLAN data is unavailable.</Typography>;
    }

    return (
        <Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                <Typography variant="body2" color="text.secondary">
                    VLANs observed from providers/discovery. Reconciliation links an observation to an authoritative VLAN when it exists.
                </Typography>
                <AuthorityBadge authoritative={false} />
            </Box>
            <DataTable<ObservedVlan>
                columns={columns}
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
                        label: 'Link to authoritative VLAN',
                        onClick: (vlan) => {
                            const value = window.prompt('Authoritative VLAN ID');
                            if (value && Number.isInteger(Number(value)) && Number(value) > 0) reconcile.mutate({ observedVlanId: vlan.id, vlanId: Number(value) });
                        },
                        visible: (vlan) => !vlan.reconciled_vlan,
                    },
                    {
                        icon: <LinkOffIcon fontSize="small" />,
                        label: 'Remove authoritative VLAN link',
                        onClick: (vlan) => unreconcile.mutate(vlan.id),
                        color: 'error',
                        visible: (vlan) => !!vlan.reconciled_vlan,
                    },
                ] : undefined}
                emptyMessage="No observed VLANs yet. Run observation sync from the integration."
            />
        </Box>
    );
};
