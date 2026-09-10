import { useParams, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Box, Button, Card, CardContent, Chip, Divider, Stack, Typography } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { PageErrorState, PageLoadingState } from '@/components/feedback/PageStates';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage } from '@/utils';
import { bssKeys } from '@/api/queryKeys';
import { bssApi, type CustomerServiceStatus } from '../api/bss';

type TransitionStatus = Exclude<CustomerServiceStatus, 'pending'>;
const nextStates: Record<CustomerServiceStatus, TransitionStatus[]> = { pending: ['active', 'cancelled'], active: ['suspended', 'terminated'], suspended: ['active', 'terminated'], terminated: [], cancelled: [] };
export const CustomerDetailPage = () => {
    const id = Number(useParams<{ id: string }>().id); const navigate = useNavigate(); const queryClient = useQueryClient(); const { showToast } = useToast();
    const customer = useQuery({ queryKey: bssKeys.customer(id), queryFn: () => bssApi.customer(id), enabled: Number.isInteger(id) && id > 0 });
    const customerServices = useQuery({ queryKey: bssKeys.customerServices(id), queryFn: () => bssApi.customerServices(id, { per_page: 100 }), enabled: !!customer.data });
    const transition = useMutation({ mutationFn: ({ serviceId, status }: { serviceId: number; status: TransitionStatus }) => bssApi.transitionCustomerService(serviceId, status), onSuccess: () => { queryClient.invalidateQueries({ queryKey: bssKeys.customerServices(id) }); showToast('Service lifecycle updated.', 'success'); }, onError: error => showToast(getErrorMessage(error), 'error') });
    if (customer.isLoading) return <PageLoadingState label="Loading customer authority..." />;
    if (customer.error || !customer.data) return <PageErrorState message="Customer is unavailable or outside your management scope." onRetry={() => customer.refetch()} />;
    const record = customer.data;
    return <Box><PageHeader title={record.name} subtitle={`${record.customer_code} · Company #${record.company_id}`} breadcrumbs={[{ label: 'BSS', path: '/bss' }, { label: 'Customer' }]} actions={<Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/bss')}>Back</Button>} />
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}><Card sx={{ flex: 1 }}><CardContent><Typography variant="h6">Customer authority</Typography><Divider sx={{ my: 1.5 }} /><Detail label="Type" value={record.type} /><Detail label="Status" value={<Chip size="small" label={record.status} color={record.status === 'active' ? 'success' : 'default'} />} /><Detail label="Email" value={record.email || 'Not recorded'} /><Detail label="Phone" value={record.phone || 'Not recorded'} /><Detail label="Address" value={record.address || 'Not recorded'} /></CardContent></Card>
            <Card sx={{ flex: 2 }}><CardContent><Typography variant="h6">Customer services</Typography><Divider sx={{ my: 1.5 }} />{customerServices.isLoading && <Typography color="text.secondary">Loading service history...</Typography>}{!customerServices.isLoading && !customerServices.data?.data.length && <Typography color="text.secondary">No services have been assigned.</Typography>}<Stack spacing={1}>{customerServices.data?.data.map(item => <Box key={item.id} sx={{ border: '1px solid', borderColor: 'divider', p: 1.5, borderRadius: 1 }}><Stack direction="row" justifyContent="space-between" alignItems="center"><Box><Typography fontWeight={600}>{item.service?.service_code} · {item.service?.name}</Typography><Typography variant="caption" color="text.secondary">{item.starts_on || 'No start date'} {item.ends_on ? `to ${item.ends_on}` : ''}</Typography></Box><Stack direction="row" spacing={0.5} alignItems="center"><Chip size="small" label={item.status} />{nextStates[item.status].map(status => <Can key={status} permission="bss.customer-services.update"><Button size="small" disabled={transition.isPending} onClick={() => transition.mutate({ serviceId: item.id, status })}>{status}</Button></Can>)}</Stack></Stack></Box>)}</Stack></CardContent></Card></Stack>
    </Box>;
};
const Detail = ({ label, value }: { label: string; value: React.ReactNode }) => <Box sx={{ mb: 1.25 }}><Typography variant="caption" color="text.secondary">{label}</Typography><Typography>{value}</Typography></Box>;
export default CustomerDetailPage;
