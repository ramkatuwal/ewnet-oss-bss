import { Box, Chip, Stack, Typography } from '@mui/material';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { PassiveOpticalPort, SplitterProfile } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';

const columns: Column<PassiveOpticalPort>[] = [
    { key: 'port_number', label: 'Port', render: (p) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{p.port_number ?? '—'}</Box> },
    { key: 'connector_type', label: 'Connector', render: (p) => p.connector_type ?? '—' },
    { key: 'port_role', label: 'Role', render: (p) => (p.port_role ? <Chip label={p.port_role} size="small" variant="outlined" /> : '—') },
    { key: 'network_connection_point_id', label: 'Connection Point', render: (p) => (p.network_connection_point_id ? <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>#{p.network_connection_point_id}</Box> : '—') },
];

export const AssetFimTab = ({ passivePorts, splitter }: { passivePorts: PassiveOpticalPort[]; splitter: SplitterProfile | null }) => {
    const splitRatio = splitter?.split_ratio;
    return (
        <Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                <Typography variant="body2" color="text.secondary">FIM passive infrastructure for this asset.</Typography>
                <AuthorityBadge authoritative />
            </Box>

            {splitter && (
                <Box sx={{ mb: 2, p: 1.5, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}>
                    <Stack direction="row" spacing={3} alignItems="center">
                        <Typography variant="body2" fontWeight="600">Splitter Profile</Typography>
                        <Typography variant="body2">
                            {splitter.input_port_count}×{splitter.output_port_count}
                            {splitRatio ? ` · ${splitRatio}` : ''}
                        </Typography>
                        <Chip label="1:N passive" size="small" variant="outlined" />
                    </Stack>
                </Box>
            )}

            <DataTable<PassiveOpticalPort>
                columns={columns}
                data={passivePorts}
                total={passivePorts.length}
                page={0}
                rowsPerPage={10}
                onPageChange={() => undefined}
                onRowsPerPageChange={() => undefined}
                emptyMessage={splitter ? 'No passive ports recorded on this splitter.' : 'No FIM passive ports or splitter profile for this asset.'}
            />
        </Box>
    );
};