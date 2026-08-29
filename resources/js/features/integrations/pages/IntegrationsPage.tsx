import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box, Paper, Table, TableHead, TableRow, TableCell, TableBody,
  Button, Chip, IconButton, Dialog, DialogTitle, DialogContent, DialogActions,
  TextField, MenuItem, Switch, FormControlLabel, CircularProgress, Stack,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import { useNavigate } from 'react-router-dom';
import { integrationApi, type Integration } from '@/api/integrations';
import { PageHeader } from '@/components/layout/PageHeader';

const STATUS_COLORS: Record<string, 'success' | 'warning' | 'error' | 'default' | 'info'> = {
  connected: 'success', degraded: 'warning', failed: 'error', disabled: 'default', pending: 'info', unknown: 'default',
};

const TYPES = ['monitoring', 'aaa', 'network_device', 'access_network', 'dns', 'dhcp', 'logging', 'authentication', 'billing', 'other'];

export const IntegrationsPage = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [form, setForm] = useState({ name: '', provider: '', type: 'monitoring', description: '', enabled: false, endpoint: '' });

  const { data, isLoading } = useQuery({ queryKey: ['integrations'], queryFn: () => integrationApi.list() });
  const createMut = useMutation({ mutationFn: integrationApi.create, onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['integrations'] }); setDialogOpen(false); } });
  const deleteMut = useMutation({ mutationFn: integrationApi.delete, onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integrations'] }) });

  const handleCreate = () => {
    createMut.mutate({
      name: form.name, provider: form.provider, type: form.type, description: form.description || undefined,
      enabled: form.enabled, configuration: form.endpoint ? { endpoint: form.endpoint } : undefined,
    });
  };

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;

  return (
    <Box>
      <PageHeader title="Integrations" subtitle="Manage external system integrations" actions={<Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>Add Integration</Button>} />
      <Paper sx={{ mt: 2 }}>
        <Table>
          <TableHead><TableRow>
            <TableCell>Name</TableCell><TableCell>Provider</TableCell><TableCell>Type</TableCell>
            <TableCell>Status</TableCell><TableCell>Enabled</TableCell><TableCell>Last Health Check</TableCell><TableCell>Actions</TableCell>
          </TableRow></TableHead>
          <TableBody>
            {(data as any)?.data?.map((i: Integration) => (
              <TableRow key={i.id} hover sx={{ cursor: 'pointer' }} onClick={() => navigate(`/system/integrations/${i.id}`)}>
                <TableCell>{i.name}</TableCell>
                <TableCell>{i.provider}</TableCell>
                <TableCell><Chip label={i.type} size="small" variant="outlined" /></TableCell>
                <TableCell><Chip label={i.status} color={STATUS_COLORS[i.status] || 'default'} size="small" /></TableCell>
                <TableCell>{i.enabled ? 'Yes' : 'No'}</TableCell>
                <TableCell>{i.last_health_check_at ? new Date(i.last_health_check_at).toLocaleString() : '—'}</TableCell>
                <TableCell onClick={(e) => e.stopPropagation()}>
                  <Stack direction="row" spacing={0.5}>
                    <IconButton size="small" onClick={() => navigate(`/system/integrations/${i.id}`)}><EditIcon fontSize="small" /></IconButton>
                    <IconButton size="small" color="error" onClick={() => { if (confirm('Delete?')) deleteMut.mutate(i.id); }}><DeleteIcon fontSize="small" /></IconButton>
                  </Stack>
                </TableCell>
              </TableRow>
            ))}
            {(!data || (data as any)?.data?.length === 0) && <TableRow><TableCell colSpan={7} align="center">No integrations configured</TableCell></TableRow>}
          </TableBody>
        </Table>
      </Paper>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Add Integration</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField label="Name" fullWidth required value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} />
            <TextField label="Provider" fullWidth required value={form.provider} onChange={e => setForm({ ...form, provider: e.target.value })} helperText="e.g., librenms, radius, juniper" />
            <TextField select label="Type" fullWidth value={form.type} onChange={e => setForm({ ...form, type: e.target.value })}>
              {TYPES.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
            </TextField>
            <TextField label="Endpoint URL" fullWidth value={form.endpoint} onChange={e => setForm({ ...form, endpoint: e.target.value })} />
            <TextField label="Description" fullWidth multiline rows={2} value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} />
            <FormControlLabel control={<Switch checked={form.enabled} onChange={e => setForm({ ...form, enabled: e.target.checked })} />} label="Enabled" />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={handleCreate} disabled={!form.name || !form.provider || createMut.isPending}>
            {createMut.isPending ? 'Creating...' : 'Create'}
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
