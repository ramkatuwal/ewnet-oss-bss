import { useState } from 'react';
import {
    Box,
    Button,
    Card,
    CardContent,
    Chip,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
    Stack,
    Tab,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Tabs,
    TextField,
    Typography,
} from '@mui/material';
import { Add, Delete, Edit } from '@mui/icons-material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { assetModelSettingsApi } from '../api/assetModelSettings';
import type { AssetCategory, AssetDeviceType, AssetUnit } from '../types/assetModelSettings';

export const ModelSettingsPage = () => {
    const queryClient = useQueryClient();
    const [tab, setTab] = useState(0);

    // Dialog states
    const [categoryDialog, setCategoryDialog] = useState<{ open: boolean; editing?: AssetCategory }>({ open: false });
    const [typeDialog, setTypeDialog] = useState<{ open: boolean; editing?: AssetDeviceType }>({ open: false });
    const [unitDialog, setUnitDialog] = useState<{ open: boolean; editing?: AssetUnit }>({ open: false });
    const [deleteConfirm, setDeleteConfirm] = useState<{ type: 'category' | 'deviceType' | 'unit'; id: number; name: string } | null>(null);

    // Form state
    const [form, setForm] = useState({ code: '', name: '', description: '', category_id: 0, is_active: true });

    // Queries
    const { data: categories = [] } = useQuery({
        queryKey: ['settings', 'asset-categories'],
        queryFn: () => assetModelSettingsApi.getCategories().then((r) => r.data.data),
    });

    const { data: deviceTypes = [] } = useQuery({
        queryKey: ['settings', 'asset-device-types'],
        queryFn: () => assetModelSettingsApi.getDeviceTypes().then((r) => r.data.data),
    });

    const { data: units = [] } = useQuery({
        queryKey: ['settings', 'asset-units'],
        queryFn: () => assetModelSettingsApi.getUnits().then((r) => r.data.data),
    });

    // Mutations
    const saveCategory = useMutation({
        mutationFn: async () => {
            if (categoryDialog.editing) {
                await assetModelSettingsApi.updateCategory(categoryDialog.editing.id, form);
                return;
            }
            await assetModelSettingsApi.createCategory(form);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settings', 'asset-categories'] });
            setCategoryDialog({ open: false });
            setForm({ code: '', name: '', description: '', category_id: 0, is_active: true });
        },
    });

    const saveDeviceType = useMutation({
        mutationFn: async () => {
            if (typeDialog.editing) {
                await assetModelSettingsApi.updateDeviceType(typeDialog.editing.id, form);
                return;
            }
            await assetModelSettingsApi.createDeviceType(form);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settings', 'asset-device-types'] });
            setTypeDialog({ open: false });
            setForm({ code: '', name: '', description: '', category_id: 0, is_active: true });
        },
    });

    const saveUnit = useMutation({
        mutationFn: async () => {
            if (unitDialog.editing) {
                await assetModelSettingsApi.updateUnit(unitDialog.editing.id, form);
                return;
            }
            await assetModelSettingsApi.createUnit(form);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['settings', 'asset-units'] });
            setUnitDialog({ open: false });
            setForm({ code: '', name: '', description: '', category_id: 0, is_active: true });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async () => {
            if (!deleteConfirm) return;
            if (deleteConfirm.type === 'category') await assetModelSettingsApi.deleteCategory(deleteConfirm.id);
            else if (deleteConfirm.type === 'deviceType') await assetModelSettingsApi.deleteDeviceType(deleteConfirm.id);
            else await assetModelSettingsApi.deleteUnit(deleteConfirm.id);
        },
        onSuccess: () => {
            if (deleteConfirm?.type === 'category') queryClient.invalidateQueries({ queryKey: ['settings', 'asset-categories'] });
            if (deleteConfirm?.type === 'deviceType') queryClient.invalidateQueries({ queryKey: ['settings', 'asset-device-types'] });
            if (deleteConfirm?.type === 'unit') queryClient.invalidateQueries({ queryKey: ['settings', 'asset-units'] });
            setDeleteConfirm(null);
        },
    });

    const openCreateCategory = () => {
        setForm({ code: '', name: '', description: '', category_id: 0, is_active: true });
        setCategoryDialog({ open: true });
    };

    const openEditCategory = (cat: AssetCategory) => {
        setForm({ code: cat.code, name: cat.name, description: cat.description ?? '', category_id: 0, is_active: cat.is_active });
        setCategoryDialog({ open: true, editing: cat });
    };

    const openCreateType = () => {
        setForm({ code: '', name: '', description: '', category_id: categories[0]?.id ?? 0, is_active: true });
        setTypeDialog({ open: true });
    };

    const openEditType = (dt: AssetDeviceType) => {
        setForm({ code: dt.code, name: dt.name, description: dt.description ?? '', category_id: dt.category_id, is_active: dt.is_active });
        setTypeDialog({ open: true, editing: dt });
    };

    const openCreateUnit = () => {
        setForm({ code: '', name: '', description: '', category_id: 0, is_active: true });
        setUnitDialog({ open: true });
    };

    const openEditUnit = (u: AssetUnit) => {
        setForm({ code: u.code, name: u.name, description: u.description ?? '', category_id: 0, is_active: u.is_active });
        setUnitDialog({ open: true, editing: u });
    };

    return (
        <Box sx={{ p: 3 }}>
            <Typography variant="h5" gutterBottom>Asset Model Settings</Typography>

            <Card>
                <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ px: 2, pt: 1 }}>
                    <Tab label={`Categories (${categories.length})`} />
                    <Tab label={`Device Types (${deviceTypes.length})`} />
                    <Tab label={`Units (${units.length})`} />
                </Tabs>

                <CardContent>
                    {tab === 0 && (
                        <Box>
                            <Stack direction="row" justifyContent="flex-end" sx={{ mb: 2 }}>
                                <Button startIcon={<Add />} variant="contained" onClick={openCreateCategory}>
                                    Add Category
                                </Button>
                            </Stack>
                            <TableContainer>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Code</TableCell>
                                            <TableCell>Name</TableCell>
                                            <TableCell>Description</TableCell>
                                            <TableCell align="center">Active</TableCell>
                                            <TableCell align="center">Assets</TableCell>
                                            <TableCell align="right">Actions</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {categories.map((cat) => (
                                            <TableRow key={cat.id}>
                                                <TableCell><Chip label={cat.code} size="small" /></TableCell>
                                                <TableCell>{cat.name}</TableCell>
                                                <TableCell>{cat.description ?? '—'}</TableCell>
                                                <TableCell align="center">
                                                    <Chip label={cat.is_active ? 'Yes' : 'No'} size="small" color={cat.is_active ? 'success' : 'default'} />
                                                </TableCell>
                                                <TableCell align="center">{cat.assets_count ?? 0}</TableCell>
                                                <TableCell align="right">
                                                    <IconButton size="small" onClick={() => openEditCategory(cat)}><Edit fontSize="small" /></IconButton>
                                                    <IconButton size="small" onClick={() => setDeleteConfirm({ type: 'category', id: cat.id, name: cat.name })}><Delete fontSize="small" /></IconButton>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </TableContainer>
                        </Box>
                    )}

                    {tab === 1 && (
                        <Box>
                            <Stack direction="row" justifyContent="flex-end" sx={{ mb: 2 }}>
                                <Button startIcon={<Add />} variant="contained" onClick={openCreateType}>
                                    Add Device Type
                                </Button>
                            </Stack>
                            <TableContainer>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Code</TableCell>
                                            <TableCell>Name</TableCell>
                                            <TableCell>Category</TableCell>
                                            <TableCell>Description</TableCell>
                                            <TableCell align="center">Active</TableCell>
                                            <TableCell align="center">Assets</TableCell>
                                            <TableCell align="right">Actions</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {deviceTypes.map((dt) => (
                                            <TableRow key={dt.id}>
                                                <TableCell><Chip label={dt.code} size="small" /></TableCell>
                                                <TableCell>{dt.name}</TableCell>
                                                <TableCell><Chip label={dt.category?.code ?? '?'} size="small" variant="outlined" /></TableCell>
                                                <TableCell>{dt.description ?? '—'}</TableCell>
                                                <TableCell align="center">
                                                    <Chip label={dt.is_active ? 'Yes' : 'No'} size="small" color={dt.is_active ? 'success' : 'default'} />
                                                </TableCell>
                                                <TableCell align="center">{dt.assets_count ?? 0}</TableCell>
                                                <TableCell align="right">
                                                    <IconButton size="small" onClick={() => openEditType(dt)}><Edit fontSize="small" /></IconButton>
                                                    <IconButton size="small" onClick={() => setDeleteConfirm({ type: 'deviceType', id: dt.id, name: dt.name })}><Delete fontSize="small" /></IconButton>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </TableContainer>
                        </Box>
                    )}

                    {tab === 2 && (
                        <Box>
                            <Stack direction="row" justifyContent="flex-end" sx={{ mb: 2 }}>
                                <Button startIcon={<Add />} variant="contained" onClick={openCreateUnit}>
                                    Add Unit
                                </Button>
                            </Stack>
                            <TableContainer>
                                <Table size="small">
                                    <TableHead>
                                        <TableRow>
                                            <TableCell>Code</TableCell>
                                            <TableCell>Name</TableCell>
                                            <TableCell>Description</TableCell>
                                            <TableCell align="center">Active</TableCell>
                                            <TableCell align="center">Assets</TableCell>
                                            <TableCell align="right">Actions</TableCell>
                                        </TableRow>
                                    </TableHead>
                                    <TableBody>
                                        {units.map((u) => (
                                            <TableRow key={u.id}>
                                                <TableCell><Chip label={u.code} size="small" /></TableCell>
                                                <TableCell>{u.name}</TableCell>
                                                <TableCell>{u.description ?? '—'}</TableCell>
                                                <TableCell align="center">
                                                    <Chip label={u.is_active ? 'Yes' : 'No'} size="small" color={u.is_active ? 'success' : 'default'} />
                                                </TableCell>
                                                <TableCell align="center">{u.assets_count ?? 0}</TableCell>
                                                <TableCell align="right">
                                                    <IconButton size="small" onClick={() => openEditUnit(u)}><Edit fontSize="small" /></IconButton>
                                                    <IconButton size="small" onClick={() => setDeleteConfirm({ type: 'unit', id: u.id, name: u.name })}><Delete fontSize="small" /></IconButton>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </TableContainer>
                        </Box>
                    )}
                </CardContent>
            </Card>

            {/* Category Dialog */}
            <Dialog open={categoryDialog.open} onClose={() => setCategoryDialog({ open: false })} maxWidth="sm" fullWidth>
                <DialogTitle>{categoryDialog.editing ? 'Edit Category' : 'Add Category'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField label="Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required disabled={Boolean(categoryDialog.editing)} />
                        <TextField label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                        <TextField label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} multiline rows={2} />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setCategoryDialog({ open: false })}>Cancel</Button>
                    <Button onClick={() => saveCategory.mutate()} variant="contained" disabled={saveCategory.isPending}>
                        {saveCategory.isPending ? 'Saving...' : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>

            {/* Device Type Dialog */}
            <Dialog open={typeDialog.open} onClose={() => setTypeDialog({ open: false })} maxWidth="sm" fullWidth>
                <DialogTitle>{typeDialog.editing ? 'Edit Device Type' : 'Add Device Type'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField
                            select
                            label="Category"
                            value={form.category_id}
                            onChange={(e) => setForm({ ...form, category_id: Number(e.target.value) })}
                            SelectProps={{ native: true }}
                            required
                        >
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </TextField>
                        <TextField label="Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required disabled={Boolean(typeDialog.editing)} />
                        <TextField label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                        <TextField label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} multiline rows={2} />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setTypeDialog({ open: false })}>Cancel</Button>
                    <Button onClick={() => saveDeviceType.mutate()} variant="contained" disabled={saveDeviceType.isPending}>
                        {saveDeviceType.isPending ? 'Saving...' : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>

            {/* Unit Dialog */}
            <Dialog open={unitDialog.open} onClose={() => setUnitDialog({ open: false })} maxWidth="sm" fullWidth>
                <DialogTitle>{unitDialog.editing ? 'Edit Unit' : 'Add Unit'}</DialogTitle>
                <DialogContent>
                    <Stack spacing={2} sx={{ mt: 1 }}>
                        <TextField label="Code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} required disabled={Boolean(unitDialog.editing)} />
                        <TextField label="Name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
                        <TextField label="Description" value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} multiline rows={2} />
                    </Stack>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setUnitDialog({ open: false })}>Cancel</Button>
                    <Button onClick={() => saveUnit.mutate()} variant="contained" disabled={saveUnit.isPending}>
                        {saveUnit.isPending ? 'Saving...' : 'Save'}
                    </Button>
                </DialogActions>
            </Dialog>

            {/* Delete Confirmation */}
            <Dialog open={Boolean(deleteConfirm)} onClose={() => setDeleteConfirm(null)}>
                <DialogTitle>Delete {deleteConfirm?.type === 'category' ? 'Category' : deleteConfirm?.type === 'deviceType' ? 'Device Type' : 'Unit'}</DialogTitle>
                <DialogContent>
                    <Typography>
                        Are you sure you want to delete <strong>{deleteConfirm?.name}</strong>?
                    </Typography>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setDeleteConfirm(null)}>Cancel</Button>
                    <Button onClick={() => deleteMutation.mutate()} color="error" variant="contained" disabled={deleteMutation.isPending}>
                        {deleteMutation.isPending ? 'Deleting...' : 'Delete'}
                    </Button>
                </DialogActions>
            </Dialog>
        </Box>
    );
};

export default ModelSettingsPage;
