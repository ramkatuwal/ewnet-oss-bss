import React, { useEffect } from 'react';
import {
    Drawer, Box, Typography, TextField, Button, FormControlLabel,
    Switch, Divider, IconButton, Grid,
} from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { Company } from '@/types';

const companySchema = z.object({
    name: z.string().min(1, 'Company name is required').max(255),
    registration_number: z.string().max(255).optional().or(z.literal('')),
    pan_number: z.string().max(255).optional().or(z.literal('')),
    email: z.string().email('Invalid email').max(255).optional().or(z.literal('')),
    phone: z.string().max(255).optional().or(z.literal('')),
    address: z.string().optional().or(z.literal('')),
    city: z.string().max(255).optional().or(z.literal('')),
    state: z.string().max(255).optional().or(z.literal('')),
    postal_code: z.string().max(255).optional().or(z.literal('')),
    country: z.string().min(1, 'Country is required').max(255),
    website: z.string().url('Invalid URL').max(255).optional().or(z.literal('')),
    is_active: z.boolean(),
});

type CompanyFormData = z.infer<typeof companySchema>;

interface Props {
    open: boolean;
    onClose: () => void;
    company: Company | null;
    onSubmit: (data: Partial<Company>) => void;
    loading?: boolean;
}

export const CompanyFormDrawer = ({ open, onClose, company, onSubmit, loading }: Props) => {
    const isEdit = !!company;

    const { control, handleSubmit, reset, formState: { errors } } = useForm<CompanyFormData>({
        resolver: zodResolver(companySchema),
        defaultValues: {
            name: '', registration_number: '', pan_number: '',
            email: '', phone: '', address: '', city: '', state: '',
            postal_code: '', country: 'Nepal', website: '', is_active: true,
        },
    });

    useEffect(() => {
        if (open) {
            reset({
                name: company?.name ?? '',
                registration_number: company?.registration_number ?? '',
                pan_number: company?.pan_number ?? '',
                email: company?.email ?? '',
                phone: company?.phone ?? '',
                address: company?.address ?? '',
                city: company?.city ?? '',
                state: company?.state ?? '',
                postal_code: company?.postal_code ?? '',
                country: company?.country ?? 'Nepal',
                website: company?.website ?? '',
                is_active: company?.is_active ?? true,
            });
        }
    }, [open, company, reset]);

    const Field = ({ name, label, required, multiline, rows, half }: any) => (
        <Grid item xs={half ? 6 : 12}>
            <Controller
                name={name}
                control={control}
                render={({ field }) => (
                    <TextField
                        {...field}
                        label={label}
                        fullWidth
                        required={required}
                        multiline={multiline}
                        rows={rows}
                        size="small"
                        error={!!errors[name as keyof CompanyFormData]}
                        helperText={errors[name as keyof CompanyFormData]?.message}
                    />
                )}
            />
        </Grid>
    );

    return (
        <Drawer anchor="right" open={open} onClose={onClose} sx={{ '& .MuiDrawer-paper': { width: 520, p: 3 } }}>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h5" fontWeight={600}>
                    {isEdit ? 'Edit Company' : 'Create Company'}
                </Typography>
                <IconButton onClick={onClose} size="small"><CloseIcon /></IconButton>
            </Box>
            <Divider sx={{ mb: 3 }} />

            <form onSubmit={handleSubmit(onSubmit)}>
                <Grid container spacing={2}>
                    {/* Basic Information */}
                    <Grid item xs={12}>
                        <Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mb: 1 }}>
                            Basic Information
                        </Typography>
                    </Grid>
                    <Field name="name" label="Company Name" required half />
                    <Field name="country" label="Country" required half />
                    <Field name="registration_number" label="Registration Number" half />
                    <Field name="pan_number" label="PAN Number" half />

                    {/* Contact Information */}
                    <Grid item xs={12}>
                        <Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 2, mb: 1 }}>
                            Contact Information
                        </Typography>
                    </Grid>
                    <Field name="email" label="Email Address" half />
                    <Field name="phone" label="Phone Number" half />
                    <Field name="website" label="Website URL" />

                    {/* Address */}
                    <Grid item xs={12}>
                        <Typography variant="subtitle2" color="primary" fontWeight={600} sx={{ mt: 2, mb: 1 }}>
                            Address
                        </Typography>
                    </Grid>
                    <Field name="address" label="Street Address" multiline rows={2} />
                    <Field name="city" label="City" half />
                    <Field name="state" label="State / Province" half />
                    <Field name="postal_code" label="Postal Code" half />

                    {/* Status */}
                    <Grid item xs={12} sx={{ mt: 2 }}>
                        <Divider sx={{ mb: 2 }} />
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
                    </Grid>

                    {/* Actions */}
                    <Grid item xs={12} sx={{ display: 'flex', gap: 2, mt: 2 }}>
                        <Button variant="outlined" onClick={onClose} disabled={loading} fullWidth>Cancel</Button>
                        <Button type="submit" variant="contained" disabled={loading} fullWidth>
                            {loading ? 'Saving...' : isEdit ? 'Update Company' : 'Create Company'}
                        </Button>
                    </Grid>
                </Grid>
            </form>
        </Drawer>
    );
};
