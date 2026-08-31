import { useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import {
    Box,
    AppBar,
    Toolbar,
    IconButton,
    Typography,
    CircularProgress,
} from '@mui/material';
import MenuIcon from '@mui/icons-material/Menu';
import DarkModeIcon from '@mui/icons-material/DarkMode';
import LightModeIcon from '@mui/icons-material/LightMode';
import { useAuthStore } from '@/stores/authStore';
import { useConfigStore } from '@/stores/configStore';
import { useThemeStore } from '@/stores/themeStore';
import { Sidebar, DRAWER_WIDTH } from '@/components/navigation/Sidebar';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { UserMenu } from '@/components/layout/UserMenu';
import { EWNET_BRAND } from '@/theme/theme';

export const MainLayout = () => {
    const [mobileOpen, setMobileOpen] = useState(false);
    const location = useLocation();
    const { authState } = useAuthStore();
    const { mode, toggle } = useThemeStore();
    const config = useConfigStore((state) => state.config);

    if (authState === 'booting') {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
                <CircularProgress />
            </Box>
        );
    }

    const showLogo = config?.header?.show_logo !== false;
    const showTitle = config?.header?.show_title !== false;
    const logoPath = config?.branding?.logo_path;
    const appName = config?.branding?.app_name || 'EWNET';

    return (
        <Box sx={{ display: 'flex', minHeight: '100vh' }}>
            {/* App Bar */}
            <AppBar
                position="fixed"
                elevation={0}
                sx={{
                    zIndex: 1100,
                    bgcolor: EWNET_BRAND.headerBg,
                    color: '#212529',
                    borderBottom: `1px solid ${EWNET_BRAND.headerBorder}`,
                    width: { sm: `calc(100% - ${DRAWER_WIDTH}px)` },
                    ml: { sm: `${DRAWER_WIDTH}px` },
                    '& .MuiIconButton-root': { color: '#495057' },
                }}
            >
                <Toolbar>
                    <IconButton
                        color="inherit"
                        edge="start"
                        onClick={() => setMobileOpen(!mobileOpen)}
                        sx={{ mr: 2, display: { sm: 'none' } }}
                    >
                        <MenuIcon />
                    </IconButton>
                    <Box sx={{ display: 'flex', alignItems: 'center', flexGrow: 1, gap: 2 }}>
                        {showLogo && logoPath && (
                            <img
                                src={logoPath}
                                alt={appName}
                                style={{ height: '36px', objectFit: 'contain' }}
                            />
                        )}
                        {showTitle && (
                            <Typography variant="h6" fontWeight={600} noWrap>
                                {appName}
                            </Typography>
                        )}
                    </Box>
                    <IconButton color="inherit" onClick={toggle} sx={{ mr: 1 }}>
                        {mode === 'dark' ? <LightModeIcon /> : <DarkModeIcon />}
                    </IconButton>
                    {config?.header?.show_user_menu !== false && <UserMenu />}
                </Toolbar>
            </AppBar>

            {/* Sidebar */}
            <Sidebar mobileOpen={mobileOpen} onMobileClose={() => setMobileOpen(false)} />

            {/* Main Content */}
            <Box
                component="main"
                sx={{
                    flexGrow: 1,
                    p: 3,
                    width: { sm: `calc(100% - ${DRAWER_WIDTH}px)` },
                    mt: 8,
                    minHeight: 'calc(100vh - 64px)',
                }}
            >
                <ErrorBoundary key={location.pathname}>
                    <Outlet />
                </ErrorBoundary>
            </Box>
        </Box>
    );
};
