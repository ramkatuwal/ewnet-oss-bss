import { useState } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box, Typography, Paper, Tabs, Tab, Button, Chip, Stack, Alert, CircularProgress,
  Table, TableHead, TableRow, TableCell, TableBody, Dialog, DialogTitle, DialogContent,
  DialogActions, TextField, MenuItem, IconButton, Pagination, Grid,
} from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import HealthAndSafetyIcon from '@mui/icons-material/HealthAndSafety';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/Add';
import AutorenewIcon from '@mui/icons-material/Autorenew';
import toast from 'react-hot-toast';
import { integrationApi, type Integration, type IntegrationCredential, type IntegrationSync, type IntegrationStats, type AuditLogEntry } from '@/api/integrations';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';

const CRED_TYPES = ['api_token', 'username_password', 'ssh_key', 'shared_secret', 'certificate', 'oauth', 'none'];

const STAT_COLORS: Record<string, 'success' | 'warning' | 'error' | 'default' | 'info'> = {
  connected: 'success', degraded: 'warning', failed: 'error', disabled: 'default', pending: 'info', unknown: 'default',
};

const formatDuration = (seconds?: number | null): string => {
  if (seconds === null || seconds === undefined) return '—';
  if (seconds < 60) return `${seconds}s`;
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return m > 0 ? `${m}m ${s}s` : `${s}s`;
};

const MetricsCard = ({ label, value, hint }: { label: string; value: React.ReactNode; hint?: string }) => (
  <Paper sx={{ p: 2, minWidth: 140 }}>
    <Typography variant="caption" color="text.secondary">{label}</Typography>
    <Typography variant="h6" sx={{ mt: 0.5 }}>{value}</Typography>
    {hint && <Typography variant="caption" color="text.secondary">{hint}</Typography>}
  </Paper>
);

export const IntegrationDetailPage = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const integId = Number(id);

  const initialTab = Number(searchParams.get('tab') || 0);
  const [tab, setTab] = useState<number>(Number.isFinite(initialTab) ? initialTab : 0);
  const [credDialogOpen, setCredDialogOpen] = useState(false);
  const [credForm, setCredForm] = useState({ credential_type: 'api_token', label: '', value: '' });
  const [rotateCred, setRotateCred] = useState<IntegrationCredential | null>(null);
  const [rotateValue, setRotateValue] = useState('');
  const [syncPage, setSyncPage] = useState(1);
  const [auditPage, setAuditPage] = useState(1);

  const { data: integ, isLoading, isError, error } = useQuery<Integration>({
    queryKey: ['integration', integId],
    queryFn: () => integrationApi.get(integId),
  });

  const { data: stats } = useQuery<IntegrationStats>({
    queryKey: ['integration-stats', integId],
    queryFn: () => integrationApi.stats(integId),
  });

  const { data: syncsData } = useQuery({
    queryKey: ['integration-syncs', integId, syncPage],
    queryFn: () => integrationApi.getSyncs(integId, { page: syncPage, per_page: 10 }),
  });

  const { data: credsData } = useQuery({ queryKey: ['integration-creds', integId], queryFn: () => integrationApi.getCredentials(integId) });

  const { data: auditData } = useQuery({
    queryKey: ['integration-audit', integId, auditPage],
    queryFn: () => integrationApi.getAuditLogs(integId, { page: auditPage, per_page: 10 }),
  });

  const testMut = useMutation({ mutationFn: () => integrationApi.testConnection(integId), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration', integId] }) });
  const healthMut = useMutation({ mutationFn: () => integrationApi.healthCheck(integId), onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration', integId] }) });
  const syncMut = useMutation({ mutationFn: () => integrationApi.sync(integId), onSuccess: () => { toast.success('Sync started'); queryClient.invalidateQueries({ queryKey: ['integration-syncs', integId] }); } });
  const credCreateMut = useMutation({
    mutationFn: () => integrationApi.createCredential(integId, credForm),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['integration-creds', integId] }); setCredDialogOpen(false); },
  });
  const credDeleteMut = useMutation({
    mutationFn: (cid: number) => integrationApi.deleteCredential(integId, cid),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['integration-creds', integId] }),
  });
  const rotateMut = useMutation({
    mutationFn: () => { if (!rotateCred) throw new Error('No credential selected'); return integrationApi.rotateCredential(integId, rotateCred.id, rotateValue); },
    onSuccess: () => {
      toast.success('Credential rotated');
      setRotateCred(null);
      setRotateValue('');
      queryClient.invalidateQueries({ queryKey: ['integration-creds', integId] });
      queryClient.invalidateQueries({ queryKey: ['integration-audit', integId] });
    },
    onError: () => toast.error('Rotation failed'),
  });

  if (isLoading) return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;

  if (isError) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="error">
          Failed to load integration: {(error as Error).message || 'Integration not found'}
        </Alert>
        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/system/integrations')} sx={{ mt: 2 }}>
          Back to Integrations
        </Button>
      </Box>
    );
  }

  if (!integ) return <Alert severity="warning">No integration data available.</Alert>;

  const health = integ.health_status || integ.status;

  return (
    <Box>
      <PageHeader title={integ.name} subtitle={`${integ.provider} · ${integ.type}${integ.company_scope === 'company' && integ.company ? ` · ${integ.company.name}` : ' · Global'}`} actions={
        <Stack direction="row" spacing={1}>
          <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/system/integrations')}>Back</Button>
          <Can permission="integrations.test">
            <Button variant="outlined" startIcon={<HealthAndSafetyIcon />} onClick={() => healthMut.mutate()} disabled={healthMut.isPending}>Health Check</Button>
            <Button variant="outlined" startIcon={<PlayArrowIcon />} onClick={() => testMut.mutate()} disabled={testMut.isPending}>Test Connection</Button>
          </Can>
          <Can permission="integrations.sync">
            <Button variant="contained" startIcon={<PlayArrowIcon />} onClick={() => syncMut.mutate()} disabled={syncMut.isPending}>Sync</Button>
          </Can>
        </Stack>
      } />

      {(testMut.data || healthMut.data) && (
        <Alert severity={(testMut.data?.success || healthMut.data?.status === 'connected') ? 'success' : 'warning'} sx={{ mt: 2 }}>
          {testMut.data ? `Connection test: ${testMut.data.success ? 'OK' : 'Failed'}${testMut.data.response_time_ms ? ` (${testMut.data.response_time_ms}ms)` : ''}` : `Health: ${healthMut.data?.status}`}
        </Alert>
      )}

      <Grid container spacing={2} sx={{ mt: 0 }}>
        <Grid item><MetricsCard label="Health" value={STAT_COLORS[health] ? <Chip label={health} color={STAT_COLORS[health]} size="small" /> : health} hint={`Last check: ${integ.last_health_check_at ? new Date(integ.last_health_check_at).toLocaleString() : 'Never'}`} /></Grid>
        <Grid item><MetricsCard label="Imported Objects" value={stats?.total_objects ?? integ.active_objects_count ?? '—'} /></Grid>
        <Grid item><MetricsCard label="Syncs" value={stats?.syncs_total ?? '—'} hint={stats ? `${stats.syncs_completed} ok · ${stats.syncs_failed} failed` : undefined} /></Grid>
        <Grid item><MetricsCard label="Success Rate" value={stats?.success_rate !== null && stats?.success_rate !== undefined ? `${stats.success_rate}%` : '—'} /></Grid>
        <Grid item><MetricsCard label="Last Sync Duration" value={formatDuration(stats?.last_sync_duration_seconds)} hint={stats?.last_sync_status ? `Status: ${stats.last_sync_status}` : undefined} /></Grid>
        {stats?.last_error_summary ? (
          <Grid item xs={12}><Alert severity="error" sx={{ fontSize: '0.85rem' }}>Last sync error: {stats.last_error_summary}</Alert></Grid>
        ) : null}
      </Grid>

      <Paper sx={{ mt: 2 }}>
        <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ borderBottom: 1, borderColor: 'divider' }}>
          <Tab label="Overview" />
          <Tab label="Credentials" />
          <Tab label="Sync History" />
          <Tab label="Config & Metadata" />
        </Tabs>

        <Box sx={{ p: 3 }}>
          {tab === 0 && (
            <Stack spacing={2}>
              <Stack direction="row" spacing={4}>
                <Box><Typography variant="subtitle2">Status</Typography><Chip label={health} color={STAT_COLORS[health] || 'default'} /></Box>
                <Box><Typography variant="subtitle2">Enabled</Typography><Typography>{integ.enabled ? 'Yes' : 'No'}</Typography></Box>
                <Box><Typography variant="subtitle2">Provider</Typography><Typography>{integ.provider}</Typography></Box>
                <Box><Typography variant="subtitle2">Type</Typography><Typography>{integ.type}</Typography></Box>
                <Box><Typography variant="subtitle2">Scope</Typography><Typography>{integ.company_scope === 'company' ? integ.company?.name || 'Company' : 'Global'}</Typography></Box>
              </Stack>
              <Box><Typography variant="subtitle2">Description</Typography><Typography>{integ.description || '—'}</Typography></Box>
              <Box><Typography variant="subtitle2">Configuration</Typography><Typography component="pre" sx={{ bgcolor: 'grey.100', p: 1.5, borderRadius: 1, fontSize: '0.85rem' }}>{JSON.stringify(integ.configuration, null, 2)}</Typography></Box>
              <Stack direction="row" spacing={4}>
                <Box><Typography variant="subtitle2">Last Health Check</Typography><Typography>{integ.last_health_check_at ? new Date(integ.last_health_check_at).toLocaleString() : 'Never'}</Typography></Box>
                <Box><Typography variant="subtitle2">Last Sync</Typography><Typography>{integ.last_sync_at ? new Date(integ.last_sync_at).toLocaleString() : 'Never'}</Typography></Box>
                <Box><Typography variant="subtitle2">Created</Typography><Typography>{integ.created_at ? new Date(integ.created_at).toLocaleString() : '—'}</Typography></Box>
                <Box><Typography variant="subtitle2">Updated By</Typography><Typography>{integ.updated_by || '—'}</Typography></Box>
              </Stack>
            </Stack>
          )}

          {tab === 1 && (
            <Box>
              <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
                <Typography variant="h6">Credentials</Typography>
                <Can permission="integrations.credentials.manage">
                  <Button startIcon={<AddIcon />} onClick={() => setCredDialogOpen(true)}>Add Credential</Button>
                </Can>
              </Stack>
              <Table size="small">
                <TableHead><TableRow><TableCell>Type</TableCell><TableCell>Label</TableCell><TableCell>Masked Value</TableCell><TableCell>Created</TableCell><TableCell>Active</TableCell><TableCell>Actions</TableCell></TableRow></TableHead>
                <TableBody>
                  {credsData?.map((c: IntegrationCredential) => (
                    <TableRow key={c.id}>
                      <TableCell>{c.credential_type}</TableCell>
                      <TableCell>{c.label || '—'}</TableCell>
                      <TableCell><code>{c.masked_hint || '********'}</code></TableCell>
                      <TableCell>{c.created_at ? new Date(c.created_at).toLocaleString() : '—'}</TableCell>
                      <TableCell>{c.is_active ? 'Yes' : 'No'}</TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={0.5}>
                          <Can permission="integrations.credentials.manage">
                            <IconButton size="small" title="Rotate credential" onClick={() => { setRotateCred(c); setRotateValue(''); }}>
                              <AutorenewIcon fontSize="small" />
                            </IconButton>
                          </Can>
                          <Can permission="integrations.credentials.manage">
                            <IconButton size="small" color="error" onClick={() => { if (confirm('Delete credential?')) credDeleteMut.mutate(c.id); }}>
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Can>
                        </Stack>
                      </TableCell>
                    </TableRow>
                  ))}
                  {(!credsData || credsData.length === 0) && <TableRow><TableCell colSpan={6} align="center">No credentials configured</TableCell></TableRow>}
                </TableBody>
              </Table>
            </Box>
          )}

          {tab === 2 && (
            <>
              <Table size="small">
                <TableHead><TableRow><TableCell>Operation</TableCell><TableCell>Status</TableCell><TableCell>Started</TableCell><TableCell>Finished</TableCell><TableCell>Duration</TableCell><TableCell>Processed</TableCell><TableCell>Created</TableCell><TableCell>Updated</TableCell><TableCell>Skipped</TableCell><TableCell>Failed</TableCell><TableCell>Error</TableCell></TableRow></TableHead>
                <TableBody>
                  {syncsData?.data?.map((s: IntegrationSync) => (
                    <TableRow key={s.id}>
                      <TableCell>{s.operation}</TableCell>
                      <TableCell><Chip label={s.status} size="small" color={s.status === 'completed' ? 'success' : s.status === 'failed' ? 'error' : 'default'} /></TableCell>
                      <TableCell>{s.started_at ? new Date(s.started_at).toLocaleString() : '—'}</TableCell>
                      <TableCell>{s.finished_at ? new Date(s.finished_at).toLocaleString() : '—'}</TableCell>
                      <TableCell>{formatDuration(s.duration_seconds)}</TableCell>
                      <TableCell>{s.records_processed}</TableCell>
                      <TableCell>{s.records_created}</TableCell>
                      <TableCell>{s.records_updated}</TableCell>
                      <TableCell>{s.records_skipped ?? '—'}</TableCell>
                      <TableCell>{s.records_failed}</TableCell>
                      <TableCell sx={{ maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.error_summary || '—'}</TableCell>
                    </TableRow>
                  ))}
                  {(!syncsData || !syncsData.data || syncsData.data.length === 0) && <TableRow><TableCell colSpan={11} align="center">No sync history</TableCell></TableRow>}
                </TableBody>
              </Table>
              {syncsData && (syncsData.last_page ?? 1) > 1 && (
                <Stack direction="row" justifyContent="center" sx={{ mt: 2 }}>
                  <Pagination count={syncsData.last_page ?? 1} page={syncsData.current_page ?? 1} onChange={(_, v) => setSyncPage(v)} size="small" />
                </Stack>
              )}
            </>
          )}

          {tab === 3 && (
            <Stack spacing={3}>
              <Box>
                <Typography variant="subtitle2">Connection Parameters</Typography>
                <Typography component="pre" sx={{ bgcolor: 'grey.100', p: 1.5, borderRadius: 1, fontSize: '0.85rem' }}>{JSON.stringify(integ.configuration, null, 2)}</Typography>
                <Typography variant="caption" color="text.secondary">Secrets are redacted by the API and never returned in plaintext.</Typography>
              </Box>
              <Box>
                <Typography variant="subtitle2">Scoping</Typography>
                <Stack direction="row" spacing={2} sx={{ mt: 1 }}>
                  <Chip label={integ.company_scope === 'company' ? `Company: ${integ.company?.name || integ.company_id}` : 'Global (system-wide)'} color={integ.company_scope === 'company' ? 'info' : 'default'} size="small" />
                </Stack>
                {integ.can && (
                  <Stack direction="row" spacing={1} sx={{ mt: 1 }}>
                    {(['view', 'update', 'delete', 'sync', 'test', 'view_logs', 'import', 'manage_credentials'] as const).map(p => (
                      <Chip key={p} label={p} size="small" color={integ.can![p] ? 'success' : 'default'} variant={integ.can![p] ? 'filled' : 'outlined'} />
                    ))}
                  </Stack>
                )}
              </Box>
              <Box>
                <Typography variant="subtitle2" sx={{ mb: 1 }}>Audit Trail</Typography>
                <Table size="small">
                  <TableHead><TableRow><TableCell>Time</TableCell><TableCell>Action</TableCell><TableCell>Result</TableCell><TableCell>Actor</TableCell></TableRow></TableHead>
                  <TableBody>
                    {auditData?.data?.map((a: AuditLogEntry) => (
                      <TableRow key={a.id}>
                        <TableCell>{a.created_at ? new Date(a.created_at).toLocaleString() : '—'}</TableCell>
                        <TableCell>{a.action}</TableCell>
                        <TableCell><Chip label={a.result} size="small" color={a.result === 'success' ? 'success' : a.result === 'failure' ? 'error' : 'default'} /></TableCell>
                        <TableCell>{a.actor_name || 'system'}</TableCell>
                      </TableRow>
                    ))}
                    {(!auditData || !auditData.data || auditData.data.length === 0) && <TableRow><TableCell colSpan={4} align="center">No audit events</TableCell></TableRow>}
                  </TableBody>
                </Table>
                {auditData && (auditData.last_page ?? 1) > 1 && (
                  <Stack direction="row" justifyContent="center" sx={{ mt: 2 }}>
                    <Pagination count={auditData.last_page ?? 1} page={auditData.current_page ?? 1} onChange={(_, v) => setAuditPage(v)} size="small" />
                  </Stack>
                )}
              </Box>
            </Stack>
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
          <Button variant="contained" onClick={() => credCreateMut.mutate()} disabled={!credForm.value || credCreateMut.isPending}>Save</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={!!rotateCred} onClose={() => setRotateCred(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Rotate Credential</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <Typography variant="body2" color="text.secondary">
              Rotating {rotateCred?.label || rotateCred?.credential_type} ({rotateCred?.masked_hint || '********'}). The new value replaces the current one and the old credential is deactivated.
            </Typography>
            <TextField label="New Secret Value" fullWidth type="password" required value={rotateValue} onChange={e => setRotateValue(e.target.value)} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRotateCred(null)}>Cancel</Button>
          <Button variant="contained" onClick={() => rotateMut.mutate()} disabled={!rotateValue || rotateMut.isPending}>Rotate</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};