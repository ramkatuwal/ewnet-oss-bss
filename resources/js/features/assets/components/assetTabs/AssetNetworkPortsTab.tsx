import { Box, Typography } from '@mui/material';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { NetworkPort } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';

const columns: Column<NetworkPort>[] = [
    { key: 'port_key', label: 'Port' },
    { key: 'name', label: 'Name', render: (port) => port.name ?? '—' },
    { key: 'connector_type', label: 'Connector', render: (port) => port.connector_type ?? '—' },
    { key: 'technology', label: 'Technology', render: (port) => port.technology ?? '—' },
    {
        key: 'pon_domain',
        label: 'PON Domain',
        render: (port) => (port.pon_domain ? <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>#{port.pon_domain.id} · {port.pon_domain.memberships?.length ?? 0} ONU</Box> : '—'),
    },
];

export const AssetNetworkPortsTab = ({ ports }: { ports: NetworkPort[] }) => (
    <Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
            <Typography variant="body2" color="text.secondary">Authoritative physical port records for this asset.</Typography>
            <AuthorityBadge authoritative />
        </Box>
        <DataTable<NetworkPort>
            columns={columns}
            data={ports}
            total={ports.length}
            page={0}
            rowsPerPage={10}
            onPageChange={() => undefined}
            onRowsPerPageChange={() => undefined}
            emptyMessage="No network ports have been recorded."
        />
    </Box>
);