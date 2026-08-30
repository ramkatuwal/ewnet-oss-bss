import React, { useEffect, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { 
    Drawer, Box, Typography, TextField, Button, 
    MenuItem, Grid, FormControl, InputLabel, Select 
} from '@mui/material';
import { createAsset, updateAsset, getAsset } from '../api/assets';
import toast from 'react-hot-toast';

interface Props {
    open: boolean;
    onClose: () => void;
    assetId: number | null;
}

const AssetFormDrawer: React.FC<Props> = ({ open, onClose, assetId }) => {
    const queryClient = useQueryClient();
    const [formData, setFormData] = useState<any>({
        asset_tag: '', type: '', category: 'POWER', quantity: 1, status: 'OPERATIONAL', unit: 'pcs'
    });

    useEffect(() => {
        if (assetId) {
            getAsset(assetId).then(res => setFormData(res.data));
        } else {
            setFormData({ asset_tag: '', type: '', category: 'POWER', quantity: 1, status: 'OPERATIONAL', unit: 'pcs' });
        }
    }, [assetId, open]);

    const mutation = useMutation({
        mutationFn: assetId ? (data: any) => updateAsset(assetId, data) : createAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            queryClient.invalidateQueries({ queryKey: ['asset-dashboard'] });
            toast.success(assetId ? 'Asset updated' : 'Asset created');
            onClose();
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Operation failed');
        }
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        mutation.mutate(formData);
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ width: 500 }}>
            <Box sx={{ width: 500, p: 3 }}>
                <Typography variant="h6" sx={{ mb: 2 }}>{assetId ? 'Edit Asset' : 'Add Asset'}</Typography>
                <form onSubmit={handleSubmit}>
                    <Grid container spacing={2}>
                        <Grid item xs={12}>
                            <TextField fullWidth label="Asset Tag" required value={formData.asset_tag || ''} onChange={e => setFormData({...formData, asset_tag: e.target.value})} />
                        </Grid>
                        <Grid item xs={6}>
                            <FormControl fullWidth>
                                <InputLabel>Category</InputLabel>
                                <Select value={formData.category || ''} label="Category" onChange={e => setFormData({...formData, category: e.target.value})}>
                                    <MenuItem value="POWER">Power</MenuItem>
                                    <MenuItem value="NETWORK">Network</MenuItem>
                                    <MenuItem value="INFRASTRUCTURE">Infrastructure</MenuItem>
                                    <MenuItem value="OTHER">Other</MenuItem>
                                </Select>
                            </FormControl>
                        </Grid>
                        <Grid item xs={6}>
                            <TextField fullWidth label="Type" required value={formData.type || ''} onChange={e => setFormData({...formData, type: e.target.value})} />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField fullWidth label="Manufacturer" value={formData.manufacturer || ''} onChange={e => setFormData({...formData, manufacturer: e.target.value})} />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField fullWidth label="Model" value={formData.model || ''} onChange={e => setFormData({...formData, model: e.target.value})} />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField fullWidth label="Serial Number" value={formData.serial_number || ''} onChange={e => setFormData({...formData, serial_number: e.target.value})} />
                        </Grid>
                        <Grid item xs={6}>
                            <TextField fullWidth label="Quantity" type="number" value={formData.quantity || 1} onChange={e => setFormData({...formData, quantity: parseInt(e.target.value)})} />
                        </Grid>
                        <Grid item xs={6}>
                            <FormControl fullWidth>
                                <InputLabel>Status</InputLabel>
                                <Select value={formData.status || ''} label="Status" onChange={e => setFormData({...formData, status: e.target.value})}>
                                    <MenuItem value="OPERATIONAL">Operational</MenuItem>
                                    <MenuItem value="SPARE">Spare</MenuItem>
                                    <MenuItem value="MAINTENANCE">Maintenance</MenuItem>
                                    <MenuItem value="FAULTY">Faulty</MenuItem>
                                    <MenuItem value="RETIRED">Retired</MenuItem>
                                </Select>
                            </FormControl>
                        </Grid>
                    </Grid>
                    <Box sx={{ mt: 3, display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
                        <Button onClick={onClose}>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={mutation.isPending}>Save</Button>
                    </Box>
                </form>
            </Box>
        </Drawer>
    );
};

export default AssetFormDrawer;
