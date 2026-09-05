import React from 'react';
import { Paper, Typography, Box, Chip, Button, Grid } from '@mui/material';
import { CheckCircle, Error, Help, Refresh } from '@mui/icons-material';

interface Integration {
  id: number;
  name: string;
  provider: string;
  status: string;
  enabled: boolean;
}

interface ImportSourceCardProps {
  title: string;
  source: string;
  integration: Integration | null;
  integrations: Integration[];
  onIntegrationSelect: (id: number) => void;
  onRefresh: () => void;
  isLoading?: boolean;
  isRefreshing?: boolean;
  connectionStatus?: 'connected' | 'disconnected' | 'unknown' | 'failed';
  recordsCount?: number;
  recordsLabel?: string;
}

const ImportSourceCard: React.FC<ImportSourceCardProps> = ({
  title,
  source,
  integration,
  integrations,
  onIntegrationSelect,
  onRefresh,
  isLoading,
  isRefreshing,
  connectionStatus = 'unknown',
  recordsCount,
  recordsLabel = 'records',
}) => {
  const getStatusIcon = () => {
    switch (connectionStatus) {
      case 'connected':
        return <CheckCircle sx={{ color: '#2e7d32', fontSize: 16 }} />;
      case 'disconnected':
      case 'failed':
        return <Error sx={{ color: '#d32f2f', fontSize: 16 }} />;
      default:
        return <Help sx={{ color: '#ed6c02', fontSize: 16 }} />;
    }
  };

  const getStatusLabel = () => {
    switch (connectionStatus) {
      case 'connected': return 'Connected';
      case 'disconnected': return 'Disconnected';
      case 'failed': return 'Failed';
      default: return 'Unknown';
    }
  };

  const getStatusColor = () => {
    switch (connectionStatus) {
      case 'connected': return 'success';
      case 'disconnected': return 'error';
      case 'failed': return 'error';
      default: return 'warning';
    }
  };

  return (
    <Paper sx={{ p: 3, mb: 3 }}>
      <Grid container alignItems="center" spacing={2}>
        <Grid item xs={12} md={3}>
          <Typography variant="h6">{title}</Typography>
          <Typography variant="body2" color="text.secondary">
            Source: <strong>{source.toUpperCase()}</strong>
          </Typography>
        </Grid>

        <Grid item xs={12} md={4}>
          <Box>
            <Typography variant="caption" color="text.secondary">Integration</Typography>
            <select
              className="form-select"
              style={{ width: '100%', padding: '8px 12px', borderRadius: '4px', border: '1px solid #ccc' }}
              value={integration?.id || ''}
              onChange={(e) => onIntegrationSelect(Number(e.target.value))}
              disabled={isLoading}
            >
              <option value="">Select Integration...</option>
              {integrations.map((int) => (
                <option key={int.id} value={int.id}>
                  {int.name} ({int.provider})
                </option>
              ))}
            </select>
          </Box>
        </Grid>

        <Grid item xs={12} md={3}>
          <Box display="flex" alignItems="center" gap={2}>
            <Typography variant="caption" color="text.secondary">Status</Typography>
            <Chip
              icon={getStatusIcon()}
              label={getStatusLabel()}
              color={getStatusColor()}
              size="small"
            />
            {recordsCount !== undefined && (
              <Chip
                label={`${recordsCount} ${recordsLabel}`}
                color="primary"
                size="small"
                variant="outlined"
              />
            )}
          </Box>
        </Grid>

        <Grid item xs={12} md={2}>
          <Button
            variant="outlined"
            startIcon={<Refresh />}
            onClick={onRefresh}
            disabled={isRefreshing || isLoading}
            fullWidth
          >
            {isRefreshing ? 'Loading...' : 'Refresh'}
          </Button>
        </Grid>
      </Grid>
    </Paper>
  );
};

export default ImportSourceCard;
