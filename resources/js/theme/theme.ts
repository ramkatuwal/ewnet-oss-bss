// EWNET Brand Tokens — centralized for consistent branding across the application
export const EWNET_BRAND = {
    // LibreNMS default theme: clean white header, subtle borders
    headerBg: '#ffffff',
    headerBorder: '#dee2e6',
    sidebarBg: '#ffffff',
    sidebarActiveBg: '#e9ecef',
    sidebarText: '#495057',
    sidebarActiveText: '#212529',
    sidebarDivider: '#dee2e6',
    pageHeaderBg: '#f8f9fa',
    pageHeaderBorder: '#dee2e6',
} as const;

import { createTheme } from '@mui/material/styles';

export const createAppTheme = (mode: 'light' | 'dark') => {
    return createTheme({
        palette: {
            mode,
            primary: {
                main: '#1976d2',
                light: '#42a5f5',
                dark: '#1565c0',
            },
            secondary: {
                main: '#9c27b0',
                light: '#ba68c8',
                dark: '#7b1fa2',
            },
            success: {
                main: '#2e7d32',
                light: '#4caf50',
                dark: '#1b5e20',
            },
            warning: {
                main: '#ed6c02',
                light: '#ff9800',
                dark: '#e65100',
            },
            error: {
                main: '#d32f2f',
                light: '#ef5350',
                dark: '#c62828',
            },
            background: {
                default: mode === 'light' ? '#f5f5f5' : '#121212',
                paper: mode === 'light' ? '#ffffff' : '#1e1e1e',
            },
        },
        typography: {
            fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
            fontSize: 13,
            h1: { fontSize: '1.75rem', fontWeight: 600 },
            h2: { fontSize: '1.5rem', fontWeight: 600 },
            h3: { fontSize: '1.25rem', fontWeight: 600 },
            h4: { fontSize: '1.125rem', fontWeight: 600 },
            h5: { fontSize: '1rem', fontWeight: 600 },
            h6: { fontSize: '0.875rem', fontWeight: 600 },
            body1: { fontSize: '0.875rem' },
            body2: { fontSize: '0.8125rem' },
            caption: { fontSize: '0.75rem' },
        },
        spacing: 8,
        shape: {
            borderRadius: 6,
        },
        components: {
            MuiButton: {
                styleOverrides: {
                    root: {
                        textTransform: 'none',
                        fontWeight: 500,
                        fontSize: '0.8125rem',
                    },
                    sizeSmall: {
                        padding: '4px 12px',
                    },
                    sizeMedium: {
                        padding: '6px 16px',
                    },
                },
            },
            MuiCard: {
                styleOverrides: {
                    root: {
                        boxShadow: mode === 'light' 
                            ? '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)'
                            : '0 1px 3px 0 rgba(0, 0, 0, 0.3), 0 1px 2px 0 rgba(0, 0, 0, 0.2)',
                    },
                },
            },
            MuiCardContent: {
                styleOverrides: {
                    root: {
                        padding: '12px 16px',
                        '&:last-child': {
                            paddingBottom: '12px',
                        },
                    },
                },
            },
            MuiTableCell: {
                styleOverrides: {
                    root: {
                        padding: '8px 12px',
                        fontSize: '0.8125rem',
                    },
                    head: {
                        fontWeight: 600,
                        backgroundColor: mode === 'light' ? '#f9fafb' : '#2a2a2a',
                    },
                },
            },
            MuiTableRow: {
                styleOverrides: {
                    root: {
                        '&:hover': {
                            backgroundColor: mode === 'light' ? '#f5f5f5' : '#2a2a2a',
                        },
                    },
                },
            },
            MuiListItemButton: {
                styleOverrides: {
                    root: {
                        paddingTop: 6,
                        paddingBottom: 6,
                    },
                },
            },
            MuiChip: {
                styleOverrides: {
                    root: {
                        fontSize: '0.75rem',
                        height: 24,
                    },
                    sizeSmall: {
                        height: 20,
                        fontSize: '0.6875rem',
                    },
                },
            },
        },
    });
};
