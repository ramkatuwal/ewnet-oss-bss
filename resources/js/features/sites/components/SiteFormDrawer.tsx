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
import SearchableSelect, { SearchableSelectOption } from '@/components/forms/SearchableSelect';

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
    const [companyLabel, setCompanyLabel] = useState('');
    const [regionLabel, setRegionLabel] = useState('');
    const [branchLabel, setBranchLabel] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});

    const { data: siteData } = useQuery({
        queryKey: ['site', siteId],
        queryFn: () => sitesApi.get(siteId!),
        enabled: !!siteId && open,
    });

    const { data: regions } = useQuery({
        queryKey: ['regions', companyId],
        queryFn: () => regionsApi.getAll({ company_id: companyId, per_page: 500 }),
        enabled: !!companyId,
    });

    const { data: branches } = useQuery({
        queryKey: ['branches', regionId],
        queryFn: () => branchesApi.getAll({ region_id: regionId, per_page: 500 }),
        enabled: !!regionId,
    });

    useEffect(() => {
        if (siteData) {
            setFormData(siteData);
            setCompanyId(siteData.company_id || '');
            setRegionId(siteData.region_id || '');
            setCompanyLabel(siteData.company?.name || '');
            setRegionLabel(siteData.region?.name || '');
            setBranchLabel(siteData.branch?.name || '');
        } else {
            setFormData({
                type: 'pop',
                status: 'planned',
            });
            setCompanyId('');
            setRegionId('');
            setCompanyLabel('');
            setRegionLabel('');
            setBranchLabel('');
        }
        setErrors({});
    }, [siteData, open]);

    // Type-ahead server-side lookup for companies (efficient with many records)
    const loadCompanyOptions = async (query: string): Promise<SearchableSelectOption<number>[]> => {
        const res = await companiesApi.getAll({ search: query || undefined, per_page: 50 });
        return (res.data || []).map((c) => ({ value: c.id, label: c.name }));
    };

    const regionOptions: SearchableSelectOption<number>[] = (regions?.data || []).map((r) => ({
        value: r.id,
        label: r.name,
    }));
    const branchOptions: SearchableSelectOption<number>[] = (branches?.data || []).map((b) => ({
        value: b.id,
        label: b.name,
    }));

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
                    <SearchableSelect<SearchableSelectOption<number>>
                        label="Company"
                        value={companyId !== '' ? { value: companyId, label: companyLabel || `Company #${companyId}` } : null}
                        onChange={(option) => {
                            if (option) {
                                setCompanyId(option.value);
                                setCompanyLabel(option.label);
                                setFormData({ ...formData, company_id: option.value, region_id: undefined, branch_id: undefined });
                                setRegionId('');
                                setRegionLabel('');
                                setBranchLabel('');
                            } else {
                                setCompanyId('');
                                setCompanyLabel('');
                                setFormData({ ...formData, company_id: undefined, region_id: undefined, branch_id: undefined });
                                setRegionId('');
                                setRegionLabel('');
                                setBranchLabel('');
                            }
                        }}
                        loadOptions={loadCompanyOptions}
                        getOptionLabel={(o) => o.label}
                        placeholder="Search by company name..."
                        error={!!errors.company_id}
                        helperText={errors.company_id?.[0]}
                    />

                    <SearchableSelect<SearchableSelectOption<number>>
                        label="Region"
                        value={regionId !== '' ? { value: regionId, label: regionLabel || `Region #${regionId}` } : null}
                        onChange={(option) => {
                            if (option) {
                                setRegionId(option.value);
                                setRegionLabel(option.label);
                                setFormData({ ...formData, region_id: option.value, branch_id: undefined });
                                setBranchLabel('');
                            } else {
                                setRegionId('');
                                setRegionLabel('');
                                setFormData({ ...formData, region_id: undefined, branch_id: undefined });
                                setBranchLabel('');
                            }
                        }}
                        options={regionOptions}
                        getOptionLabel={(o) => o.label}
                        placeholder="Type to search region..."
                        disabled={!companyId}
                        error={!!errors.region_id}
                        helperText={errors.region_id?.[0]}
                    />

                    <SearchableSelect<SearchableSelectOption<number>>
                        label="Branch"
                        value={formData.branch_id ? { value: formData.branch_id, label: branchLabel || `Branch #${formData.branch_id}` } : null}
                        onChange={(option) => {
                            setFormData({ ...formData, branch_id: option ? option.value : undefined });
                            setBranchLabel(option ? option.label : '');
                        }}
                        options={branchOptions}
                        getOptionLabel={(o) => o.label}
                        placeholder="Type to search branch..."
                        disabled={!regionId}
                        error={!!errors.branch_id}
                        helperText={errors.branch_id?.[0]}
                    />

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
