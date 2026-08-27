import React, { useEffect } from 'react';
import {
    Drawer,
    Box,
    Typography,
    TextField,
    Button,
    FormControlLabel,
    Switch,
    Divider,
    IconButton,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { Company } from '@/types';

const companySchema = z.object({
    name: z.string().min(1, 'Company name is required').max(255),
    code: z.string().max(50).optional().or(z.literal('')),
    is_active: z.boolean(),
});

type CompanyFormData = z.infer<typeof companySchema>;

interface CompanyFormDrawerProps {
    open: boolean;
    onClose: () => void;
    company: Company | null;
    onSubmit: (data: Partial<Company>) => void;
    loading?: boolean;
}

export const CompanyFormDrawer = ({
    open,
    onClose,
    company,
    onSubmit,
    loading,
}: CompanyFormDrawerProps) => {
    const isEdit = !!company;

    const {
        control,
        handleSubmit,
        reset,
        formState: { errors },
    } = useForm<CompanyFormData>({
        resolver: zodResolver(companySchema),
        defaultValues: {
            name: '',
            code: '',
            is_active: true,
        },
    });

    useEffect(() => {
        if (open) {
            reset({
                name: company?.name ?? '',
                code: company?.code ?? '',
                is_active: company?.is_active ?? true,
            });
        }
    }, [open, company, reset]);

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ '& .MuiDrawer-paper': { width: 420, p: 3 } }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h5" fontWeight={600}>
                    {isEdit ? 'Edit Company' : 'Create Company'}
                </Typography>
                <IconButton onClick={onClose} size="small">
                    <CloseIcon />
                </IconButton>
            </Box>

            <Divider sx={{ mb: 3 }} />

            <form onSubmit={handleSubmit(onSubmit)} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
                <Controller
                    name="name"
                    control={control}
                    render={({ field }) => (
                        <TextField
                            {...field}
                            label="Company Name"
                            fullWidth
                            required
                            error={!!errors.name}
                            helperText={errors.name?.message}
                        />
                    )}
                />

                <Controller
                    name="code"
                    control={control}
                    render={({ field }) => (
                        <TextField
                            {...field}
                            label="Code"
                            fullWidth
                            placeholder="Optional short code"
                            error={!!errors.code}
                            helperText={errors.code?.message}
                        />
                    )}
                />

                <Controller
                    name="is_active"
                    control={control}
                    render={({ field }) => (
                        <FormControlLabel
                            control={<Switch checked={field.value} onChange={field.onChange} />}
                            label="Active"
                        />
                    )}
                />

                <Box sx={{ display: 'flex', gap: 2, mt: 2 }}>
                    <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="contained"
                        disabled={loading}
                        fullWidth
                    >
                        {loading ? 'Saving...' : isEdit ? 'Update Company' : 'Create Company'}
                    </Button>
                </Box>
            </form>
        </Drawer>
    );
};
