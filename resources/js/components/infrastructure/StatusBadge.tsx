import { Chip } from '@mui/material';

const colors: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    ACTIVE: 'success', OPERATIONAL: 'success', PLANNED: 'info', SPARE: 'info',
    MAINTENANCE: 'warning', FAULTY: 'error', MISSING: 'error',
    INACTIVE: 'default', DECOMMISSIONED: 'default', RETIRED: 'default', DISPOSED: 'default',
};

export const StatusBadge = ({ status }: { status: string }) => (
    <Chip label={status.replaceAll('_', ' ')} size="small" color={colors[status.toUpperCase()] ?? 'default'} />
);
