import { Box, Typography, Breadcrumbs, Link as MuiLink, Skeleton } from '@mui/material';
import { Link } from 'react-router-dom';
import { EWNET_BRAND } from '@/theme/theme';

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
        <Box
            sx={{
                bgcolor: EWNET_BRAND.pageHeaderBg,
                borderBottom: `1px solid ${EWNET_BRAND.pageHeaderBorder}`,
                mx: -3,
                px: 3,
                py: 2,
                mb: 3,
            }}
        >
            {breadcrumbs && breadcrumbs.length > 0 && (
                <Breadcrumbs sx={{ mb: 0.5 }} separator={<span style={{ color: '#adb5bd' }}>/</span>}>
                    {breadcrumbs.map((crumb, index) => {
                        const isLast = index === breadcrumbs.length - 1;
                        if (isLast || !crumb.path) {
                            return (
                                <Typography key={index} sx={{ color: '#6c757d' }} variant="body2">
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
                                sx={{ color: '#495057', '&:hover': { color: '#212529' } }}
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
                            <Typography variant="h4" component="h1" fontWeight={600} sx={{ color: '#212529' }}>
                                {title}
                            </Typography>
                            {subtitle && (
                                <Typography variant="body1" sx={{ mt: 0.5, color: '#6c757d' }}>
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
