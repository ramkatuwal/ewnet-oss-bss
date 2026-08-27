import { Box, Typography, Card, CardContent, Button, Alert } from '@mui/material';
import { Refresh as RefreshIcon } from '@mui/icons-material';
import { useState } from 'react';

export const DebugPage = () => {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<any>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchDebug = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetch('/api/v1/debug');
            const result = await response.json();
            setData(result);
        } catch (e) {
            setError('Failed to fetch debug data');
        }
        setLoading(false);
    };

    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
                <Typography>Loading...</Typography>
            </Box>
        );
    }

    if (error) {
        return <Box sx={{ p: 4 }}><Alert severity="error">{error}</Alert></Box>;
    }

    if (!data) {
        return (
            <Box sx={{ p: 4 }}>
                <Button variant="contained" startIcon={<RefreshIcon />} onClick={fetchDebug}>
                    Load Debug Data
                </Button>
            </Box>
        );
    }

    return (
        <Box>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
                <Typography variant="h4">Debug Center</Typography>
                <Button variant="contained" startIcon={<RefreshIcon />} onClick={fetchDebug}>
                    Refresh
                </Button>
            </Box>

            <Card>
                <CardContent>
                    <Typography variant="h6">System Status</Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                        System is running.
                    </Typography>
                    <Box sx={{ mt: 2 }}>
                        <pre style={{ overflow: 'auto', maxHeight: '400px' }}>
                            {JSON.stringify(data, null, 2)}
                        </pre>
                    </Box>
                </CardContent>
            </Card>
        </Box>
    );
};
