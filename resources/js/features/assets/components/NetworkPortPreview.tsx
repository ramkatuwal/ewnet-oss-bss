import { useQuery } from '@tanstack/react-query';
import { Alert, Box, Typography } from '@mui/material';
import { DataTable } from '@/components/tables/DataTable';
import { infrastructureKeys } from '@/api/queryKeys';
import { getNetworkPorts, NetworkPort } from '../api/assets';

export const NetworkPortPreview = ({ assetId }: { assetId: number }) => {
    const { data, isLoading, isError } = useQuery({
        queryKey: infrastructureKeys.networkPorts(assetId),
        queryFn: () => getNetworkPorts(assetId, { per_page: 10 }),
    });

    if (isError) {
        return <Alert severity="info">Network port data is unavailable.</Alert>;
    }

    return (
        <Box>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
                Read-only preview of authoritative physical port records.
            </Typography>
            <DataTable<NetworkPort>
                loading={isLoading}
                data={data?.data ?? []}
                total={data?.total ?? 0}
                page={0}
                rowsPerPage={10}
                onPageChange={() => undefined}
                onRowsPerPageChange={() => undefined}
                emptyMessage="No network ports have been recorded."
                columns={[
                    { key: 'port_key', label: 'Port' },
                    { key: 'name', label: 'Name', render: (port) => port.name ?? '—' },
                    { key: 'connector_type', label: 'Connector', render: (port) => port.connector_type ?? '—' },
                    { key: 'technology', label: 'Technology', render: (port) => port.technology ?? '—' },
                ]}
            />
        </Box>
    );
};
