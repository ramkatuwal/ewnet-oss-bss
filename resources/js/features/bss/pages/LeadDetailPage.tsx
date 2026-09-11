import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Card, CardContent, Stack, TextField, Typography } from '@mui/material';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { PageErrorState, PageLoadingState } from '@/components/feedback/PageStates';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage } from '@/utils';
import { bssKeys } from '@/api/queryKeys';
import { bssApi } from '../api/bss';

export const LeadDetailPage = () => {
    const id = Number(useParams<{ id: string }>().id); const navigate = useNavigate(); const queryClient = useQueryClient(); const { showToast } = useToast(); const [qualification, setQualification] = useState('{"serviceable": true}'); const [code, setCode] = useState('');
    const lead = useQuery({ queryKey: bssKeys.lead(id), queryFn: () => bssApi.lead(id), enabled: id > 0 });
    const candidates = useQuery({ queryKey: ['bss', 'lead-candidates', id], queryFn: () => bssApi.duplicateCandidates(id), enabled: !!lead.data && lead.data.status === 'qualified' });
    const refresh = () => { queryClient.invalidateQueries({ queryKey: bssKeys.lead(id) }); queryClient.invalidateQueries({ queryKey: ['bss', 'leads'] }); };
    const action = useMutation<unknown, Error, 'qualify' | 'unqualify' | 'lose' | 'convert'>({ mutationFn: (kind) => kind === 'qualify' ? bssApi.qualifyLead(id, JSON.parse(qualification) as Record<string, unknown>) : kind === 'unqualify' ? bssApi.unqualifyLead(id) : kind === 'lose' ? bssApi.loseLead(id) : bssApi.convertLead(id, code), onSuccess: () => { refresh(); showToast('Lead lifecycle updated.', 'success'); }, onError: error => showToast(getErrorMessage(error), 'error') });
    if (lead.isLoading) return <PageLoadingState label="Loading lead..." />;
    if (lead.error || !lead.data) return <PageErrorState message="Lead is unavailable or outside your management scope." onRetry={() => lead.refetch()} />;
    const record = lead.data;
    return <Box><PageHeader title={record.name} subtitle={`${record.lead_code} · ${record.status}`} breadcrumbs={[{ label: 'BSS', path: '/bss' }, { label: 'Lead' }]} actions={<Button onClick={() => navigate('/bss')}>Back</Button>} /><Stack spacing={2}><Card><CardContent><Typography>{record.email || 'No email'} · {record.phone || 'No phone'}</Typography><Typography color="text.secondary">Company #{record.company_id}</Typography></CardContent></Card>{record.status === 'new' && <Can permission="bss.leads.update"><Card><CardContent><TextField fullWidth label="Qualification JSON" value={qualification} onChange={event => setQualification(event.target.value)} /><Button sx={{ mt: 1 }} variant="contained" onClick={() => action.mutate('qualify')}>Verify and qualify</Button><Button sx={{ mt: 1, ml: 1 }} color="error" onClick={() => action.mutate('lose')}>Mark lost</Button></CardContent></Card></Can>}{record.status === 'qualified' && <Can permission="bss.leads.convert"><Card><CardContent><Typography variant="h6">Duplicate candidates</Typography>{candidates.data?.map(candidate => <Typography key={candidate.id}>{candidate.customer_code} · {candidate.name}</Typography>)}<TextField sx={{ mt: 1 }} label="New customer code" value={code} onChange={event => setCode(event.target.value)} /><Stack direction="row" spacing={1} sx={{ mt: 1 }}><Button variant="contained" disabled={!code} onClick={() => action.mutate('convert')}>Convert</Button><Can permission="bss.leads.update"><Button onClick={() => action.mutate('unqualify')}>Unqualify</Button><Button color="error" onClick={() => action.mutate('lose')}>Mark lost</Button></Can></Stack></CardContent></Card></Can>}</Stack></Box>;
};
export default LeadDetailPage;
