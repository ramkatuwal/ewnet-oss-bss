import { useEffect, useMemo } from 'react';
import { Box, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { branchesApi } from '@/api/branches';

export interface OrgSelection {
    company_id?: number;
    region_id?: number;
    branch_id?: number;
}

interface OrgCascadePickerProps {
    value: OrgSelection;
    onChange: (value: OrgSelection) => void;
    disabled?: boolean;
    companyLocked?: boolean;
    companyLockedLabel?: string;
    showCompanyPicker?: boolean;
}

export const OrgCascadePicker = ({
    value,
    onChange,
    disabled,
    companyLocked,
    companyLockedLabel,
    showCompanyPicker = true,
}: OrgCascadePickerProps) => {
    const { data: companies } = useQuery({
        queryKey: ['organization', 'companies', 'options'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
        enabled: showCompanyPicker && !disabled,
        placeholderData: keepPreviousData,
    });

    const { data: regions, isFetching: regionsLoading } = useQuery({
        queryKey: ['organization', 'regions', 'options', value.company_id ?? 0],
        queryFn: () => regionsApi.getAll({ company_id: value.company_id, per_page: 500 }),
        enabled: Boolean(value.company_id) && !disabled,
        placeholderData: keepPreviousData,
    });

    const { data: branches, isFetching: branchesLoading } = useQuery({
        queryKey: ['organization', 'branches', 'options', value.region_id ?? 0],
        queryFn: () => branchesApi.getAll({ region_id: value.region_id, per_page: 500 }),
        enabled: Boolean(value.region_id) && !disabled,
        placeholderData: keepPreviousData,
    });

    const companyOptions = useMemo(() => companies?.data ?? [], [companies]);
    const regionOptions = useMemo(() => regions?.data ?? [], [regions]);
    const branchOptions = useMemo(() => branches?.data ?? [], [branches]);

    // Keep region/branch consistent when the filtered list no longer contains
    // the previously selected value.
    useEffect(() => {
        if (value.company_id && value.region_id && !regionsLoading && regionOptions.length > 0 && !regionOptions.some((r) => r.id === value.region_id)) {
            onChange({ ...value, region_id: undefined, branch_id: undefined });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [regionOptions, regionsLoading, value.company_id, value.region_id]);

    useEffect(() => {
        if (value.region_id && value.branch_id && !branchesLoading && branchOptions.length > 0 && !branchOptions.some((b) => b.id === value.branch_id)) {
            onChange({ ...value, branch_id: undefined });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [branchOptions, branchesLoading, value.region_id, value.branch_id]);

    return (
        <Box>
            <Stack direction="row" spacing={1.5} alignItems="flex-start">
                {showCompanyPicker ? (
                    <TextField
                        select
                        label="Company"
                        size="small"
                        value={value.company_id ?? ''}
                        disabled={disabled}
                        onChange={(e) => onChange({ company_id: e.target.value ? Number(e.target.value) : undefined })}
                        helperText="Limits site selection to this company."
                        sx={{ minWidth: 200 }}
                    >
                        <MenuItem value="">All Companies</MenuItem>
                        {companyOptions.map((c) => (
                            <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                        ))}
                    </TextField>
                ) : companyLocked ? (
                    <Box sx={{ display: 'flex', alignItems: 'center', minWidth: 200, py: 1 }}>
                        <Typography variant="caption" color="text.secondary" sx={{ mr: 1 }}>Company</Typography>
                        <Typography variant="body2" fontWeight="medium">{companyLockedLabel || 'Current company'}</Typography>
                    </Box>
                ) : null}

                <TextField
                    select
                    label="Region"
                    size="small"
                    value={value.region_id ?? ''}
                    disabled={disabled || !value.company_id}
                    onChange={(e) => onChange({ ...value, region_id: e.target.value ? Number(e.target.value) : undefined, branch_id: undefined })}
                    sx={{ minWidth: 180 }}
                >
                    <MenuItem value="">All Regions</MenuItem>
                    {regionOptions.map((r) => (
                        <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>
                    ))}
                </TextField>

                <TextField
                    select
                    label="Branch"
                    size="small"
                    value={value.branch_id ?? ''}
                    disabled={disabled || !value.region_id}
                    onChange={(e) => onChange({ ...value, branch_id: e.target.value ? Number(e.target.value) : undefined })}
                    sx={{ minWidth: 180 }}
                >
                    <MenuItem value="">All Branches</MenuItem>
                    {branchOptions.map((b) => (
                        <MenuItem key={b.id} value={b.id}>{b.name}</MenuItem>
                    ))}
                </TextField>
            </Stack>
        </Box>
    );
};