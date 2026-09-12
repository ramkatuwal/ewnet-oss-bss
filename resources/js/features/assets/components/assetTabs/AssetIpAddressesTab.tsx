import { Box, Chip, Typography } from '@mui/material';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { IpAddress } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';

const columns: Column<IpAddress>[] = [
    {
        key: 'ip_address',
        label: 'Address',
        render: (ip) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{ip.ip_address}{ip.prefix_length ? `/${ip.prefix_length}` : ''}</Box>,
    },
    { key: 'family', label: 'Family', render: (ip) => ip.family ?? '—' },
    {
        key: 'is_primary',
        label: 'Role',
        render: (ip) => (
            <Box sx={{ display: 'flex', gap: 0.5 }}>
                {ip.is_primary && <Chip label="Primary" size="small" color="primary" />}
                {ip.is_management && <Chip label="Mgmt" size="small" />}
            </Box>
        ),
    },
    { key: 'provider', label: 'Provider', render: (ip) => (ip.provider ? <Chip label={ip.provider} size="small" variant="outlined" /> : '—') },
    { key: 'interface', label: 'Interface', render: (ip) => (ip.interface ? <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{ip.interface.name}</Box> : '—') },
    { key: 'last_seen_at', label: 'Last Seen', render: (ip) => (ip.last_seen_at ? new Date(ip.last_seen_at).toLocaleString() : '—') },
];

export const AssetIpAddressesTab = ({ addresses }: { addresses: IpAddress[] }) => (
    <Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
            <Typography variant="body2" color="text.secondary">Observed IP addresses. Read-only. Confirm against authoritative assignments before use.</Typography>
            <AuthorityBadge authoritative={false} />
        </Box>
        <DataTable<IpAddress>
            columns={columns}
            data={addresses}
            total={addresses.length}
            page={0}
            rowsPerPage={10}
            onPageChange={() => undefined}
            onRowsPerPageChange={() => undefined}
            emptyMessage="No observed IP addresses."
        />
    </Box>
);