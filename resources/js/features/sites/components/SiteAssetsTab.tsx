import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Button, IconButton, Tooltip, Chip, Typography,
    Grid, Card, CardContent, TextField, MenuItem, FormControl, InputLabel, Select
} from '@mui/material';
import { Add, Delete, Edit } from '@mui/icons-material';
import { DataGrid, GridColDef, GridRenderCellParams } from '@mui/x-data-grid';
import { Can } from '@/components/auth/Can';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import { getSiteAssets, deleteAsset } from '../../assets/api/assets';
import AssetFormDrawer from '../../assets/components/AssetFormDrawer';
import toast from 'react-hot-toast';

interface SiteAssetsTabProps {
    siteId: number;
}

export const SiteAssetsTab: React.FC<SiteAssetsTabProps> = ({ siteId }) => {
    const queryClient = useQueryClient();
    const [openForm, setOpenForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [page, setPage] = useState(0);
    const [pageSize, setPageSize] = useState(10);
    const [searchValue, setSearchValue] = useState('');
    const [filters, setFilters] = useState<any>({});

    const { data, isLoading } = useQuery({
        queryKey: ['site-assets', siteId, page, pageSize, searchValue, filters],
        queryFn: () => getSiteAssets(siteId, {
            page: page + 1,
            per_page: pageSize,
            search: searchValue || undefined,
            ...filters
        }),
        enabled: !!siteId,
    });

    const deleteMutation = useMutation({
        mutationFn: deleteAsset,
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['site-assets', siteId] });
            queryClient.invalidateQueries({ queryKey: ['assets'] });
            setDeleteId(null);
            toast.success('Asset deleted successfully');
        },
        onError: () => toast.error('Failed to delete asset'),
    });

    const columns: GridColDef[] = [
        { field: 'asset_tag', headerName: 'Asset Tag', flex: 1 },
        { field: 'type', headerName: 'Type', flex: 1 },
        { field: 'manufacturer', headerName: 'Manufacturer', flex: 0.8 },
        { field: 'model', headerName: 'Model', flex: 0.8 },
        { field: 'serial_number', headerName: 'Serial', flex: 0.8 },
        { field: 'quantity', headerName: 'Qty', width: 60 },
        { field: 'unit', headerName: 'Unit', width: 60 },
        {
            field: 'status',
            headerName: 'Status',
            width: 110,
            renderCell: (params: GridRenderCellParams) => (
                <Chip
                    label={params.value}
                    size="small"
                    color={params.value === 'OPERATIONAL' ? 'success' : 'default'}
                />
            )
        },
        {
            field: 'actions',
            headerName: 'Actions',
            width: 120,
            renderCell: (params: GridRenderCellParams) => (
                <Box>
                    <Can permission="assets.update">
                        <Tooltip title="Edit">
                            <IconButton size="small" onClick={() => {
                                setEditingId(params.row.id);
                                setOpenForm(true);
                            }}>
                                <Edit fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    </Can>
                    <Can permission="assets.delete">
                        <Tooltip title="Delete">
                            <IconButton size="small" color="error" onClick={() => setDeleteId(params.row.id)}>
                                <Delete fontSize="small" />
                            </IconButton>
                        </Tooltip>
                    </Can>
                </Box>
            )
        },
    ];

    return (
        <Box>
            {/* Summary Cards */}
            <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid item xs={3}>
                    <Card>
                        <CardContent>
                            <Typography variant="h6">{data?.total || 0}</Typography>
                            <Typography variant="body2" color="text.secondary">Total Assets</Typography>
                        </CardContent>
                    </Card>
                </Grid>
                <Grid item xs={3}>
                    <Card>
                        <CardContent>
                            <Typography variant="h6">
                                {data?.data?.reduce((sum: number, a: any) => sum + (a.quantity || 0), 0) || 0}
                            </Typography>
                            <Typography variant="body2" color="text.secondary">Total Units</Typography>
                        </CardContent>
                    </Card>
                </Grid>
            </Grid>

            {/* Toolbar */}
            <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, flexWrap: 'wrap', gap: 1 }}>
                <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                    <TextField
                        size="small"
                        placeholder="Search assets..."
                        value={searchValue}
                        onChange={(e) => setSearchValue(e.target.value)}
                        sx={{ width: 200 }}
                    />
                    <FormControl size="small" sx={{ minWidth: 120 }}>
                        <InputLabel>Category</InputLabel>
                        <Select
                            value={filters.category || ''}
                            label="Category"
                            onChange={(e) => setFilters({ ...filters, category: e.target.value })}
                        >
                            <MenuItem value="">All</MenuItem>
                            <MenuItem value="POWER">Power</MenuItem>
                            <MenuItem value="NETWORK">Network</MenuItem>
                            <MenuItem value="INFRASTRUCTURE">Infrastructure</MenuItem>
                            <MenuItem value="OTHER">Other</MenuItem>
                        </Select>
                    </FormControl>
                    <FormControl size="small" sx={{ minWidth: 120 }}>
                        <InputLabel>Status</InputLabel>
                        <Select
                            value={filters.status || ''}
                            label="Status"
                            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                        >
                            <MenuItem value="">All</MenuItem>
                            <MenuItem value="OPERATIONAL">Operational</MenuItem>
                            <MenuItem value="SPARE">Spare</MenuItem>
                            <MenuItem value="MAINTENANCE">Maintenance</MenuItem>
                            <MenuItem value="FAULTY">Faulty</MenuItem>
                            <MenuItem value="RETIRED">Retired</MenuItem>
                        </Select>
                    </FormControl>
                </Box>
                <Box>
                    <Can permission="assets.create">
                        <Button
                            variant="contained"
                            startIcon={<Add />}
                            onClick={() => { setEditingId(null); setOpenForm(true); }}
                        >
                            Add Asset
                        </Button>
                    </Can>
                </Box>
            </Box>

            {/* Data Grid */}
            <DataGrid
                rows={data?.data || []}
                columns={columns}
                loading={isLoading}
                paginationMode="server"
                rowCount={data?.total || 0}
                paginationModel={{ page, pageSize }}
                onPaginationModelChange={(model) => {
                    setPage(model.page);
                    setPageSize(model.pageSize);
                }}
                autoHeight
                sx={{ minHeight: 300 }}
            />

            {/* Asset Form Drawer - Pass siteId for pre-selection */}
            <AssetFormDrawer
                open={openForm}
                onClose={() => { setOpenForm(false); setEditingId(null); }}
                assetId={editingId}
                siteId={siteId}
            />

            {/* Delete Confirmation */}
            <ConfirmDialog
                open={!!deleteId}
                title="Delete Asset"
                message="Are you sure you want to delete this asset?"
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />
        </Box>
    );
};
