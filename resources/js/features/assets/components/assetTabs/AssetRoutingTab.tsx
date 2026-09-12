import { Box, Chip, Stack, Typography } from '@mui/material';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { RoutingInstance, RoutingL3Interface, RoutingStaticRoute } from '@/types';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';

const ifaceColumns: Column<RoutingL3Interface>[] = [
    { key: 'name', label: 'Interface', render: (i) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{i.name ?? '—'}</Box> },
    { key: 'kind', label: 'Kind', render: (i) => (i.kind ? <Chip label={i.kind} size="small" variant="outlined" /> : '—') },
    {
        key: 'addresses',
        label: 'Addresses',
        render: (i) => (
            <Stack direction="row" spacing={1} flexWrap="wrap">
                {(i.addresses ?? []).map((a) => (
                    <Box key={a.id} component="span" sx={{ fontFamily: 'monospace', fontSize: '0.75rem', bgcolor: 'action.hover', px: 0.5, borderRadius: 0.5 }}>
                        {a.address}{a.prefix_length ? `/${a.prefix_length}` : ''}
                    </Box>
                ))}
            </Stack>
        ),
    },
];

const routeColumns: Column<RoutingStaticRoute>[] = [
    { key: 'destination', label: 'Destination', render: (r) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{r.destination}</Box> },
    { key: 'gateway', label: 'Gateway', render: (r) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>{r.gateway}</Box> },
    { key: 'route_type', label: 'Type', render: (r) => (r.route_type ? <Chip label={r.route_type} size="small" variant="outlined" /> : '—') },
];

export const AssetRoutingTab = ({ instances }: { instances: RoutingInstance[] }) => (
    <Box>
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
            <Typography variant="body2" color="text.secondary">Authoritative routing instances, L3 interfaces and static routes for this asset.</Typography>
            <AuthorityBadge authoritative />
        </Box>

        {instances.length === 0 && (
            <Typography variant="body2" color="text.secondary">No routing instances recorded.</Typography>
        )}

        <Stack spacing={3}>
            {instances.map((instance) => (
                <Box key={instance.id}>
                    <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 1 }}>
                        <Typography variant="subtitle2" sx={{ fontFamily: 'monospace' }}>{instance.name}</Typography>
                        <Chip label={instance.kind} size="small" variant="outlined" />
                    </Stack>

                    {(instance.l3_interfaces?.length ?? 0) > 0 && (
                        <DataTable<RoutingL3Interface>
                            columns={ifaceColumns}
                            data={instance.l3_interfaces ?? []}
                            total={instance.l3_interfaces?.length ?? 0}
                            page={0}
                            rowsPerPage={10}
                            onPageChange={() => undefined}
                            onRowsPerPageChange={() => undefined}
                            emptyMessage="No L3 interfaces."
                        />
                    )}

                    {(instance.static_routes?.length ?? 0) > 0 && (
                        <Box sx={{ mt: 1 }}>
                            <Typography variant="caption" color="text.secondary">Static Routes</Typography>
                            <DataTable<RoutingStaticRoute>
                                columns={routeColumns}
                                data={instance.static_routes ?? []}
                                total={instance.static_routes?.length ?? 0}
                                page={0}
                                rowsPerPage={10}
                                onPageChange={() => undefined}
                                onRowsPerPageChange={() => undefined}
                                emptyMessage="No static routes."
                            />
                        </Box>
                    )}
                </Box>
            ))}
        </Stack>
    </Box>
);