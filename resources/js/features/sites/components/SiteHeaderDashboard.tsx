import { Grid, Skeleton } from '@mui/material';
import { Business, LocationOn, Inventory } from '@mui/icons-material';
import { StatCard } from './StatCard';
import { SiteDashboardMetrics } from '@/types/site';

interface SiteHeaderDashboardProps {
    data?: SiteDashboardMetrics;
    isLoading: boolean;
}

export const SiteHeaderDashboard = ({ data, isLoading }: SiteHeaderDashboardProps) => {
    if (isLoading) {
        return (
            <Grid container spacing={2} sx={{ mb: 3 }}>
                {[1, 2, 3, 4].map((i) => (
                    <Grid item xs={6} sm={3} key={i}>
                        <Skeleton variant="rectangular" height={100} />
                    </Grid>
                ))}
            </Grid>
        );
    }

    if (!data) return null;

    return (
        <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid item xs={6} sm={3}>
                <StatCard 
                    title="Total Sites" 
                    value={data.total_sites} 
                    icon={<Business />} 
                />
            </Grid>
            <Grid item xs={6} sm={3}>
                <StatCard 
                    title="Active Sites" 
                    value={data.active_sites} 
                    color="success.main" 
                />
            </Grid>
            <Grid item xs={6} sm={3}>
                <StatCard 
                    title="Total Assets" 
                    value={data.total_assets} 
                    icon={<Inventory />} 
                />
            </Grid>
            <Grid item xs={6} sm={3}>
                <StatCard 
                    title="No Coordinates" 
                    value={data.sites_without_coordinates} 
                    color={data.sites_without_coordinates > 0 ? "warning.main" : "text.secondary"}
                    icon={<LocationOn />} 
                />
            </Grid>
        </Grid>
    );
};
