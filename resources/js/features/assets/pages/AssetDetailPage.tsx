import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Typography, Card, CardContent, Grid, Chip, Stack,
    Tabs, Tab, CircularProgress, Button, IconButton,
    Tooltip, Alert,
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
import { AssetInterfacesTab } from '../components/assetTabs/AssetInterfacesTab';
import { AssetIpAddressesTab } from '../components/assetTabs/AssetIpAddressesTab';
import { AssetFimTab } from '../components/assetTabs/AssetFimTab';
import { AssetPonTab } from '../components/assetTabs/AssetPonTab';
import { AssetVlanTab } from '../components/assetTabs/AssetVlanTab';
import { AssetRoutingTab } from '../components/assetTabs/AssetRoutingTab';
import { AssetAuditTab } from '../components/assetTabs/AssetAuditTab';
import AssetFormDrawer from '../components/AssetFormDrawer';
import toast from 'react-hot-toast';
import { infrastructureKeys } from '@/api/queryKeys';
import { AuthorityBadge } from '@/components/infrastructure/AuthorityBadge';
import { NetworkPortPreview } from '../components/NetworkPortPreview';

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
            {value || '\u2014'}
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
        queryKey: infrastructureKeys.asset(id!),
        queryFn: () => getAsset(parseInt(id!)),
        enabled: !!id,
    });

    const deleteMutation = useMutation({
        mutationFn: deleteAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'assets'] });
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
    const portsCount = asset.network_ports?.length ?? 0;
    const interfacesCount = asset.interfaces?.length ?? 0;
    const canTransfer = asset.status !== 'DISPOSED';
    const canRetire = asset.status !== 'RETIRED' && asset.status !== 'DISPOSED';
    const canDispose = asset.status === 'RETIRED';

    const hasFim = asset.passive_optical_ports?.length > 0 || asset.splitter_profile;
    const hasPon = asset.pon_memberships?.length > 0;

    const specs = asset.specifications || {};
    const isImported = specs.source === 'librenms' || specs.source === 'uisp';

    return (
        <Box sx={{ p: 3, maxWidth: 1400, mx: 'auto' }}>
            <PageHeader
                title={asset.device_name || asset.asset_tag}
                breadcrumbs={[
                    { label: 'Network', path: '/network' },
                    { label: 'Assets', path: '/network/assets' },
                    { label: asset.device_name || asset.asset_tag },
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
                <Chip label={asset.asset_tag} size="small" variant="outlined" sx={{ fontFamily: 'monospace' }} />
                <Chip label={asset.category} size="small" variant="outlined" />
                <Chip label={asset.type} size="small" variant="outlined" />
                <Chip label={asset.status} size="small" color={STATUS_COLORS[asset.status] || 'default'} />
                {asset.condition && (
                    <Chip label={asset.condition} size="small" color={CONDITION_COLORS[asset.condition] || 'default'} variant="outlined" />
                )}
                <Chip label={`${asset.quantity} ${asset.unit || 'pcs'}`} size="small" variant="outlined" />
                {portsCount > 0 && <Chip label={`${portsCount} Ports`} size="small" variant="outlined" />}
                {isImported && (
                    <Chip label={`Imported from ${specs.source}`} size="small" color="info" variant="outlined" />
                )}
                <AuthorityBadge authoritative />
            </Stack>

            <Grid container spacing={3} sx={{ mb: 3 }}>
                {/* Left Column */}
                <Grid item xs={12} md={4}>
                    {/* Identity */}
                    <Card sx={{ mb: 2 }}>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom>Identity</Typography>
                            <Stack spacing={1.5}>
                                <DetailField label="Asset Code" value={asset.asset_tag} mono />
                                <DetailField label="Device Name" value={asset.device_name} />
                                <DetailField label="Serial Number" value={asset.serial_number} mono />
                                <DetailField label="Category" value={asset.category} />
                                <DetailField label="Type" value={asset.type} />
                                <DetailField label="Quantity" value={`${asset.quantity} ${asset.unit || 'pcs'}`} />
                            </Stack>
                        </CardContent>
                    </Card>

                    {/* Placement */}
                    <Card sx={{ mb: 2 }}>
                        <CardContent>
                            <Typography variant="subtitle2" gutterBottom>Placement</Typography>
                            <Stack spacing={1.5}>
                                <Box>
                                    <Typography variant="caption" color="text.secondary">Site</Typography>
                                    {asset.site ? (
                                        <Box
                                            component="span"
                                            onClick={() => navigate(`/network/sites/${asset.site.id}`)}
                                            sx={{ cursor: 'pointer', '&:hover': { textDecoration: 'underline' } }}
                                        >
                                            <Typography variant="body2" fontWeight="medium">{asset.site.site_code}</Typography>
                                            <Typography variant="caption" color="text.secondary">{asset.site.name}</Typography>
                                        </Box>
                                    ) : (
                                        <Typography variant="body2">{'\u2014'}</Typography>
                                    )}
                                </Box>
                                <DetailField label="Company" value={asset.site?.company?.name} />
                                <DetailField label="Region" value={asset.site?.region?.name} />
                                <DetailField label="Branch" value={asset.site?.branch?.name} />
                            </Stack>
                        </CardContent>
                    </Card>

                    {/* Authoritative Hardware */}
                    <Card sx={{ mb: 2 }}>
                        <CardContent>
                            <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                                <Typography variant="subtitle2">Hardware</Typography>
                                <AuthorityBadge authoritative />
                            </Stack>
                            <Stack spacing={1.5}>
                                <DetailField label="Manufacturer" value={asset.manufacturer} />
                                <DetailField label="Model" value={asset.model} />
                                <DetailField label="Condition" value={asset.condition} />
                            </Stack>
                        </CardContent>
                    </Card>

                    {/* Observed Device / LibreNMS */}
                    {isImported && (
                        <Card sx={{ mb: 2 }}>
                            <CardContent>
                                <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                                    <Typography variant="subtitle2">Observed Device</Typography>
                                    <AuthorityBadge authoritative={false} />
                                    <Chip label="OBSERVED" size="small" color="info" variant="outlined" sx={{ fontSize: '0.65rem', height: 20 }} />
                                </Stack>
                                <Stack spacing={1.5}>
                                    <DetailField label="Provider" value={specs.source ? String(specs.source).toUpperCase() : undefined} />
                                    {specs.external_id && (
                                        <DetailField label="Provider Device ID" value={specs.external_id} mono />
                                    )}
                                    {specs.observed_hostname && (
                                        <DetailField label="Observed Hostname" value={specs.observed_hostname} mono />
                                    )}
                                    {specs.ip_address && (
                                        <DetailField label="Management IP" value={specs.ip_address} mono />
                                    )}
                                    {specs.observed_os && (
                                        <DetailField label="OS / Platform" value={specs.observed_os} />
                                    )}
                                    {specs.observed_hardware && (
                                        <DetailField label="Hardware" value={specs.observed_hardware} />
                                    )}
                                    {specs.observed_version && (
                                        <DetailField label="Version" value={specs.observed_version} />
                                    )}
                                    {specs.provider_status && (
                                        <Box>
                                            <Typography variant="caption" color="text.secondary">Observed Status</Typography>
                                            <Chip
                                                label={specs.provider_status}
                                                size="small"
                                                color={specs.provider_status === 'UP' ? 'success' : specs.provider_status === 'DOWN' ? 'error' : 'warning'}
                                                sx={{ fontSize: '0.7rem', height: 22, mt: 0.5 }}
                                            />
                                        </Box>
                                    )}
                                    {specs.observed_uptime != null && (
                                        <DetailField label="Uptime" value={`${Math.floor(Number(specs.observed_uptime) / 86400)}d ${Math.floor((Number(specs.observed_uptime) % 86400) / 3600)}h`} />
                                    )}
                                    {specs.serial_number && (
                                        <DetailField label="Observed Serial" value={specs.serial_number} mono />
                                    )}
                                    {specs.mac_address && (
                                        <DetailField label="MAC Address" value={specs.mac_address} mono />
                                    )}
                                    {specs.last_observed_at && (
                                        <DetailField label="Last Observed" value={new Date(specs.last_observed_at).toLocaleString()} />
                                    )}
                                    {specs.last_synced && (
                                        <DetailField label="Last Synced" value={new Date(specs.last_synced).toLocaleString()} />
                                    )}
                                </Stack>
                            </CardContent>
                        </Card>
                    )}

                    {/* Network Observations (non-imported assets with IP/MAC) */}
                    {!isImported && (asset.ip_address || asset.mac_address) && (
                        <Card sx={{ mb: 2 }}>
                            <CardContent>
                                <Stack direction="row" spacing={1} alignItems="center" sx={{ mb: 1 }}>
                                    <Typography variant="subtitle2">Network Observations</Typography>
                                    <AuthorityBadge authoritative={false} />
                                </Stack>
                                <DetailField label="Primary IP" value={asset.ip_address} mono />
                                <Box sx={{ mt: 1 }}><DetailField label="Primary MAC" value={asset.mac_address} mono /></Box>
                            </CardContent>
                        </Card>
                    )}
                </Grid>

                {/* Right Column */}
                <Grid item xs={12} md={8}>
                    {/* Lifecycle & Warranty */}
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
                                {asset.category === 'NETWORK' && <Tab label="Network Ports" />}
                                {interfacesCount > 0 && <Tab label="Interfaces" />}
                                {interfacesCount > 0 && <Tab label="IP Addresses" />}
                                {hasFim && <Tab label="FIM" />}
                                {hasPon && <Tab label="PON" />}
                                <Tab label="VLAN" />
                                {asset.routing_instances?.length > 0 && <Tab label="Routing" />}
                                <Tab label="Audit" />
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
                        {asset.category === 'NETWORK' && (
                            <TabPanel value={tabValue} index={2}>
                                <NetworkPortPreview assetId={assetId} />
                            </TabPanel>
                        )}
                        {interfacesCount > 0 && (
                            <TabPanel value={tabValue} index={asset.category === 'NETWORK' ? 3 : 2}>
                                <AssetInterfacesTab interfaces={asset.interfaces ?? []} />
                            </TabPanel>
                        )}
                        {interfacesCount > 0 && (
                            <TabPanel value={tabValue} index={asset.category === 'NETWORK' ? 4 : 3}>
                                <AssetIpAddressesTab addresses={asset.ip_addresses ?? []} />
                            </TabPanel>
                        )}
                        {hasFim && (
                            <TabPanel value={tabValue} index={asset.category === 'NETWORK' ? 5 : 4}>
                                <AssetFimTab passivePorts={asset.passive_optical_ports ?? []} splitter={asset.splitter_profile ?? null} />
                            </TabPanel>
                        )}
                        {hasPon && (
                            <TabPanel value={tabValue} index={asset.category === 'NETWORK' ? 6 : 5}>
                                <AssetPonTab memberships={asset.pon_memberships ?? []} />
                            </TabPanel>
                        )}
                        <TabPanel value={tabValue} index={asset.category === 'NETWORK' ? (hasFim ? 7 : (hasPon ? 6 : 5)) : (hasFim ? 5 : (hasPon ? 4 : 3))}>
                            <AssetVlanTab assetId={assetId} />
                        </TabPanel>
                        {asset.routing_instances?.length > 0 && (
                            <TabPanel value={tabValue} index={asset.category === 'NETWORK' ? 8 : 6}>
                                <AssetRoutingTab instances={asset.routing_instances ?? []} />
                            </TabPanel>
                        )}
                        <TabPanel value={tabValue} index={tabValue}>
                            <AssetAuditTab assetId={assetId} />
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
