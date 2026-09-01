import React, { useEffect, useState } from 'react';
import { Box, Card, CardContent, Typography, Grid, Chip, Table, TableBody, TableCell, TableHead, TableRow, Tabs, Tab, Alert } from '@mui/material';
import { apiClient } from '@/api/client';

interface LogEntry {
    ip?: string;
    time?: string;
    method?: string;
    path?: string;
    status?: number;
    level?: string;
    message?: string;
    classification?: string;
}

export const SystemLogsPage: React.FC = () => {
    const [tab, setTab] = useState(0);
    const [nginxLogs, setNginxLogs] = useState<LogEntry[]>([]);
    const [laravelLogs, setLaravelLogs] = useState<LogEntry[]>([]);
    const [summary, setSummary] = useState<any>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetchSummary();
        fetchNginxLogs();
        fetchLaravelLogs();
    }, []);

    const fetchSummary = async () => {
        try {
            const res = await apiClient.get('/api/v1/debug/summary');
            setSummary(res.data);
            setError(null);
        } catch (e: any) {
            console.error(e);
            setError('Failed to load summary: ' + (e.response?.data?.message || e.message));
        }
    };

    const fetchNginxLogs = async () => {
        try {
            const res = await apiClient.get('/api/v1/debug/logs?type=nginx&limit=50');
            // Ensure we always set an array
            setNginxLogs(Array.isArray(res.data) ? res.data : []);
            setError(null);
        } catch (e: any) {
            console.error(e);
            setError('Failed to load Nginx logs: ' + (e.response?.data?.message || e.message));
            setNginxLogs([]);
        }
    };

    const fetchLaravelLogs = async () => {
        try {
            const res = await apiClient.get('/api/v1/debug/logs?type=laravel&limit=50');
            // Ensure we always set an array
            setLaravelLogs(Array.isArray(res.data) ? res.data : []);
            setError(null);
        } catch (e: any) {
            console.error(e);
            setError('Failed to load Laravel logs: ' + (e.response?.data?.message || e.message));
            setLaravelLogs([]);
        }
    };

    const getStatusColor = (status?: number) => {
        if (!status) return 'default';
        if (status < 300) return 'success';
        if (status < 400) return 'info';
        if (status < 500) return 'warning';
        return 'error';
    };

    return (
        <Box sx={{ p: 3 }}>
            <Typography variant="h4" gutterBottom>System Logs</Typography>
            
            {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

            {summary && !error && (
                <Grid container spacing={2} sx={{ mb: 3 }}>
                    <Grid item xs={3}><Card><CardContent><Typography>Total Requests</Typography><Typography variant="h6">{summary.total}</Typography></CardContent></Card></Grid>
                    <Grid item xs={3}><Card><CardContent><Typography>2xx</Typography><Typography variant="h6" color="success.main">{summary['2xx']}</Typography></CardContent></Card></Grid>
                    <Grid item xs={3}><Card><CardContent><Typography>4xx</Typography><Typography variant="h6" color="warning.main">{summary['4xx']}</Typography></CardContent></Card></Grid>
                    <Grid item xs={3}><Card><CardContent><Typography>5xx</Typography><Typography variant="h6" color="error.main">{summary['5xx']}</Typography></CardContent></Card></Grid>
                </Grid>
            )}

            <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 2 }}>
                <Tab label="Nginx Access" />
                <Tab label="Laravel Logs" />
            </Tabs>

            {tab === 0 && (
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Time</TableCell>
                            <TableCell>IP</TableCell>
                            <TableCell>Method</TableCell>
                            <TableCell>Path</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell>Classification</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {Array.isArray(nginxLogs) && nginxLogs.map((log, i) => (
                            <TableRow key={i}>
                                <TableCell>{log.time}</TableCell>
                                <TableCell>{log.ip}</TableCell>
                                <TableCell>{log.method}</TableCell>
                                <TableCell>{log.path}</TableCell>
                                <TableCell><Chip label={log.status} color={getStatusColor(log.status)} size="small" /></TableCell>
                                <TableCell>{log.classification}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            {tab === 1 && (
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Time</TableCell>
                            <TableCell>Level</TableCell>
                            <TableCell>Message</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {Array.isArray(laravelLogs) && laravelLogs.map((log, i) => (
                            <TableRow key={i}>
                                <TableCell>{log.time}</TableCell>
                                <TableCell><Chip label={log.level} color={log.level === 'error' ? 'error' : 'default'} size="small" /></TableCell>
                                <TableCell>{log.message}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}
        </Box>
    );
};
