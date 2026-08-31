import React, { useState } from 'react';
import { useParams, useNavigate, Link as RouterLink } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Card, CardContent, Grid, Chip, Stack,
    Tabs, Tab, CircularProgress, Button, IconButton,
    Tooltip, Alert, Link,
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
    OPERATIONAL: 'success',
    SPARE: 'info',
    MAINTENANCE: 'warning',
    FAULTY: 'error',
    RETIRED: 'default',
    MISSING: 'error',
    DISPOSED: 'default',
};

const CONDITION_COLORS: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    EXCELLENT: 'success',
    GOOD: 'success',
    FAIR: 'warning',
    POOR: 'error',
    CRITICAL: 'error',
};

const DetailField: React.FC<{ label: string; value?: React.ReactNode; mono?: boolean }> = ({ label, value, mono }) => (
    <Box>
        <Typography variant="caption" color="text.secondary">{label}</Typography>
        <Typography variant="body2" sx={mono ? { fontFamily: 'monospace', fontSize: '0.85rem' } : undefined}>
            {value || '—'}
        </Typography>
    </Box>
);

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

    if (isLoading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error || !data) {
        return (
            <Box sx={{ p: 3 }}>
                <Alert severity="error">Unable to load asset details.</Alert>
                <Button startIcon={<ArrowBack />} onClick={() => navigate('/network/assets')} sx={{ mt: 2 }}>
                    Back to Assets
                </Button>
            </Box>
        );
    }

    const asset = data as any;
    const assetId = parseInt(id!);
    const canTransfer = asset.status !== 'DISPOSED';
    const canRetire = asset.status !== 'RETIRED' && asset.status !== 'DISPOSED';
    const canDispose = asset.status === 'RETIRED';

    return (
        <Box sx={{ p: 3, maxWidth: 1400, mx: 'auto' }}>
            <PageHeader
                title={`${asset.asset_tag}`}
                breadcrumbs={[
                    { label: 'Network', path: '/network' },
                    { label: 'Assets', path: '/network/assets' },
                    { label: asset.asset_tag },
                ]}
                actions={
                    <Stack direction="row" spacing={1}>
                        <Can permission="assets.transfer">
                            <Tooltip title="Transfer to another site">
                                <span>
                                    <Button variant="outlined" startIcon={<SwapHoriz />} onClick={() => setTransferOpen(true)} disabled={!canTransfer}>
                                        Transfer
                                    </Button>
                                </span>
                            </Tooltip>
                        </Can>
                        <Can permission="assets.retire">
                            <Tooltip title="Retire this asset">
                                <span>
                                    <Button variant="outlined" color="warning" startIcon={<Warning />} onClick={() => setStatusAction('retire')} disabled={!canRetire}>
                                        Retire
                                    </Button>
                                </span>
                            </Tooltip>
                        </Can>
                        <Can permission="assets.dispose">
                            <Tooltip title="Dispose of this asset">
                                <span>
                                    <Button variant="outlined" color="error" startIcon={<DeleteForever />} onClick={() => setStatusAction('dispose')} disabled={!canDispose}>
                                        Dispose
                                    </Button>
                                </span>
                            </Tooltip>
                        </Can>
                        <Can permission="assets.update">
                            <Button variant="contained" startIcon={<Edit />} onClick={() => setEditOpen(true)}>Edit</Button>
                        </Can>
                        <Can permission="assets.delete">
                            <IconButton color="error" onClick={() => setDeleteId(asset.id)}><Delete /></IconButton>
                        </Can>
                    </Stack>
                }
            />

            {/* Status Bar */}
            <Stack direction="row" spacing={1} flexWrap="wrap" sx={{ mb: 3 }}>
                <Chip label={asset.category} size="small" variant="outlined" />
                <Chip label={asset.type} size="small" variant="outlined" />
                <Chip label={asset.status} size="small" color={STATUS_COLORS[asset.status] || 'default'} />
                {asset.condition && (
                    <Chip label={asset.condition} size="small" color={CONDITION_COLORS[asset.condition] || 'default'} variant="outlined" />
                )}
                <Chip label={`${asset.quantity} ${asset.unit || 'pcs'}`} size="small" variant="outlined" />
            </Stack>

            <Grid container spacing={3} sx={{ mb: 3 }}>
                {/* Left Column: Overview + Organization */}
                <Grid item xs={12} md={4}>
                    <Card sx={{ mb: 2 }}>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom>Overview</Typography>
                            <Stack spacing={1.5}>
                                <DetailField label="Asset Tag" value={asset.asset_tag} mono />
                                <DetailField label="Serial Number" value={asset.serial_number} mono />
                                <DetailField label="Manufacturer" value={asset.manufacturer} />
                                <DetailField label="Model" value={asset.model} />
                                <DetailField label="Category" value={asset.category} />
                                <DetailField label="Type" value={asset.type} />
                                <DetailField label="Condition" value={asset.condition} />
                                <DetailField label="Quantity" value={`${asset.quantity} ${asset.unit || 'pcs'}`} />
                            </Stack>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom>Organization</Typography>
                            <Stack spacing={1.5}>
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Site</Typography>
                                    {asset.site ? (
                                        <Link component={RouterLink} to={`/network/sites/${asset.site.id}`} underline="hover" display="block">
                                            <Typography variant="body2" fontWeight="medium">{asset.site.site_code}</Typography>
                                            <Typography variant="caption" color="text.secondary">{asset.site.name}</Typography>
                                        </Link>
                                    ) : (
                                        <Typography variant="body2">—</Typography>
                                    )}
                                </Box>
                                <DetailField label="Company" value={asset.site?.company?.name} />
                                <DetailField label="Region" value={asset.site?.region?.name} />
                                <DetailField label="Branch" value={asset.site?.branch?.name} />
                            </Stack>
                        </CardContent>
                    </Card>
                </Grid>

                {/* Right Column: Dates + Description + Tabs */}
                <Grid item xs={12} md={8}>
                    <Card sx={{ mb: 2 }}>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom>Lifecycle & Warranty</Typography>
                            <Grid container spacing={2}>
                                <Grid item xs={6} sm={4}><DetailField label="Purchase Date" value={asset.purchase_date ? new Date(asset.purchase_date).toLocaleDateString() : undefined} /></Grid>
                                <Grid item xs={6} sm={4}><DetailField label="Installation Date" value={asset.installation_date ? new Date(asset.installation_date).toLocaleDateString() : undefined} /></Grid>
                                <Grid item xs={6} sm={4}><DetailField label="Warranty Expiry" value={asset.warranty_expiry ? new Date(asset.warranty_expiry).toLocaleDateString() : undefined} /></Grid>
                                <Grid item xs={6} sm={4}><DetailField label="Created" value={asset.created_at ? new Date(asset.created_at).toLocaleDateString() : undefined} /></Grid>
                                <Grid item xs={6} sm={4}><DetailField label="Updated" value={asset.updated_at ? new Date(asset.updated_at).toLocaleDateString() : undefined} /></Grid>
                            </Grid>
                            {asset.description && (
                                <Box sx={{ mt: 2 }}>
                                    <Typography variant="caption" color="text.secondary">Description</Typography>
                                    <Typography variant="body2">{asset.description}</Typography>
                                </Box>
                            )}
                            {asset.notes && (
                                <Box sx={{ mt: 1 }}>
                                    <Typography variant="caption" color="text.secondary">Notes</Typography>
                                    <Typography variant="body2">{asset.notes}</Typography>
                                </Box>
                            )}
                        </CardContent>
                    </Card>

                    {/* Tabs */}
                    <Card>
                        <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                            <Tabs value={tabValue} onChange={(_, v) => setTabValue(v)}>
                                <Tab label="Lifecycle History" />
                                <Tab label="Photos" />
                            </Tabs>
                        </Box>
                        <TabPanel value={tabValue} index={0}>
                            <Can permission="assets.lifecycle.view">
                                <AssetLifecycleTimeline assetId={assetId} />
                            </Can>
                        </TabPanel>
                        <TabPanel value={tabValue} index={1}>
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
                    </Card>
                </Grid>
            </Grid>

            {/* Dialogs */}
            <AssetFormDrawer open={editOpen} onClose={() => setEditOpen(false)} assetId={assetId} />
            <AssetTransferDialog
                open={transferOpen}
                onClose={() => setTransferOpen(false)}
                assetId={assetId}
                currentSiteName={asset.site?.site_code || 'Unknown'}
                currentSiteId={asset.site_id}
                onSuccess={() => { queryClient.invalidateQueries({ queryKey: ['asset', id] }); }}
            />
            <AssetStatusChangeDialog
                open={statusAction !== null}
                onClose={() => setStatusAction(null)}
                assetId={assetId}
                currentStatus={asset.status}
                action={statusAction}
                onSuccess={() => {
                    queryClient.invalidateQueries({ queryKey: ['asset', id] });
                    queryClient.invalidateQueries({ queryKey: ['asset-lifecycle', id] });
                }}
            />
            <ConfirmDialog
                open={!!deleteId}
                title="Delete Asset"
                message="Are you sure you want to delete this asset? This action cannot be undone."
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />
        </Box>
    );
};

export default AssetDetailPage;
