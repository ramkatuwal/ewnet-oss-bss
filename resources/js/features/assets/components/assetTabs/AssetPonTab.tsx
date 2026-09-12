import { Box, Link, Typography } from '@mui/material';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { PonMembership } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';
import { Link as RouterLink } from 'react-router-dom';

const columns: Column<PonMembership>[] = [
    {
        key: 'pon_domain_id',
        label: 'PON Domain',
        render: (m) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>#{m.pon_domain_id}</Box>,
    },
    {
        key: 'olt_port',
        label: 'OLT Port',
        render: (m) => {
            const port = m.pon_domain?.olt_port?.port_key
                ?? m.pon_domain?.olt_port?.name;
            return port ? <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{port}</Box> : '—';
        },
    },
    {
        key: 'onu',
        label: 'ONU Asset',
        render: (m) => (
            m.onu_asset
                ? <Link component={RouterLink} to={`/network/assets/${m.onu_asset.id}`} underline="hover"><Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{m.onu_asset.asset_tag}</Box></Link>
                : '—'
        ),
    },
    { key: 'onu_id', label: 'ONU ID', render: (m) => (m.onu_id ? <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{m.onu_id}</Box> : '—') },
];

export const AssetPonTab = ({ memberships }: { memberships: PonMembership[] }) => (
    <Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
            <Typography variant="body2" color="text.secondary">PON membership mapping between OLT domains and ONU assets.</Typography>
            <AuthorityBadge authoritative />
        </Box>
        <DataTable<PonMembership>
            columns={columns}
            data={memberships}
            total={memberships.length}
            page={0}
            rowsPerPage={10}
            onPageChange={() => undefined}
            onRowsPerPageChange={() => undefined}
            emptyMessage="No PON memberships for this asset."
        />
    </Box>
);