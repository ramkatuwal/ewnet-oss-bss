import React, { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { useThemeStore } from '@/stores/themeStore';
import { useAuthStore } from '@/stores/authStore';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { MainLayout } from '@/layouts/MainLayout';
import { LoginPage } from '@/features/auth/pages/LoginPage';
import { CompaniesPage } from '@/features/companies/pages/CompaniesPage';
import { RegionsPage } from '@/features/regions/pages/RegionsPage';
import { BranchesPage } from '@/features/branches/pages/BranchesPage';
import { DepartmentsPage } from '@/features/departments/pages/DepartmentsPage';
import { UsersPage } from '@/features/users/pages/UsersPage';
import { RolesPage } from '@/features/roles/pages/RolesPage';
import { PermissionsPage } from '@/features/permissions/pages/PermissionsPage';
import { DashboardPage } from '@/features/dashboard/pages/DashboardPage';
import { DebugPage } from '@/features/debug/pages/DebugPage';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 1000 * 60 * 5,
            refetchOnWindowFocus: false,
        },
    },
});

const AppContent: React.FC = () => {
    const { mode } = useThemeStore();
    const { hydrate, authState } = useAuthStore();

    useEffect(() => {
        hydrate();
    }, []);

    const theme = createTheme({
        palette: {
            mode: mode === 'dark' ? 'dark' : 'light',
        },
    });

    if (authState === 'booting') {
        return <div>Loading...</div>;
    }

    return (
        <ThemeProvider theme={theme}>
            <CssBaseline />
            <BrowserRouter>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/" element={<ProtectedRoute />}>
                        <Route element={<MainLayout />}>
                            <Route index element={<Navigate to="/dashboard" replace />} />
                            <Route path="dashboard" element={<DashboardPage />} />
                            <Route path="companies" element={<CompaniesPage />} />
                            <Route path="regions" element={<RegionsPage />} />
                            <Route path="branches" element={<BranchesPage />} />
                            <Route path="departments" element={<DepartmentsPage />} />
                            <Route path="users" element={<UsersPage />} />
                            <Route path="roles" element={<RolesPage />} />
                            <Route path="permissions" element={<PermissionsPage />} />
                            <Route path="debug" element={<DebugPage />} />
                        </Route>
                    </Route>
                </Routes>
            </BrowserRouter>
        </ThemeProvider>
    );
};

export const App: React.FC = () => {
    return (
        <QueryClientProvider client={queryClient}>
            <AppContent />
            <ReactQueryDevtools initialIsOpen={false} />
        </QueryClientProvider>
    );
};

export default App;
