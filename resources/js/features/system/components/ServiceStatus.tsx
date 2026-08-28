import React from 'react';
import { Box, Chip, Typography, Grid2 as Grid } from '@mui/material';

interface ServiceStatusProps {
  name: string;
  status: 'healthy' | 'unhealthy' | 'unknown' | 'running' | 'stopped';
}

const statusMap = {
  healthy: { label: 'Healthy', color: 'success' as const },
  running: { label: 'Running', color: 'success' as const },
  unhealthy: { label: 'Unhealthy', color: 'error' as const },
  stopped: { label: 'Stopped', color: 'error' as const },
  unknown: { label: 'Unknown', color: 'default' as const },
};

export const ServiceStatus: React.FC<ServiceStatusProps> = ({ name, status }) => {
  const statusInfo = statusMap[status] || statusMap.unknown;

  return (
    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
      <Typography variant="body2" sx={{ minWidth: 120 }}>
        {name}
      </Typography>
      <Chip
        label={statusInfo.label}
        color={statusInfo.color}
        size="small"
        sx={{ fontWeight: 500 }}
      />
    </Box>
  );
};

export const ServiceStatusList: React.FC<{
  services: Record<string, { status: string }>;
}> = ({ services }) => {
  const serviceNames: Record<string, string> = {
    postgresql: 'PostgreSQL',
    redis: 'Redis',
    horizon: 'Horizon',
    nginx: 'Nginx',
  };

  return (
    <Grid container spacing={1}>
      {Object.entries(services).map(([key, value]) => (
        <Grid size={{ xs: 12, sm: 6 }} key={key}>
          <ServiceStatus
            name={serviceNames[key] || key}
            status={value.status as 'healthy' | 'unhealthy' | 'unknown' | 'running' | 'stopped'}
          />
        </Grid>
      ))}
    </Grid>
  );
};
