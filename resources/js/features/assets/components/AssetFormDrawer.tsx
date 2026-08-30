import React, { useEffect, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Drawer, Box, Typography, TextField, Button,
    MenuItem, Grid, FormControl, InputLabel, Select,
    FormHelperText, Stack, Chip
} from '@mui/material';
import { createAsset, updateAsset, getAsset } from '../api/assets';
import { useQuery } from '@tanstack/react-query';
import { sitesApi } from '@/api/sites';
import toast from 'react-hot-toast';

interface Props {
    open: boolean;
    onClose: () => void;
    assetId: number | null;
}

interface SiteWithRelations {
    id: number;
    site_code: string;
    name: string;
    company?: { id: number; name: string };
    region?: { id: number; name: string };
    branch?: { id: number; name: string };
}

const AssetFormDrawer: React.FC<Props> = ({ open, onClose, assetId }) => {
    const queryClient = useQueryClient();
    const [formData, setFormData] = useState<any>({
        asset_tag: '',
        type: '',
        category: 'POWER',
        quantity: 1,
        status: 'OPERATIONAL',
        unit: 'pcs',
        site_id: undefined,
    });
    const [selectedSite, setSelectedSite] = useState<SiteWithRelations | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Load asset data when editing
    useEffect(() => {
        if (assetId && open) {
            getAsset(assetId).then((data: any) => {
                setFormData(data);
                // Load site details with relationships
                if (data.site_id) {
                    sitesApi.get(data.site_id).then((site: SiteWithRelations) => {
                        setSelectedSite(site);
                    });
                }
            }).catch(() => {
                toast.error('Failed to load asset data');
            });
        } else {
            setFormData({
                asset_tag: '',
                type: '',
                category: 'POWER',
                quantity: 1,
                status: 'OPERATIONAL',
                unit: 'pcs',
                site_id: undefined,
            });
            setSelectedSite(null);
        }
        setErrors({});
    }, [assetId, open]);

    // Queries for site list (scope-filtered by backend)
    const { data: sites } = useQuery({
        queryKey: ['sites', 'list'],
        queryFn: () => sitesApi.list({ per_page: 1000 }),
        enabled: open,
    });

    // Load site details when site_id changes
    const { data: siteDetails } = useQuery({
        queryKey: ['site', formData.site_id],
        queryFn: () => sitesApi.get(formData.site_id as number),
        enabled: !!formData.site_id && open,
    });

    // Update selectedSite when siteDetails changes
    useEffect(() => {
        if (siteDetails) {
            setSelectedSite(siteDetails);
        }
    }, [siteDetails]);

    const mutation = useMutation({
        mutationFn: assetId ? (data: any) => updateAsset(assetId, data) : createAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['asset-dashboard'] });
            queryClient.invalidateQueries({ queryKey: ['site-assets'] });
            toast.success(assetId ? 'Asset updated' : 'Asset created');
            onClose();
        },
        onError: (err: any) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                toast.error(err.response?.data?.message || 'Operation failed');
            }
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Validate site is selected
        if (!formData.site_id) {
            toast.error('Please select a Site for this asset.');
            return;
        }
        mutation.mutate(formData);
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ width: 550 }}>
            <Box sx={{ width: 550, p: 3 }}>
                <Typography variant="h6" sx={{ mb: 2 }}>{assetId ? 'Edit Asset' : 'Add Asset'}</Typography>
                <form onSubmit={handleSubmit}>
                    <Grid container spacing={2}>
                        {/* Site Selection - ONLY Site */}
                        <Grid item xs={12}>
                            <Typography variant="subtitle2" sx={{ mb: 1, color: 'text.secondary' }}>
                                Site Location
                            </Typography>
                        </Grid>
                        <Grid item xs={12}>
                            <FormControl fullWidth error={!!errors.site_id} required>
                                <InputLabel>Site *</InputLabel>
                                <Select
                                    value={formData.site_id || ''}
                                    label="Site *"
                                    onChange={(e) => {
                                        const val = e.target.value as number | '';
                                        setFormData({ ...formData, site_id: val || undefined });
                                        setSelectedSite(null);
                                    }}
                                >
                                    <MenuItem value=""><em>Select a site</em></MenuItem>
                                    {sites?.data?.map((s: any) => (
                                        <MenuItem key={s.id} value={s.id}>
                                            {s.site_code} - {s.name}
                                        </MenuItem>
                                    ))}
                                </Select>
                                {errors.site_id && <FormHelperText>{errors.site_id[0]}</FormHelperText>}
                            </FormControl>
                        </Grid>

                        {/* Read-only hierarchy display */}
                        {selectedSite && (
                            <Grid item xs={12}>
                                <Stack direction="row" spacing={1} flexWrap="wrap" sx={{ mt: 0.5 }}>
                                    <Chip
                                        label={`Company: ${selectedSite.company?.name || 'N/A'}`}
                                        size="small"
                                        variant="outlined"
                                    />
                                    <Chip
                                        label={`Region: ${selectedSite.region?.name || 'N/A'}`}
                                        size="small"
                                        variant="outlined"
                                    />
                                    <Chip
                                        label={`Branch: ${selectedSite.branch?.name || 'N/A'}`}
                                        size="small"
                                        variant="outlined"
                                    />
                                </Stack>
                            </Grid>
                        )}

                        {/* Asset Identification */}
                        <Grid item xs={12}>
                            <Typography variant="subtitle2" sx={{ mt: 1, mb: 1, color: 'text.secondary' }}>
                                Asset Details
                            </Typography>
                        </Grid>
                        <Grid item xs={12}>
                            <TextField
                                fullWidth
                                label="Asset Tag"
                                required
                                value={formData.asset_tag || ''}
                                onChange={e => setFormData({...formData, asset_tag: e.target.value})}
                                error={!!errors.asset_tag}
                                helperText={errors.asset_tag?.[0]}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <FormControl fullWidth error={!!errors.category}>
                                <InputLabel>Category *</InputLabel>
                                <Select
                                    value={formData.category || ''}
                                    label="Category *"
                                    onChange={e => setFormData({...formData, category: e.target.value})}
                                >
                                    <MenuItem value="POWER">Power</MenuItem>
                                    <MenuItem value="NETWORK">Network</MenuItem>
                                    <MenuItem value="INFRASTRUCTURE">Infrastructure</MenuItem>
                                    <MenuItem value="OTHER">Other</MenuItem>
                                </Select>
                                {errors.category && <FormHelperText>{errors.category[0]}</FormHelperText>}
                            </FormControl>
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                fullWidth
                                label="Type"
                                required
                                value={formData.type || ''}
                                onChange={e => setFormData({...formData, type: e.target.value})}
                                error={!!errors.type}
                                helperText={errors.type?.[0]}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                fullWidth
                                label="Manufacturer"
                                value={formData.manufacturer || ''}
                                onChange={e => setFormData({...formData, manufacturer: e.target.value})}
                                error={!!errors.manufacturer}
                                helperText={errors.manufacturer?.[0]}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                fullWidth
                                label="Model"
                                value={formData.model || ''}
                                onChange={e => setFormData({...formData, model: e.target.value})}
                                error={!!errors.model}
                                helperText={errors.model?.[0]}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                fullWidth
                                label="Serial Number"
                                value={formData.serial_number || ''}
                                onChange={e => setFormData({...formData, serial_number: e.target.value})}
                                error={!!errors.serial_number}
                                helperText={errors.serial_number?.[0]}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                fullWidth
                                label="Quantity"
                                type="number"
                                value={formData.quantity || 1}
                                onChange={e => setFormData({...formData, quantity: parseInt(e.target.value) || 1})}
                                error={!!errors.quantity}
                                helperText={errors.quantity?.[0]}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField
                                fullWidth
                                label="Unit"
                                value={formData.unit || 'pcs'}
                                onChange={e => setFormData({...formData, unit: e.target.value})}
                            />
                        </Grid>
                        <Grid item xs={6}>
                            <FormControl fullWidth error={!!errors.status}>
                                <InputLabel>Status *</InputLabel>
                                <Select
                                    value={formData.status || ''}
                                    label="Status *"
                                    onChange={e => setFormData({...formData, status: e.target.value})}
                                >
                                    <MenuItem value="OPERATIONAL">Operational</MenuItem>
                                    <MenuItem value="SPARE">Spare</MenuItem>
                                    <MenuItem value="MAINTENANCE">Maintenance</MenuItem>
                                    <MenuItem value="FAULTY">Faulty</MenuItem>
                                    <MenuItem value="RETIRED">Retired</MenuItem>
                                    <MenuItem value="MISSING">Missing</MenuItem>
                                    <MenuItem value="DISPOSED">Disposed</MenuItem>
                                </Select>
                                {errors.status && <FormHelperText>{errors.status[0]}</FormHelperText>}
                            </FormControl>
                        </Grid>
                        <Grid item xs={6}>
                            <FormControl fullWidth error={!!errors.condition}>
                                <InputLabel>Condition</InputLabel>
                                <Select
                                    value={formData.condition || ''}
                                    label="Condition"
                                    onChange={e => setFormData({...formData, condition: e.target.value})}
                                >
                                    <MenuItem value="">None</MenuItem>
                                    <MenuItem value="EXCELLENT">Excellent</MenuItem>
                                    <MenuItem value="GOOD">Good</MenuItem>
                                    <MenuItem value="FAIR">Fair</MenuItem>
                                    <MenuItem value="POOR">Poor</MenuItem>
                                    <MenuItem value="CRITICAL">Critical</MenuItem>
                                </Select>
                                {errors.condition && <FormHelperText>{errors.condition[0]}</FormHelperText>}
                            </FormControl>
                        </Grid>
                    </Grid>
                    <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
                        <Button onClick={onClose}>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={mutation.isPending}>
                            {mutation.isPending ? 'Saving...' : 'Save'}
                        </Button>
                    </Box>
                </form>
            </Box>
        </Drawer>
    );
};

export default AssetFormDrawer;
