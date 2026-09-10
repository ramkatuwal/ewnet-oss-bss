import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Box, Button, Drawer, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAsset, getAsset, updateAsset } from '../api/assets';
import { sitesApi, Site } from '@/api/sites';
import { AsyncSitePicker } from '@/components/infrastructure/AsyncSitePicker';
import { normalizeApiError } from '@/api/errors';
import { infrastructureKeys } from '@/api/queryKeys';
import toast from 'react-hot-toast';

const schema = z.object({
    site_id: z.number({ required_error: 'A site is required.' }), asset_tag: z.string().min(1, 'Asset tag is required.').max(255),
    category: z.enum(['POWER', 'NETWORK', 'INFRASTRUCTURE', 'OTHER']), type: z.string().min(1, 'Type is required.').max(100),
    quantity: z.number().int().min(1), status: z.enum(['OPERATIONAL', 'SPARE', 'MAINTENANCE', 'FAULTY', 'RETIRED', 'MISSING', 'DISPOSED']),
    serial_number: z.string().max(255).optional(), manufacturer: z.string().max(255).optional(), model: z.string().max(255).optional(), unit: z.string().max(20).optional(),
});
type AssetForm = z.infer<typeof schema>;
interface Props { open: boolean; onClose: () => void; assetId: number | null; siteId?: number | null; }

const defaults = (siteId?: number | null): AssetForm => ({ site_id: siteId ?? 0, asset_tag: '', category: 'POWER', type: '', quantity: 1, status: 'OPERATIONAL', unit: 'pcs' });

const AssetFormDrawer = ({ open, onClose, assetId, siteId }: Props) => {
    const queryClient = useQueryClient();
    const { data: asset } = useQuery({ queryKey: infrastructureKeys.asset(assetId ?? 0), queryFn: () => getAsset(assetId!), enabled: open && Boolean(assetId) });
    const { control, register, handleSubmit, reset, setError, formState: { errors } } = useForm<AssetForm>({ resolver: zodResolver(schema), defaultValues: defaults(siteId) });
    const [selectedSite, setSelectedSite] = useState<Site | null>(null);

    useEffect(() => {
        if (asset) {
            reset({ site_id: asset.site_id, asset_tag: asset.asset_tag, category: asset.category as AssetForm['category'], type: asset.type, quantity: asset.quantity, status: asset.status as AssetForm['status'], serial_number: asset.serial_number ?? undefined, manufacturer: asset.manufacturer ?? undefined, model: asset.model ?? undefined, unit: asset.unit ?? undefined });
            setSelectedSite(asset.site as Site ?? null);
        } else if (open) {
            reset(defaults(siteId));
            setSelectedSite(null);
            if (siteId) sitesApi.get(siteId).then(setSelectedSite).catch(() => undefined);
        }
    }, [asset, open, reset, siteId]);

    const mutation = useMutation({
        mutationFn: (data: AssetForm) => assetId ? updateAsset(assetId, data) : createAsset(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: infrastructureKeys.assets() });
            queryClient.invalidateQueries({ queryKey: infrastructureKeys.assetDashboard() });
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'site-assets'] });
            toast.success(assetId ? 'Asset updated.' : 'Asset created.'); onClose();
        },
        onError: (error) => {
            const normalized = normalizeApiError(error);
            Object.entries(normalized.fieldErrors).forEach(([field, message]) => setError(field as keyof AssetForm, { message }));
            if (!Object.keys(normalized.fieldErrors).length) toast.error(normalized.message);
        },
    });
    const input = (name: keyof AssetForm, label: string, type?: string) => <TextField fullWidth label={label} type={type} {...register(name, type === 'number' ? { setValueAs: (value) => Number(value) } : undefined)} error={Boolean(errors[name])} helperText={errors[name]?.message} />;

    return <Drawer anchor="right" open={open} onClose={onClose}><Box component="form" onSubmit={handleSubmit((data) => mutation.mutate(data))} sx={{ width: { xs: '100vw', sm: 550 }, p: 3 }}>
        <Typography variant="h6" sx={{ mb: 2 }}>{assetId ? 'Edit Asset' : 'Add Asset'}</Typography><Stack spacing={2}>
            <AsyncSitePicker control={control} selectedSite={selectedSite} onSelected={setSelectedSite} disabled={Boolean(siteId && !assetId)} error={errors.site_id?.message} />
            {input('asset_tag', 'Asset Tag *')}
            <TextField select label="Category *" defaultValue="POWER" {...register('category')} error={Boolean(errors.category)} helperText={errors.category?.message}>{['POWER', 'NETWORK', 'INFRASTRUCTURE', 'OTHER'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField>
            {input('type', 'Type *')}{input('serial_number', 'Serial Number')}{input('manufacturer', 'Manufacturer')}{input('model', 'Model')}{input('quantity', 'Quantity *', 'number')}{input('unit', 'Unit')}
            <TextField select label="Status *" defaultValue="OPERATIONAL" {...register('status')} error={Boolean(errors.status)} helperText={errors.status?.message}>{['OPERATIONAL', 'SPARE', 'MAINTENANCE', 'FAULTY', 'RETIRED', 'MISSING', 'DISPOSED'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField>
            <Stack direction="row" spacing={2}><Button fullWidth onClick={onClose}>Cancel</Button><Button fullWidth type="submit" variant="contained" disabled={mutation.isPending}>{mutation.isPending ? 'Saving...' : 'Save'}</Button></Stack>
        </Stack>
    </Box></Drawer>;
};

export default AssetFormDrawer;
