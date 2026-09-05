import React from 'react';
import {
  Drawer,
  Box,
  Typography,
  Chip,
  Grid,
  Paper,
  IconButton,
  Divider,
} from '@mui/material';
import { Close } from '@mui/icons-material';

interface Evidence {
  field: string;
  value: string;
  strength: 'strong' | 'medium' | 'weak';
}

interface ConflictDetailDrawerProps {
  open: boolean;
  onClose: () => void;
  source: any;
  existing?: any;
  analysis: {
    decision: string;
    evidence: Evidence[];
    reason?: string;
    destination_id?: number | null;
  };
  type: 'device' | 'site';
}

const getDecisionColor = (decision: string): 'success' | 'warning' | 'error' | 'info' | 'default' => {
  switch (decision) {
    case 'CREATE': return 'success';
    case 'LINK': return 'info';
    case 'UPDATE': return 'info';
    case 'REVIEW': return 'warning';
    case 'CONFLICT': return 'error';
    case 'WARNING': return 'warning';
    default: return 'default';
  }
};

const getStrengthColor = (strength: string): 'success' | 'warning' | 'error' => {
  switch (strength) {
    case 'strong': return 'success';
    case 'medium': return 'warning';
    case 'weak': return 'error';
    default: return 'warning';
  }
};

const ConflictDetailDrawer: React.FC<ConflictDetailDrawerProps> = ({
  open,
  onClose,
  source,
  existing,
  analysis,
  type,
}) => {
  const renderField = (label: string, value: any) => (
    <Box sx={{ mb: 1 }}>
      <Typography variant="caption" color="text.secondary">{label}</Typography>
      <Typography variant="body2">{value || 'N/A'}</Typography>
    </Box>
  );

  const renderSourceFields = () => {
    if (type === 'device') {
      return (
        <>
          {renderField('Name', source.name)}
          {renderField('External ID', source.external_id)}
          {renderField('Hostname', source.hostname)}
          {renderField('IP Address', source.ip_address)}
          {renderField('MAC Address', source.mac_address)}
          {renderField('Serial', source.serial_number)}
          {renderField('Vendor', source.vendor)}
          {renderField('Model', source.model)}
          {renderField('Site', source.site_name)}
        </>
      );
    }
    return (
      <>
        {renderField('Name', source.name)}
        {renderField('External ID', source.external_id)}
        {renderField('Address', source.address)}
        {renderField('Coordinates', source.latitude && source.longitude ? `${source.latitude}, ${source.longitude}` : 'N/A')}
        {renderField('Organization', source.organization)}
        {renderField('Region', source.region)}
        {renderField('Branch', source.branch)}
      </>
    );
  };

  const renderExistingFields = () => {
    if (!existing) return null;
    if (type === 'device') {
      return (
        <>
          {renderField('Asset ID', existing.id)}
          {renderField('Name', existing.name)}
          {renderField('Asset Tag', existing.asset_tag)}
          {renderField('Serial', existing.serial_number)}
          {renderField('Model', existing.model)}
          {renderField('Status', existing.status)}
          {renderField('Site', existing.site_name)}
        </>
      );
    }
    return (
      <>
        {renderField('Site ID', existing.id)}
        {renderField('Name', existing.name)}
        {renderField('Status', existing.status)}
        {renderField('Type', existing.type)}
        {renderField('Organization', existing.organization)}
      </>
    );
  };

  return (
    <Drawer anchor="right" open={open} onClose={onClose} sx={{ '& .MuiDrawer-paper': { width: 500, maxWidth: '90vw' } }}>
      <Box sx={{ p: 3 }}>
        <Box display="flex" justifyContent="space-between" alignItems="center" mb={2}>
          <Typography variant="h6">Conflict Details</Typography>
          <IconButton onClick={onClose}><Close /></IconButton>
        </Box>

        <Box display="flex" gap={1} mb={2}>
          <Chip
            label={`Decision: ${analysis.decision}`}
            color={getDecisionColor(analysis.decision)}
          />
          {analysis.destination_id && (
            <Chip label={`Destination ID: #${analysis.destination_id}`} variant="outlined" size="small" />
          )}
        </Box>

        {analysis.reason && (
          <Box sx={{ mb: 2, p: 2, bgcolor: '#fff3e0', borderRadius: 1 }}>
            <Typography variant="caption" color="warning.main">Reason</Typography>
            <Typography variant="body2">{analysis.reason}</Typography>
          </Box>
        )}

        <Typography variant="subtitle2" gutterBottom>Matched Fields</Typography>
        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mb: 3 }}>
          {analysis.evidence.length > 0 ? (
            analysis.evidence.map((ev, i) => (
              <Chip
                key={i}
                label={`${ev.field}: ${ev.value}`}
                color={getStrengthColor(ev.strength)}
                size="small"
                variant="outlined"
              />
            ))
          ) : (
            <Typography variant="body2" color="text.secondary">No evidence found</Typography>
          )}
        </Box>

        <Divider sx={{ my: 2 }} />

        <Grid container spacing={2}>
          <Grid item xs={6}>
            <Typography variant="subtitle2" color="primary" gutterBottom>Source Record</Typography>
            <Paper variant="outlined" sx={{ p: 2, bgcolor: '#f5f5f5' }}>
              {renderSourceFields()}
            </Paper>
          </Grid>
          <Grid item xs={6}>
            <Typography variant="subtitle2" color="secondary" gutterBottom>Existing Record</Typography>
            <Paper variant="outlined" sx={{ p: 2, bgcolor: '#f5f5f5' }}>
              {existing ? renderExistingFields() : (
                <Typography color="text.secondary">No existing record found</Typography>
              )}
            </Paper>
          </Grid>
        </Grid>

        {existing && analysis.decision === 'UPDATE' && (
          <>
            <Divider sx={{ my: 2 }} />
            <Typography variant="subtitle2" gutterBottom>Field Changes</Typography>
            <Paper variant="outlined" sx={{ p: 2 }}>
              <Grid container spacing={1}>
                <Grid item xs={4}><Typography variant="caption" color="text.secondary">Field</Typography></Grid>
                <Grid item xs={4}><Typography variant="caption" color="text.secondary">Existing</Typography></Grid>
                <Grid item xs={4}><Typography variant="caption" color="text.secondary">Incoming</Typography></Grid>
                <Grid item xs={12}><Divider /></Grid>
                <Grid item xs={12}>
                  <Typography variant="body2" color="text.secondary">Field-level diff will be shown here</Typography>
                </Grid>
              </Grid>
            </Paper>
          </>
        )}
      </Box>
    </Drawer>
  );
};

export default ConflictDetailDrawer;
