import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Card, CardContent, Grid, Stack,
    Tabs, Tab, Button, Divider, Chip, Skeleton
} from '@mui/material';
import { ArrowBack, Edit, LocationOn, Business } from '@mui/icons-material';
import { sitesApi } from '@/api/sites';
import { SiteAssetsTab } from '../components/SiteAssetsTab';
import { SiteFormDrawer } from '../components/SiteFormDrawer';
import { SiteStatusBadge } from '../components/SiteStatusBadge';
import { PhotoGallery } from '@/features/shared/components/PhotoGallery';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import toast from 'react-hot-toast';

interface TabPanelProps {
    children?: React.ReactNode;
    index: number;
    value: number;
}

function TabPanel(props: TabPanelProps) {
    const { children, value, index, ...other } = props;
    return (
        <div role="tabpanel" hidden={value !== index} {...other}>
            {value === index && <Box sx={{ p: 3 }}>{children}</Box>}
        </div>
    );
}

const SiteDetailPage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [tabValue, setTabValue] = useState(0);
    const [formOpen, setFormOpen] = useState(false);

    const { data: site, isLoading, error } = useQuery({
        queryKey: ['site', id],
        queryFn: () => sitesApi.get(parseInt(id!)),
        enabled: !!id,
    });

    if (isLoading) {
        return (
            <Box sx={{ p: 3, display: 'flex', gap: 3, flexDirection: 'column' }}>
                <Skeleton variant="rectangular" height={60} />
                <Grid container spacing={3}>
                    <Grid item xs={12} md={8}><Skeleton variant="rectangular" height={300} /></Grid>
                    <Grid item xs={12} md={4}><Skeleton variant="rectangular" height={300} /></Grid>
                </Grid>
            </Box>
        );
    }

    if (error || !site) {
        return (
            <Box sx={{ p: 3 }}>
                <Typography color="error">Failed to load site details.</Typography>
                <Button startIcon={<ArrowBack />} onClick={() => navigate('/network/sites')}>Back to Sites</Button>
            </Box>
        );
    }

    const getTypeLabel = (type: string) => {
        const labels: Record<string, string> = {
            pop: 'Point of Presence', tower: 'Tower', office: 'Office',
            warehouse: 'Warehouse', datacenter: 'Datacenter', customer_premises: 'Customer Premises',
            solar_site: 'Solar Site', repeater_site: 'Repeater Site', other: 'Other'
        };
        return labels[type] || type;
    };

    const handleEditSuccess = () => {
        setFormOpen(false);
        queryClient.invalidateQueries({ queryKey: ['site', id] });
        queryClient.invalidateQueries({ queryKey: ['sites'] });
        toast.success('Site updated successfully');
    };

    const siteId = parseInt(id!);

    return (
        <Box sx={{ p: 3, maxWidth: 1600, mx: 'auto' }}>
            <PageHeader
                title={site.name}
                breadcrumbs={[
                    { label: 'Network', path: '/network' },
                    { label: 'Sites', path: '/network/sites' },
                    { label: site.site_code }
                ]}
                actions={
                    <Can permission="sites.update">
                        <Button variant="contained" startIcon={<Edit />} onClick={() => setFormOpen(true)}>Edit Site</Button>
                    </Can>
                }
            />

            {/* Header Summary */}
            <Card sx={{ mb: 3 }}>
                <CardContent>
                    <Grid container spacing={3} alignItems="center">
                        <Grid item xs={12} md={8}>
                            <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 1 }}>
                                <Typography variant="h4">{site.site_code}</Typography>
                                <SiteStatusBadge status={site.status} />
                                <Chip label={getTypeLabel(site.type)} variant="outlined" size="small" />
                            </Stack>
                            <Typography variant="body1" color="text.secondary">
                                {site.description || 'No description provided for this site.'}
                            </Typography>
                        </Grid>
                        <Grid item xs={12} md={4} sx={{ textAlign: 'right' }}>
                            <Stack direction="row" spacing={2} justifyContent="flex-end">
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Created</Typography>
                                    <Typography variant="body2">{new Date(site.created_at).toLocaleDateString()}</Typography>
                                </Box>
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Last Updated</Typography>
                                    <Typography variant="body2">{new Date(site.updated_at).toLocaleDateString()}</Typography>
                                </Box>
                            </Stack>
                        </Grid>
                    </Grid>
                </CardContent>
            </Card>

            <Grid container spacing={3}>
                {/* Left Column: Overview & Location */}
                <Grid item xs={12} md={4}>
                    <Card sx={{ mb: 3 }}>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Business fontSize="small" /> Organization
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            <Stack spacing={1.5}>
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Company</Typography>
                                    <Typography variant="body2">{site.company?.name || 'N/A'}</Typography>
                                </Box>
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Region</Typography>
                                    <Typography variant="body2">{site.region?.name || 'N/A'}</Typography>
                                </Box>
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Branch</Typography>
                                    <Typography variant="body2">{site.branch?.name || 'N/A'}</Typography>
                                </Box>
                            </Stack>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <LocationOn fontSize="small" /> Location Details
                            </Typography>
                            <Divider sx={{ mb: 2 }} />
                            {site.latitude && site.longitude ? (
                                <Stack spacing={1.5}>
                                    <Box>
                                        <Typography variant="caption" color="text.secondary">Coordinates</Typography>
                                        <Typography variant="body2">{site.latitude}, {site.longitude}</Typography>
                                    </Box>
                                    {site.altitude && (
                                        <Box>
                                            <Typography variant="caption" color="text.secondary">Altitude</Typography>
                                            <Typography variant="body2">{site.altitude}m</Typography>
                                        </Box>
                                    )}
                                    <Box>
                                        <Typography variant="caption" color="text.secondary">Address</Typography>
                                        <Typography variant="body2">{site.address || 'No address recorded'}</Typography>
                                        <Typography variant="body2">{[site.municipality, site.district, site.province].filter(Boolean).join(', ')}</Typography>
                                    </Box>
                                </Stack>
                            ) : (
                                <Typography variant="body2" color="text.secondary" sx={{ fontStyle: 'italic' }}>
                                    Geographic coordinates are currently unavailable for this site.
                                </Typography>
                            )}
                        </CardContent>
                    </Card>
                </Grid>

                {/* Right Column: Tabs for Assets, Photos, etc. */}
                <Grid item xs={12} md={8}>
                    <Card>
                        <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                            <Tabs value={tabValue} onChange={(_, v) => setTabValue(v)}>
                                <Tab label={`Assets (${(site as any).assets?.length || 0})`} />
                                <Tab label="Photos" />
                                <Tab label="Integrations" />
                            </Tabs>
                        </Box>
                        <TabPanel value={tabValue} index={0}>
                            <SiteAssetsTab siteId={siteId} />
                        </TabPanel>
                        <TabPanel value={tabValue} index={1}>
                            <Can permission="sites.update">
                                <PhotoGallery
                                    entityType="site"
                                    entityId={siteId}
                                    getPhotosUrl={`/api/v1/sites/${siteId}/photos`}
                                    uploadUrl={`/api/v1/sites/${siteId}/photos`}
                                    deleteUrl={(photoId: number) => `/api/v1/sites/${siteId}/photos/${photoId}`}
                                    categories={['site', 'rack', 'power', 'tower', 'equipment', 'other']}
                                    defaultCategory="site"
                                    canUpload={true}
                                    canDelete={true}
                                />
                            </Can>
                        </TabPanel>
                        <TabPanel value={tabValue} index={2}>
                            <Typography variant="body2" color="text.secondary">
                                Integration status and synchronization details will be displayed here.
                            </Typography>
                        </TabPanel>
                    </Card>
                </Grid>
            </Grid>

            <SiteFormDrawer
                open={formOpen}
                siteId={siteId}
                onClose={() => setFormOpen(false)}
                onSuccess={handleEditSuccess}
            />
        </Box>
    );
};

export default SiteDetailPage;
