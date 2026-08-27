import React from 'react';
import { Typography, Box, Card, CardContent, Grid } from '@mui/material';

export const DashboardPage: React.FC = () => {
    const stats = [
        { title: 'Total Companies', value: 0, color: '#1976d2' },
        { title: 'Total Regions', value: 0, color: '#2e7d32' },
        { title: 'Total Branches', value: 0, color: '#ed6c02' },
        { title: 'Total Employees', value: 0, color: '#9c27b0' },
    ];

    return (
        <Box>
            <Typography variant="h4" gutterBottom>
                Dashboard
            </Typography>
            <Typography variant="body1" color="text.secondary" paragraph>
                Welcome to EWNET OSS/BSS Dashboard
            </Typography>

            <Grid container spacing={3}>
                {stats.map((stat) => (
                    <Grid key={stat.title} xs={12} sm={6} md={3}>
                        <Card>
                            <CardContent>
                                <Typography color="text.secondary" variant="subtitle2">
                                    {stat.title}
                                </Typography>
                                <Typography variant="h4">{stat.value}</Typography>
                            </CardContent>
                        </Card>
                    </Grid>
                ))}
            </Grid>

            <Box sx={{ mt: 4 }}>
                <Typography variant="h6" gutterBottom>
                    Quick Actions
                </Typography>
                <Typography variant="body2" color="text.secondary">
                    Use the sidebar to navigate to different modules.
                </Typography>
            </Box>
        </Box>
    );
};
