import React, { useState, useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Dialog, DialogTitle, DialogContent, DialogActions,
    Button, TextField, Typography, Box, Stack, Alert
} from '@mui/material';
import { retireAsset, disposeAsset } from '../api/assets';
import toast from 'react-hot-toast';

interface AssetStatusChangeDialogProps {
    open: boolean;
    onClose: () => void;
    assetId: number;
    currentStatus: string;
    action: 'retire' | 'dispose' | null;
    onSuccess: () => void;
}

const STATUS_LABELS: Record<string, string> = {
    'OPERATIONAL': 'Operational',
    'SPARE': 'Spare',
    'MAINTENANCE': 'Maintenance',
    'FAULTY': 'Faulty',
    'RETIRED': 'Retired',
    'MISSING': 'Missing',
    'DISPOSED': 'Disposed',
};

const ACTION_CONFIG: Record<string, { title: string; message: string; confirmText: string; newStatus: string }> = {
    retire: {
        title: 'Retire Asset',
        message: 'Are you sure you want to retire this asset? This action can be undone.',
        confirmText: 'Retire',
        newStatus: 'RETIRED',
    },
    dispose: {
        title: 'Dispose Asset',
        message: 'This action is irreversible. The asset must be retired before disposal.',
        confirmText: 'Dispose',
        newStatus: 'DISPOSED',
    },
};

export const AssetStatusChangeDialog: React.FC<AssetStatusChangeDialogProps> = ({
    open,
    onClose,
    assetId,
    currentStatus,
    action,
    onSuccess,
}) => {
    const queryClient = useQueryClient();
    const [notes, setNotes] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (open) {
            setNotes('');
            setErrors({});
        }
    }, [open]);

    const isRetire = action === 'retire';
    const config = action ? ACTION_CONFIG[action] : null;

    const mutation = useMutation({
        mutationFn: (data: { notes?: string }) => {
            if (isRetire) {
                return retireAsset(assetId, data);
            }
            return disposeAsset(assetId, data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['asset-lifecycle', assetId] });
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['asset-dashboard'] });
            toast.success(isRetire ? 'Asset retired successfully' : 'Asset disposed successfully');
            onSuccess();
            onClose();
        },
        onError: (err: any) => {
            if (err.response?.data?.errors) {
                setErrors(err.response.data.errors);
            } else {
                toast.error(err.response?.data?.message || 'Operation failed');
            }
        },
    });

    const handleConfirm = () => {
        mutation.mutate({ notes: notes || undefined });
    };

    if (!action || !config) return null;

    const isDisabled = currentStatus === config.newStatus || mutation.isPending;

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{config.title}</DialogTitle>
            <DialogContent>
                <Box sx={{ mt: 1 }}>
                    <Stack spacing={2}>
                        <Alert severity="warning">
                            {config.message}
                        </Alert>

                        <Box>
                            <Typography variant="caption" color="text.secondary">
                                Current Status
                            </Typography>
                            <Typography variant="body1">
                                {STATUS_LABELS[currentStatus] || currentStatus}
                            </Typography>
                        </Box>

                        <Box>
                            <Typography variant="caption" color="text.secondary">
                                New Status
                            </Typography>
                            <Typography variant="body1" fontWeight="bold" color="error">
                                {STATUS_LABELS[config.newStatus] || config.newStatus}
                            </Typography>
                        </Box>

                        <TextField
                            label="Notes (Optional)"
                            multiline
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            fullWidth
                            placeholder={`Reason for ${isRetire ? 'retirement' : 'disposal'}...`}
                            error={!!errors.notes}
                            helperText={errors.notes?.[0]}
                        />
                    </Stack>
                </Box>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose}>Cancel</Button>
                <Button
                    variant="contained"
                    color="error"
                    onClick={handleConfirm}
                    disabled={isDisabled}
                >
                    {mutation.isPending ? 'Processing...' : config.confirmText}
                </Button>
            </DialogActions>
        </Dialog>
    );
};
