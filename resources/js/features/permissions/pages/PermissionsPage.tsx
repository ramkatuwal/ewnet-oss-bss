import React, { useState, useEffect } from 'react';
import { Box, Typography, Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Alert, CircularProgress, Chip } from '@mui/material';
import { useAuthStore } from '@/stores/authStore';
import { permissionsApi } from '@/api/permissions';
import { Permission } from '@/types';

export const PermissionsPage: React.FC = () => {
    const { authState, hasPermission } = useAuthStore();
    const [permissions, setPermissions] = useState<Permission[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const canView = hasPermission('permissions.view');

    useEffect(() => {
        if (authState === 'authenticated' && canView) {
            setLoading(true);
            permissionsApi.getAll().then(res => setPermissions(res || [])).catch(err => setError(err.message)).finally(() => setLoading(false));
        }
    }, [authState, canView]);

    if (authState === 'booting') return <Box display="flex" justifyContent="center" p={4}><CircularProgress /></Box>;
    if (!canView) return <Box p={3}><Alert severity="warning">No permission to view permissions.</Alert></Box>;

    return (
        <Box>
            <Typography variant="h5" mb={2}>Permissions Registry</Typography>
            {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}
            {loading ? <Box display="flex" justifyContent="center" py={4}><CircularProgress /></Box> : (
                <TableContainer component={Paper}>
                    <Table>
                        <TableHead><TableRow><TableCell>Name</TableCell><TableCell>Guard</TableCell></TableRow></TableHead>
                        <TableBody>
                            {permissions.map((perm) => (
                                <TableRow key={perm.id}>
                                    <TableCell>{perm.name}</TableCell>
                                    <TableCell><Chip label={perm.guard_name} size="small" /></TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
            )}
        </Box>
    );
};
