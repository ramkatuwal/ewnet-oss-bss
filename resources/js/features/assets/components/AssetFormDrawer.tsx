import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    Box,
    Alert,
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
import { assetModelSettingsApi } from '@/features/settings/api/assetModelSettings';
import toast from 'react-hot-toast';

const STATUSES = ['OPERATIONAL', 'SPARE', 'MAINTENANCE', 'FAULTY', 'RETIRED', 'MISSING', 'DISPOSED'] as const;
const CONDITIONS = ['EXCELLENT', 'GOOD', 'FAIR', 'POOR', 'CRITICAL'] as const;

const schema = z.object({
    site_id: z.number().min(1, 'A site is required.'),
    device_name: z.string().max(255).optional().or(z.literal('')),
    management_ip: z.string().optional().or(z.literal('')),
    asset_tag: z.string().max(255).optional().or(z.literal('')),
    category: z.string().min(1, 'Category is required.'),
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
    management_ip: '',
    asset_tag: '',
    category: '',
    type: '',
    serial_number: '',
    manufacturer: '',
    model: '',
    quantity: 1,
    unit: '',
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

    const { data: categories = [], isError: categoriesError, isPending: categoriesLoading } = useQuery({
        queryKey: ['settings', 'asset-categories'],
        queryFn: () => assetModelSettingsApi.getCategories().then((r) => r.data.data),
        enabled: open,
    });

    const { data: allTypes = [], isError: typesError, isPending: typesLoading } = useQuery({
        queryKey: ['settings', 'asset-device-types'],
        queryFn: () => assetModelSettingsApi.getDeviceTypes().then((r) => r.data.data),
        enabled: open,
    });

    const { data: units = [], isError: unitsError, isPending: unitsLoading } = useQuery({
        queryKey: ['settings', 'asset-units'],
        queryFn: () => assetModelSettingsApi.getUnits().then((r) => r.data.data),
        enabled: open,
    });

    const {
        control,
        register,
        handleSubmit,
        reset,
        watch,
        setError,
        setValue,
        formState: { errors },
    } = useForm<AssetForm>({
        resolver: zodResolver(schema),
        defaultValues: defaults(siteId),
    });

    const selectedCategory = watch('category');
    const selectedType = watch('type');
    const selectedUnit = watch('unit');
    const settingsError = categoriesError || typesError || unitsError;
    const [siteData, setSiteData] = useState<Site | null>(null);
    const isCreate = !assetId;
    const siteDisabled = Boolean(siteId && !assetId);

    // Filter types by selected category
    const filteredTypes = selectedCategory
        ? allTypes.filter((t) => t.category_id === categories.find(c => c.code === selectedCategory)?.id && t.is_active)
        : [];

    useEffect(() => {
        if (asset) {
            reset({
                site_id: asset.site_id,
                device_name: asset.device_name ?? '',
                management_ip: asset.management_ip ?? '',
                asset_tag: asset.asset_tag,
                category: asset.category,
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
            if (asset.site) setSiteData(asset.site as Site);
        } else if (open) {
            reset(defaults(siteId));
            setSiteData(null);
            if (siteId) {
                sitesApi.get(siteId).then((site) => {
                    setSiteData(site);
                }).catch(() => undefined);
            }
        }
    }, [asset, open, reset, siteId]);

    const mutation = useMutation({
        mutationFn: (data: AssetForm) => {
            const payload = { ...data };
            if (!payload.asset_tag) delete payload.asset_tag;
            if (!payload.device_name) delete payload.device_name;
            if (!payload.management_ip) delete payload.management_ip;
            return assetId ? updateAsset(assetId, payload) : createAsset(payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['infrastructure', 'assets'] });
            if (assetId) queryClient.invalidateQueries({ queryKey: infrastructureKeys.asset(assetId) });
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

    const input = (name: keyof AssetForm, label: string, opts?: { type?: string; multiline?: boolean; rows?: number; min?: string; disabled?: boolean }) => (
        <TextField
            fullWidth
            size="small"
            label={label}
            type={opts?.type}
            multiline={opts?.multiline}
            rows={opts?.rows}
            disabled={opts?.disabled}
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
                        {settingsError && <Alert severity="error">Unable to load Model Settings. Close and reopen to retry.</Alert>}
                        {/* Organization — Site Only */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Placement</SectionTitle>
                            <AsyncSitePicker
                                control={control}
                                selectedSite={siteData}
                                onSelected={setSiteData}
                                disabled={siteDisabled}
                                error={errors.site_id?.message}
                            />
                            {siteData && <OrgInfo site={siteData} />}
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

                        {/* Management IP — NETWORK only */}
                        <Stack spacing={1.5}>
                            <SectionTitle>Network</SectionTitle>
                            {input('management_ip', 'Management IP', { disabled: selectedCategory !== 'NETWORK' })}
                            {selectedCategory !== 'NETWORK' && (
                                <Typography variant="caption" color="text.secondary">
                                    Management IP is only available for NETWORK category assets.
                                </Typography>
                            )}
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
                                    {...register('category')}
                                    value={selectedCategory}
                                    onChange={(event) => {
                                        setValue('category', event.target.value, { shouldDirty: true });
                                        setValue('type', '', { shouldDirty: true });
                                    }}
                                    error={Boolean(errors.category)}
                                    helperText={errors.category?.message}
                                >
                                    <MenuItem value="" disabled>Select category</MenuItem>
                                    {selectedCategory && !categories.some(c => c.code === selectedCategory && c.is_active) && (
                                        <MenuItem value={selectedCategory} disabled>{categories.find(c => c.code === selectedCategory)?.name ?? selectedCategory} (historical)</MenuItem>
                                    )}
                                    {categories.filter(c => c.is_active).map((c) => (
                                        <MenuItem key={c.id} value={c.code}>{c.name}</MenuItem>
                                    ))}
                                </TextField>
                                <TextField
                                    select
                                    fullWidth
                                    size="small"
                                    label="Type *"
                                    {...register('type')}
                                    value={selectedType}
                                    disabled={!selectedCategory || typesLoading}
                                    error={Boolean(errors.type)}
                                    helperText={errors.type?.message}
                                >
                                    <MenuItem value="" disabled>Select type</MenuItem>
                                    {selectedType && !filteredTypes.some(t => t.code === selectedType) && (
                                        <MenuItem value={selectedType} disabled>{selectedType} (historical)</MenuItem>
                                    )}
                                    {filteredTypes.map((t) => (
                                        <MenuItem key={t.id} value={t.code}>{t.name}</MenuItem>
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
                                    {...register('unit')}
                                    value={selectedUnit}
                                    error={Boolean(errors.unit)}
                                    helperText={errors.unit?.message}
                                >
                                    <MenuItem value="" disabled>Select unit</MenuItem>
                                    {selectedUnit && !units.some(u => u.code === selectedUnit && u.is_active) && (
                                        <MenuItem value={selectedUnit} disabled>{units.find(u => u.code === selectedUnit)?.name ?? selectedUnit} (historical)</MenuItem>
                                    )}
                                    {units.filter(u => u.is_active).map((u) => (
                                        <MenuItem key={u.id} value={u.code}>{u.name}</MenuItem>
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
                                    value={watch('status')}
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
                                    value={watch('condition')}
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
                            disabled={mutation.isPending || settingsError || categoriesLoading || typesLoading || unitsLoading}
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
