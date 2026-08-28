import React, { useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Grid2 as Grid,
  Typography,
  Button,
  CircularProgress,
  Alert,
  Paper,
  Divider,
} from '@mui/material';
import RefreshIcon from '@mui/icons-material/Refresh';
import { useQuery } from '@tanstack/react-query';
import { systemApi } from '@/api/system';
import { PageHeader } from '@/components/layout/PageHeader';
import { ServiceStatusList } from '../components/ServiceStatus';

const InfoCard: React.FC<{ title: string; children: React.ReactNode }> = ({ title, children }) => (
  <Card>
    <CardContent>
      <Typography variant="subtitle2" color="text.secondary" gutterBottom>
        {title}
      </Typography>
      {children}
    </CardContent>
  </Card>
);

const InfoRow: React.FC<{ label: string; value: React.ReactNode }> = ({ label, value }) => (
  <Box sx={{ display: 'flex', justifyContent: 'space-between', py: 0.5 }}>
    <Typography variant="body2" color="text.secondary">
      {label}
    </Typography>
    <Typography variant="body2" sx={{ fontFamily: 'monospace' }}>
      {value || '—'}
    </Typography>
  </Box>
);

export const SystemInfoPage: React.FC = () => {
  const [refreshing, setRefreshing] = useState(false);

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['systemInfo'],
    queryFn: systemApi.getInfo,
  });

  const handleRefresh = async () => {
    setRefreshing(true);
    await refetch();
    setRefreshing(false);
  };

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: 400 }}>
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="error">Failed to load system information.</Alert>
      </Box>
    );
  }

  if (!data) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="warning">No system information available.</Alert>
      </Box>
    );
  }

  return (
    <Box>
      <PageHeader
        title="System Information"
        subtitle="View application, runtime, and service status"
        actions={
          <Button
            variant="contained"
            startIcon={refreshing ? <CircularProgress size={20} /> : <RefreshIcon />}
            onClick={handleRefresh}
            disabled={refreshing}
          >
            Refresh
          </Button>
        }
      />

      <Grid container spacing={3}>
        {/* Application */}
        <Grid size={{ xs: 12, md: 6 }}>
          <InfoCard title="Application">
            <InfoRow label="Name" value={data.application.name} />
            <InfoRow label="Environment" value={data.application.environment} />
            <InfoRow label="URL" value={data.application.url} />
          </InfoCard>
        </Grid>

        {/* Runtime */}
        <Grid size={{ xs: 12, md: 6 }}>
          <InfoCard title="Runtime">
            <InfoRow label="Laravel" value={data.runtime.laravel} />
            <InfoRow label="PHP" value={data.runtime.php} />
            <InfoRow label="Node" value={data.runtime.node} />
            <InfoRow label="Composer" value={data.runtime.composer} />
          </InfoCard>
        </Grid>

        {/* Container */}
        <Grid size={{ xs: 12, md: 6 }}>
          <InfoCard title="Container Information">
            <Typography variant="caption" color="text.secondary" display="block" sx={{ mb: 1 }}>
              This shows container-level information, not physical host resources.
            </Typography>
            <InfoRow label="Hostname" value={data.container.hostname} />
            <InfoRow label="Memory Limit" value={data.container.memory_limit} />
            <InfoRow label="Max Execution Time" value={data.container.max_execution_time} />
          </InfoCard>
        </Grid>

        {/* Git */}
        <Grid size={{ xs: 12, md: 6 }}>
          <InfoCard title="Git">
            <InfoRow label="Commit" value={data.git.commit} />
            <InfoRow label="Branch" value={data.git.branch} />
            <InfoRow label="Tag" value={data.git.tag} />
          </InfoCard>
        </Grid>

        {/* Services */}
        <Grid size={{ xs: 12 }}>
          <Card>
            <CardContent>
              <Typography variant="subtitle2" color="text.secondary" gutterBottom>
                Services
              </Typography>
              <Divider sx={{ mb: 2 }} />
              <ServiceStatusList services={data.services} />
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
};
