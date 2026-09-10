import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Box, Button, Drawer, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { sitesApi } from '@/api/sites';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { branchesApi } from '@/api/branches';
import SearchableSelect, { SearchableSelectOption } from '@/components/forms/SearchableSelect';
import { normalizeApiError } from '@/api/errors';

const schema = z.object({
    site_code: z.string().min(1, 'Site code is required.').max(255),
    name: z.string().min(1, 'Name is required.').max(255),
    type: z.string().min(1), status: z.string().min(1),
    company_id: z.number().optional(), region_id: z.number().optional(), branch_id: z.number().optional(),
    latitude: z.number().min(-90).max(90).optional(), longitude: z.number().min(-180).max(180).optional(), altitude: z.number().optional(),
    province: z.string().optional(), district: z.string().optional(), municipality: z.string().optional(), address: z.string().optional(),
});
type SiteForm = z.infer<typeof schema>;

interface Props { open: boolean; siteId?: number; onClose: () => void; onSuccess: () => void; }

export const SiteFormDrawer = ({ open, siteId, onClose, onSuccess }: Props) => {
    const { data: site } = useQuery({ queryKey: ['site', siteId], queryFn: () => sitesApi.get(siteId!), enabled: open && Boolean(siteId) });
    const { register, handleSubmit, reset, setValue, watch, setError, formState: { errors, isSubmitting } } = useForm<SiteForm>({
        resolver: zodResolver(schema), defaultValues: { site_code: '', name: '', type: 'pop', status: 'planned' },
    });
    const companyId = watch('company_id');
    const regionId = watch('region_id');
    const branchId = watch('branch_id');
    const { data: regions } = useQuery({ queryKey: ['regions', companyId], queryFn: () => regionsApi.getAll({ company_id: companyId, per_page: 500 }), enabled: Boolean(companyId) });
    const { data: branches } = useQuery({ queryKey: ['branches', regionId], queryFn: () => branchesApi.getAll({ region_id: regionId, per_page: 500 }), enabled: Boolean(regionId) });

    useEffect(() => {
        reset(site ? { ...site, latitude: site.latitude ? Number(site.latitude) : undefined, longitude: site.longitude ? Number(site.longitude) : undefined, altitude: site.altitude ? Number(site.altitude) : undefined } : { site_code: '', name: '', type: 'pop', status: 'planned' });
    }, [site, open, reset]);

    const submit = async (data: SiteForm) => {
        try {
            if (siteId) await sitesApi.update(siteId, data); else await sitesApi.create(data);
            onSuccess();
        } catch (error) {
            const normalized = normalizeApiError(error);
            Object.entries(normalized.fieldErrors).forEach(([field, message]) => setError(field as keyof SiteForm, { message }));
        }
    };
    const field = (name: keyof SiteForm, label: string, type?: string) => <TextField label={label} type={type} fullWidth {...register(name, type === 'number' ? { setValueAs: (value) => value === '' ? undefined : Number(value) } : undefined)} error={Boolean(errors[name])} helperText={errors[name]?.message} />;

    return <Drawer anchor="right" open={open} onClose={onClose}><Box component="form" onSubmit={handleSubmit(submit)} sx={{ width: { xs: '100vw', sm: 500 }, p: 3 }}>
        <Typography variant="h6" sx={{ mb: 2 }}>{siteId ? 'Edit Site' : 'Add Site'}</Typography><Stack spacing={2}>
            {field('site_code', 'Site Code *')}{field('name', 'Name *')}
            <TextField select label="Type *" defaultValue="pop" {...register('type')} error={Boolean(errors.type)} helperText={errors.type?.message}>{['pop', 'tower', 'office', 'warehouse', 'datacenter', 'customer_premises', 'solar_site', 'repeater_site', 'other'].map((value) => <MenuItem key={value} value={value}>{value.replaceAll('_', ' ')}</MenuItem>)}</TextField>
            <TextField select label="Status *" defaultValue="planned" {...register('status')} error={Boolean(errors.status)} helperText={errors.status?.message}>{['planned', 'active', 'maintenance', 'inactive', 'decommissioned'].map((value) => <MenuItem key={value} value={value}>{value}</MenuItem>)}</TextField>
            <SearchableSelect<SearchableSelectOption<number>> label="Company" value={companyId ? { value: companyId, label: site?.company?.name || `Company #${companyId}` } : null} onChange={(option) => { setValue('company_id', option?.value); setValue('region_id', undefined); setValue('branch_id', undefined); }} loadOptions={async (search) => (await companiesApi.getAll({ search: search || undefined, per_page: 50 })).data.map((company) => ({ value: company.id, label: company.name }))} getOptionLabel={(option) => option.label} placeholder="Search companies..." error={Boolean(errors.company_id)} helperText={errors.company_id?.message} />
            <SearchableSelect<SearchableSelectOption<number>> label="Region" value={regionId ? { value: regionId, label: site?.region?.name || `Region #${regionId}` } : null} onChange={(option) => { setValue('region_id', option?.value); setValue('branch_id', undefined); }} options={(regions?.data ?? []).map((region) => ({ value: region.id, label: region.name }))} getOptionLabel={(option) => option.label} placeholder="Select region..." disabled={!companyId} error={Boolean(errors.region_id)} helperText={errors.region_id?.message} />
            <SearchableSelect<SearchableSelectOption<number>> label="Branch" value={branchId ? { value: branchId, label: site?.branch?.name || `Branch #${branchId}` } : null} onChange={(option) => setValue('branch_id', option?.value)} options={(branches?.data ?? []).map((branch) => ({ value: branch.id, label: branch.name }))} getOptionLabel={(option) => option.label} placeholder="Select branch..." disabled={!regionId} error={Boolean(errors.branch_id)} helperText={errors.branch_id?.message} />
            {field('latitude', 'Latitude', 'number')}{field('longitude', 'Longitude', 'number')}{field('altitude', 'Altitude', 'number')}{field('province', 'Province')}{field('district', 'District')}{field('municipality', 'Municipality')}{field('address', 'Address')}
            <Stack direction="row" spacing={2}><Button fullWidth onClick={onClose}>Cancel</Button><Button fullWidth type="submit" variant="contained" disabled={isSubmitting}>{isSubmitting ? 'Saving...' : 'Save'}</Button></Stack>
        </Stack>
    </Box></Drawer>;
};
