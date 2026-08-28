import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Grid, Box, CircularProgress, Typography, Alert } from '@mui/material';
import BusinessIcon from '@mui/icons-material/Business';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import StorefrontIcon from '@mui/icons-material/Storefront';
import ApartmentIcon from '@mui/icons-material/Apartment';
import PeopleIcon from '@mui/icons-material/People';
import SecurityIcon from '@mui/icons-material/Security';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import { dashboardApi } from '@/api/dashboard';
import { StatCard } from '../widgets/StatCard';
import { RecentActivity } from '../widgets/RecentActivity';
import { QuickActions } from '../widgets/QuickActions';

export const DashboardPage: React.FC = () => {
    const { data, isLoading, error } = useQuery({
        queryKey: ['dashboard'],
        queryFn: dashboardApi.getData,
        staleTime: 30000, // 30 seconds
    });

    if (isLoading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '400px' }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return (
            <Box sx={{ p: 3 }}>
                <Alert severity="error">
                    Failed to load dashboard data. Please try again.
                </Alert>
            </Box>
        );
    }

    if (!data) {
        return null;
    }

    const { organization, security, activity, account } = data;

    return (
        <Box>
            <Typography variant="h5" gutterBottom fontWeight={600}>
                Welcome back, {account.user.name}
            </Typography>

            {/* Organization Stats */}
            <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Companies"
                        value={organization.companies}
                        icon={<BusinessIcon />}
                        color="primary.main"
                        link="/manage/companies"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Regions"
                        value={organization.regions}
                        icon={<LocationOnIcon />}
                        color="success.main"
                        link="/manage/regions"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Branches"
                        value={organization.branches}
                        icon={<StorefrontIcon />}
                        color="warning.main"
                        link="/manage/branches"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Departments"
                        value={organization.departments}
                        icon={<ApartmentIcon />}
                        color="info.main"
                        link="/manage/departments"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={4} lg={2.4}>
                    <StatCard
                        title="Users"
                        value={organization.users}
                        icon={<PeopleIcon />}
                        color="secondary.main"
                        link="/manage/users"
                    />
                </Grid>
            </Grid>

            {/* Security Stats */}
            <Grid container spacing={2} sx={{ mb: 3 }}>
                <Grid item xs={12} sm={6} md={3}>
                    <StatCard
                        title="Roles"
                        value={security.roles}
                        icon={<SecurityIcon />}
                        color="primary.main"
                        link="/manage/roles"
                    />
                </Grid>
                <Grid item xs={12} sm={6} md={3}>
                    <StatCard
                        title="Permissions"
                        value={security.permissions}
                        icon={<VpnKeyIcon />}
                        color="secondary.main"
                        link="/manage/permissions"
                    />
                </Grid>
            </Grid>

            {/* Activity and Quick Actions */}
            <Grid container spacing={2}>
                <Grid item xs={12} md={8}>
                    <RecentActivity activities={activity} />
                </Grid>
                <Grid item xs={12} md={4}>
                    <QuickActions />
                </Grid>
            </Grid>
        </Box>
    );
};
