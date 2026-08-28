import { useEffect, useState } from 'react';
import {
    Drawer, Box, Typography, TextField, Button, Divider, IconButton, Grid,
    FormControl, InputLabel, Select, MenuItem, Chip, OutlinedInput, Alert,
    FormControlLabel, Switch, Stack, Paper,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import AddCircleOutlineIcon from '@mui/icons-material/AddCircleOutline';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { companiesApi } from '@/api/companies';
import { regionsApi } from '@/api/regions';
import { branchesApi } from '@/api/branches';
import { departmentsApi } from '@/api/departments';
import { scopesApi } from '@/api/scopes';
import { apiClient } from '@/api/client';
import type { UserListItem, ManagementScope, ScopeType } from '@/types';

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

interface PendingScope {
    scope_type: ScopeType;
    scope_id: number;
    scope_name?: string;
}

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
    const [pendingScopes, setPendingScopes] = useState<PendingScope[]>([]);

    // Scope assignment form state
    const [scopeType, setScopeType] = useState<ScopeType | ''>('');
    const [scopeCompany, setScopeCompany] = useState<number>(0);
    const [scopeRegion, setScopeRegion] = useState<number>(0);
    const [scopeBranch, setScopeBranch] = useState<number>(0);
    const [scopeDepartment, setScopeDepartment] = useState<number>(0);

    const { control, handleSubmit, reset, formState: { errors } } = useForm<UserFormData>({
        resolver: zodResolver(userSchema),
        defaultValues: {
            name: '', email: '', password: '', phone_number: '',
            company_id: 0, branch_id: null, department_id: null,
            roles: [], is_active: true,
        },
    });

    // ── Reference Data Queries ──────────────────────────────────

    const { data: companiesData } = useQuery({
        queryKey: ['companies', 'all'],
        queryFn: () => companiesApi.getAll({ per_page: 100 }),
    });

    useQuery({
        queryKey: ['regions', 'filter', selectedCompany],
        queryFn: () => regionsApi.getAll({ per_page: 100, ...(selectedCompany ? { company_id: selectedCompany } : {}) }),
        enabled: !!selectedCompany,
    });

    const { data: branchesData } = useQuery({
        queryKey: ['branches', 'filter', selectedCompany],
        queryFn: () => branchesApi.getAll({ per_page: 100, ...(selectedCompany ? { company_id: selectedCompany } : {}) }),
        enabled: !!selectedCompany,
    });

    const { data: departmentsData } = useQuery({
        queryKey: ['departments', 'filter', selectedBranch],
        queryFn: () => departmentsApi.getAll({ per_page: 100, ...(selectedBranch ? { branch_id: selectedBranch } : {}) }),
        enabled: !!selectedBranch,
    });

    const { data: rolesData } = useQuery({
        queryKey: ['roles', 'all'],
        queryFn: () => apiClient.get('/api/v1/security/roles').then(r => r.data),
    });

    const { data: userScopes } = useQuery({
        queryKey: ['userScopes', user?.id],
        queryFn: () => scopesApi.getUserScopes(user!.id),
        enabled: !!user?.id,
    });

    // ── Scope Cascading Queries ─────────────────────────────────

    const { data: scopeRegionsData } = useQuery({
        queryKey: ['scopeRegions', scopeCompany],
        queryFn: () => regionsApi.getAll({ per_page: 100, company_id: scopeCompany }),
        enabled: !!scopeCompany && (scopeType === 'region' || scopeType === 'branch' || scopeType === 'department'),
    });

    const { data: scopeBranchesData } = useQuery({
        queryKey: ['scopeBranches', scopeRegion],
        queryFn: () => branchesApi.getAll({ per_page: 100, region_id: scopeRegion }),
        enabled: !!scopeRegion && (scopeType === 'branch' || scopeType === 'department'),
    });

    const { data: scopeDepartmentsData } = useQuery({
        queryKey: ['scopeDepartments', scopeBranch],
        queryFn: () => departmentsApi.getAll({ per_page: 100, branch_id: scopeBranch }),
        enabled: !!scopeBranch && scopeType === 'department',
    });

    // ── Form Reset ──────────────────────────────────────────────

    useEffect(() => {
        if (open) {
            const companyId = user?.company_id ?? authUser?.company_id ?? 0;
            const branchId = user?.branch_id ?? authUser?.branch_id ?? 0;
            setSelectedCompany(companyId);
            setSelectedBranch(branchId);
            setPendingScopes((user?.management_scopes ?? []).map(s => ({
                scope_type: s.scope_type as ScopeType,
                scope_id: s.scope_id,
                scope_name: s.scope_name,
            })));
            resetScopeForm();
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

    const resetScopeForm = () => {
        setScopeType('');
        setScopeCompany(0);
        setScopeRegion(0);
        setScopeBranch(0);
        setScopeDepartment(0);
    };

    // ── Data Derivation ─────────────────────────────────────────

    const allRoles: { id: number; name: string }[] = Array.isArray(rolesData) ? rolesData : (rolesData as any)?.data ?? [];
    const availableRoles = isSuperAdmin()
        ? allRoles
        : allRoles.filter((r) => r.name !== 'Super Admin');

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

    // ── Scope Management ────────────────────────────────────────

    const addScope = () => {
        if (!scopeType) return;

        let scopeId = 0;
        let scopeName = '';

        if (scopeType === 'company') {
            scopeId = scopeCompany;
            scopeName = (companiesData?.data ?? []).find(c => c.id === scopeCompany)?.name ?? '';
        } else if (scopeType === 'region') {
            scopeId = scopeRegion;
            scopeName = (scopeRegionsData?.data ?? []).find(r => r.id === scopeRegion)?.name ?? '';
        } else if (scopeType === 'branch') {
            scopeId = scopeBranch;
            scopeName = (scopeBranchesData?.data ?? []).find(b => b.id === scopeBranch)?.name ?? '';
        } else if (scopeType === 'department') {
            scopeId = scopeDepartment;
            scopeName = (scopeDepartmentsData?.data ?? []).find(d => d.id === scopeDepartment)?.name ?? '';
        }

        if (!scopeId) return;

        // Prevent duplicates
        const exists = pendingScopes.some(s => s.scope_type === scopeType && s.scope_id === scopeId);
        if (exists) return;

        setPendingScopes([...pendingScopes, { scope_type: scopeType, scope_id: scopeId, scope_name: scopeName }]);
        resetScopeForm();
    };

    const removeScope = (index: number) => {
        setPendingScopes(pendingScopes.filter((_, i) => i !== index));
    };

    // ── Form Submission ─────────────────────────────────────────

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
            management_scopes: pendingScopes,
        };
        if (data.password) payload.password = data.password;
        onSubmit(payload);
    };

    return (
        <Drawer
            anchor="right"
            open={open}
            onClose={onClose}
            sx={{ '& .MuiDrawer-paper': { width: 560, p: 3 } }}
        >
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>
                    {isEdit ? 'Edit User' : 'Create User'}
                </Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 2 }} />

            {!isSuperAdmin() && (
                <Alert severity="info" sx={{ mb: 2 }} variant="outlined">
                    Organization options are limited to your authorized scope.
                </Alert>
            )}

            <form onSubmit={handleSubmit(onFormSubmit)}>
                <Grid container spacing={2}>
                    {/* ── ACCOUNT INFORMATION ─────────────────────── */}
                    <Grid item xs={12}>
                        <Typography variant="subtitle2" color="primary" fontWeight={600}>
                            Account Information
                        </Typography>
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="name" control={control} render={({ field }) => (
                            <TextField {...field} label="Full Name" fullWidth required size="small"
                                error={!!errors.name} helperText={errors.name?.message} />
                        )} />
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="email" control={control} render={({ field }) => (
                            <TextField {...field} label="Email Address" fullWidth required size="small"
                                error={!!errors.email} helperText={errors.email?.message} />
                        )} />
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="password" control={control} render={({ field }) => (
                            <TextField {...field} type="password"
                                label={isEdit ? 'New Password (leave blank to keep)' : 'Password'}
                                fullWidth required={!isEdit} size="small"
                                error={!!errors.password} helperText={errors.password?.message} />
                        )} />
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="phone_number" control={control} render={({ field }) => (
                            <TextField {...field} label="Phone Number" fullWidth size="small" />
                        )} />
                    </Grid>

                    {/* ── MEMBERSHIP ──────────────────────────────── */}
                    <Grid item xs={12}>
                        <Divider sx={{ my: 1 }} />
                        <Typography variant="subtitle2" color="primary" fontWeight={600}>
                            Membership (Where the user belongs)
                        </Typography>
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="company_id" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small" error={!!errors.company_id} required>
                                <InputLabel>Company</InputLabel>
                                <Select {...field} label="Company"
                                    disabled={!isSuperAdmin() && availableCompanies.length <= 1}
                                    onChange={(e) => {
                                        field.onChange(e);
                                        setSelectedCompany(Number(e.target.value));
                                        setSelectedBranch(0);
                                    }}>
                                    <MenuItem value={0} disabled>Select Company</MenuItem>
                                    {availableCompanies.map((c: any) => (
                                        <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                                    ))}
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
                                    onChange={(e) => {
                                        field.onChange(e.target.value || null);
                                        setSelectedBranch(Number(e.target.value));
                                    }}>
                                    <MenuItem value={0}>None</MenuItem>
                                    {availableBranches.map((b: any) => (
                                        <MenuItem key={b.id} value={b.id}>{b.name}</MenuItem>
                                    ))}
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
                                    {availableDepartments.map((d: any) => (
                                        <MenuItem key={d.id} value={d.id}>{d.name}</MenuItem>
                                    ))}
                                </Select>
                            </FormControl>
                        )} />
                    </Grid>

                    {/* ── ROLES ───────────────────────────────────── */}
                    <Grid item xs={12}>
                        <Divider sx={{ my: 1 }} />
                        <Typography variant="subtitle2" color="primary" fontWeight={600}>
                            Roles
                        </Typography>
                    </Grid>
                    <Grid item xs={12}>
                        <Controller name="roles" control={control} render={({ field }) => (
                            <FormControl fullWidth size="small" error={!!errors.roles}>
                                <InputLabel>Roles</InputLabel>
                                <Select {...field} multiple label="Roles"
                                    input={<OutlinedInput label="Roles" />}
                                    renderValue={(selected) => (
                                        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 0.5 }}>
                                            {(selected as number[]).map((id) => {
                                                const role = availableRoles.find((r: any) => r.id === id);
                                                return (
                                                    <Chip key={id} label={role?.name || id} size="small"
                                                        color={role?.name === 'Super Admin' ? 'error' : 'default'} />
                                                );
                                            })}
                                        </Box>
                                    )}>
                                    {availableRoles.map((role: any) => (
                                        <MenuItem key={role.id} value={role.id}>{role.name}</MenuItem>
                                    ))}
                                </Select>
                                {errors.roles && (
                                    <Typography variant="caption" color="error">{errors.roles.message}</Typography>
                                )}
                            </FormControl>
                        )} />
                    </Grid>

                    {/* ── MANAGEMENT SCOPE ────────────────────────── */}
                    <Grid item xs={12}>
                        <Divider sx={{ my: 1 }} />
                        <Typography variant="subtitle2" color="primary" fontWeight={600}>
                            Management Scope (Where the user can manage)
                        </Typography>
                    </Grid>

                    {/* Current scopes */}
                    <Grid item xs={12}>
                        <Paper variant="outlined" sx={{ p: 2 }}>
                            <Typography variant="caption" color="text.secondary" gutterBottom>
                                Assigned Scopes
                            </Typography>
                            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1, mt: 1 }}>
                                {pendingScopes.length === 0 && (
                                    <Typography variant="body2" color="text.secondary">
                                        No explicit scopes — will use membership-based fallback
                                    </Typography>
                                )}
                                {pendingScopes.map((scope, idx) => (
                                    <Chip
                                        key={idx}
                                        label={`${scope.scope_type}: ${scope.scope_name || scope.scope_id}`}
                                        onDelete={() => removeScope(idx)}
                                        color={scope.scope_type === 'company' ? 'primary' : 'default'}
                                        size="small"
                                    />
                                ))}
                            </Box>
                        </Paper>
                    </Grid>

                    {/* Add scope form */}
                    <Grid item xs={12}>
                        <Paper variant="outlined" sx={{ p: 2 }}>
                            <Typography variant="caption" color="text.secondary" gutterBottom>
                                Add Management Scope
                            </Typography>
                            <Stack spacing={1.5} sx={{ mt: 1 }}>
                                {/* Scope Type */}
                                <FormControl fullWidth size="small">
                                    <InputLabel>Scope Type</InputLabel>
                                    <Select value={scopeType} label="Scope Type"
                                        onChange={(e) => {
                                            setScopeType(e.target.value as ScopeType);
                                            setScopeCompany(0);
                                            setScopeRegion(0);
                                            setScopeBranch(0);
                                            setScopeDepartment(0);
                                        }}>
                                        <MenuItem value="">Select Type</MenuItem>
                                        <MenuItem value="company">Company</MenuItem>
                                        <MenuItem value="region">Region</MenuItem>
                                        <MenuItem value="branch">Branch</MenuItem>
                                        <MenuItem value="department">Department</MenuItem>
                                    </Select>
                                </FormControl>

                                {/* Cascading selectors */}
                                {scopeType && (
                                    <FormControl fullWidth size="small">
                                        <InputLabel>Company</InputLabel>
                                        <Select value={scopeCompany} label="Company"
                                            onChange={(e) => {
                                                setScopeCompany(Number(e.target.value));
                                                setScopeRegion(0);
                                                setScopeBranch(0);
                                                setScopeDepartment(0);
                                            }}>
                                            <MenuItem value={0} disabled>Select Company</MenuItem>
                                            {(companiesData?.data ?? []).map((c: any) => (
                                                <MenuItem key={c.id} value={c.id}>{c.name}</MenuItem>
                                            ))}
                                        </Select>
                                    </FormControl>
                                )}

                                {(scopeType === 'region' || scopeType === 'branch' || scopeType === 'department') && scopeCompany > 0 && (
                                    <FormControl fullWidth size="small">
                                        <InputLabel>Region</InputLabel>
                                        <Select value={scopeRegion} label="Region"
                                            onChange={(e) => {
                                                setScopeRegion(Number(e.target.value));
                                                setScopeBranch(0);
                                                setScopeDepartment(0);
                                            }}>
                                            <MenuItem value={0} disabled>Select Region</MenuItem>
                                            {(scopeRegionsData?.data ?? []).map((r: any) => (
                                                <MenuItem key={r.id} value={r.id}>{r.name}</MenuItem>
                                            ))}
                                        </Select>
                                    </FormControl>
                                )}

                                {(scopeType === 'branch' || scopeType === 'department') && scopeRegion > 0 && (
                                    <FormControl fullWidth size="small">
                                        <InputLabel>Branch</InputLabel>
                                        <Select value={scopeBranch} label="Branch"
                                            onChange={(e) => {
                                                setScopeBranch(Number(e.target.value));
                                                setScopeDepartment(0);
                                            }}>
                                            <MenuItem value={0} disabled>Select Branch</MenuItem>
                                            {(scopeBranchesData?.data ?? []).map((b: any) => (
                                                <MenuItem key={b.id} value={b.id}>{b.name}</MenuItem>
                                            ))}
                                        </Select>
                                    </FormControl>
                                )}

                                {scopeType === 'department' && scopeBranch > 0 && (
                                    <FormControl fullWidth size="small">
                                        <InputLabel>Department</InputLabel>
                                        <Select value={scopeDepartment} label="Department"
                                            onChange={(e) => setScopeDepartment(Number(e.target.value))}>
                                            <MenuItem value={0} disabled>Select Department</MenuItem>
                                            {(scopeDepartmentsData?.data ?? []).map((d: any) => (
                                                <MenuItem key={d.id} value={d.id}>{d.name}</MenuItem>
                                            ))}
                                        </Select>
                                    </FormControl>
                                )}

                                <Button
                                    variant="outlined"
                                    size="small"
                                    startIcon={<AddCircleOutlineIcon />}
                                    onClick={addScope}
                                    disabled={!scopeType || (
                                        scopeType === 'company' ? !scopeCompany :
                                        scopeType === 'region' ? !scopeRegion :
                                        scopeType === 'branch' ? !scopeBranch :
                                        !scopeDepartment
                                    )}
                                >
                                    Add Scope
                                </Button>
                            </Stack>
                        </Paper>
                    </Grid>

                    {/* ── STATUS ──────────────────────────────────── */}
                    <Grid item xs={12} sx={{ mt: 1 }}>
                        <Divider sx={{ mb: 2 }} />
                        <Controller name="is_active" control={control} render={({ field }) => (
                            <FormControlLabel
                                control={<Switch checked={field.value} onChange={field.onChange} />}
                                label="Active"
                            />
                        )} />
                    </Grid>

                    {/* ── ACTIONS ─────────────────────────────────── */}
                    <Grid item xs={12} sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>
                            Cancel
                        </Button>
                        <Button type="submit" variant="contained" disabled={loading} fullWidth>
                            {loading ? 'Saving...' : isEdit ? 'Update User' : 'Create User'}
                        </Button>
                    </Grid>
                </Grid>
            </form>
        </Drawer>
    );
};
