import { useParams, useNavigate } from 'react-router-dom';
import { Box, Button, Card, CardContent, Chip, Divider, Stack, Typography } from '@mui/material';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import { useQuery } from '@tanstack/react-query';
import { PageHeader } from '@/components/layout/PageHeader';
import { PageErrorState, PageLoadingState } from '@/components/feedback/PageStates';
import { bssKeys } from '@/api/queryKeys';
import { bssApi } from '../api/bss';

export const ServiceDetailPage = () => {
    const id = Number(useParams<{ id: string }>().id); const navigate = useNavigate();
    const service = useQuery({ queryKey: bssKeys.service(id), queryFn: () => bssApi.service(id), enabled: Number.isInteger(id) && id > 0 });
    if (service.isLoading) return <PageLoadingState label="Loading service authority..." />;
    if (service.error || !service.data) return <PageErrorState message="Service is unavailable or outside your management scope." onRetry={() => service.refetch()} />;
    const record = service.data;
    return <Box><PageHeader title={record.name} subtitle={`${record.service_code} · Company #${record.company_id}`} breadcrumbs={[{ label: 'BSS', path: '/bss' }, { label: 'Service' }]} actions={<Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/bss')}>Back</Button>} /><Card><CardContent><Typography variant="h6">Service authority</Typography><Divider sx={{ my: 1.5 }} /><Stack spacing={1}><Detail label="Type" value={record.type} /><Detail label="Status" value={<Chip size="small" label={record.status} color={record.status === 'active' ? 'success' : 'default'} />} /><Detail label="Description" value={record.description || 'No description recorded'} /></Stack></CardContent></Card></Box>;
};
const Detail = ({ label, value }: { label: string; value: React.ReactNode }) => <Box><Typography variant="caption" color="text.secondary">{label}</Typography><Typography>{value}</Typography></Box>;
export default ServiceDetailPage;
