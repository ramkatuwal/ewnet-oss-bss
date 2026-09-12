import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    Box,
    Button,
    Drawer,
    Divider,
    MenuItem,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createAsset, getAsset, updateAsset } from '../api/assets';
import { sitesApi, Site } from '@/api/sites';
import { AsyncSitePicker } from '@/components/infrastructure/AsyncSitePicker';
import { normalizeApiError } from '@/api/errors';
import { infrastructureKeys } from '@/api/queryKeys';
import toast from 'react-hot-toast';

const CATEGORIES = ['POWER', 'NETWORK', 'INFRASTRUCTURE', 'OTHER'] as const;
const STATUSES = ['OPERATIONAL', 'SPARE', 'MAINTENANCE', 'FAULTY', 'RETIRED', 'MISSING', 'DISPOSED'] as const;
const CONDITIONS = ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'CRITICAL'] as const;
const ASSET_TYPES = [
    'OLT', 'ONU', 'SWITCH', 'ROUTER', 'AP', 'CONTROLLER',
    'CABINET', 'CLOSURE', 'FAT', 'FDT', 'FDH', 'ODF', 'PATCH_PANEL', 'SPLITTER',
    'BATTERY', 'UPS', 'SOLAR_PANEL', 'INVERTER',
    'RACK', 'PDU', 'AC',
    'OTHER',
] as const;
const UNITS = ['pcs', 'm', 'km', 'pair', 'set', 'roll', 'box', 'unit'] as const;

const schema = z.object({
    site_id: z.number().min(1, 'A site is required.'),
    device_name: z.string().max(255).optional().or(z.literal('')),
    asset_tag: z.string().max(255).optional().or(z.literal('')),
    category: z.enum(CATEGORIES),
    type: z.string().min(1, 'Type is required.').max(100),
    serial_number: z.string().max(255).optional().or(z.literal('')),
    manufacturer: z.string().max(255).optional().or(z.literal('')),
    model: z.string().max(255).optional().or(z.literal('')),
    quantity: z.coerce.number().int().min(1, 'Must be at least 1.'),
    unit: z.string().max(20).optional().or(z.literal('')),
    status: z.enum(STATUSES),
    condition: z.enum(CONDITIONS).optional().or(z.literal('')),
    purchase_date: z.string().optional().or(z.literal('')),
    installation_date: z.string().optional().or(z.literal('')),
    warranty_expiry: z.string().optional().or(z.literal('')),
    description: z.string().max(2000).optional().or(z.literal('')),
    notes: z.string().max(2000).optional().or(z.literal('')),
});

type AssetForm = z.infer<typeof schema>;

interface Props {
    open: boolean;
    onClose: () => void;
    assetId: number | null;
    siteId?: number | null;
}

const defaults = (siteId?: number | null): AssetForm => ({
    site_id: siteId ?? 0,
    device_name: '',
    asset_tag: '',
    category: 'POWER',
    type: '',
    serial_number: '',
    manufacturer: '',
    model: '',
    quantity: 1,
    unit: 'pcs',
    status: 'OPERATIONAL',
    condition: '',
    purchase_date: '',
    installation_date: '',
    warranty_expiry: '',
    description: '',
    notes: '',
});

const SectionTitle = ({ children }: { children: React.ReactNode }) => (
    <Typography variant="caption" fontWeight={600} color="text.secondary" sx={{ textTransform: 'uppercase', letterSpacing: 0.5 }}>
        {children}
    </Typography>
);

const OrgInfo = ({ site }: { site: Site }) => {
    const parts = [site.company?.name, site.region?.name, site.branch?.name].filter(Boolean);
    if (parts.length === 0) return null;
    return (
        <Box sx={{ p: 1.5, border: '1px solid', borderColor: 'divider', borderRadius: 1, bgcolor: 'action.hover' }}>
            <Typography variant="caption" color="text.secondary">Organization (from Site)</Typography>
            <Typography variant="body2">{parts.join(' \u2022 ')}</Typography>
        </Box>
    );
};

const AssetFormDrawer = ({ open, onClose, assetId, siteId }: Props) => {
    const queryClient = useQueryClient();

    const { data: asset } = useQuery({
        queryKey: infrastructureKeys.asset(assetId ?? 0),
        queryFn: () => getAsset(assetId!),
        enabled: open && Boolean(assetId),
    });

    const {
        control,
        register,
        handleSubmit,
        reset,
        setError,
        formState: { errors },
    } = useForm<AssetForm>({
        resolver: zodResolver(schema),
        defaultValues: defaults(siteId),
    });

    const [selectedSite, setSelectedSite] = useState<Site | null>(null);
    const isCreate = !assetId;
    const siteDisabled = Boolean(siteId && !assetId);

    useEffect(() => {
        if (asset) {
            reset({
                site_id: asset.site_id,
                device_name: asset.device_name ?? '',
                asset_tag: asset.asset_tag,
                category: asset.category as AssetForm['category'],
                type: asset.type,
                serial_number: asset.serial_number ?? '',
                manufacturer: asset.manufacturer ?? '',
                model: asset.model ?? '',
                quantity: asset.quantity,
                unit: asset.unit ?? '',
                status: asset.status as AssetForm['status'],
                condition: (asset.condition as AssetForm['condition']) ?? '',
                purchase_date: asset.purchase_date ?? '',
                installation_date: asset.installation_date ?? '',
                warranty_expiry: asset.warranty_expiry ?? '',
                description: asset.description ?? '',
                notes: asset.notes ?? '',
            });
            setSelectedSite((asset.site as Site) ?? null);
        } else if (open) {
            reset(defaults(siteId));
            setSelectedSite(null);
            if (siteId) {
                sitesApi.get(siteId).then((site) => {
                    setSelectedSite(site);
                }).catch(() => undefined);
            }
        }
    }, [asset, open, reset, siteId]);

    const mutation = useMutation({
        mutationFn: (data: AssetForm) => {
            const payload = { ...data };
            if (!payload.asset_tag) delete payload.asset_tag;
            if (!payload.device_name) delete payload.device_name;
            return assetId ? updateAsset(assetId, payload) : createAsset(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: infrastructureKeys.assets() });
            queryClient.invalidateQueries({ queryKey: infrastructureKeys.assetDashboard() });
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'site-assets'] });
            toast.success(assetId ? 'Asset updated.' : 'Asset created.');
            onClose();
        },
        onError: (error) => {
            const normalized = normalizeApiError(error);
            Object.entries(normalized.fieldErrors).forEach(([field, message]) =>
                setError(field as keyof AssetForm, { message }),
            );
            if (!Object.keys(normalized.fieldErrors).length) {
                toast.error(normalized.message);
            }
        },
    });

    const input = (name: keyof AssetForm, label: string, opts?: { type?: string; multiline?: boolean; rows?: number; min?: string }) => (
        <TextField
            fullWidth
            size="small"
            label={label}
            type={opts?.type}
            multiline={opts?.multiline}
            rows={opts?.rows}
            InputLabelProps={opts?.type === 'date' ? { shrink: true } : undefined}
            inputProps={opts?.min ? { min: opts.min } : undefined}
            {...register(name)}
            error={Boolean(errors[name])}
            helperText={errors[name]?.message}
        />
    );

    return (
        <Drawer
            anchor="right"
            open={open}
            onClose={onClose}
            PaperProps={{ sx: { width: { xs: '100%', sm: 720 }, maxWidth: '100vw' } }}
        >
            <Box
                component="form"
                onSubmit={handleSubmit((data) => mutation.mutate(data))}
                sx={{ display: 'flex', flexDirection: 'column', height: '100%' }}
            >
                <Box sx={{ p: 3, pb: 2, flexShrink: 0 }}>
                    <Typography variant="h6">
                        {assetId ? `Edit Asset ${asset?.asset_tag ?? ''}` : 'Add Asset'}
                    </Typography>
                </Box>

                <Divider />

                <Box sx={{ flex: 1, overflow: 'auto', p: 3, pt: 2 }}>
                    <Stack spacing={3}>
                        {/* Organization — Site Only */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Placement</SectionTitle>
                            <AsyncSitePicker
                                control={control}
                                selectedSite={selectedSite}
                                onSelected={setSelectedSite}
                                disabled={siteDisabled}
                                error={errors.site_id?.message}
                            />
                            {selectedSite && <OrgInfo site={selectedSite} />}
                        </Stack>

                        <Divider />

                        {/* Asset Code + Device Name */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Identity</SectionTitle>
                            {isCreate ? (
                                <Box sx={{ p: 1.5, border: '1px dashed', borderColor: 'divider', borderRadius: 1, bgcolor: 'action.hover' }}>
                                    <Typography variant="body2" fontWeight="medium" sx={{ fontFamily: 'monospace', mb: 0.5 }}>AST-000123</Typography>
                                    <Typography variant="caption" color="text.secondary">generated automatically after save</Typography>
                                </Box>
                            ) : (
                                <TextField
                                    fullWidth
                                    size="small"
                                    label="Asset Code"
                                    value={asset?.asset_tag ?? ''}
                                    InputProps={{ readOnly: true }}
                                    helperText="System generated \u2022 immutable"
                                    FormHelperTextProps={{ sx: { color: 'text.secondary' } }}
                                />
                            )}
                            {input('device_name', 'Device Name')}
                        </Stack>

                        <Divider />

                        {/* Category + Type */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Classification</SectionTitle>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Category *"
                                    defaultValue="POWER"
                                    {...register('category')}
                                    error={Boolean(errors.category)}
                                    helperText={errors.category?.message}
                                >
                                    {CATEGORIES.map((v) => (
                                        <MenuItem key={v} value={v}>{v}</MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Type *"
                                    defaultValue=""
                                    {...register('type')}
                                    error={Boolean(errors.type)}
                                    helperText={errors.type?.message}
                                >
                                    <MenuItem value="" disabled>Select type</MenuItem>
                                    {ASSET_TYPES.map((v) => (
                                        <MenuItem key={v} value={v}>{v}</MenuItem>
                                    ))}
                                </TextField>
                            </Stack>
                        </Stack>

                        <Divider />

                        {/* Hardware */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Hardware</SectionTitle>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                {input('manufacturer', 'Manufacturer')}
                                {input('model', 'Model')}
                            </Stack>
                            {input('serial_number', 'Serial Number')}
                        </Stack>

                        <Divider />

                        {/* Quantity */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Quantity</SectionTitle>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                {input('quantity', 'Quantity *', { type: 'number' })}
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Unit"
                                    defaultValue="pcs"
                                    {...register('unit')}
                                    error={Boolean(errors.unit)}
                                    helperText={errors.unit?.message}
                                >
                                    {UNITS.map((v) => (
                                        <MenuItem key={v} value={v}>{v}</MenuItem>
                                    ))}
                                </TextField>
                            </Stack>
                        </Stack>

                        <Divider />

                        {/* Status */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Status</SectionTitle>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Status *"
                                    defaultValue="OPERATIONAL"
                                    {...register('status')}
                                    error={Boolean(errors.status)}
                                    helperText={errors.status?.message}
                                >
                                    {STATUSES.map((v) => (
                                        <MenuItem key={v} value={v}>{v}</MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Condition"
                                    defaultValue=""
                                    {...register('condition')}
                                    error={Boolean(errors.condition)}
                                    helperText={errors.condition?.message}
                                >
                                    <MenuItem value="">Not set</MenuItem>
                                    {CONDITIONS.map((v) => (
                                        <MenuItem key={v} value={v}>{v}</MenuItem>
                                    ))}
                                </TextField>
                            </Stack>
                        </Stack>

                        <Divider />

                        {/* Lifecycle / Warranty */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Lifecycle / Warranty</SectionTitle>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                {input('purchase_date', 'Purchase Date', { type: 'date' })}
                                {input('installation_date', 'Installation Date', { type: 'date' })}
                            </Stack>
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5}>
                                {input('warranty_expiry', 'Warranty Expiry', { type: 'date' })}
                                <Box sx={{ flex: 1 }} />
                            </Stack>
                        </Stack>

                        <Divider />

                        {/* Description / Notes */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Description / Notes</SectionTitle>
                            {input('description', 'Description', { multiline: true, rows: 3 })}
                            {input('notes', 'Notes', { multiline: true, rows: 3 })}
                        </Stack>
                    </Stack>
                </Box>

                {/* Persistent Bottom Actions */}
                <Divider />
                <Box sx={{ p: 2, flexShrink: 0, bgcolor: 'background.paper' }}>
                    <Stack direction="row" spacing={1.5} justifyContent="flex-end">
                        <Button onClick={onClose} disabled={mutation.isPending}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="contained"
                            disabled={mutation.isPending}
                        >
                            {mutation.isPending ? 'Saving...' : 'Save'}
                        </Button>
                    </Stack>
                </Box>
            </Box>
        </Drawer>
    );
};

export default AssetFormDrawer;
