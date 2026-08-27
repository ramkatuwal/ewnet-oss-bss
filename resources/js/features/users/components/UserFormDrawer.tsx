import React, { useEffect, useState } from 'react';
import { Drawer, Box, Typography, TextField, Button, FormControlLabel, Switch, Divider, IconButton, Grid, FormControl, InputLabel, Select, MenuItem, Chip, OutlinedInput, Alert } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { companiesApi } from '@/api/companies';
import { branchesApi } from '@/api/branches';
import { regionsApi } from '@/api/regions';
import type { UserListItem } from '@/types';

const userSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
    email: z.string().email('Invalid email').max(255),
    password: z.string().min(8, 'Password must be at least 8 characters').optional().or(z.literal('')),
    phone_number: z.string().max(255).optional().or(z.literal('')),
    company_id: z.number().min(1, 'Company is required'),
    branch_id: z.number().nullable().optional(),
    department_id: z.number().nullable().optional(),
    roles: z.array(z.number()).min(1, 'At least one role is required'),
    is_active: z.boolean(),
});

type UserFormData = z.infer<typeof userSchema>;

interface Props {
    open: boolean;
    onClose: () => void;
    user: UserListItem | null;
    onSubmit: (data: Record<string, unknown>) => void;
    loading?: boolean;
}

export const UserFormDrawer = ({ open, onClose, user, onSubmit, loading }: Props) => {
    const isEdit = !!user;
    const { user: authUser, isSuperAdmin } = useAuthStore();

    const [selectedCompany, setSelectedCompany] = useState<number>(0);
    const [selectedBranch, setSelectedBranch] = useState<number>(0);

    const { control, handleSubmit, reset, watch, formState: { errors } } = useForm<UserFormData>({
        resolver: zodResolver(userSchema),
        defaultValues: { name: '', email: '', password: '', phone_number: '', company_id: 0, branch_id: null, department_id: null, roles: [], is_active: true },
    });

    // Fetch reference data
    const { data: companiesData } = useQuery({ queryKey: ['companies', 'all'], queryFn: () => companiesApi.getAll({ per_page: 100 }) });
    const { data: regionsData } = useQuery({
        queryKey: ['regions', 'filter', selectedCompany],
        queryFn: () => regionsApi.getAll({ per_page: 100, ...(selectedCompany ? { company_id: selectedCompany } : {}) }),
        enabled: !!selectedCompany,
    });
    const { data: branchesData } = useQuery({
        queryKey: ['branches', 'filter', selectedCompany],
        queryFn: () => branchesApi.getAll({ per_page: 100, ...(selectedCompany ? { company_id: selectedCompany } : {}) }),
        enabled: !!selectedCompany,
    });

    // Fetch departments filtered by selected branch
    const { data: departmentsData } = useQuery({
        queryKey: ['departments', 'filter', selectedBranch],
        queryFn: () => import('@/api/client').then(m => m.apiClient.get('/api/v1/organization/departments', { params: { per_page: 100, branch_id: selectedBranch || undefined } }).then(r => r.data)),
        enabled: !!selectedBranch,
    });

    // Fetch available roles
    const { data: rolesData } = useQuery({
        queryKey: ['roles', 'all'],
        queryFn: () => import('@/api/client').then(m => m.apiClient.get('/api/v1/security/roles').then(r => r.data)),
    });

    useEffect(() => {
        if (open) {
            const companyId = user?.company_id ?? authUser?.company_id ?? 0;
            const branchId = user?.branch_id ?? authUser?.branch_id ?? 0;
            setSelectedCompany(companyId);
            setSelectedBranch(branchId);
            reset({
                name: user?.name ?? '',
                email: user?.email ?? '',
                password: '',
                phone_number: user?.phone_number ?? '',
                company_id: companyId,
                branch_id: user?.branch_id ?? null,
                department_id: user?.department_id ?? null,
                roles: (user?.roles ?? []).map(r => r.id),
                is_active: user?.is_active ?? true,
            });
        }
    }, [open, user, reset, authUser]);

    // Filter roles based on auth user's privileges
    const allRoles = Array.isArray(rolesData) ? rolesData : (rolesData as any)?.data ?? [];
    const availableRoles = isSuperAdmin()
        ? allRoles
        : allRoles.filter((r: any) => r.name !== 'Super Admin');

    // Filtered org options based on auth scope
    const availableCompanies = isSuperAdmin()
        ? (companiesData?.data ?? [])
        : (companiesData?.data ?? []).filter(c => c.id === authUser?.company_id);

    const availableBranches = isSuperAdmin()
        ? (branchesData?.data ?? [])
        : (branchesData?.data ?? []).filter(b => !authUser?.branch_id || b.id === authUser.branch_id);

    const depts = departmentsData?.data ?? [];
    const availableDepartments = isSuperAdmin()
        ? depts
        : depts.filter((d: any) => !authUser?.department_id || d.id === authUser.department_id);

    const onFormSubmit = (data: UserFormData) => {
        const payload: Record<string, unknown> = {
            name: data.name,
            email: data.email,
            phone_number: data.phone_number || null,
            company_id: data.company_id,
            branch_id: data.branch_id || null,
            department_id: data.department_id || null,
            roles: data.roles,
            is_active: data.is_active,
        };
        if (data.password) payload.password = data.password;
        onSubmit(payload);
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ '& .MuiDrawer-paper': { width: 520, p: 3 } }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>{isEdit ? 'Edit User' : 'Create User'}</Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 3 }} />

            {!isSuperAdmin() && (
                <Alert severity="info" sx={{ mb: 2 }} variant="outlined">
                    Organization options are limited to your authorized scope.
                </Alert>
            )}

            <form onSubmit={handleSubmit(onFormSubmit)}>
                <Grid container spacing={2}>
                    {/* Account Info */}
                    <Grid item xs={12}><Typography variant="subtitle2" color="primary" fontWeight={600}>Account Information</Typography></Grid>
                    <Grid item xs={12}><Controller name="name" control={control} render={({ field }) => <TextField {...field} label="Full Name" fullWidth required size="small" error={!!errors.name} helperText={errors.name?.message} />} /></Grid>
                    <Grid item xs={12}><Controller name="email" control={control} render={({ field }) => <TextField {...field} label="Email Address" fullWidth required size="small" error={!!errors.email} helperText={errors.email?.message} />} /></Grid>
                    <Grid item xs={12}><Controller name="password" control={control} render={({ field }) => <TextField {...field} type="password" label={isEdit ? 'New Password (leave blank to keep)' : 'Password'} fullWidth required={!isEdit} size="small" error={!!errors.password} helperText={errors.password?.message} />} /></Grid>
                    <Grid item xs={12}><Controller name="phone_number" control={control} render={({ field }) => <TextField {...field} label="Phone Number" fullWidth size="small" />} /></Grid>

                    {/* Organization */}
                    <Grid item xs={12}><Divider sx={{ my: 1 }} /><Typography variant="subtitle2" color="primary" fontWeight={600}>Organization Assignment</Typography></Grid>
                    <Grid item xs={12}>
                        <Controller name="company_id" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small" error={!!errors.company_id} required>
                                <InputLabel>Company</InputLabel>
                                <Select {...field} label="Company" disabled={!isSuperAdmin() && availableCompanies.length <= 1}
                                    onChange={(e) => { field.onChange(e); setSelectedCompany(Number(e.target.value)); setSelectedBranch(0); }}>
                                    <MenuItem value={0} disabled>Select Company</MenuItem>
                                    {availableCompanies.map((c: any) => <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>)}
                                </Select>
                            </FormControl>
                        )} />
                    </Grid>
                    <Grid item xs={6}>
                        <Controller name="branch_id" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small">
                                <InputLabel>Branch</InputLabel>
                                <Select {...field} label="Branch" value={field.value ?? 0}
                                    disabled={!selectedCompany || (!isSuperAdmin() && availableBranches.length <= 1)}
                                    onChange={(e) => { field.onChange(e.target.value || null); setSelectedBranch(Number(e.target.value)); }}>
                                    <MenuItem value={0}>None</MenuItem>
                                    {availableBranches.map((b: any) => <MenuItem key={b.id} value={b.id}>{b.name}</MenuItem>)}
                                </Select>
                            </FormControl>
                        )} />
                    </Grid>
                    <Grid item xs={6}>
                        <Controller name="department_id" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small">
                                <InputLabel>Department</InputLabel>
                                <Select {...field} label="Department" value={field.value ?? 0}
                                    disabled={!selectedBranch || (!isSuperAdmin() && availableDepartments.length <= 1)}>
                                    <MenuItem value={0}>None</MenuItem>
                                    {availableDepartments.map((d: any) => <MenuItem key={d.id} value={d.id}>{d.name}</MenuItem>)}
                                </Select>
                            </FormControl>
                        )} />
                    </Grid>

                    {/* Roles */}
                    <Grid item xs={12}><Divider sx={{ my: 1 }} /><Typography variant="subtitle2" color="primary" fontWeight={600}>Role Assignment</Typography></Grid>
                    <Grid item xs={12}>
                        <Controller name="roles" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small" error={!!errors.roles}>
                                <InputLabel>Roles</InputLabel>
                                <Select {...field} multiple label="Roles" input={<OutlinedInput label="Roles" />}
                                    renderValue={(selected) => (
                                        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                                            {(selected as number[]).map((id) => {
                                                const role = availableRoles.find((r: any) => r.id === id);
                                                return <Chip key={id} label={role?.name || id} size="small" color={role?.name === 'Super Admin' ? 'error' : 'default'} />;
                                            })}
                                        </Box>
                                    )}>
                                    {availableRoles.map((role: any) => (
                                        <MenuItem key={role.id} value={role.id}>{role.name}</MenuItem>
                                    ))}
                                </Select>
                                {errors.roles && <Typography variant="caption" color="error">{errors.roles.message}</Typography>}
                            </FormControl>
                        )} />
                    </Grid>

                    {/* Status */}
                    <Grid item xs={12} sx={{ mt: 1 }}>
                        <Divider sx={{ mb: 2 }} />
                        <Controller name="is_active" control={control} render={({ field }) => <FormControlLabel control={<Switch checked={field.value} onChange={field.onChange} />} label="Active" />} />
                    </Grid>

                    {/* Actions */}
                    <Grid item xs={12} sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={loading} fullWidth>{loading ? 'Saving...' : isEdit ? 'Update User' : 'Create User'}</Button>
                    </Grid>
                </Grid>
            </form>
        </Drawer>
    );
};
