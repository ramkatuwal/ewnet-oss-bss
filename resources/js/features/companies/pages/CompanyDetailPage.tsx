import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Paper, Grid, Button, Chip, Divider,
    CircularProgress, Card, CardContent, Stack, Link as MuiLink,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import EmailIcon from '@mui/icons-material/Email';
import PhoneIcon from '@mui/icons-material/Phone';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import LanguageIcon from '@mui/icons-material/Language';
import BusinessIcon from '@mui/icons-material/Business';
import BadgeIcon from '@mui/icons-material/Badge';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { StatusChip } from '@/components/tables/DataTable';
import { CompanyFormDrawer } from '../components/CompanyFormDrawer';
import { companiesApi } from '@/api/companies';
import { formatDateTime } from '@/utils';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage } from '@/utils';
import type { Company } from '@/types';

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

    const updateMutation = useMutation({
        mutationFn: (data: Partial<Company>) => companiesApi.update(Number(id), data),
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
                        <Button startIcon={<ArrowBackIcon />} onClick={() => navigate('/manage/companies')}>
                            Back
                        </Button>
                        <Can permission="companies.update">
                            <Button variant="contained" startIcon={<EditIcon />} onClick={() => setEditOpen(true)}>
                                Edit
                            </Button>
                        </Can>
                    </Stack>
                }
            />

            <Grid container spacing={3}>
                {/* Overview Card */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Avatar src={company.logo_url || undefined} sx={{ width: 48, height: 48, mr: 1 }} variant="rounded"><BusinessIcon /></Avatar> Company Information
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow label="Status" value={<StatusChip active={company.is_active} />} />
                                <DetailRow label="Registration Number" value={company.registration_number} icon={<BadgeIcon fontSize="small" />} />
                                <DetailRow label="PAN Number" value={company.pan_number} />
                                <DetailRow label="Country" value={company.country} />
                                <DetailRow label="Created" value={formatDateTime(company.created_at)} />
                                <DetailRow label="Last Updated" value={formatDateTime(company.updated_at)} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Contact Card */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <EmailIcon color="primary" /> Contact Information
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <DetailRow
                                    label="Email"
                                    value={company.email ? <MuiLink href={`mailto:${company.email}`}>{company.email}</MuiLink> : null}
                                    icon={<EmailIcon fontSize="small" />}
                                />
                                <DetailRow
                                    label="Phone"
                                    value={company.phone ? <MuiLink href={`tel:${company.phone}`}>{company.phone}</MuiLink> : null}
                                    icon={<PhoneIcon fontSize="small" />}
                                />
                                <DetailRow
                                    label="Website"
                                    value={company.website ? <MuiLink href={company.website} target="_blank" rel="noopener">{company.website}</MuiLink> : null}
                                    icon={<LanguageIcon fontSize="small" />}
                                />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Address Card */}
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

                {/* Quick Stats Placeholder */}
                <Grid item xs={12} md={6}>
                    <Card elevation={0} sx={{ border: 1, borderColor: 'divider', height: '100%' }}>
                        <CardContent>
                            <Typography variant="h6" gutterBottom>Summary</Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1}>
                                <Typography variant="body2" color="text.secondary">
                                    Region, branch, and user counts will be displayed here once those modules are rebuilt.
                                </Typography>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            {/* Edit Drawer */}
            <CompanyFormDrawer
                open={editOpen}
                onClose={() => setEditOpen(false)}
                company={company}
                onSubmit={(data) => updateMutation.mutate(data)}
                loading={updateMutation.isPending}
            />
        </>
    );
};

// Helper component for consistent detail rows
const DetailRow = ({ label, value, icon }: { label: string; value: React.ReactNode; icon?: React.ReactNode }) => (
    <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1 }}>
        {icon && <Box sx={{ mt: 0.25, color: 'text.secondary' }}>{icon}</Box>}
        <Box>
            <Typography variant="caption" color="text.secondary" display="block">
                {label}
            </Typography>
            <Typography variant="body2" fontWeight={500}>
                {value || <span style={{ color: '#999' }}>—</span>}
            </Typography>
        </Box>
    </Box>
);
