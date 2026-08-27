import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, createTheme, CssBaseline } from '@mui/material';
import { useEffect, useMemo } from 'react';
import { AppRouter } from '@/routes';
import { useAuthStore } from '@/stores/authStore';
import { useThemeStore } from '@/stores/themeStore';
import { ToastProvider } from '@/components/feedback/ToastProvider';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5 * 60 * 1000,
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

export const App = () => {
    const { hydrate, authState } = useAuthStore();
    const { mode } = useThemeStore();

    useEffect(() => {
        hydrate();
    }, [hydrate]);

    const theme = useMemo(
        () =>
            createTheme({
                palette: {
                    mode,
                    primary: { main: '#1976d2' },
                    secondary: { main: '#dc004e' },
                    background: {
                        default: mode === 'dark' ? '#121212' : '#f5f5f5',
                    },
                },
                typography: {
                    fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
                },
                shape: { borderRadius: 8 },
                components: {
                    MuiButton: { styleOverrides: { root: { textTransform: 'none' } } },
                    MuiCard: { styleOverrides: { root: { boxShadow: '0 1px 3px rgba(0,0,0,0.1)' } } },
                },
            }),
        [mode]
    );

    if (authState === 'booting') {
        return null; // MainLayout handles the loading state
    }

    return (
        <QueryClientProvider client={queryClient}>
            <ThemeProvider theme={theme}>
                <CssBaseline />
                <ToastProvider>
                    <BrowserRouter>
                        <AppRouter />
                    </BrowserRouter>
                </ToastProvider>
            </ThemeProvider>
        </QueryClientProvider>
    );
};
