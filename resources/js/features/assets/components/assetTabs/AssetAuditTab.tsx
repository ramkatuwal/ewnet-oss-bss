import { Box, Chip, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { DataTable, Column } from '@/components/tables/DataTable';
import type { AuditLog } from '@/api/audit';
import { auditApi } from '@/api/audit';
import { Can } from '@/components/auth/Can';

const RESULT_COLORS: Record<string, 'success' | 'error' | 'default'> = {
    success: 'success',
    failure: 'error',
    error: 'error',
};

const columns: Column<AuditLog>[] = [
    { key: 'action', label: 'Action', render: (log) => <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{log.action}</Box> },
    { key: 'result', label: 'Result', render: (log) => <Chip label={log.result} size="small" color={RESULT_COLORS[log.result] ?? 'default'} /> },
    { key: 'actor', label: 'Actor', render: (log) => log.actor?.name ?? '—' },
    { key: 'ip_address', label: 'IP', render: (log) => (log.ip_address ? <Box component="span" sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>{log.ip_address}</Box> : '—') },
    { key: 'created_at', label: 'Timestamp', render: (log) => new Date(log.created_at).toLocaleString() },
];

export const AssetAuditTab = ({ assetId }: { assetId: number }) => {
    const { data, isLoading, isError } = useQuery({
        queryKey: ['security', 'audit-logs', 'asset', assetId],
        queryFn: () => auditApi.getAll({ target_type: 'App\\Models\\Asset', target_id: assetId, per_page: 25 }),
        enabled: !!assetId,
    });

    return (
        <Can permission="system.debug.view" fallback={
            <Typography variant="body2" color="text.secondary">You do not have permission to view audit logs.</Typography>
        }>
            {isError
                ? <Typography variant="body2" color="text.secondary">Audit log data is unavailable.</Typography>
                : (
                    <DataTable<AuditLog>
                        columns={columns}
                        data={data?.data ?? []}
                        loading={isLoading}
                        total={data?.total ?? 0}
                        page={(data?.current_page ?? 1) - 1}
                        rowsPerPage={data?.per_page ?? 10}
                        onPageChange={() => undefined}
                        onRowsPerPageChange={() => undefined}
                        emptyMessage="No audit entries for this asset."
                    />
                )}
        </Can>
    );
};