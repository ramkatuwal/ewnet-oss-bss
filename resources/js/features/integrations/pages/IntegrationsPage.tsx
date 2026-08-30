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
import SyncIcon from '@mui/icons-material/Sync';
import { useNavigate } from 'react-router-dom';
import { integrationApi, type Integration } from '@/api/integrations';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';

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
  
  const createMut = useMutation({ 
    mutationFn: integrationApi.create, 
    onSuccess: () => { 
      queryClient.invalidateQueries({ queryKey: ['integrations'] }); 
      setDialogOpen(false); 
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

  const handleCreate = () => {
    createMut.mutate({
      name: form.name, provider: form.provider, type: form.type, description: form.description || undefined,
      enabled: form.enabled, configuration: form.endpoint ? { endpoint: form.endpoint } : undefined,
    });
  };

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;

  return (
    <Box sx={{ p: 3 }}>
      <PageHeader 
        title="Integrations" 
        subtitle="Manage external system integrations" 
        actions={<Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>Add Integration</Button>} 
      />
      <Paper sx={{ mt: 2 }}>
        <Table>
          <TableHead><TableRow>
            <TableCell>Name</TableCell><TableCell>Provider</TableCell><TableCell>Type</TableCell>
            <TableCell>Status</TableCell><TableCell>Enabled</TableCell><TableCell>Last Sync</TableCell><TableCell>Actions</TableCell>
          </TableRow></TableHead>
          <TableBody>
            {(data as any)?.data?.map((i: Integration) => (
              <TableRow key={i.id}>
                <TableCell>{i.name}</TableCell>
                <TableCell>{i.provider}</TableCell>
                <TableCell>{i.type}</TableCell>
                <TableCell><Chip label={i.status} color={STATUS_COLORS[i.status] || 'default'} size="small" /></TableCell>
                <TableCell><Switch checked={i.enabled} disabled /></TableCell>
                <TableCell>{i.last_sync_at ? new Date(i.last_sync_at).toLocaleString() : 'Never'}</TableCell>
                <TableCell>
                  <Stack direction="row" spacing={1}>
                    <Can permission="integrations.sync">
                      <IconButton size="small" onClick={() => syncMut.mutate(i.id)} disabled={syncMut.isPending}>
                        <SyncIcon />
                      </IconButton>
                    </Can>
                    <IconButton size="small" onClick={() => navigate(`/system/integrations/${i.id}`)}>
                      <EditIcon />
                    </IconButton>
                    <Can permission="integrations.delete">
                      <IconButton size="small" color="error" onClick={() => deleteMut.mutate(i.id)}>
                        <DeleteIcon />
                      </IconButton>
                    </Can>
                  </Stack>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Paper>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)}>
        <DialogTitle>Add Integration</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField label="Name" fullWidth value={form.name} onChange={e => setForm({...form, name: e.target.value})} />
            <TextField select label="Provider" fullWidth value={form.provider} onChange={e => setForm({...form, provider: e.target.value})}>
              <MenuItem value="librenms">LibreNMS</MenuItem>
              <MenuItem value="uisp">UISP</MenuItem>
            </TextField>
            <TextField select label="Type" fullWidth value={form.type} onChange={e => setForm({...form, type: e.target.value})}>
              {TYPES.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
            </TextField>
            <TextField label="Endpoint URL" fullWidth value={form.endpoint} onChange={e => setForm({...form, endpoint: e.target.value})} />
            <FormControlLabel control={<Switch checked={form.enabled} onChange={e => setForm({...form, enabled: e.target.checked})} />} label="Enabled" />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
          <Button onClick={handleCreate} variant="contained" disabled={createMut.isPending}>Create</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
