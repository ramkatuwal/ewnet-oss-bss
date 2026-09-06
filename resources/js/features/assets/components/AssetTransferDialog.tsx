import React, { useState, useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Dialog, DialogTitle, DialogContent, DialogActions,
    Button, TextField, Typography, Box, Stack
} from '@mui/material';
import { sitesApi } from '@/api/sites';
import SearchableSelect, { SearchableSelectOption } from '@/components/forms/SearchableSelect';
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

interface SiteOption extends SearchableSelectOption<number> {
    siteCode: string;
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

    // Type-ahead server-side lookup for destination sites (excludes the current site)
    const loadSiteOptions = async (query: string): Promise<SiteOption[]> => {
        const res = await sitesApi.list({ search: query || undefined, per_page: 50 });
        return (res.data || [])
            .filter((s) => s.id !== currentSiteId)
            .map((s) => ({
                value: s.id,
                label: `${s.site_code} — ${s.name}`,
                secondary: s.address || undefined,
                siteCode: s.site_code,
            }));
    };

    const mutation = useMutation({
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
        mutation.mutate({
            to_site_id: toSiteId as number,
            notes: notes || undefined,
        });
    };

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

                        <SearchableSelect<SiteOption>
                            label="Destination Site *"
                            value={null}
                            onChange={(option) => {
                                setToSiteId(option ? option.value : '');
                                setErrors({});
                            }}
                            loadOptions={loadSiteOptions}
                            getOptionLabel={(o) => o.label}
                            getOptionSecondary={(o) => o.secondary}
                            placeholder="Search by site code or name..."
                            required
                            error={!!errors.to_site_id}
                            helperText={errors.to_site_id?.[0]}
                        />

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
                    disabled={mutation.isPending || !toSiteId || toSiteId === currentSiteId}
                >
                    {mutation.isPending ? 'Transferring...' : 'Transfer'}
                </Button>
            </DialogActions>
        </Dialog>
    );
};
