import { useState } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
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
import { useThemeStore } from '@/stores/themeStore';
import { Sidebar, DRAWER_WIDTH } from '@/components/navigation/Sidebar';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';
import { UserMenu } from '@/components/layout/UserMenu';

export const MainLayout = () => {
    const [mobileOpen, setMobileOpen] = useState(false);
    const { authState } = useAuthStore();
    const { mode, toggle } = useThemeStore();

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
                    <Typography variant="h6" noWrap sx={{ flexGrow: 1 }} fontWeight={600}>
                        EWNET OSS/BSS
                    </Typography>
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
                <ErrorBoundary>
                    <Outlet />
                </ErrorBoundary>
            </Box>
        </Box>
    );
};
