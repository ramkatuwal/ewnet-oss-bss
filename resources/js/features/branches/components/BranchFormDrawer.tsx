import { useEffect, useState } from 'react';
import { Drawer, Box, Typography, TextField, Button, FormControlLabel, Switch, Divider, IconButton, Grid, FormControl, InputLabel, Select, MenuItem } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { Branch, Region, Company } from '@/types';

const branchSchema = z.object({
    name: z.string().min(1, 'Branch name is required').max(255),
    code: z.string().min(1, 'Code is required').max(50),
    region_id: z.number({ required_error: 'Region is required' }).min(1, 'Region is required'),
    address: z.string().optional().or(z.literal('')),
    city: z.string().max(255).optional().or(z.literal('')),
    state: z.string().max(255).optional().or(z.literal('')),
    postal_code: z.string().max(255).optional().or(z.literal('')),
    country: z.string().min(1).max(255),
    phone: z.string().max(20).optional().or(z.literal('')),
    email: z.string().email('Invalid email').max(255).optional().or(z.literal('')),
    latitude: z.string().optional().or(z.literal('')),
    longitude: z.string().optional().or(z.literal('')),
    is_active: z.boolean(),
});

type BranchFormData = z.infer<typeof branchSchema>;

interface Props {
    open: boolean;
    onClose: () => void;
    branch: Branch | null;
    regions: Region[];
    companies: Company[];
    onSubmit: (data: Partial<Branch>) => void;
    loading?: boolean;
}

export const BranchFormDrawer = ({ open, onClose, branch, regions, companies, onSubmit, loading }: Props) => {
    const isEdit = !!branch;
    const [selectedCompany, setSelectedCompany] = useState<number>(0);

    const { control, handleSubmit, reset, formState: { errors } } = useForm<BranchFormData>({
        resolver: zodResolver(branchSchema),
        defaultValues: { name: '', code: '', region_id: 0, address: '', city: '', state: '', postal_code: '', country: 'Nepal', phone: '', email: '', latitude: '', longitude: '', is_active: true },
    });

    useEffect(() => {
        if (open) {
            const companyId = branch?.region?.company_id ?? (companies.length === 1 ? companies[0].id : 0);
            setSelectedCompany(companyId);
            reset({
                name: branch?.name ?? '', code: branch?.code ?? '',
                region_id: branch?.region_id ?? 0,
                address: branch?.address ?? '', city: branch?.city ?? '',
                state: branch?.state ?? '', postal_code: branch?.postal_code ?? '',
                country: branch?.country ?? 'Nepal', phone: branch?.phone ?? '',
                email: branch?.email ?? '', latitude: branch?.latitude ?? '',
                longitude: branch?.longitude ?? '', is_active: branch?.is_active ?? true,
            });
        }
    }, [open, branch, reset, companies]);

    const filteredRegions = regions.filter((r) => !selectedCompany || r.company_id === selectedCompany);

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ '& .MuiDrawer-paper': { width: 520, p: 3 } }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>{isEdit ? 'Edit Branch' : 'Create Branch'}</Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 3 }} />
            <form onSubmit={handleSubmit((data) => onSubmit(data as Partial<Branch>))}>
                <Grid container spacing={2}>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600}>Organization</Typography></Grid>
                    <Grid item xs={6}>
                        <FormControl fullWidth size="small" required>
                            <InputLabel>Company</InputLabel>
                            <Select value={selectedCompany} onChange={(e) => setSelectedCompany(Number(e.target.value))} label="Company" disabled={isEdit}>
                                <MenuItem value={0} disabled>Select Company</MenuItem>
                                {companies.map((c) => <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>)}
                            </Select>
                        </FormControl>
                    </Grid>
                    <Grid item xs={6}>
                        <Controller name="region_id" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small" error={!!errors.region_id} required>
                                <InputLabel>Region</InputLabel>
                                <Select {...field} label="Region" disabled={isEdit}>
                                    <MenuItem value={0} disabled>Select Region</MenuItem>
                                    {filteredRegions.map((r) => <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>)}
                                </Select>
                            </FormControl>
                        )} />
                    </Grid>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 1 }}>Branch Details</Typography></Grid>
                    <Grid item xs={6}><Controller name="name" control={control} render={({ field }) => <TextField {...field} label="Branch Name" fullWidth required size="small" error={!!errors.name} helperText={errors.name?.message} />} /></Grid>
                    <Grid item xs={6}><Controller name="code" control={control} render={({ field }) => <TextField {...field} label="Code" fullWidth required size="small" error={!!errors.code} helperText={errors.code?.message} />} /></Grid>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 1 }}>Contact</Typography></Grid>
                    <Grid item xs={6}><Controller name="email" control={control} render={({ field }) => <TextField {...field} label="Email" fullWidth size="small" error={!!errors.email} helperText={errors.email?.message} />} /></Grid>
                    <Grid item xs={6}><Controller name="phone" control={control} render={({ field }) => <TextField {...field} label="Phone" fullWidth size="small" />} /></Grid>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 1 }}>Address</Typography></Grid>
                    <Grid item xs={12}><Controller name="address" control={control} render={({ field }) => <TextField {...field} label="Street Address" fullWidth multiline rows={2} size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="city" control={control} render={({ field }) => <TextField {...field} label="City" fullWidth size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="state" control={control} render={({ field }) => <TextField {...field} label="State / Province" fullWidth size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="postal_code" control={control} render={({ field }) => <TextField {...field} label="Postal Code" fullWidth size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="country" control={control} render={({ field }) => <TextField {...field} label="Country" fullWidth size="small" required />} /></Grid>
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 1 }}>Coordinates</Typography></Grid>
                    <Grid item xs={6}><Controller name="latitude" control={control} render={({ field }) => <TextField {...field} label="Latitude" fullWidth size="small" />} /></Grid>
                    <Grid item xs={6}><Controller name="longitude" control={control} render={({ field }) => <TextField {...field} label="Longitude" fullWidth size="small" />} /></Grid>
                    <Grid item xs={12} sx={{ mt: 1 }}>
                        <Divider sx={{ mb: 2 }} />
                        <Controller name="is_active" control={control} render={({ field }) => <FormControlLabel control={<Switch checked={field.value} onChange={field.onChange} />} label="Active" />} />
                    </Grid>
                    <Grid item xs={12} sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={loading} fullWidth>{loading ? 'Saving...' : isEdit ? 'Update Branch' : 'Create Branch'}</Button>
                    </Grid>
                </Grid>
            </form>
        </Drawer>
    );
};
