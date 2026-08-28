import { useEffect, useState } from 'react';
import {
    Drawer, Box, Typography, TextField, Button, Divider, IconButton, Grid,
    FormControlLabel, Switch, Alert, FormControl, InputLabel, Select, MenuItem,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery } from '@tanstack/react-query';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { branchesApi } from '@/api/branches';
import type { Department } from '@/types';

const deptSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    code: z.string().min(1, 'Code is required').max(255),
    description: z.string().max(2000).optional().or(z.literal('')),
    company_id: z.number().min(1, 'Company is required'),
    branch_id: z.number().min(1, 'Branch is required'),
    is_active: z.boolean(),
});

type DeptFormData = z.infer<typeof deptSchema>;

interface Props {
    open: boolean;
    onClose: () => void;
    department: Department | null;
    branchId?: number; // If provided, branch is fixed (contextual mode)
    onSubmit: (data: Partial<Department>) => void;
    loading?: boolean;
}

export const DepartmentFormDrawer = ({ open, onClose, department, branchId, onSubmit, loading }: Props) => {
    const isEdit = !!department;
    const isContextual = !!branchId; // true when opened from branch detail
    // const { isSuperAdmin } = useAuthStore();

    const [selectedCompany, setSelectedCompany] = useState<number>(0);
    const [selectedRegion, setSelectedRegion] = useState<number>(0);

    const { control, handleSubmit, reset, setValue, formState: { errors } } = useForm<DeptFormData>({
        resolver: zodResolver(deptSchema),
        defaultValues: {
            name: '', code: '', description: '',
            company_id: 0, branch_id: 0, is_active: true,
        },
    });

    // const watchedCompanyId = watch('company_id');
    // const watchedBranchId = watch('branch_id');

    // ── Reference Data Queries ──────────────────────────────────

    const { data: companiesData } = useQuery({
        queryKey: ['companies', 'all'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    // Regions filtered by selected company
    const { data: regionsData } = useQuery({
        queryKey: ['regions', 'filter', selectedCompany],
        queryFn: () => regionsApi.getAll({ per_page: 200, company_id: selectedCompany }),
        enabled: !!selectedCompany && !isContextual,
    });

    // Branches filtered by selected company (for top-level mode)
    const { data: branchesByCompanyData } = useQuery({
        queryKey: ['branches', 'byCompany', selectedCompany],
        queryFn: () => branchesApi.getAll({ per_page: 200, company_id: selectedCompany }),
        enabled: !!selectedCompany && !isContextual,
    });

    // Branch info for contextual mode
    const { data: contextBranchData } = useQuery({
        queryKey: ['branch', branchId],
        queryFn: () => branchesApi.getById(branchId!),
        enabled: !!branchId,
    });

    // ── Form Reset ──────────────────────────────────────────────

    useEffect(() => {
        if (open) {
            if (isContextual && contextBranchData) {
                // Contextual mode: branch is fixed
                const cb = contextBranchData as any;
                const companyId = cb.region?.company?.id ?? cb.company_id ?? 0;
                setSelectedCompany(companyId);
                setSelectedRegion(cb.region?.id ?? 0);
                reset({
                    name: department?.name ?? '',
                    code: department?.code ?? '',
                    description: department?.description ?? '',
                    company_id: companyId,
                    branch_id: branchId!,
                    is_active: department?.is_active ?? true,
                });
            } else if (isEdit && department) {
                // Edit mode: pre-populate from existing department
                const companyId = department.company_id ?? 0;
                const bId = department.branch_id ?? 0;
                setSelectedCompany(companyId);
                // Find region from branch data
                const regionId = (department as any).branch?.region?.id ?? 0;
                setSelectedRegion(regionId);
                reset({
                    name: department.name ?? '',
                    code: department.code ?? '',
                    description: department.description ?? '',
                    company_id: companyId,
                    branch_id: bId,
                    is_active: department.is_active ?? true,
                });
            } else {
                // Create mode (top-level): everything selectable
                setSelectedCompany(0);
                setSelectedRegion(0);
                reset({
                    name: '', code: '', description: '',
                    company_id: 0, branch_id: 0, is_active: true,
                });
            }
        }
    }, [open, department, branchId, isContextual, isEdit, contextBranchData, reset]);

    // ── Available Options ───────────────────────────────────────

    const companies = companiesData?.data ?? [];
    const regions = regionsData?.data ?? [];
    const branches = branchesByCompanyData?.data ?? [];

    // In contextual mode, show only the fixed branch
    // const availableBranches = isContextual
    //     ? (contextBranchData ? [contextBranchData] : [])
    //     : branches;

    // Context display for contextual mode
    const contextBranch = contextBranchData as any;
    const contextCompanyName = contextBranch?.region?.company?.name;
    const contextRegionName = contextBranch?.region?.name;
    const contextBranchName = contextBranch?.name;

    // ── Form Submission ─────────────────────────────────────────

    const onFormSubmit = (data: DeptFormData) => {
        onSubmit({
            name: data.name,
            code: data.code,
            description: data.description || null,
            company_id: data.company_id,
            branch_id: data.branch_id,
            is_active: data.is_active,
        });
    };

    return (
        <Drawer
            anchor="right"
            open={open}
            onClose={onClose}
            sx={{ '& .MuiDrawer-paper': { width: 520, p: 3 } }}
        >
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>
                    {isEdit ? 'Edit Department' : 'Create Department'}
                </Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 2 }} />

            {/* Contextual mode: show fixed hierarchy */}
            {isContextual && (
                <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
                    <Typography variant="caption" display="block">Parent Branch (fixed)</Typography>
                    <Typography variant="body2" fontWeight={600}>
                        {[contextCompanyName, contextRegionName, contextBranchName].filter(Boolean).join(' → ')}
                    </Typography>
                </Alert>
            )}

            <form onSubmit={handleSubmit(onFormSubmit)}>
                <Grid container spacing={2}>
                    {/* ── ORGANIZATION (only in top-level mode) ──── */}
                    {!isContextual && (
                        <>
                            <Grid item xs={12}>
                                <Typography variant="subtitle2" color="primary" fontWeight={600}>
                                    Organization
                                </Typography>
                            </Grid>
                            <Grid item xs={12}>
                                <Controller name="company_id" control={control} render={({ field }) => (
                                    <FormControl fullWidth size="small" error={!!errors.company_id} required>
                                        <InputLabel>Company</InputLabel>
                                        <Select {...field} label="Company"
                                            onChange={(e) => {
                                                field.onChange(e);
                                                setSelectedCompany(Number(e.target.value));
                                                setSelectedRegion(0);
                                                setValue('branch_id', 0);
                                            }}>
                                            <MenuItem value={0} disabled>Select Company</MenuItem>
                                            {companies.map((c: any) => (
                                                <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                                            ))}
                                        </Select>
                                        {errors.company_id && (
                                            <Typography variant="caption" color="error">{errors.company_id.message}</Typography>
                                        )}
                                    </FormControl>
                                )} />
                            </Grid>
                            <Grid item xs={12}>
                                <FormControl fullWidth size="small">
                                    <InputLabel>Region (optional filter)</InputLabel>
                                    <Select value={selectedRegion} label="Region (optional filter)"
                                        disabled={!selectedCompany}
                                        onChange={(e) => {
                                            setSelectedRegion(Number(e.target.value));
                                            setValue('branch_id', 0);
                                        }}>
                                        <MenuItem value={0}>All Regions</MenuItem>
                                        {regions.map((r: any) => (
                                            <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>
                                        ))}
                                    </Select>
                                </FormControl>
                            </Grid>
                            <Grid item xs={12}>
                                <Controller name="branch_id" control={control} render={({ field }) => (
                                    <FormControl fullWidth size="small" error={!!errors.branch_id} required>
                                        <InputLabel>Branch</InputLabel>
                                        <Select {...field} label="Branch"
                                            disabled={!selectedCompany}>
                                            <MenuItem value={0} disabled>Select Branch</MenuItem>
                                            {(selectedRegion
                                                ? branches.filter((b: any) => b.region_id === selectedRegion)
                                                : branches
                                            ).map((b: any) => (
                                                <MenuItem key={b.id} value={b.id}>
                                                    {b.name} {b.region?.name ? `(${b.region.name})` : ''}
                                                </MenuItem>
                                            ))}
                                        </Select>
                                        {errors.branch_id && (
                                            <Typography variant="caption" color="error">{errors.branch_id.message}</Typography>
                                        )}
                                    </FormControl>
                                )} />
                            </Grid>
                            <Grid item xs={12}><Divider sx={{ my: 0.5 }} /></Grid>
                        </>
                    )}

                    {/* ── DEPARTMENT INFORMATION ─────────────────── */}
                    <Grid item xs={12}>
                        <Typography variant="subtitle2" color="primary" fontWeight={600}>
                            Department Information
                        </Typography>
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="name" control={control} render={({ field }) => (
                            <TextField {...field} label="Department Name" fullWidth required size="small"
                                error={!!errors.name} helperText={errors.name?.message} />
                        )} />
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="code" control={control} render={({ field }) => (
                            <TextField {...field} label="Code" fullWidth required size="small"
                                error={!!errors.code} helperText={errors.code?.message} />
                        )} />
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="description" control={control} render={({ field }) => (
                            <TextField {...field} label="Description" fullWidth multiline rows={3} size="small"
                                error={!!errors.description} helperText={errors.description?.message} />
                        )} />
                    </Grid>

                    {/* ── STATUS ──────────────────────────────────── */}
                    <Grid item xs={12}>
                        <Divider sx={{ my: 1 }} />
                        <Controller name="is_active" control={control} render={({ field }) => (
                            <FormControlLabel
                                control={<Switch checked={field.value} onChange={field.onChange} />}
                                label="Active"
                            />
                        )} />
                    </Grid>

                    {/* ── ACTIONS ─────────────────────────────────── */}
                    <Grid item xs={12} sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={loading} fullWidth>
                            {loading ? 'Saving...' : isEdit ? 'Update Department' : 'Create Department'}
                        </Button>
                    </Grid>
                </Grid>
            </form>
        </Drawer>
    );
};
