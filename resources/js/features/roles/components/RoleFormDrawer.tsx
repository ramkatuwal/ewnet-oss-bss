import React, { useEffect, useMemo } from 'react';
import {
    Drawer, Box, Typography, TextField, Button, Divider, IconButton,
    Checkbox, FormControlLabel, FormGroup, Alert, Paper,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuthStore } from '@/stores/authStore';
import type { Permission } from '@/types';

const roleSchema = z.object({
    name: z.string().min(1, 'Name is required').max(255),
});

interface Props {
    open: boolean;
    onClose: () => void;
    role: { id: number; name: string; permissions?: Permission[] } | null;
    allPermissions: Permission[];
    onSubmit: (data: { name: string; permissions: number[] }) => void;
    loading?: boolean;
}

export const RoleFormDrawer = ({ open, onClose, role, allPermissions, onSubmit, loading }: Props) => {
    const isEdit = !!role;
    const { user: authUser, isSuperAdmin } = useAuthStore();

    const { control, handleSubmit, reset, formState: { errors } } = useForm({
        resolver: zodResolver(roleSchema),
        defaultValues: { name: '' },
    });

    const [selectedPerms, setSelectedPerms] = React.useState<number[]>([]);

    // Group permissions by domain
    const groupedPerms = useMemo(() => {
        const groups: Record<string, Permission[]> = {};
        allPermissions.forEach(p => {
            const domain = p.domain || 'other';
            if (!groups[domain]) groups[domain] = [];
            groups[domain].push(p);
        });
        return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
    }, [allPermissions]);

    // Filter permissions: non-Super Admin can only assign permissions they possess
    const availablePerms = useMemo(() => {
        if (isSuperAdmin()) return allPermissions;
        const actorPerms = authUser?.permissions ?? [];
        return allPermissions.filter(p => actorPerms.includes(p.name));
    }, [allPermissions, authUser, isSuperAdmin]);

    useEffect(() => {
        if (open) {
            reset({ name: role?.name ?? '' });
            setSelectedPerms((role?.permissions ?? []).map(p => p.id));
        }
    }, [open, role, reset]);

    const togglePerm = (id: number) => {
        setSelectedPerms(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    };

    const onFormSubmit = (data: { name: string }) => {
        onSubmit({ name: data.name, permissions: selectedPerms });
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose}
            sx={{ '& .MuiDrawer-paper': { width: 560, p: 3 } }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>{isEdit ? 'Edit Role' : 'Create Role'}</Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 2 }} />

            {!isSuperAdmin() && (
                <Alert severity="info" variant="outlined" sx={{ mb: 2 }}>
                    You can only assign permissions you currently possess.
                </Alert>
            )}

            <form onSubmit={handleSubmit(onFormSubmit)}>
                <Controller name="name" control={control} render={({ field }) => (
                    <TextField {...field} label="Role Name" fullWidth required size="small" sx={{ mb: 2 }}
                        error={!!errors.name} helperText={errors.name?.message}
                        disabled={role?.name === 'Super Admin' && !isSuperAdmin()} />
                )} />

                <Typography variant="subtitle2" color="primary" fontWeight={600} gutterBottom>
                    Permissions ({selectedPerms.length} selected)
                </Typography>

                <Paper variant="outlined" sx={{ p: 2, maxHeight: 500, overflow: 'auto' }}>
                    {groupedPerms.map(([domain, perms]) => {
                        const domainPerms = perms.filter(p => availablePerms.some(ap => ap.id === p.id));
                        if (domainPerms.length === 0) return null;
                        return (
                            <Box key={domain} sx={{ mb: 2 }}>
                                <Typography variant="caption" fontWeight={700} textTransform="uppercase" color="text.secondary">
                                    {domain}
                                </Typography>
                                <FormGroup>
                                    {domainPerms.map(perm => (
                                        <FormControlLabel key={perm.id}
                                            control={<Checkbox size="small" checked={selectedPerms.includes(perm.id)}
                                                onChange={() => togglePerm(perm.id)} />}
                                            label={<Typography variant="body2">{perm.action}</Typography>} />
                                    ))}
                                </FormGroup>
                            </Box>
                        );
                    })}
                    {availablePerms.length === 0 && (
                        <Typography variant="body2" color="text.secondary">No permissions available to assign.</Typography>
                    )}
                </Paper>

                <Box sx={{ display: 'flex', gap: 2, mt: 3 }}>
                    <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>Cancel</Button>
                    <Button type="submit" variant="contained" disabled={loading} fullWidth>
                        {loading ? 'Saving...' : isEdit ? 'Update Role' : 'Create Role'}
                    </Button>
                </Box>
            </form>
        </Drawer>
    );
};
