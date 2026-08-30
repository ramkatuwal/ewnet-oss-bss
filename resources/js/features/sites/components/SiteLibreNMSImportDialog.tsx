import React, { useState } from 'react';
import {
    Dialog, DialogTitle, DialogContent, DialogActions,
    Button, Stack, Typography, Box, Chip, Table, TableBody,
    TableCell, TableContainer, TableHead, TableRow, Paper,
    CircularProgress, Alert, Checkbox, FormControlLabel
} from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import toast from 'react-hot-toast';

interface SiteLibreNMSImportDialogProps {
    open: boolean;
    onClose: () => void;
    siteId: number;
    siteName: string;
    integrationId: number;
    onSuccess: () => void;
}

interface DeviceItem {
    device_id: string;
    hostname: string;
    status: string;
    site_mapped: boolean;
    site_id?: number;
    site_message: string;
    action: string;
    device_data: any;
}

export const SiteLibreNMSImportDialog: React.FC<SiteLibreNMSImportDialogProps> = ({
    open,
    onClose,
    siteId,
    siteName,
    integrationId,
    onSuccess,
}) => {
    const [selectedDevices, setSelectedDevices] = useState<Set<string>>(new Set());
    const [isImporting, setIsImporting] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const { data: preview, isLoading } = useQuery({
        queryKey: ['librenms-preview', integrationId, siteId],
        queryFn: () => {
            return axios.get(`/api/v1/integrations/librenms/${integrationId}/preview`)
                .then(res => res.data);
        },
        enabled: open && !!integrationId,
    });

    const importableDevices = preview?.preview?.filter(
        (item: DeviceItem) => item.site_mapped || item.site_message === 'No matching site'
    ) || [];

    const handleToggleSelect = (deviceId: string) => {
        const newSet = new Set(selectedDevices);
        if (newSet.has(deviceId)) {
            newSet.delete(deviceId);
        } else {
            newSet.add(deviceId);
        }
        setSelectedDevices(newSet);
    };

    const handleSelectAll = () => {
        if (selectedDevices.size === importableDevices.length) {
            setSelectedDevices(new Set());
        } else {
            setSelectedDevices(new Set(importableDevices.map((d: DeviceItem) => d.device_id)));
        }
    };

    const handleImport = () => {
        setIsImporting(true);
        const promises = Array.from(selectedDevices).map(deviceId => {
            return axios.post(`/api/v1/integrations/librenms/${integrationId}/import`, {
                device_ids: [deviceId],
                target_site_id: siteId,
            });
        });

        Promise.all(promises)
            .then(() => {
                toast.success(`Imported ${selectedDevices.size} devices successfully`);
                setIsImporting(false);
                setSelectedDevices(new Set());
                onSuccess();
                onClose();
            })
            .catch((err) => {
                toast.error(err.response?.data?.message || 'Import failed');
                setIsImporting(false);
            });
    };

    const getStatusChip = (status: string) => {
        const statusMap: Record<string, { label: string; color: any }> = {
            '1': { label: 'Up', color: 'success' },
            '0': { label: 'Down', color: 'error' },
            '2': { label: 'Warning', color: 'warning' },
        };
        const s = statusMap[status] || { label: status, color: 'default' };
        return <Chip label={s.label} size="small" color={s.color} />;
    };

    const getSiteStatus = (item: DeviceItem) => {
        if (item.site_mapped && item.site_id === siteId) {
            return <Chip label="Matches Site" size="small" color="success" />;
        } else if (item.site_mapped && item.site_id !== siteId) {
            return <Chip label={`Mapped to Site ${item.site_id}`} size="small" color="warning" />;
        } else {
            return <Chip label="Unmapped" size="small" color="info" />;
        }
    };

    const selectedCount = selectedDevices.size;

    return (
        <>
            <Dialog open={open} onClose={onClose} maxWidth="lg" fullWidth>
                <DialogTitle>
                    Import from LibreNMS - {siteName}
                </DialogTitle>
                <DialogContent>
                    <Box sx={{ mt: 1 }}>
                        {isLoading ? (
                            <Box sx={{ display: 'flex', justifyContent: 'center', p: 4 }}>
                                <CircularProgress />
                            </Box>
                        ) : !preview?.preview ? (
                            <Alert severity="info">
                                No devices found or unable to connect to LibreNMS.
                            </Alert>
                        ) : (
                            <>
                                <Box sx={{ mb: 2, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <Typography variant="body2">
                                        {importableDevices.length} devices available for import.
                                        {selectedCount > 0 && ` ${selectedCount} selected.`}
                                    </Typography>
                                    <Stack direction="row" spacing={1}>
                                        <FormControlLabel
                                            control={
                                                <Checkbox
                                                    checked={selectedCount === importableDevices.length && importableDevices.length > 0}
                                                    indeterminate={selectedCount > 0 && selectedCount < importableDevices.length}
                                                    onChange={handleSelectAll}
                                                />
                                            }
                                            label="Select All"
                                        />
                                        <Button
                                            variant="contained"
                                            color="primary"
                                            onClick={() => setConfirmOpen(true)}
                                            disabled={selectedCount === 0 || isImporting}
                                        >
                                            Import Selected
                                        </Button>
                                    </Stack>
                                </Box>

                                <TableContainer component={Paper} variant="outlined">
                                    <Table size="small">
                                        <TableHead>
                                            <TableRow>
                                                <TableCell padding="checkbox">
                                                    <Checkbox />
                                                </TableCell>
                                                <TableCell>Device ID</TableCell>
                                                <TableCell>Hostname</TableCell>
                                                <TableCell>Status</TableCell>
                                                <TableCell>Site Status</TableCell>
                                            </TableRow>
                                        </TableHead>
                                        <TableBody>
                                            {importableDevices.map((item: DeviceItem) => (
                                                <TableRow key={item.device_id}>
                                                    <TableCell padding="checkbox">
                                                        <Checkbox
                                                            checked={selectedDevices.has(item.device_id)}
                                                            onChange={() => handleToggleSelect(item.device_id)}
                                                        />
                                                    </TableCell>
                                                    <TableCell>{item.device_id}</TableCell>
                                                    <TableCell>{item.hostname}</TableCell>
                                                    <TableCell>{getStatusChip(item.status)}</TableCell>
                                                    <TableCell>{getSiteStatus(item)}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </TableContainer>
                            </>
                        )}
                    </Box>
                </DialogContent>
                <DialogActions>
                    <Button onClick={onClose}>Cancel</Button>
                </DialogActions>
            </Dialog>

            <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
                <DialogTitle>Confirm Import</DialogTitle>
                <DialogContent>
                    <Typography>
                        This will import {selectedCount} device(s) as Assets into <strong>{siteName}</strong>.
                    </Typography>
                    <Alert severity="warning" sx={{ mt: 2 }}>
                        This action cannot be undone. Devices already imported will be updated.
                    </Alert>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setConfirmOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        color="primary"
                        onClick={handleImport}
                        disabled={isImporting}
                    >
                        {isImporting ? 'Importing...' : 'Confirm Import'}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
};
