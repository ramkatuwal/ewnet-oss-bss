import { Box, Chip, Typography } from '@mui/material';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { AssetInterface } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';

const columns: Column<AssetInterface>[] = [
    { key: 'name', label: 'Interface', render: (itf) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{itf.name}</Box> },
    { key: 'type', label: 'Type', render: (itf) => itf.type ?? '—' },
    { key: 'mac_address', label: 'MAC', render: (iface) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{iface.mac_address ?? '—'}</Box> },
    { key: 'speed', label: 'Speed', render: (itf) => (itf.speed ? `${itf.speed}` : '—') },
    { key: 'status', label: 'Status', render: (itf) => (itf.status ? <Chip label={itf.status} size="small" /> : '—') },
    { key: 'is_management', label: 'Management', render: (itf) => (itf.is_management ? 'Yes' : 'No') },
    {
        key: 'provider',
        label: 'Provider',
        render: (itf) => (itf.provider ? <Chip label={itf.provider} size="small" variant="outlined" /> : '—'),
    },
    { key: 'ip_count', label: 'IPs', render: (itf) => itf.ip_addresses?.length ?? 0 },
];

export const AssetInterfacesTab = ({ interfaces }: { interfaces: AssetInterface[] }) => (
    <Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
            <Typography variant="body2" color="text.secondary">Observed interfaces from providers/discovery. Read-only.</Typography>
            <AuthorityBadge authoritative={false} />
        </Box>
        <DataTable<AssetInterface>
            columns={columns}
            data={interfaces}
            total={interfaces.length}
            page={0}
            rowsPerPage={10}
            onPageChange={() => undefined}
            onRowsPerPageChange={() => undefined}
            emptyMessage="No observed interfaces."
        />
    </Box>
);