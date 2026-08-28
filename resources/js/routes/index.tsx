import { Suspense, lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { AuthLayout } from '@/layouts/AuthLayout';
import { MainLayout } from '@/layouts/MainLayout';
import { Box, CircularProgress, Typography } from '@mui/material';

const LoginPage = lazy(() => import('@/features/auth/pages/LoginPage').then(m => ({ default: m.LoginPage })));
const DashboardPage = lazy(() => import('@/features/dashboard/pages/DashboardPage').then(m => ({ default: m.DashboardPage })));

const CompaniesPage = lazy(() => import('@/features/companies/pages/CompaniesPage').then(m => ({ default: m.CompaniesPage })));
const CompanyDetailPage = lazy(() => import('@/features/companies/pages/CompanyDetailPage').then(m => ({ default: m.CompanyDetailPage })));
const RegionsPage = lazy(() => import('@/features/regions/pages/RegionsPage').then(m => ({ default: m.RegionsPage })));
const RegionDetailPage = lazy(() => import('@/features/regions/pages/RegionDetailPage').then(m => ({ default: m.RegionDetailPage })));
const BranchesPage = lazy(() => import('@/features/branches/pages/BranchesPage').then(m => ({ default: m.BranchesPage })));
const BranchDetailPage = lazy(() => import('@/features/branches/pages/BranchDetailPage').then(m => ({ default: m.BranchDetailPage })));
const DepartmentsPage = lazy(() => import('@/features/departments/pages/DepartmentsPage').then(m => ({ default: m.DepartmentsPage })));
const DepartmentDetailPage = lazy(() => import('@/features/departments/pages/DepartmentDetailPage').then(m => ({ default: m.DepartmentDetailPage })));
const UsersPage = lazy(() => import('@/features/users/pages/UsersPage').then(m => ({ default: m.UsersPage })));
const UserDetailPage = lazy(() => import('@/features/users/pages/UserDetailPage').then(m => ({ default: m.UserDetailPage })));
const RolesPage = lazy(() => import('@/features/roles/pages/RolesPage').then(m => ({ default: m.RolesPage })));
const RoleDetailPage = lazy(() => import('@/features/roles/pages/RoleDetailPage').then(m => ({ default: m.RoleDetailPage })));
const PermissionsPage = lazy(() => import('@/features/permissions/pages/PermissionsPage').then(m => ({ default: m.PermissionsPage })));
const SecurityActivityPage = lazy(() => import('@/features/audit/pages/SecurityActivityPage').then(m => ({ default: m.SecurityActivityPage })));
const ProfilePage = lazy(() => import('@/features/account/pages/ProfilePage').then(m => ({ default: m.ProfilePage })));

const PageLoader = () => (
    <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', minHeight: '60vh', gap: 2 }}>
        <CircularProgress /><Typography variant="body2" color="text.secondary">Loading...</Typography>
    </Box>
);

const NotFoundPage = () => (
    <Box sx={{ textAlign: 'center', py: 8 }}>
        <Typography variant="h3" gutterBottom>404</Typography>
        <Typography variant="h6" color="text.secondary">Page not found</Typography>
    </Box>
);

export const AppRouter = () => (
    <Suspense fallback={<PageLoader />}>
        <Routes>
            <Route element={<AuthLayout />}><Route path="/login" element={<LoginPage />} /></Route>
            <Route element={<ProtectedRoute />}>
                <Route element={<MainLayout />}>
                    <Route index element={<Navigate to="/dashboard" replace />} />
                    <Route path="dashboard" element={<DashboardPage />} />
                    <Route path="manage">
                        <Route path="companies" element={<CompaniesPage />} />
                        <Route path="companies/:id" element={<CompanyDetailPage />} />
                        <Route path="regions" element={<RegionsPage />} />
                        <Route path="regions/:id" element={<RegionDetailPage />} />
                        <Route path="branches" element={<BranchesPage />} />
                        <Route path="branches/:id" element={<BranchDetailPage />} />
                        <Route path="departments" element={<DepartmentsPage />} />
                        <Route path="branches/:branchId/departments" element={<DepartmentsPage />} />
                        <Route path="branches/:branchId/departments/:departmentId" element={<DepartmentDetailPage />} />
                        <Route path="departments/:departmentId" element={<DepartmentDetailPage />} />
                        <Route path="users" element={<UsersPage />} />
                        <Route path="users/:id" element={<UserDetailPage />} />
                        <Route path="roles" element={<RolesPage />} />
                        <Route path="roles/:id" element={<RoleDetailPage />} />
                        <Route path="permissions" element={<PermissionsPage />} />
                    </Route>
                    <Route path="audit"><Route path="security" element={<SecurityActivityPage />} /></Route>
                    
                    {/* Legacy redirects */}
                    <Route path="companies" element={<Navigate to="/manage/companies" replace />} />
                    <Route path="regions" element={<Navigate to="/manage/regions" replace />} />
                    <Route path="branches" element={<Navigate to="/manage/branches" replace />} />
                    <Route path="users" element={<Navigate to="/manage/users" replace />} />
                    <Route path="roles" element={<Navigate to="/manage/roles" replace />} />
                    <Route path="permissions" element={<Navigate to="/manage/permissions" replace />} />
                    <Route path="debug" element={<Navigate to="/audit/security" replace />} />
                    <Route path="*" element={<NotFoundPage />} />
                </Route>
                    <Route path="account">
                        <Route path="profile" element={<ProfilePage />} />
                    </Route>
            </Route>
        </Routes>
    </Suspense>
);
