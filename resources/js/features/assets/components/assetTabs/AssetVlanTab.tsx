import { Box, Chip, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { AssetVlanMembership } from '@/types';
import { getAssetVlanMemberships } from '../../api/assets';
import { infrastructureKeys } from '@/api/queryKeys';

const columns: Column<AssetVlanMembership>[] = [
    { key: 'port_key', label: 'Port', render: (m) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{m.port_key ?? '—'}</Box> },
    { key: 'mode', label: 'Mode', render: (m) => (m.mode ? <Chip label={m.mode} size="small" variant="outlined" /> : '—') },
    { key: 'tagging', label: 'Tagging', render: (m) => m.tagging ?? '—' },
    {
        key: 'vlan',
        label: 'VLAN',
        render: (m) => (
            m.vlan
                ? <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                    <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{m.vlan.vid}</Box>
                    <Typography variant="caption" color="text.secondary">{m.vlan.name ?? ''}</Typography>
                    {m.vlan.reserved && <Chip label="Reserved" size="small" color="warning" />}
                </Box>
                : <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{m.vlan_id}</Box>
        ),
    },
];

export const AssetVlanTab = ({ assetId }: { assetId: number }) => {
    const { data, isLoading, isError } = useQuery({
        queryKey: infrastructureKeys.assetVlanMemberships(assetId),
        queryFn: () => getAssetVlanMemberships(assetId, { per_page: 50 }),
        enabled: !!assetId,
    });

    if (isError) {
        return <Typography variant="body2" color="text.secondary">Switching/VLAN data is unavailable.</Typography>;
    }

    const memberships = data?.data ?? [];

    return (
        <Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                <Typography variant="body2" color="text.secondary">Authoritative switch port VLAN membership for this asset.</Typography>
            </Box>
            <DataTable<AssetVlanMembership>
                columns={columns}
                data={memberships}
                loading={isLoading}
                total={data?.total ?? 0}
                page={(data?.current_page ?? 1) - 1}
                rowsPerPage={data?.per_page ?? 10}
                onPageChange={() => undefined}
                onRowsPerPageChange={() => undefined}
                emptyMessage="No switch port VLAN memberships recorded."
            />
        </Box>
    );
};