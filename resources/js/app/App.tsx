import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, CssBaseline } from '@mui/material';
import { useEffect, useMemo } from 'react';
import { AppRouter } from '@/routes';
import { useAuthStore } from '@/stores/authStore';
import { useThemeStore } from '@/stores/themeStore';
import { createAppTheme } from '@/theme/theme';
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

    const theme = useMemo(() => createAppTheme(mode), [mode]);

    useEffect(() => {
        hydrate();
    }, [hydrate]);

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
