import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import {
    Box,
    Drawer,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    Collapse,
} from '@mui/material';
import ExpandLess from '@mui/icons-material/ExpandLess';
import ExpandMore from '@mui/icons-material/ExpandMore';
import { Can } from '@/components/auth/Can';
import { navigationItems } from './navConfig';
import type { NavItem } from '@/types';
import { EWNET_BRAND } from '@/theme/theme';

const DRAWER_WIDTH = 280;

interface SidebarProps {
    mobileOpen: boolean;
    onMobileClose: () => void;
}

const NavItemComponent: React.FC<{ item: NavItem; depth?: number }> = ({ item, depth = 0 }) => {
    const [open, setOpen] = useState(false);
    const navigate = useNavigate();
    const location = useLocation();

    // Check if any child route is active
    const isChildActive = item.children?.some(
        (child) => child.path && location.pathname.startsWith(child.path)
    );

    const isActive = item.path ? location.pathname === item.path : isChildActive;

    // Auto-expand if a child is active
    React.useEffect(() => {
        if (isChildActive) setOpen(true);
    }, [isChildActive]);

    const handleClick = () => {
        if (item.children) {
            setOpen(!open);
        } else if (item.path) {
            navigate(item.path);
        }
    };

    // Permission-gated rendering
    if (item.permission) {
        return (
            <Can permission={item.permission} fallback={null}>
                <NavItemInner
                    item={item}
                    depth={depth}
                    isActive={isActive || !!isChildActive}
                    isOpen={open}
                    onClick={handleClick}
                />
            </Can>
        );
    }

    return (
        <NavItemInner
            item={item}
            depth={depth}
            isActive={isActive || !!isChildActive}
            isOpen={open}
            onClick={handleClick}
        />
    );
};

const NavItemInner: React.FC<{
    item: NavItem;
    depth: number;
    isActive: boolean;
    isOpen: boolean;
    onClick: () => void;
}> = ({ item, depth, isActive, isOpen, onClick }) => {
    return (
        <>
            <ListItem disablePadding sx={{ display: 'block' }}>
                <ListItemButton
                    selected={isActive && !item.children}
                    onClick={onClick}
                    sx={{
                        pl: 2 + depth * 2,
                        minHeight: 44,
                        color: EWNET_BRAND.sidebarText,
                        '&:hover': {
                            backgroundColor: EWNET_BRAND.sidebarActiveBg,
                        },
                        '&.Mui-selected': {
                            backgroundColor: EWNET_BRAND.sidebarActiveBg,
                            color: EWNET_BRAND.sidebarActiveText,
                            fontWeight: 600,
                            borderRight: 3,
                            borderColor: 'primary.main',
                        },
                    }}
                >
                    <ListItemIcon sx={{ minWidth: 40 }}>{item.icon}</ListItemIcon>
                    <ListItemText
                        primary={item.label}
                        primaryTypographyProps={{
                            fontSize: depth > 0 ? '0.875rem' : '0.95rem',
                            fontWeight: isActive ? 600 : 400,
                        }}
                    />
                    {item.children && (isOpen ? <ExpandLess /> : <ExpandMore />)}
                </ListItemButton>
            </ListItem>
            {item.children && (
                <Collapse in={isOpen} timeout="auto" unmountOnExit>
                    <List component="div" disablePadding>
                        {item.children.map((child) => (
                            <NavItemComponent key={child.label} item={child} depth={depth + 1} />
                        ))}
                    </List>
                </Collapse>
            )}
        </>
    );
};

export const Sidebar: React.FC<SidebarProps> = ({ mobileOpen, onMobileClose }) => {

    const drawerContent = (
        <Box sx={{ bgcolor: EWNET_BRAND.sidebarBg, color: EWNET_BRAND.sidebarText, height: '100%' }}>
            <List sx={{ pt: 2 }}>
                {navigationItems.map((item) => (
                    <NavItemComponent key={item.label} item={item} />
                ))}
            </List>
        </Box>
    );

    return (
        <Box component="nav" sx={{ width: { sm: DRAWER_WIDTH }, flexShrink: { sm: 0 } }}>
            {/* Mobile drawer */}
            <Drawer
                variant="temporary"
                open={mobileOpen}
                onClose={onMobileClose}
                ModalProps={{ keepMounted: true }}
                sx={{
                    display: { xs: 'block', sm: 'none' },
                    '& .MuiDrawer-paper': { width: DRAWER_WIDTH, boxSizing: 'border-box', bgcolor: EWNET_BRAND.sidebarBg, color: EWNET_BRAND.sidebarText },
                }}
            >
                {drawerContent}
            </Drawer>
            {/* Desktop drawer */}
            <Drawer
                variant="permanent"
                sx={{
                    display: { xs: 'none', sm: 'block' },
                    '& .MuiDrawer-paper': { width: DRAWER_WIDTH, boxSizing: 'border-box', bgcolor: EWNET_BRAND.sidebarBg, color: EWNET_BRAND.sidebarText, borderRight: 'none' },
                }}
                open
            >
                {drawerContent}
            </Drawer>
        </Box>
    );
};

export { DRAWER_WIDTH };
