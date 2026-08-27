import React, { Suspense, lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { AuthLayout } from '@/layouts/AuthLayout';
import { MainLayout } from '@/layouts/MainLayout';
import { Box, CircularProgress, Typography } from '@mui/material';

// Lazy-loaded pages
const LoginPage = lazy(() => import('@/features/auth/pages/LoginPage').then(m => ({ default: m.LoginPage })));
const DashboardPage = lazy(() => import('@/features/dashboard/pages/DashboardPage').then(m => ({ default: m.DashboardPage })));

// Manage pages (lazy)
const CompaniesPage = lazy(() => import('@/features/companies/pages/CompaniesPage').then(m => ({ default: m.CompaniesPage })));
const RegionsPage = lazy(() => import('@/features/regions/pages/RegionsPage').then(m => ({ default: m.RegionsPage })));
const BranchesPage = lazy(() => import('@/features/branches/pages/BranchesPage').then(m => ({ default: m.BranchesPage })));
const DepartmentsPage = lazy(() => import('@/features/departments/pages/DepartmentsPage').then(m => ({ default: m.DepartmentsPage })));
const UsersPage = lazy(() => import('@/features/users/pages/UsersPage').then(m => ({ default: m.UsersPage })));
const RolesPage = lazy(() => import('@/features/roles/pages/RolesPage').then(m => ({ default: m.RolesPage })));
const PermissionsPage = lazy(() => import('@/features/permissions/pages/PermissionsPage').then(m => ({ default: m.PermissionsPage })));

// Placeholder pages for TASK-025
const SecurityActivityPage = lazy(() => import('@/features/debug/pages/DebugPage').then(m => ({ default: m.DebugPage })));

// Loading fallback
const PageLoader = () => (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', minHeight: '60vh', gap: 2 }}>
        <CircularProgress />
        <Typography variant="body2" color="text.secondary">Loading...</Typography>
    </Box>
);

// Not Found page
const NotFoundPage = () => (
    <Box sx={{ textAlign: 'center', py: 8 }}>
        <Typography variant="h3" gutterBottom>404</Typography>
        <Typography variant="h6" color="text.secondary">Page not found</Typography>
    </Box>
);

export const AppRouter = () => {
    return (
        <Suspense fallback={<PageLoader />}>
            <Routes>
                {/* Public routes */}
                <Route element={<AuthLayout />}>
                    <Route path="/login" element={<LoginPage />} />
                </Route>

                {/* Protected routes */}
                <Route element={<ProtectedRoute />}>
                    <Route element={<MainLayout />}>
                        <Route index element={<Navigate to="/dashboard" replace />} />
                        <Route path="dashboard" element={<DashboardPage />} />

                        {/* Manage section */}
                        <Route path="manage">
                            <Route path="companies" element={<CompaniesPage />} />
                            <Route path="regions" element={<RegionsPage />} />
                            <Route path="branches" element={<BranchesPage />} />
                            <Route path="departments" element={<DepartmentsPage />} />
                            <Route path="users" element={<UsersPage />} />
                            <Route path="roles" element={<RolesPage />} />
                            <Route path="permissions" element={<PermissionsPage />} />
                        </Route>

                        {/* Audit section */}
                        <Route path="audit">
                            <Route path="security" element={<SecurityActivityPage />} />
                        </Route>

                        {/* Account section */}
                        <Route path="account">
                            <Route path="profile" element={<DashboardPage />} /> {/* Placeholder until TASK-025 */}
                        </Route>

                        {/* Legacy route redirects */}
                        <Route path="companies" element={<Navigate to="/manage/companies" replace />} />
                        <Route path="regions" element={<Navigate to="/manage/regions" replace />} />
                        <Route path="branches" element={<Navigate to="/manage/branches" replace />} />
                        <Route path="departments" element={<Navigate to="/manage/departments" replace />} />
                        <Route path="users" element={<Navigate to="/manage/users" replace />} />
                        <Route path="roles" element={<Navigate to="/manage/roles" replace />} />
                        <Route path="permissions" element={<Navigate to="/manage/permissions" replace />} />
                        <Route path="debug" element={<Navigate to="/audit/security" replace />} />

                        {/* Catch-all */}
                        <Route path="*" element={<NotFoundPage />} />
                    </Route>
                </Route>
            </Routes>
        </Suspense>
    );
};
