import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Dialog, DialogTitle, DialogContent, DialogActions,
    Button, TextField, FormControl, InputLabel, Select,
    MenuItem, Typography, Box, Stack, CircularProgress, FormHelperText
} from '@mui/material';
import { sitesApi } from '@/api/sites';
import { transferAsset } from '../api/assets';
import toast from 'react-hot-toast';

interface AssetTransferDialogProps {
    open: boolean;
    onClose: () => void;
    assetId: number;
    currentSiteName: string;
    currentSiteId: number;
    onSuccess: () => void;
}

export const AssetTransferDialog: React.FC<AssetTransferDialogProps> = ({
    open,
    onClose,
    assetId,
    currentSiteName,
    currentSiteId,
    onSuccess,
}) => {
    const queryClient = useQueryClient();
    const [toSiteId, setToSiteId] = useState<number | ''>('');
    const [notes, setNotes] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setToSiteId('');
            setNotes('');
            setErrors({});
        }
    }, [open]);

    const { data: sites, isLoading: sitesLoading } = useQuery({
        queryKey: ['sites', 'list'],
        queryFn: () => sitesApi.list({ per_page: 1000 }),
        enabled: open,
    });

    const transferMutation = useMutation({
        mutationFn: (data: { to_site_id: number; notes?: string }) =>
            transferAsset(assetId, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['asset-lifecycle', assetId] });
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['site-assets'] });
            toast.success('Asset transferred successfully');
            onSuccess();
            onClose();
        },
        onError: (err: any) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                toast.error(err.response?.data?.message || 'Transfer failed');
            }
        },
    });

    const handleTransfer = () => {
        if (!toSiteId) {
            toast.error('Please select a destination site');
            return;
        }
        if (toSiteId === currentSiteId) {
            toast.error('Asset is already at this site');
            return;
        }
        transferMutation.mutate({
            to_site_id: toSiteId as number,
            notes: notes || undefined,
        });
    };

    const availableSites = sites?.data?.filter((s: any) => s.id !== currentSiteId) || [];

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>Transfer Asset</DialogTitle>
            <DialogContent>
                <Box sx={{ mt: 1 }}>
                    <Stack spacing={2}>
                        <Box>
                            <Typography variant="caption" color="text.secondary">
                                Current Site
                            </Typography>
                            <Typography variant="body1">
                                {currentSiteName}
                            </Typography>
                        </Box>

                        <FormControl fullWidth error={!!errors.to_site_id}>
                            <InputLabel>Destination Site *</InputLabel>
                            <Select
                                value={toSiteId}
                                label="Destination Site *"
                                onChange={(e) => {
                                    setToSiteId(e.target.value as number);
                                    setErrors({});
                                }}
                                disabled={sitesLoading}
                            >
                                <MenuItem value="">
                                    <em>Select a site</em>
                                </MenuItem>
                                {availableSites.map((site: any) => (
                                    <MenuItem key={site.id} value={site.id}>
                                        {site.site_code} - {site.name}
                                    </MenuItem>
                                ))}
                            </Select>
                            {errors.to_site_id && (
                                <FormHelperText>{errors.to_site_id[0]}</FormHelperText>
                            )}
                            {sitesLoading && (
                                <Box sx={{ mt: 1, display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <CircularProgress size={16} />
                                    <Typography variant="caption">Loading sites...</Typography>
                                </Box>
                            )}
                            {availableSites.length === 0 && !sitesLoading && (
                                <FormHelperText>No other sites available</FormHelperText>
                            )}
                        </FormControl>

                        <TextField
                            label="Notes (Optional)"
                            multiline
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            fullWidth
                            placeholder="Reason for transfer..."
                        />
                    </Stack>
                </Box>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    onClick={handleTransfer}
                    disabled={transferMutation.isPending || !toSiteId || toSiteId === currentSiteId}
                >
                    {transferMutation.isPending ? 'Transferring...' : 'Transfer'}
                </Button>
            </DialogActions>
        </Dialog>
    );
};
