import { zodResolver } from '@hookform/resolvers/zod';
import { Button, Drawer, MenuItem, Stack, TextField, Typography } from '@mui/material';
import { Controller, useForm } from 'react-hook-form';
import { z } from 'zod';
import type { Service, ServiceInput } from '../api/bss';

const schema = z.object({ company_id: z.coerce.number().int().positive(), service_code: z.string().min(1).max(64), name: z.string().min(1), type: z.enum(['internet', 'voice', 'iptv', 'other']), description: z.string().optional() });
type Form = z.infer<typeof schema>;
export const ServiceFormDrawer = ({ service, onSubmit, onClose }: { service?: Service; onSubmit: (value: ServiceInput) => void; onClose: () => void }) => {
    const { control, handleSubmit, register, formState: { errors } } = useForm<Form>({ resolver: zodResolver(schema), defaultValues: service ? { ...service, description: service.description || '' } : { type: 'internet' } });
    return <Drawer anchor="right" open onClose={onClose}><Stack component="form" onSubmit={handleSubmit(value => onSubmit({ ...value, description: value.description || null }))} spacing={2} sx={{ p: 3, width: { xs: '100vw', sm: 420 } }}><Typography variant="h6">{service ? 'Edit service' : 'New service'}</Typography><TextField label="Company ID" type="number" disabled={!!service} {...register('company_id')} error={!!errors.company_id} helperText={errors.company_id?.message} /><TextField label="Service code" disabled={!!service} {...register('service_code')} error={!!errors.service_code} helperText={errors.service_code?.message} /><TextField label="Name" {...register('name')} error={!!errors.name} helperText={errors.name?.message} /><Controller control={control} name="type" render={({ field }) => <TextField {...field} select label="Type">{['internet', 'voice', 'iptv', 'other'].map(type => <MenuItem key={type} value={type}>{type}</MenuItem>)}</TextField>} /><TextField label="Description" multiline minRows={2} {...register('description')} /><Stack direction="row" spacing={1}><Button onClick={onClose}>Cancel</Button><Button type="submit" variant="contained">Save</Button></Stack></Stack></Drawer>;
};
