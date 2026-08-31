import { useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import {
    Box,
    AppBar,
    Toolbar,
    IconButton,
    
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

export const MainLayout = () => {
    const [mobileOpen, setMobileOpen] = useState(false);
    const location = useLocation();
    const { authState } = useAuthStore();
    const { mode, toggle } = useThemeStore();
    const config = useConfigStore((state) => state.config);
    console.log('[MainLayout] Config loaded:', config);

    if (authState === 'booting') {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
                <CircularProgress />
            </Box>
        );
    }

    return (
        <Box sx={{ display: 'flex', minHeight: '100vh' }}>
            {/* App Bar */}
            <AppBar
                position="fixed"
                elevation={0}
                sx={{
                    zIndex: (theme) => theme.zIndex.drawer + 1,
                    width: { sm: `calc(100% - ${DRAWER_WIDTH}px)` },
                    ml: { sm: `${DRAWER_WIDTH}px` },
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
                    <Box sx={{ display: 'flex', alignItems: 'center', flexGrow: 1 }}>
                        {config?.branding?.logo_path && (
                            <img 
                                src={config.branding.logo_path} 
                                alt={config.branding.app_name || 'Logo'} 
                                style={{ height: '40px', marginRight: '16px', objectFit: 'contain' }} 
                            />
                        )}
                        
                    </Box>
                    <IconButton color="inherit" onClick={toggle} sx={{ mr: 1 }}>
                        {mode === 'dark' ? <LightModeIcon /> : <DarkModeIcon />}
                    </IconButton>
                    <UserMenu />
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
