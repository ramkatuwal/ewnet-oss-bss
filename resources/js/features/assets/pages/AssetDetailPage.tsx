import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Card, CardContent, Grid, Chip, Stack,
    Tabs, Tab, CircularProgress, Button, Divider, IconButton,
    Tooltip, Alert
} from '@mui/material';
import { ArrowBack, Edit, Delete, SwapHoriz, Warning, DeleteForever } from '@mui/icons-material';
import { getAsset, deleteAsset } from '../api/assets';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { AssetLifecycleTimeline } from '../components/AssetLifecycleTimeline';
import { AssetTransferDialog } from '../components/AssetTransferDialog';
import { AssetStatusChangeDialog } from '../components/AssetStatusChangeDialog';
import { PhotoGallery } from '@/features/shared/components/PhotoGallery';
import AssetFormDrawer from '../components/AssetFormDrawer';
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

const STATUS_COLORS: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    'OPERATIONAL': 'success',
    'SPARE': 'info',
    'MAINTENANCE': 'warning',
    'FAULTY': 'error',
    'RETIRED': 'default',
    'MISSING': 'error',
    'DISPOSED': 'default',
};

const STATUS_LABELS: Record<string, string> = {
    'OPERATIONAL': 'Operational',
    'SPARE': 'Spare',
    'MAINTENANCE': 'Maintenance',
    'FAULTY': 'Faulty',
    'RETIRED': 'Retired',
    'MISSING': 'Missing',
    'DISPOSED': 'Disposed',
};

const CONDITION_LABELS: Record<string, string> = {
    'EXCELLENT': 'Excellent',
    'GOOD': 'Good',
    'FAIR': 'Fair',
    'POOR': 'Poor',
    'CRITICAL': 'Critical',
};

const AssetDetailPage: React.FC = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [tabValue, setTabValue] = useState(0);
    const [editOpen, setEditOpen] = useState(false);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [transferOpen, setTransferOpen] = useState(false);
    const [statusAction, setStatusAction] = useState<'retire' | 'dispose' | null>(null);

    const { data, isLoading, error } = useQuery({
        queryKey: ['asset', id],
        queryFn: () => getAsset(parseInt(id!)),
        enabled: !!id,
    });

    const deleteMutation = useMutation({
        mutationFn: deleteAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['asset-dashboard'] });
            toast.success('Asset deleted successfully');
            navigate('/network/assets');
        },
        onError: () => toast.error('Failed to delete asset'),
    });

    const asset = data;

    if (isLoading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error || !asset) {
        return (
            <Box sx={{ p: 3 }}>
                <Alert severity="error">Failed to load asset details.</Alert>
                <Button startIcon={<ArrowBack />} onClick={() => navigate('/network/assets')} sx={{ mt: 2 }}>
                    Back to Assets
                </Button>
            </Box>
        );
    }

    const handleDelete = () => {
        if (deleteId) {
            deleteMutation.mutate(deleteId);
        }
    };

    const handleTransferSuccess = () => {
        queryClient.invalidateQueries({ queryKey: ['asset', id] });
    };

    const handleStatusChangeSuccess = () => {
        queryClient.invalidateQueries({ queryKey: ['asset', id] });
        queryClient.invalidateQueries({ queryKey: ['asset-lifecycle', id] });
    };

    const assetId = parseInt(id!);
    const canTransfer = asset.status !== 'DISPOSED';
    const canRetire = asset.status !== 'RETIRED' && asset.status !== 'DISPOSED';
    const canDispose = asset.status === 'RETIRED';

    return (
        <Box sx={{ p: 3 }}>
            <PageHeader
                title={`${asset.asset_tag} - ${asset.type || 'Asset'}`}
                breadcrumbs={[
                    { label: 'Network', path: '/network' },
                    { label: 'Assets', path: '/network/assets' },
                    { label: asset.asset_tag || 'Details' }
                ]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Can permission="assets.transfer">
                            <Tooltip title="Transfer Asset">
                                <Button
                                    variant="outlined"
                                    startIcon={<SwapHoriz />}
                                    onClick={() => setTransferOpen(true)}
                                    disabled={!canTransfer}
                                >
                                    Transfer
                                </Button>
                            </Tooltip>
                        </Can>
                        <Can permission="assets.retire">
                            <Tooltip title="Retire Asset">
                                <Button
                                    variant="outlined"
                                    color="warning"
                                    startIcon={<Warning />}
                                    onClick={() => setStatusAction('retire')}
                                    disabled={!canRetire}
                                >
                                    Retire
                                </Button>
                            </Tooltip>
                        </Can>
                        <Can permission="assets.dispose">
                            <Tooltip title="Dispose Asset">
                                <Button
                                    variant="outlined"
                                    color="error"
                                    startIcon={<DeleteForever />}
                                    onClick={() => setStatusAction('dispose')}
                                    disabled={!canDispose}
                                >
                                    Dispose
                                </Button>
                            </Tooltip>
                        </Can>
                        <Can permission="assets.update">
                            <Button
                                variant="contained"
                                startIcon={<Edit />}
                                onClick={() => setEditOpen(true)}
                            >
                                Edit
                            </Button>
                        </Can>
                        <Can permission="assets.delete">
                            <IconButton color="error" onClick={() => setDeleteId(asset.id)}>
                                <Delete />
                            </IconButton>
                        </Can>
                    </Stack>
                }
            />

            <Grid container spacing={3} sx={{ mb: 3 }}>
                <Grid item xs={12} md={8}>
                    <Card>
                        <CardContent>
                            <Stack spacing={2}>
                                <Stack direction="row" spacing={1} flexWrap="wrap">
                                    <Chip
                                        label={asset.asset_tag}
                                        color="primary"
                                        size="small"
                                    />
                                    <Chip
                                        label={asset.category || 'Uncategorized'}
                                        size="small"
                                        variant="outlined"
                                    />
                                    <Chip
                                        label={asset.type || 'Unknown Type'}
                                        size="small"
                                        variant="outlined"
                                    />
                                    <Chip
                                        label={STATUS_LABELS[asset.status] || asset.status}
                                        size="small"
                                        color={STATUS_COLORS[asset.status] || 'default'}
                                    />
                                    {asset.condition && (
                                        <Chip
                                            label={CONDITION_LABELS[asset.condition] || asset.condition}
                                            size="small"
                                            variant="outlined"
                                        />
                                    )}
                                </Stack>

                                <Divider />

                                <Grid container spacing={2}>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Manufacturer</Typography>
                                        <Typography variant="body2">{asset.manufacturer || 'N/A'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Model</Typography>
                                        <Typography variant="body2">{asset.model || 'N/A'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Serial Number</Typography>
                                        <Typography variant="body2">{asset.serial_number || 'N/A'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Quantity</Typography>
                                        <Typography variant="body2">{asset.quantity} {asset.unit || 'pcs'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Site</Typography>
                                        <Typography variant="body2">{asset.site?.site_code || 'N/A'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Company</Typography>
                                        <Typography variant="body2">{asset.site?.company?.name || 'N/A'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Region</Typography>
                                        <Typography variant="body2">{asset.site?.region?.name || 'N/A'}</Typography>
                                    </Grid>
                                    <Grid item xs={6}>
                                        <Typography variant="caption" color="text.secondary">Branch</Typography>
                                        <Typography variant="body2">{asset.site?.branch?.name || 'N/A'}</Typography>
                                    </Grid>
                                    {asset.purchase_date && (
                                        <Grid item xs={6}>
                                            <Typography variant="caption" color="text.secondary">Purchase Date</Typography>
                                            <Typography variant="body2">{new Date(asset.purchase_date).toLocaleDateString()}</Typography>
                                        </Grid>
                                    )}
                                    {asset.installation_date && (
                                        <Grid item xs={6}>
                                            <Typography variant="caption" color="text.secondary">Installation Date</Typography>
                                            <Typography variant="body2">{new Date(asset.installation_date).toLocaleDateString()}</Typography>
                                        </Grid>
                                    )}
                                    {asset.warranty_expiry && (
                                        <Grid item xs={6}>
                                            <Typography variant="caption" color="text.secondary">Warranty Expiry</Typography>
                                            <Typography variant="body2">{new Date(asset.warranty_expiry).toLocaleDateString()}</Typography>
                                        </Grid>
                                    )}
                                    {asset.description && (
                                        <Grid item xs={12}>
                                            <Typography variant="caption" color="text.secondary">Description</Typography>
                                            <Typography variant="body2">{asset.description}</Typography>
                                        </Grid>
                                    )}
                                    {asset.notes && (
                                        <Grid item xs={12}>
                                            <Typography variant="caption" color="text.secondary">Notes</Typography>
                                            <Typography variant="body2">{asset.notes}</Typography>
                                        </Grid>
                                    )}
                                </Grid>
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                <Tabs value={tabValue} onChange={(_, v) => setTabValue(v)}>
                    <Tab label="Lifecycle" />
                    <Tab label="Details" />
                    <Tab label="Photos" />
                </Tabs>
            </Box>

            <TabPanel value={tabValue} index={0}>
                <Can permission="assets.lifecycle.view">
                    <AssetLifecycleTimeline assetId={assetId} />
                </Can>
            </TabPanel>

            <TabPanel value={tabValue} index={1}>
                <Typography variant="body2" color="text.secondary">
                    Additional asset details will be displayed here.
                </Typography>
            </TabPanel>

            <TabPanel value={tabValue} index={2}>
                <Can permission="assets.update">
                    <PhotoGallery
                        entityType="asset"
                        entityId={assetId}
                        getPhotosUrl={`/api/v1/assets/${assetId}/photos`}
                        uploadUrl={`/api/v1/assets/${assetId}/photos`}
                        deleteUrl={(photoId: number) => `/api/v1/assets/${assetId}/photos/${photoId}`}
                        categories={['asset', 'documentation', 'label', 'installation', 'other']}
                        defaultCategory="asset"
                        canUpload={true}
                        canDelete={true}
                    />
                </Can>
            </TabPanel>

            <AssetFormDrawer
                open={editOpen}
                onClose={() => setEditOpen(false)}
                assetId={assetId}
            />

            <AssetTransferDialog
                open={transferOpen}
                onClose={() => setTransferOpen(false)}
                assetId={assetId}
                currentSiteName={asset.site?.site_code || 'Unknown'}
                currentSiteId={asset.site_id}
                onSuccess={handleTransferSuccess}
            />

            <AssetStatusChangeDialog
                open={statusAction !== null}
                onClose={() => setStatusAction(null)}
                assetId={assetId}
                currentStatus={asset.status}
                action={statusAction}
                onSuccess={handleStatusChangeSuccess}
            />

            <ConfirmDialog
                open={!!deleteId}
                title="Delete Asset"
                message="Are you sure you want to delete this asset? This action cannot be undone."
                onConfirm={handleDelete}
                onCancel={() => setDeleteId(null)}
            />
        </Box>
    );
};

export default AssetDetailPage;
