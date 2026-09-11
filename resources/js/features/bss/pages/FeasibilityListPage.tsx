import { useDeferredValue, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Chip, Dialog, DialogActions, DialogContent, DialogTitle, MenuItem, Select, Stack, TextField, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import { PageHeader } from '@/components/layout/PageHeader';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { Can } from '@/components/auth/Can';
import { PageEmptyState, PageErrorState, PageLoadingState } from '@/components/feedback/PageStates';
import { getErrorMessage } from '@/utils';
import { useToast } from '@/components/feedback/ToastProvider';
import { bssKeys } from '@/api/queryKeys';
import { bssApi, type FeasibilityCheck, type FeasibilityStatus } from '../api/bss';

const statusColor = (status: FeasibilityStatus): 'default' | 'success' | 'warning' | 'info' | 'error' => status === 'feasible' ? 'success' : status === 'not_feasible' || status === 'cancelled' || status === 'expired' ? 'error' : status === 'conditionally_feasible' ? 'info' : status === 'requested' || status === 'reviewing' || status === 'survey_required' || status === 'survey_scheduled' || status === 'surveyed' ? 'warning' : 'default';
const statusOptions: FeasibilityStatus[] = ['requested', 'reviewing', 'survey_required', 'survey_scheduled', 'surveyed', 'feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'];

export const FeasibilityListPage = () => {
    const queryClient = useQueryClient(); const { showToast } = useToast(); const navigate = useNavigate();
    const [search, setSearch] = useState(''); const deferredSearch = useDeferredValue(search);
    const [statusFilter, setStatusFilter] = useState(''); const [leadFilter, setLeadFilter] = useState('');
    const [createOpen, setCreateOpen] = useState(false); const [form, setForm] = useState({ lead_id: '', customer_id: '', requested_service_summary: '', requested_location_summary: '', requested_location_lat: '', requested_location_lng: '' });
    const list = useQuery({
        queryKey: bssKeys.feasibilityChecks({ search: deferredSearch, status: statusFilter, lead_id: leadFilter, per_page: 25 }),
        queryFn: () => bssApi.feasibilityChecks({
            search: deferredSearch || undefined,
            status: statusFilter || undefined,
            lead_id: leadFilter ? Number(leadFilter) : undefined,
            per_page: 25,
        }),
    });
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['bss', 'feasibility-checks'] });
    const create = useMutation({ mutationFn: bssApi.createFeasibility, onSuccess: record => { refresh(); queryClient.invalidateQueries({ queryKey: ['bss', 'leads'] }); showToast('Feasibility check requested.', 'success'); setCreateOpen(false); navigate(`/bss/feasibility/${record.id}`); }, onError: error => showToast(getErrorMessage(error), 'error') });
    const submit = () => create.mutate({
        ...(form.lead_id ? { lead_id: Number(form.lead_id) } : {}),
        ...(form.customer_id ? { customer_id: Number(form.customer_id) } : {}),
        requested_service_summary: form.requested_service_summary,
        ...(form.requested_location_summary ? { requested_location_summary: form.requested_location_summary } : {}),
        ...(form.requested_location_lat !== '' && form.requested_location_lng !== '' ? { requested_location_lat: Number(form.requested_location_lat), requested_location_lng: Number(form.requested_location_lng) } : {}),
    });
    if (list.isLoading) return <PageLoadingState label="Loading feasibility checks..." />;
    if (list.error) return <PageErrorState message="Unable to load feasibility checks." onRetry={() => list.refetch()} />;
    return <Box>
        <PageHeader title="Feasibility" subtitle="Coverage feasibility checks and customer confirmations across the network footprint" breadcrumbs={[{ label: 'BSS', path: '/bss' }, { label: 'Feasibility' }]} actions={<Can permission="bss.feasibility.create"><Button variant="contained" startIcon={<AddIcon />} onClick={() => setCreateOpen(true)}>Request feasibility</Button></Can>} />
        <SearchFilterBar searchValue={search} onSearchChange={setSearch} placeholder="Search feasibility code, lead, customer, location...">
            <Select size="small" value={statusFilter} onChange={event => setStatusFilter(event.target.value)} displayEmpty sx={{ minWidth: 180 }}><MenuItem value="">All statuses</MenuItem>{statusOptions.map(status => <MenuItem key={status} value={status}>{status}</MenuItem>)}</Select>
            <TextField size="small" label="Lead ID" type="number" value={leadFilter} onChange={event => setLeadFilter(event.target.value)} sx={{ minWidth: 140 }} />
        </SearchFilterBar>
        <Stack spacing={1}>{(list.data?.data || []).map((record: FeasibilityCheck) => <Box key={record.id} sx={{ p: 2, border: '1px solid', borderColor: 'divider', borderRadius: 1, display: 'flex', justifyContent: 'space-between', gap: 2, alignItems: 'center' }}><Box onClick={() => navigate(`/bss/feasibility/${record.id}`)} sx={{ cursor: 'pointer' }}><Typography fontWeight={700}>{record.feasibility_code}</Typography><Typography variant="body2" color="text.secondary">{record.lead?.name ?? record.customer?.name ?? `Company #${record.company_id}`} · {record.requested_service_summary}{record.lead ? ` · Lead ${record.lead.status}` : ''}</Typography>{record.requested_location_summary && <Typography variant="body2" color="text.secondary">{record.requested_location_summary}</Typography>}</Box><Chip size="small" label={record.outcome ?? record.status} color={statusColor(record.status)} /></Box>)}</Stack>
        {!list.data?.data.length && <PageEmptyState message={search || statusFilter || leadFilter ? 'No feasibility checks match the current filters.' : 'No feasibility checks are visible in your management scope.'} />}
        <Dialog open={createOpen} onClose={() => setCreateOpen(false)} fullWidth maxWidth="sm"><DialogTitle>Request feasibility</DialogTitle><DialogContent><Stack spacing={2} sx={{ pt: 1 }}>
            <TextField label="Lead ID" type="number" value={form.lead_id} onChange={event => setForm({ ...form, lead_id: event.target.value })} helperText="Optional: create from a qualified lead." />
            <TextField label="Customer ID" type="number" value={form.customer_id} onChange={event => setForm({ ...form, customer_id: event.target.value })} helperText="Optional: create directly for an existing customer." />
            <TextField required label="Requested service summary" value={form.requested_service_summary} onChange={event => setForm({ ...form, requested_service_summary: event.target.value })} multiline minRows={2} />
            <TextField label="Requested location summary" value={form.requested_location_summary} onChange={event => setForm({ ...form, requested_location_summary: event.target.value })} multiline />
            <Stack direction="row" spacing={1}><TextField label="Latitude" type="number" value={form.requested_location_lat} onChange={event => setForm({ ...form, requested_location_lat: event.target.value })} /><TextField label="Longitude" type="number" value={form.requested_location_lng} onChange={event => setForm({ ...form, requested_location_lng: event.target.value })} /></Stack>
        </Stack></DialogContent><DialogActions><Button onClick={() => setCreateOpen(false)}>Cancel</Button><Button variant="contained" disabled={create.isPending || !form.requested_service_summary || (!form.lead_id && !form.customer_id)} onClick={submit}>Create feasibility check</Button></DialogActions></Dialog>
    </Box>;
};

export default FeasibilityListPage;