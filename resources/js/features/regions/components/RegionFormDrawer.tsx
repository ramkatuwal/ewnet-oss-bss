import { useEffect } from 'react';
import { Drawer, Box, Typography, TextField, Button, FormControlLabel, Switch, Divider, IconButton, Grid, FormControl, InputLabel, Select, MenuItem } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { Region, Company } from '@/types';

const regionSchema = z.object({
    name: z.string().min(1, 'Region name is required').max(255),
    code: z.string().min(1, 'Code is required').max(50),
    company_id: z.number({ required_error: 'Company is required' }).min(1, 'Company is required'),
    description: z.string().optional().or(z.literal('')),
    city: z.string().max(255).optional().or(z.literal('')),
    state: z.string().max(255).optional().or(z.literal('')),
    country: z.string().min(1).max(255),
    is_active: z.boolean(),
});

type RegionFormData = z.infer<typeof regionSchema>;

interface Props {
    open: boolean;
    onClose: () => void;
    region: Region | null;
    companies: Company[];
    onSubmit: (data: Partial<Region>) => void;
    loading?: boolean;
}

export const RegionFormDrawer = ({ open, onClose, region, companies, onSubmit, loading }: Props) => {
    const isEdit = !!region;
    const { control, handleSubmit, reset, formState: { errors } } = useForm<RegionFormData>({
        resolver: zodResolver(regionSchema),
        defaultValues: { name: '', code: '', company_id: 0, description: '', city: '', state: '', country: 'Nepal', is_active: true },
    });

    useEffect(() => {
        if (open) {
            reset({
                name: region?.name ?? '', code: region?.code ?? '',
                company_id: region?.company_id ?? (companies.length === 1 ? companies[0].id : 0),
                description: region?.description ?? '', city: region?.city ?? '',
                state: region?.state ?? '', country: region?.country ?? 'Nepal',
                is_active: region?.is_active ?? true,
            });
        }
    }, [open, region, reset, companies]);

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ '& .MuiDrawer-paper': { width: 480, p: 3 } }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>{isEdit ? 'Edit Region' : 'Create Region'}</Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 3 }} />
            <form onSubmit={handleSubmit((data) => onSubmit(data as Partial<Region>))}>
                <Grid container spacing={2}>
                    <Grid item xs={12}>
                        <Typography variant="subtitle2" color="primary" fontWeight={600}>Organization</Typography>
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="company_id" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small" error={!!errors.company_id} required>
                                <InputLabel>Company</InputLabel>
                                <Select {...field} label="Company" disabled={isEdit}>
                                    <MenuItem value={0} disabled>Select Company</MenuItem>
                                    {companies.map((c) => <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>)}
                                </Select>
                            </FormControl>
                        )} />
                    </Grid>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 1 }}>Region Details</Typography></Grid>
                    <Grid item xs={6}><Controller name="name" control={control} render={({ field }) => <TextField {...field} label="Region Name" fullWidth required size="small" error={!!errors.name} helperText={errors.name?.message} />} /></Grid>
                    <Grid item xs={6}><Controller name="code" control={control} render={({ field }) => <TextField {...field} label="Code" fullWidth required size="small" error={!!errors.code} helperText={errors.code?.message} />} /></Grid>
                    <Grid item xs={12}><Controller name="description" control={control} render={({ field }) => <TextField {...field} label="Description" fullWidth multiline rows={2} size="small" />} /></Grid>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 1 }}>Location</Typography></Grid>
                    <Grid item xs={6}><Controller name="city" control={control} render={({ field }) => <TextField {...field} label="City" fullWidth size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="state" control={control} render={({ field }) => <TextField {...field} label="State / Province" fullWidth size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="country" control={control} render={({ field }) => <TextField {...field} label="Country" fullWidth size="small" required />} /></Grid>
                    <Grid item xs={12} sx={{ mt: 1 }}>
                        <Divider sx={{ mb: 2 }} />
                        <Controller name="is_active" control={control} render={({ field }) => <FormControlLabel control={<Switch checked={field.value} onChange={field.onChange} />} label="Active" />} />
                    </Grid>
                    <Grid item xs={12} sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={loading} fullWidth>{loading ? 'Saving...' : isEdit ? 'Update Region' : 'Create Region'}</Button>
                    </Grid>
                </Grid>
            </form>
        </Drawer>
    );
};
