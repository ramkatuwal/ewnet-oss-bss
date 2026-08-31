import { Chip, ChipProps } from '@mui/material';
import { CheckCircle, Warning, Error, PauseCircle, Block } from '@mui/icons-material';
import React from 'react';

interface SiteStatusBadgeProps {
    status: string;
}

export const SiteStatusBadge = ({ status }: SiteStatusBadgeProps) => {
    const getStatusConfig = (s: string): { color: ChipProps['color']; icon?: React.ReactElement; label: string } => {
        switch (s?.toLowerCase()) {
            case 'active':
                return { color: 'success', icon: <CheckCircle fontSize="small" />, label: 'Active' };
            case 'planned':
                return { color: 'info', icon: <Warning fontSize="small" />, label: 'Planned' };
            case 'maintenance':
                return { color: 'warning', icon: <PauseCircle fontSize="small" />, label: 'Maintenance' };
            case 'inactive':
                return { color: 'default', icon: <Block fontSize="small" />, label: 'Inactive' };
            case 'decommissioned':
                return { color: 'error', icon: <Error fontSize="small" />, label: 'Decommissioned' };
            default:
                return { color: 'default', label: s || 'Unknown' };
        }
    };

    const config = getStatusConfig(status);

    return (
        <Chip
            label={config.label}
            color={config.color}
            size="small"
            icon={config.icon}
            sx={{ fontWeight: 500 }}
        />
    );
};
