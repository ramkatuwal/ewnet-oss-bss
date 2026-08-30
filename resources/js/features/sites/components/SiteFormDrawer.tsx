import { useEffect, useState } from 'react';
import {
    Drawer,
    Box,
    Typography,
    TextField,
    Button,
    Stack,
    MenuItem,
    FormControl,
    InputLabel,
    Select,
    FormHelperText,
} from '@mui/material';
import { sitesApi, Site } from '@/api/sites';
import { useQuery } from '@tanstack/react-query';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { branchesApi } from '@/api/branches';

interface SiteFormDrawerProps {
    open: boolean;
    siteId?: number;
    onClose: () => void;
    onSuccess: () => void;
}

export const SiteFormDrawer = ({ open, siteId, onClose, onSuccess }: SiteFormDrawerProps) => {
    const [formData, setFormData] = useState<Partial<Site>>({});
    const [companyId, setCompanyId] = useState<number | ''>('');
    const [regionId, setRegionId] = useState<number | ''>('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const { data: siteData } = useQuery({
        queryKey: ['site', siteId],
        queryFn: () => sitesApi.get(siteId!),
        enabled: !!siteId && open,
    });

    const { data: companies } = useQuery({
        queryKey: ['companies'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    const { data: regions } = useQuery({
        queryKey: ['regions', companyId],
        queryFn: () => regionsApi.getAll({ company_id: companyId, per_page: 100 }),
        enabled: !!companyId,
    });

    const { data: branches } = useQuery({
        queryKey: ['branches', regionId],
        queryFn: () => branchesApi.getAll({ region_id: regionId, per_page: 100 }),
        enabled: !!regionId,
    });

    useEffect(() => {
        if (siteData) {
            setFormData(siteData);
            setCompanyId(siteData.company_id || '');
            setRegionId(siteData.region_id || '');
        } else {
            setFormData({
                type: 'pop',
                status: 'planned',
            });
            setCompanyId('');
            setRegionId('');
        }
        setErrors({});
    }, [siteData, open]);

    const handleSubmit = async () => {
        try {
            setErrors({});
            if (siteId) {
                await sitesApi.update(siteId, formData);
            } else {
                await sitesApi.create(formData);
            }
            onSuccess();
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        }
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ width: 500 }}>
            <Box sx={{ width: 500, p: 3 }}>
                <Typography variant="h6" gutterBottom>
                    {siteId ? 'Edit Site' : 'Add Site'}
                </Typography>
                <Stack spacing={2}>
                    <TextField
                        label="Site Code *"
                        value={formData.site_code || ''}
                        onChange={(e) => setFormData({ ...formData, site_code: e.target.value })}
                        fullWidth
                        error={!!errors.site_code}
                        helperText={errors.site_code?.[0]}
                    />
                    <TextField
                        label="Name *"
                        value={formData.name || ''}
                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        fullWidth
                        error={!!errors.name}
                        helperText={errors.name?.[0]}
                    />
                    <FormControl fullWidth error={!!errors.type}>
                        <InputLabel>Type *</InputLabel>
                        <Select
                            value={formData.type || 'pop'}
                            label="Type *"
                            onChange={(e) => setFormData({ ...formData, type: e.target.value })}
                        >
                            <MenuItem value="pop">POP</MenuItem>
                            <MenuItem value="tower">Tower</MenuItem>
                            <MenuItem value="office">Office</MenuItem>
                            <MenuItem value="warehouse">Warehouse</MenuItem>
                            <MenuItem value="datacenter">Datacenter</MenuItem>
                            <MenuItem value="customer_premises">Customer Premises</MenuItem>
                            <MenuItem value="solar_site">Solar Site</MenuItem>
                            <MenuItem value="repeater_site">Repeater Site</MenuItem>
                            <MenuItem value="other">Other</MenuItem>
                        </Select>
                        {errors.type && <FormHelperText>{errors.type[0]}</FormHelperText>}
                    </FormControl>
                    <FormControl fullWidth error={!!errors.status}>
                        <InputLabel>Status *</InputLabel>
                        <Select
                            value={formData.status || 'planned'}
                            label="Status *"
                            onChange={(e) => setFormData({ ...formData, status: e.target.value })}
                        >
                            <MenuItem value="planned">Planned</MenuItem>
                            <MenuItem value="active">Active</MenuItem>
                            <MenuItem value="maintenance">Maintenance</MenuItem>
                            <MenuItem value="inactive">Inactive</MenuItem>
                            <MenuItem value="decommissioned">Decommissioned</MenuItem>
                        </Select>
                        {errors.status && <FormHelperText>{errors.status[0]}</FormHelperText>}
                    </FormControl>
                    
                    {/* Organization Selection */}
                    <FormControl fullWidth error={!!errors.company_id}>
                        <InputLabel>Company</InputLabel>
                        <Select
                            value={companyId}
                            label="Company"
                            onChange={(e) => {
                                const val = e.target.value as number | '';
                                setCompanyId(val);
                                setFormData({ ...formData, company_id: val || undefined, region_id: undefined, branch_id: undefined });
                                setRegionId('');
                            }}
                        >
                            <MenuItem value=""><em>None</em></MenuItem>
                            {companies?.data.map((c: any) => (
                                <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                            ))}
                        </Select>
                        {errors.company_id && <FormHelperText>{errors.company_id[0]}</FormHelperText>}
                    </FormControl>

                    <FormControl fullWidth disabled={!companyId} error={!!errors.region_id}>
                        <InputLabel>Region</InputLabel>
                        <Select
                            value={regionId}
                            label="Region"
                            onChange={(e) => {
                                const val = e.target.value as number | '';
                                setRegionId(val);
                                setFormData({ ...formData, region_id: val || undefined, branch_id: undefined });
                            }}
                        >
                            <MenuItem value=""><em>None</em></MenuItem>
                            {regions?.data.map((r: any) => (
                                <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>
                            ))}
                        </Select>
                        {errors.region_id && <FormHelperText>{errors.region_id[0]}</FormHelperText>}
                    </FormControl>

                    <FormControl fullWidth disabled={!regionId} error={!!errors.branch_id}>
                        <InputLabel>Branch</InputLabel>
                        <Select
                            value={formData.branch_id || ''}
                            label="Branch"
                            onChange={(e) => setFormData({ ...formData, branch_id: e.target.value as number || undefined })}
                        >
                            <MenuItem value=""><em>None</em></MenuItem>
                            {branches?.data.map((b: any) => (
                                <MenuItem key={b.id} value={b.id}>{b.name}</MenuItem>
                            ))}
                        </Select>
                        {errors.branch_id && <FormHelperText>{errors.branch_id[0]}</FormHelperText>}
                    </FormControl>

                    {/* Location */}
                    <TextField
                        label="Latitude"
                        type="number"
                        value={formData.latitude || ''}
                        onChange={(e) => setFormData({ ...formData, latitude: parseFloat(e.target.value) || undefined })}
                        fullWidth
                        error={!!errors.latitude}
                        helperText={errors.latitude?.[0]}
                    />
                    <TextField
                        label="Longitude"
                        type="number"
                        value={formData.longitude || ''}
                        onChange={(e) => setFormData({ ...formData, longitude: parseFloat(e.target.value) || undefined })}
                        fullWidth
                        error={!!errors.longitude}
                        helperText={errors.longitude?.[0]}
                    />
                    <TextField
                        label="Altitude"
                        type="number"
                        value={formData.altitude || ''}
                        onChange={(e) => setFormData({ ...formData, altitude: parseFloat(e.target.value) || undefined })}
                        fullWidth
                    />

                    {/* Optional Address */}
                    <TextField
                        label="Province"
                        value={formData.province || ''}
                        onChange={(e) => setFormData({ ...formData, province: e.target.value })}
                        fullWidth
                    />
                    <TextField
                        label="District"
                        value={formData.district || ''}
                        onChange={(e) => setFormData({ ...formData, district: e.target.value })}
                        fullWidth
                    />
                    <TextField
                        label="Municipality"
                        value={formData.municipality || ''}
                        onChange={(e) => setFormData({ ...formData, municipality: e.target.value })}
                        fullWidth
                    />
                    <TextField
                        label="Address"
                        multiline
                        rows={2}
                        value={formData.address || ''}
                        onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                        fullWidth
                    />

                    <Box sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button onClick={onClose} fullWidth>Cancel</Button>
                        <Button variant="contained" onClick={handleSubmit} fullWidth>Save</Button>
                    </Box>
                </Stack>
            </Box>
        </Drawer>
    );
};
