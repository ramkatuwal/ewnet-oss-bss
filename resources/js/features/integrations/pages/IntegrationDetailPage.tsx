import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box, Typography, Paper, Tabs, Tab, Button, Chip, Stack, Alert, CircularProgress,
  Table, TableHead, TableRow, TableCell, TableBody, Dialog, DialogTitle, DialogContent,
  DialogActions, TextField, MenuItem, IconButton,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import HealthAndSafetyIcon from '@mui/icons-material/HealthAndSafety';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import { integrationApi, type IntegrationSync, type IntegrationCredential } from '@/api/integrations';
import { PageHeader } from '@/components/layout/PageHeader';

const CRED_TYPES = ['api_token', 'username_password', 'ssh_key', 'shared_secret', 'certificate', 'oauth', 'none'];

export const IntegrationDetailPage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [tab, setTab] = useState(0);
  const [credDialogOpen, setCredDialogOpen] = useState(false);
  const [credForm, setCredForm] = useState({ credential_type: 'api_token', label: '', value: '' });

  const integId = Number(id);
  const { data: integData, isLoading } = useQuery({ queryKey: ['integration', integId], queryFn: () => integrationApi.get(integId) });
  const { data: syncsData } = useQuery({ queryKey: ['integration-syncs', integId], queryFn: () => integrationApi.getSyncs(integId) });
  const { data: credsData } = useQuery({ queryKey: ['integration-creds', integId], queryFn: () => integrationApi.getCredentials(integId) });

  const testMut = useMutation({ mutationFn: () => integrationApi.testConnection(integId), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration', integId] }) });
  const healthMut = useMutation({ mutationFn: () => integrationApi.healthCheck(integId), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration', integId] }) });
  const syncMut = useMutation({ mutationFn: () => integrationApi.sync(integId), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration-syncs', integId] }) });
  const credCreateMut = useMutation({ mutationFn: (d: typeof credForm) => integrationApi.createCredential(integId, d), onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['integration-creds', integId] }); setCredDialogOpen(false); } });
  const credDeleteMut = useMutation({ mutationFn: (cid: number) => integrationApi.deleteCredential(integId, cid), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration-creds', integId] }) });

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
  const integ = (integData as any)?.data;
  if (!integ) return <Alert severity="error">Integration not found</Alert>;

  return (
    <Box>
      <PageHeader title={integ.name} subtitle={`${integ.provider} · ${integ.type}`} actions={
        <Stack direction="row" spacing={1}>
          <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/system/integrations')}>Back</Button>
          <Button variant="outlined" startIcon={<HealthAndSafetyIcon />} onClick={() => healthMut.mutate()} disabled={healthMut.isPending}>Health Check</Button>
          <Button variant="outlined" startIcon={<PlayArrowIcon />} onClick={() => testMut.mutate()} disabled={testMut.isPending}>Test Connection</Button>
          <Button variant="contained" startIcon={<PlayArrowIcon />} onClick={() => syncMut.mutate()} disabled={syncMut.isPending}>Sync</Button>
        </Stack>
      } />

      {(testMut.data || healthMut.data) && (
        <Alert severity={(testMut.data?.success || healthMut.data?.status === 'connected') ? 'success' : 'warning'} sx={{ mt: 2 }}>
          {testMut.data ? `Connection test: ${testMut.data.success ? 'OK' : 'Failed'}${testMut.data.response_time_ms ? ` (${testMut.data.response_time_ms}ms)` : ''}` : `Health: ${healthMut.data?.status}`}
        </Alert>
      )}

      <Paper sx={{ mt: 2 }}>
        <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ borderBottom: 1, borderColor: 'divider' }}>
          <Tab label="Overview" /><Tab label="Credentials" /><Tab label="Sync History" />
        </Tabs>

        <Box sx={{ p: 3 }}>
          {tab === 0 && (
            <Stack spacing={2}>
              <Stack direction="row" spacing={4}>
                <Box><Typography variant="subtitle2">Status</Typography><Chip label={integ.status} color={integ.status === 'connected' ? 'success' : integ.status === 'failed' ? 'error' : 'default'} /></Box>
                <Box><Typography variant="subtitle2">Enabled</Typography><Typography>{integ.enabled ? 'Yes' : 'No'}</Typography></Box>
                <Box><Typography variant="subtitle2">Provider</Typography><Typography>{integ.provider}</Typography></Box>
                <Box><Typography variant="subtitle2">Type</Typography><Typography>{integ.type}</Typography></Box>
              </Stack>
              <Box><Typography variant="subtitle2">Description</Typography><Typography>{integ.description || '—'}</Typography></Box>
              <Box><Typography variant="subtitle2">Configuration</Typography><Typography component="pre" sx={{ bgcolor: 'grey.100', p: 1, borderRadius: 1, fontSize: '0.85rem' }}>{JSON.stringify(integ.configuration, null, 2)}</Typography></Box>
              <Stack direction="row" spacing={4}>
                <Box><Typography variant="subtitle2">Last Health Check</Typography><Typography>{integ.last_health_check_at ? new Date(integ.last_health_check_at).toLocaleString() : 'Never'}</Typography></Box>
                <Box><Typography variant="subtitle2">Last Sync</Typography><Typography>{integ.last_sync_at ? new Date(integ.last_sync_at).toLocaleString() : 'Never'}</Typography></Box>
              </Stack>
            </Stack>
          )}

          {tab === 1 && (
            <Box>
              <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
                <Typography variant="h6">Credentials</Typography>
                <Button startIcon={<AddIcon />} onClick={() => setCredDialogOpen(true)}>Add Credential</Button>
              </Stack>
              <Table size="small">
                <TableHead><TableRow><TableCell>Type</TableCell><TableCell>Label</TableCell><TableCell>Masked Value</TableCell><TableCell>Active</TableCell><TableCell>Actions</TableCell></TableRow></TableHead>
                <TableBody>
                  {(credsData as any)?.data?.map((c: IntegrationCredential) => (
                    <TableRow key={c.id}>
                      <TableCell>{c.credential_type}</TableCell>
                      <TableCell>{c.label || '—'}</TableCell>
                      <TableCell><code>{c.masked_hint || '********'}</code></TableCell>
                      <TableCell>{c.is_active ? 'Yes' : 'No'}</TableCell>
                      <TableCell><IconButton size="small" color="error" onClick={() => { if (confirm('Delete credential?')) credDeleteMut.mutate(c.id); }}><DeleteIcon fontSize="small" /></IconButton></TableCell>
                    </TableRow>
                  ))}
                  {(!credsData || (credsData as any)?.data?.length === 0) && <TableRow><TableCell colSpan={5} align="center">No credentials configured</TableCell></TableRow>}
                </TableBody>
              </Table>
            </Box>
          )}

          {tab === 2 && (
            <Table size="small">
              <TableHead><TableRow><TableCell>Operation</TableCell><TableCell>Status</TableCell><TableCell>Started</TableCell><TableCell>Finished</TableCell><TableCell>Processed</TableCell><TableCell>Created</TableCell><TableCell>Updated</TableCell><TableCell>Failed</TableCell><TableCell>Error</TableCell></TableRow></TableHead>
              <TableBody>
                {(syncsData as any)?.data?.map((s: IntegrationSync) => (
                  <TableRow key={s.id}>
                    <TableCell>{s.operation}</TableCell>
                    <TableCell><Chip label={s.status} size="small" color={s.status === 'completed' ? 'success' : s.status === 'failed' ? 'error' : 'default'} /></TableCell>
                    <TableCell>{s.started_at ? new Date(s.started_at).toLocaleString() : '—'}</TableCell>
                    <TableCell>{s.finished_at ? new Date(s.finished_at).toLocaleString() : '—'}</TableCell>
                    <TableCell>{s.records_processed}</TableCell>
                    <TableCell>{s.records_created}</TableCell>
                    <TableCell>{s.records_updated}</TableCell>
                    <TableCell>{s.records_failed}</TableCell>
                    <TableCell sx={{ maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.error_summary || '—'}</TableCell>
                  </TableRow>
                ))}
                {(!syncsData || (syncsData as any)?.data?.length === 0) && <TableRow><TableCell colSpan={9} align="center">No sync history</TableCell></TableRow>}
              </TableBody>
            </Table>
          )}
        </Box>
      </Paper>

      <Dialog open={credDialogOpen} onClose={() => setCredDialogOpen(false)} maxWidth="sm" fullWidth>
        <DialogTitle>Add Credential</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField select label="Type" fullWidth value={credForm.credential_type} onChange={e => setCredForm({ ...credForm, credential_type: e.target.value })}>
              {CRED_TYPES.map(t => <MenuItem key={t} value={t}>{t}</MenuItem>)}
            </TextField>
            <TextField label="Label" fullWidth value={credForm.label} onChange={e => setCredForm({ ...credForm, label: e.target.value })} />
            <TextField label="Secret Value" fullWidth type="password" required value={credForm.value} onChange={e => setCredForm({ ...credForm, value: e.target.value })} helperText="Will be encrypted at rest. Never returned in plaintext." />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCredDialogOpen(false)}>Cancel</Button>
          <Button variant="contained" onClick={() => credCreateMut.mutate(credForm)} disabled={!credForm.value || credCreateMut.isPending}>Save</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
