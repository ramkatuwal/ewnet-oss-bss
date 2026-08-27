import React, { useState } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import {
    Box,
    AppBar,
    Toolbar,
    IconButton,
    Typography,
    Avatar,
    Menu,
    MenuItem,
    Divider,
    CircularProgress,
} from '@mui/material';
import MenuIcon from '@mui/icons-material/Menu';
import LogoutIcon from '@mui/icons-material/Logout';
import PersonIcon from '@mui/icons-material/Person';
import DarkModeIcon from '@mui/icons-material/DarkMode';
import LightModeIcon from '@mui/icons-material/LightMode';
import { useAuthStore } from '@/stores/authStore';
import { useThemeStore } from '@/stores/themeStore';
import { Sidebar, DRAWER_WIDTH } from '@/components/navigation/Sidebar';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';

export const MainLayout = () => {
    const [mobileOpen, setMobileOpen] = useState(false);
    const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
    const navigate = useNavigate();
    const { user, logout, authState } = useAuthStore();
    const { mode, toggle } = useThemeStore();

    const handleLogout = async () => {
        setAnchorEl(null);
        await logout();
        navigate('/login');
    };

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
                    <IconButton color="inherit" onClick={(e) => setAnchorEl(e.currentTarget)}>
                        <Avatar sx={{ width: 34, height: 34, bgcolor: 'secondary.main' }}>
                            {user?.name?.charAt(0)?.toUpperCase() || 'U'}
                        </Avatar>
                    </IconButton>
                    <Menu
                        anchorEl={anchorEl}
                        open={Boolean(anchorEl)}
                        onClose={() => setAnchorEl(null)}
                        transformOrigin={{ horizontal: 'right', vertical: 'top' }}
                        anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
                    >
                        <MenuItem disabled>
                            <Box>
                                <Typography variant="body2" fontWeight={600}>
                                    {user?.name || 'User'}
                                </Typography>
                                <Typography variant="caption" color="text.secondary">
                                    {user?.email || ''}
                                </Typography>
                            </Box>
                        </MenuItem>
                        <Divider />
                        <MenuItem onClick={() => { setAnchorEl(null); navigate('/account/profile'); }}>
                            <PersonIcon fontSize="small" sx={{ mr: 1 }} />
                            Profile
                        </MenuItem>
                        <MenuItem onClick={handleLogout}>
                            <LogoutIcon fontSize="small" sx={{ mr: 1 }} />
                            Logout
                        </MenuItem>
                    </Menu>
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
