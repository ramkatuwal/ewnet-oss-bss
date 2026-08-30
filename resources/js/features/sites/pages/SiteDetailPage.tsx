import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Card, CardContent, Grid, Chip, Stack,
    Tabs, Tab, CircularProgress, Button, Divider
} from '@mui/material';
import { ArrowBack, Edit, LocationOn, Business } from '@mui/icons-material';
import { sitesApi } from '@/api/sites';
import { SiteAssetsTab } from '../components/SiteAssetsTab';
import { SiteFormDrawer } from '../components/SiteFormDrawer';
import { PhotoGallery } from '@/features/shared/components/PhotoGallery';
import { formatCoordinate } from '@/utils/format';
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
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error || !site) {
        return (
            <Box sx={{ p: 3 }}>
                <Typography color="error">Failed to load site details.</Typography>
                <Button startIcon={<ArrowBack />} onClick={() => navigate('/network/sites')}>
                    Back to Sites
                </Button>
            </Box>
        );
    }

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': return 'success';
            case 'planned': return 'info';
            case 'maintenance': return 'warning';
            case 'inactive':
            case 'decommissioned': return 'error';
            default: return 'default';
        }
    };

    const getTypeLabel = (type: string) => {
        switch (type) {
            case 'pop': return 'POP';
            case 'tower': return 'Tower';
            case 'office': return 'Office';
            case 'warehouse': return 'Warehouse';
            case 'datacenter': return 'Datacenter';
            case 'customer_premises': return 'Customer Premises';
            case 'solar_site': return 'Solar Site';
            case 'repeater_site': return 'Repeater Site';
            default: return type || 'Other';
        }
    };

    const handleEditSuccess = () => {
        setFormOpen(false);
        queryClient.invalidateQueries({ queryKey: ['site', id] });
        queryClient.invalidateQueries({ queryKey: ['sites'] });
        toast.success('Site updated successfully');
    };

    const siteId = parseInt(id!);

    return (
        <Box sx={{ p: 3 }}>
            <PageHeader
                title={site.name || site.site_code || 'Site Details'}
                breadcrumbs={[
                    { label: 'Network', path: '/network' },
                    { label: 'Sites', path: '/network/sites' },
                    { label: site.site_code || 'Details' }
                ]}
                actions={
                    <Can permission="sites.update">
                        <Button
                            variant="contained"
                            startIcon={<Edit />}
                            onClick={() => setFormOpen(true)}
                        >
                            Edit Site
                        </Button>
                    </Can>
                }
            />

            <Grid container spacing={3} sx={{ mb: 3 }}>
                <Grid item xs={12} md={8}>
                    <Card>
                        <CardContent>
                            <Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 2 }}>
                                <Typography variant="h6">{site.site_code}</Typography>
                                <Chip
                                    label={getTypeLabel(site.type)}
                                    size="small"
                                    color="primary"
                                />
                                <Chip
                                    label={site.status || 'Unknown'}
                                    size="small"
                                    color={getStatusColor(site.status)}
                                />
                            </Stack>

                            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                                {site.description || 'No description provided.'}
                            </Typography>

                            <Divider sx={{ my: 2 }} />

                            <Grid container spacing={2}>
                                <Grid item xs={6}>
                                    <Typography variant="caption" color="text.secondary">Company</Typography>
                                    <Typography variant="body2">{site.company?.name || 'N/A'}</Typography>
                                </Grid>
                                <Grid item xs={6}>
                                    <Typography variant="caption" color="text.secondary">Region</Typography>
                                    <Typography variant="body2">{site.region?.name || 'N/A'}</Typography>
                                </Grid>
                                <Grid item xs={6}>
                                    <Typography variant="caption" color="text.secondary">Branch</Typography>
                                    <Typography variant="body2">{site.branch?.name || 'N/A'}</Typography>
                                </Grid>
                                <Grid item xs={6}>
                                    <Typography variant="caption" color="text.secondary">Type</Typography>
                                    <Typography variant="body2">{getTypeLabel(site.type)}</Typography>
                                </Grid>
                            </Grid>
                        </CardContent>
                    </Card>
                </Grid>

                <Grid item xs={12} md={4}>
                    <Card>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom>
                                <LocationOn fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />
                                Location
                            </Typography>
                            {site.latitude && site.longitude ? (
                                <>
                                    <Typography variant="body2">
                                        Lat: {formatCoordinate(site.latitude)}
                                    </Typography>
                                    <Typography variant="body2">
                                        Lng: {formatCoordinate(site.longitude)}
                                    </Typography>
                                    {site.altitude && (
                                        <Typography variant="body2">
                                            Alt: {formatCoordinate(site.altitude, 2)}m
                                        </Typography>
                                    )}
                                </>
                            ) : (
                                <Typography variant="body2" color="text.secondary">
                                    No GPS coordinates available
                                </Typography>
                            )}
                            <Divider sx={{ my: 2 }} />
                            <Typography variant="subtitle2" gutterBottom>
                                <Business fontSize="small" sx={{ mr: 1, verticalAlign: 'middle' }} />
                                Address
                            </Typography>
                            {site.address || site.municipality ? (
                                <>
                                    {site.address && (
                                        <Typography variant="body2">{site.address}</Typography>
                                    )}
                                    {site.municipality && (
                                        <Typography variant="body2">{site.municipality}</Typography>
                                    )}
                                    {site.district && (
                                        <Typography variant="body2">{site.district}</Typography>
                                    )}
                                    {site.province && (
                                        <Typography variant="body2">{site.province}</Typography>
                                    )}
                                </>
                            ) : (
                                <Typography variant="body2" color="text.secondary">
                                    No address provided
                                </Typography>
                            )}
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                <Tabs value={tabValue} onChange={(_, v) => setTabValue(v)}>
                    <Tab label="Assets" />
                    <Tab label="Overview" />
                    <Tab label="Photos" />
                    <Tab label="Integrations" />
                </Tabs>
            </Box>

            <TabPanel value={tabValue} index={0}>
                <SiteAssetsTab siteId={siteId} />
            </TabPanel>

            <TabPanel value={tabValue} index={1}>
                <Typography variant="body2" color="text.secondary">
                    Site overview information will be displayed here.
                </Typography>
            </TabPanel>

            <TabPanel value={tabValue} index={2}>
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

            <TabPanel value={tabValue} index={3}>
                <Typography variant="body2" color="text.secondary">
                    Integration status and settings will be displayed here.
                </Typography>
            </TabPanel>

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
