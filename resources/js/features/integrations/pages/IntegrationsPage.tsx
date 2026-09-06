import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box, Paper, Table, TableHead, TableRow, TableCell, TableBody,
  Button, Chip, IconButton, Dialog, DialogTitle, DialogContent, DialogActions,
  TextField, MenuItem, Switch, FormControlLabel, CircularProgress, Stack, Alert, InputAdornment, Typography, Tooltip
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import SyncIcon from '@mui/icons-material/Sync';
import HistoryIcon from '@mui/icons-material/History';
import WifiTetheringIcon from '@mui/icons-material/WifiTethering';
import VisibilityIcon from '@mui/icons-material/Visibility';
import VisibilityOffIcon from '@mui/icons-material/VisibilityOff';
import toast from 'react-hot-toast';
import { useNavigate } from 'react-router-dom';
import { integrationApi, type Integration } from '@/api/integrations';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';

const STATUS_LABELS: Record<string, string> = {
  connected: 'Active', degraded: 'Degraded', failed: 'Failed', disabled: 'Disabled', pending: 'Pending', unknown: 'Unknown',
};

const STATUS_COLORS: Record<string, 'success' | 'warning' | 'error' | 'default' | 'info'> = {
  connected: 'success', degraded: 'warning', failed: 'error', disabled: 'default', pending: 'info', unknown: 'default',
};

const PROVIDERS = [
  { value: 'librenms', label: 'LibreNMS', type: 'monitoring' },
  { value: 'uisp', label: 'Ubiquiti UISP', type: 'monitoring' },
];

export const IntegrationsPage = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [showToken, setShowToken] = useState(false);

  const [form, setForm] = useState({
    name: '',
    provider: '',
    type: 'monitoring',
    description: '',
    enabled: true,
    api_url: '',
    credential_type: 'api_token',
    credential_value: '',
    credential_label: 'Primary Token',
  });

  const { data, isLoading, isError, error } = useQuery({ 
    queryKey: ['integrations'], 
    queryFn: integrationApi.list 
  });

  const createMut = useMutation({
    mutationFn: integrationApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['integrations'] });
      setDialogOpen(false);
      resetForm();
    }
  });

  const deleteMut = useMutation({
    mutationFn: integrationApi.delete,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integrations'] })
  });

  const syncMut = useMutation({
    mutationFn: (id: number) => integrationApi.sync(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integrations'] })
  });

  const testMut = useMutation({
    mutationFn: (id: number) => integrationApi.testConnection(id),
    onSuccess: (data) => {
      toast.success(data.success ? 'Connection test OK' : 'Connection test failed');
      queryClient.invalidateQueries({ queryKey: ['integrations'] });
    },
    onError: () => toast.error('Connection test failed'),
  });

  const resetForm = () => {
    setForm({
      name: '', provider: '', type: 'monitoring', description: '', enabled: true,
      api_url: '', credential_type: 'api_token', credential_value: '', credential_label: 'Primary Token',
    });
  };

  const handleProviderChange = (provider: string) => {
    const prov = PROVIDERS.find(p => p.value === provider);
    setForm(prev => ({ ...prev, provider, type: prov?.type || prev.type }));
  };

  const handleCreate = () => {
    const config: any = {};
    if (form.api_url) config.api_url = form.api_url;

    createMut.mutate({
      name: form.name,
      provider: form.provider,
      type: form.type,
      description: form.description || undefined,
      enabled: form.enabled,
      configuration: Object.keys(config).length > 0 ? config : undefined,
      credential_type: form.credential_value ? form.credential_type : undefined,
      credential_value: form.credential_value || undefined,
      credential_label: form.credential_label,
    });
  };

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
  
  if (isError) return (
    <Box sx={{ p: 3 }}>
      <Alert severity="error">Failed to load integrations: {(error as Error).message}</Alert>
    </Box>
  );

  const isEmpty = !data || data.length === 0;

  return (
    <Box sx={{ p: 3 }}>
      <PageHeader
        title="Integrations"
        subtitle="Manage external system integrations"
        actions={<Button variant="contained" startIcon={<AddIcon />} onClick={() => { resetForm(); setDialogOpen(true); }}>Add Integration</Button>}
      />
      <Paper sx={{ mt: 2 }}>
        {isEmpty ? (
          <Box sx={{ p: 4, textAlign: 'center' }}>
            <Typography variant="h6" color="text.secondary">No integrations found.</Typography>
            <Typography variant="body2" color="text.secondary">Click "Add Integration" to get started.</Typography>
          </Box>
        ) : (
          <Table>
            <TableHead><TableRow>
              <TableCell>Name</TableCell><TableCell>Provider</TableCell><TableCell>Scope</TableCell>
              <TableCell>Health</TableCell><TableCell>Objects</TableCell><TableCell>Enabled</TableCell><TableCell>Last Sync</TableCell><TableCell>Actions</TableCell>
            </TableRow></TableHead>
            <TableBody>
              {data.map((i: Integration) => {
                const providerLabel = PROVIDERS.find(p => p.value === i.provider)?.label || i.provider;
                const health = i.health_status || i.status;
                return (
                  <TableRow key={i.id} hover>
                    <TableCell>{i.name}</TableCell>
                    <TableCell>
                      <Chip label={providerLabel} color={i.provider === 'uisp' ? 'info' : 'secondary'} size="small" variant="outlined" />
                    </TableCell>
                    <TableCell>
                      {i.company_scope === 'company' && i.company
                        ? <Chip label={i.company.name} color="info" size="small" />
                        : <Chip label="Global" color="default" size="small" />}
                    </TableCell>
                    <TableCell><Chip label={STATUS_LABELS[health] || health} color={STATUS_COLORS[health] || 'default'} size="small" /></TableCell>
                    <TableCell>{i.active_objects_count ?? '—'}</TableCell>
                    <TableCell><Switch checked={i.enabled} disabled /></TableCell>
                    <TableCell>{i.last_sync_at ? new Date(i.last_sync_at).toLocaleString() : 'Never'}</TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5}>
                        <Can permission="integrations.test">
                          <Tooltip title="Test Connection">
                            <IconButton size="small" onClick={() => testMut.mutate(i.id)} disabled={testMut.isPending}>
                              <WifiTetheringIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </Can>
                        <Can permission="integrations.sync">
                          <Tooltip title="Run Sync">
                            <IconButton size="small" onClick={() => syncMut.mutate(i.id)} disabled={syncMut.isPending}>
                              <SyncIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </Can>
                        <Tooltip title="Sync History">
                          <IconButton size="small" onClick={() => navigate(`/system/integrations/${i.id}?tab=2`)}>
                            <HistoryIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                        <IconButton size="small" onClick={() => navigate(`/system/integrations/${i.id}`)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                        <Can permission="integrations.delete">
                          <IconButton size="small" color="error" onClick={() => deleteMut.mutate(i.id)}>
                            <DeleteIcon fontSize="small" />
                          </IconButton>
                        </Can>
                      </Stack>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </Paper>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Add Integration</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            {/* Section A: Integration Details */}
            <TextField label="Name" fullWidth required value={form.name} onChange={e => setForm({...form, name: e.target.value})} />

            <TextField select label="Provider" fullWidth required value={form.provider} onChange={e => handleProviderChange(e.target.value)}>
              {PROVIDERS.map(p => <MenuItem key={p.value} value={p.value}>{p.label}</MenuItem>)}
            </TextField>

            {/* Section B: Connection Configuration */}
            {form.provider && (
              <TextField
                label="API URL"
                fullWidth
                required
                value={form.api_url}
                onChange={e => setForm({...form, api_url: e.target.value})}
                placeholder="https://unms.example.com/nms/api/v2.1"
                helperText="The base URL for the provider's API including the version path."
              />
            )}

            {/* Section C: Authentication */}
            {form.provider && (
              <>
                <TextField
                  label="API Token"
                  type={showToken ? "text" : "password"}
                  fullWidth
                  required
                  value={form.credential_value}
                  onChange={e => setForm({...form, credential_value: e.target.value})}
                  InputProps={{
                    endAdornment: (
                      <InputAdornment position="end">
                        <IconButton onClick={() => setShowToken(!showToken)} edge="end">
                          {showToken ? <VisibilityOffIcon /> : <VisibilityIcon />}
                        </IconButton>
                      </InputAdornment>
                    ),
                  }}
                />
                <Alert severity="info" sx={{ fontSize: '0.8rem' }}>
                  Test Connection will be available in the Integration Detail page after saving.
                </Alert>
              </>
            )}

            <FormControlLabel control={<Switch checked={form.enabled} onChange={e => setForm({...form, enabled: e.target.checked})} />} label="Enabled" />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
          <Button onClick={handleCreate} variant="contained" disabled={createMut.isPending || !form.name || !form.provider || !form.api_url || !form.credential_value}>
            {createMut.isPending ? <CircularProgress size={24} /> : 'Save Integration'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
