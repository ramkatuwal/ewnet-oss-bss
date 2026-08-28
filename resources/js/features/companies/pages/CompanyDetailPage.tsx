import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Grid, Button, Divider, Avatar,
    CircularProgress, Card, CardContent, Stack, Link as MuiLink, Chip,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import EmailIcon from '@mui/icons-material/Email';
import PhoneIcon from '@mui/icons-material/Phone';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import LanguageIcon from '@mui/icons-material/Language';
import BusinessIcon from '@mui/icons-material/Business';
import BadgeIcon from '@mui/icons-material/Badge';
import AccountTreeIcon from '@mui/icons-material/AccountTree';
import StorefrontIcon from '@mui/icons-material/Storefront';
import PeopleIcon from '@mui/icons-material/People';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { CompanyFormDrawer } from '../components/CompanyFormDrawer';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { branchesApi } from '@/api/branches';
import { formatDateTime, getErrorMessage } from '@/utils';
import { useToast } from '@/components/feedback/ToastProvider';

export const CompanyDetailPage = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { showToast } = useToast();
    const [editOpen, setEditOpen] = useState(false);

    const { data: company, isLoading } = useQuery({
        queryKey: ['company', id],
        queryFn: () => companiesApi.getById(Number(id)),
        enabled: !!id,
    });

    // Fetch region count for this company
    const { data: regionsData } = useQuery({
        queryKey: ['regions', 'byCompany', id],
        queryFn: () => regionsApi.getAll({ company_id: Number(id), per_page: 1 }),
        enabled: !!id,
    });

    // Fetch branch count for this company
    const { data: branchesData } = useQuery({
        queryKey: ['branches', 'byCompany', id],
        queryFn: () => branchesApi.getAll({ company_id: Number(id), per_page: 1 }),
        enabled: !!id,
    });

    const updateMutation = useMutation({
        mutationFn: (formData: FormData) => companiesApi.update(Number(id), formData),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['company', id] });
            queryClient.invalidateQueries({ queryKey: ['companies'] });
            showToast('Company updated successfully', 'success');
            setEditOpen(false);
        },
        onError: (err) => showToast(getErrorMessage(err), 'error'),
    });

    if (isLoading) {
        return <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}><CircularProgress /></Box>;
    }

    if (!company) {
        return (
            <Box sx={{ textAlign: 'center', py: 8 }}>
                <Typography variant="h5">Company not found</Typography>
                <Button onClick={() => navigate('/manage/companies')} sx={{ mt: 2 }}>Back to Companies</Button>
            </Box>
        );
    }

    const addressParts = [company.address, company.city, company.state, company.postal_code, company.country].filter(Boolean);
    const regionCount = regionsData?.total ?? 0;
    const branchCount = branchesData?.total ?? 0;

    return (
        <>
            <PageHeader
                title={company.name}
                breadcrumbs={[
                    { label: 'Manage', path: '/manage/companies' },
                    { label: 'Companies', path: '/manage/companies' },
                    { label: company.name },
                ]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/companies')}>Back</Button>
                        <Can permission="companies.update">
                            <Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>Edit</Button>
                        </Can>
                    </Stack>
                }
            />

            <Grid container spacing={3}>
                {/* Company Information */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 2 }}>
                                <Avatar src={company.logo_url || undefined} sx={{ width: 56, height: 56, bgcolor: 'action.hover' }} variant="rounded">
                                    <BusinessIcon />
                                </Avatar>
                                <Box>
                                    <Typography variant="h6" fontWeight={600}>{company.name}</Typography>
                                    <StatusChip active={company.is_active} />
                                </Box>
                            </Box>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Registration Number" value={company.registration_number} icon={<BadgeIcon fontSize="small" />} />
                                <DetailRow label="PAN Number" value={company.pan_number} />
                                <DetailRow label="Country" value={company.country} />
                                <DetailRow label="Created" value={formatDateTime(company.created_at)} />
                                <DetailRow label="Last Updated" value={formatDateTime(company.updated_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Contact Information */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <EmailIcon color="primary" /> Contact Information
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Email" value={company.email ? <MuiLink href={`mailto:${company.email}`}>{company.email}</MuiLink> : null} icon={<EmailIcon fontSize="small" />} />
                                <DetailRow label="Phone" value={company.phone ? <MuiLink href={`tel:${company.phone}`}>{company.phone}</MuiLink> : null} icon={<PhoneIcon fontSize="small" />} />
                                <DetailRow label="Website" value={company.website ? <MuiLink href={company.website} target="_blank" rel="noopener">{company.website}</MuiLink> : null} icon={<LanguageIcon fontSize="small" />} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Address */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <LocationOnIcon color="primary" /> Address
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            {addressParts.length > 0 ? (
                                <Typography variant="body1" sx={{ whiteSpace: 'pre-line', lineHeight: 1.8 }}>
                                    {addressParts.join('\n')}
                                </Typography>
                            ) : (
                                <Typography variant="body2" color="text.secondary">No address on file</Typography>
                            )}
                        </CardContent>
                    </Card>
                </Grid>

                {/* Summary with live counts */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom>Summary</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Grid container spacing={2}>
                                <Grid item xs={4}>
                                    <Box
                                        sx={{ textAlign: 'center', p: 2, border: 1, borderColor: 'divider', borderRadius: 2, cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }}
                                        onClick={() => navigate(`/manage/regions?company_id=${company.id}`)}
                                    >
                                        <AccountTreeIcon color="primary" sx={{ fontSize: 32, mb: 1 }} />
                                        <Typography variant="h4" fontWeight={700}>{regionCount}</Typography>
                                        <Typography variant="caption" color="text.secondary">Regions</Typography>
                                    </Box>
                                </Grid>
                                <Grid item xs={4}>
                                    <Box
                                        sx={{ textAlign: 'center', p: 2, border: 1, borderColor: 'divider', borderRadius: 2, cursor: 'pointer', '&:hover': { bgcolor: 'action.hover' } }}
                                        onClick={() => navigate(`/manage/branches?company_id=${company.id}`)}
                                    >
                                        <StorefrontIcon color="primary" sx={{ fontSize: 32, mb: 1 }} />
                                        <Typography variant="h4" fontWeight={700}>{branchCount}</Typography>
                                        <Typography variant="caption" color="text.secondary">Branches</Typography>
                                    </Box>
                                </Grid>
                                <Grid item xs={4}>
                                    <Box sx={{ textAlign: 'center', p: 2, border: 1, borderColor: 'divider', borderRadius: 2, opacity: 0.6 }}>
                                        <PeopleIcon color="disabled" sx={{ fontSize: 32, mb: 1 }} />
                                        <Typography variant="h4" fontWeight={700} color="text.disabled">—</Typography>
                                        <Typography variant="caption" color="text.secondary">Users</Typography>
                                    </Box>
                                </Grid>
                            </Grid>
                            <Typography variant="caption" color="text.secondary" sx={{ mt: 2, display: 'block' }}>
                                Click Regions or Branches to view filtered list. User count available after TASK-023.
                            </Typography>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <CompanyFormDrawer
                open={editOpen}
                onClose={() => setEditOpen(false)}
                company={company}
                onSubmit={(formData) => updateMutation.mutate(formData)}
                loading={updateMutation.isPending}
            />
        </>
    );
};

const DetailRow = ({ label, value, icon }: { label: string; value: React.ReactNode; icon?: React.ReactNode }) => (
    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
        {icon && <Box sx={{ mt: 0.25, color: 'text.secondary' }}>{icon}</Box>}
        <Box>
            <Typography variant="caption" color="text.secondary" display="block">{label}</Typography>
            <Typography variant="body2" fontWeight={500}>{value || <span style={{ color: '#999' }}>—</span>}</Typography>
        </Box>
    </Box>
);
