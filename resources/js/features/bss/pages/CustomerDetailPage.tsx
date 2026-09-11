import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Card, CardContent, Chip, Dialog, DialogActions, DialogContent, DialogTitle, Divider, Stack, Tab, Tabs, TextField, Typography } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { PageErrorState, PageLoadingState } from '@/components/feedback/PageStates';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage } from '@/utils';
import { bssKeys } from '@/api/queryKeys';
import { bssApi } from '../api/bss';

type FormKind = 'contact' | 'person' | 'address' | 'business' | 'verification' | 'note' | null;
const fields: Record<Exclude<FormKind, null>, string[]> = { contact: ['kind', 'value'], person: ['name', 'role', 'email', 'phone'], address: ['kind', 'line1', 'city', 'country_code'], business: ['legal_name', 'registration_number', 'tax_number', 'industry'], verification: ['kind', 'status', 'reference', 'reason'], note: ['body'] };
export const CustomerDetailPage = () => {
    const id = Number(useParams<{ id: string }>().id); const navigate = useNavigate(); const queryClient = useQueryClient(); const { showToast } = useToast(); const [tab, setTab] = useState(0); const [form, setForm] = useState<FormKind>(null); const [values, setValues] = useState<Record<string, string>>({});
    const customer = useQuery({ queryKey: bssKeys.customer(id), queryFn: () => bssApi.customer(id), enabled: id > 0 });
    const profile = useQuery({ queryKey: bssKeys.customer360(id), queryFn: () => bssApi.customer360(id), enabled: id > 0 });
    const submit = useMutation({ mutationFn: () => {
        if (form === 'contact') return bssApi.addContact(id, { kind: (values.kind || 'email') as 'email' | 'phone' | 'other', value: values.value });
        if (form === 'person') return bssApi.addPerson(id, { name: values.name, role: values.role || undefined, email: values.email || undefined, phone: values.phone || undefined });
        if (form === 'address') return bssApi.addAddress(id, { kind: (values.kind || 'billing') as 'billing' | 'service' | 'other', line1: values.line1, city: values.city || undefined, country_code: values.country_code || undefined });
        if (form === 'business') return bssApi.saveBusinessProfile(id, values);
        if (form === 'verification') return bssApi.addVerification(id, { kind: values.kind, status: (values.status || 'pending') as 'pending' | 'verified' | 'rejected', reference: values.reference || undefined, reason: values.reason || undefined });
        return bssApi.addNote(id, { body: values.body });
    }, onSuccess: () => { queryClient.invalidateQueries({ queryKey: bssKeys.customer360(id) }); setForm(null); setValues({}); showToast('Customer 360 record saved.', 'success'); }, onError: error => showToast(getErrorMessage(error), 'error') });
    if (customer.isLoading || profile.isLoading) return <PageLoadingState label="Loading customer 360..." />;
    if (customer.error || profile.error || !customer.data || !profile.data) return <PageErrorState message="Customer is unavailable or outside your management scope." onRetry={() => profile.refetch()} />;
    const record = profile.data; const lists = [record.contacts, record.contact_persons, record.addresses, record.business_profile ? [record.business_profile] : [], record.verifications, record.notes]; const labels = ['Contacts', 'Contact persons', 'Addresses', 'Business profile', 'Verification', 'Notes']; const actions: Exclude<FormKind, null>[] = ['contact', 'person', 'address', 'business', 'verification', 'note'];
    return <Box><PageHeader title={record.name} subtitle={`${record.customer_code} · Company #${record.company_id}`} breadcrumbs={[{ label: 'BSS', path: '/bss' }, { label: 'Customer 360' }]} actions={<Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/bss')}>Back</Button>} />
        <Card><CardContent><Stack direction="row" spacing={2} alignItems="center"><Typography variant="h6">Customer authority</Typography><Chip size="small" label={record.status} color={record.status === 'active' ? 'success' : 'default'} /></Stack><Typography color="text.secondary">{record.email || 'No primary email'} · {record.phone || 'No primary phone'}</Typography></CardContent></Card>
        <Tabs value={tab} onChange={(_, value) => setTab(value)} variant="scrollable" sx={{ mt: 2 }}>{labels.map(label => <Tab key={label} label={label} />)}</Tabs><Card><CardContent><Stack direction="row" justifyContent="space-between" alignItems="center"><Typography variant="h6">{labels[tab]}</Typography><Can permission="bss.customers.update"><Button onClick={() => { setValues(tab === 3 && record.business_profile ? Object.fromEntries(Object.entries(record.business_profile).filter(([, value]) => typeof value === 'string')) as Record<string, string> : {}); setForm(actions[tab]); }}>Add / update</Button></Can></Stack><Divider sx={{ my: 1 }} />{lists[tab].length === 0 ? <Typography color="text.secondary">No records recorded.</Typography> : <Stack spacing={1}>{lists[tab].map((item, index) => <Box key={index} sx={{ p: 1, border: '1px solid', borderColor: 'divider', borderRadius: 1 }}><Typography>{Object.entries(item).filter(([key]) => !['id', 'created_at', 'updated_at'].includes(key)).map(([key, value]) => `${key.replace('_', ' ')}: ${String(value)}`).join(' · ')}</Typography></Box>)}</Stack>}</CardContent></Card>
        <Dialog open={form !== null} onClose={() => setForm(null)} fullWidth maxWidth="sm"><DialogTitle>{form === 'business' ? 'Update business profile' : `Add ${form}`}</DialogTitle><DialogContent><Stack spacing={2} sx={{ pt: 1 }}>{form && fields[form].map(field => <TextField key={field} label={field.replaceAll('_', ' ')} required={['value', 'name', 'line1', 'body'].includes(field)} multiline={field === 'body' || field === 'reason'} value={values[field] || ''} onChange={event => setValues({ ...values, [field]: event.target.value })} helperText={field === 'kind' ? 'Contact: email, phone, other. Address: billing, service, other.' : field === 'status' ? 'pending, verified, or rejected' : undefined} />)}</Stack></DialogContent><DialogActions><Button onClick={() => setForm(null)}>Cancel</Button><Button variant="contained" disabled={submit.isPending} onClick={() => submit.mutate()}>Save</Button></DialogActions></Dialog>
    </Box>;
};
export default CustomerDetailPage;
