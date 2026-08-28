import { Box, Typography, Breadcrumbs, Link as MuiLink, Skeleton } from '@mui/material';
import { Link } from 'react-router-dom';

interface BreadcrumbItem {
    label: string;
    path?: string;
}

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItem[];
    actions?: React.ReactNode;
    loading?: boolean;
}

export const PageHeader = ({ title, subtitle, breadcrumbs, actions, loading }: PageHeaderProps) => {
    return (
        <Box sx={{ mb: 3 }}>
            {breadcrumbs && breadcrumbs.length > 0 && (
                <Breadcrumbs sx={{ mb: 1 }}>
                    {breadcrumbs.map((crumb, index) => {
                        const isLast = index === breadcrumbs.length - 1;
                        if (isLast || !crumb.path) {
                            return (
                                <Typography key={index} color="text.primary" variant="body2">
                                    {crumb.label}
                                </Typography>
                            );
                        }
                        return (
                            <MuiLink
                                key={index}
                                component={Link}
                                to={crumb.path}
                                underline="hover"
                                variant="body2"
                            >
                                {crumb.label}
                            </MuiLink>
                        );
                    })}
                </Breadcrumbs>
            )}
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <Box>
                    {loading ? (
                        <>
                            <Skeleton width={200} height={32} />
                            <Skeleton width={300} height={20} />
                        </>
                    ) : (
                        <>
                            <Typography variant="h4" component="h1" fontWeight={600}>
                                {title}
                            </Typography>
                            {subtitle && (
                                <Typography variant="body1" color="text.secondary" sx={{ mt: 0.5 }}>
                                    {subtitle}
                                </Typography>
                            )}
                        </>
                    )}
                </Box>
                {actions && <Box sx={{ display: 'flex', gap: 1 }}>{actions}</Box>}
            </Box>
        </Box>
    );
};
