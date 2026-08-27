import { Routes, Route, Navigate } from 'react-router-dom';
import { ProtectedRoute } from '@/components/layout/ProtectedRoute';
import { AuthLayout } from '@/layouts/AuthLayout';
import { MainLayout } from '@/layouts/MainLayout';
import { LoginPage } from '@/features/auth/pages/LoginPage';
import { DashboardPage } from '@/features/dashboard/pages/DashboardPage';
import { CompaniesPage } from '@/features/companies/pages/CompaniesPage';
import { RegionsPage } from '@/features/regions/pages/RegionsPage';
import { BranchesPage } from '@/features/branches/pages/BranchesPage';
import { DepartmentsPage } from '@/features/departments/pages/DepartmentsPage';
import { UsersPage } from '@/features/users/pages/UsersPage';
import { RolesPage } from '@/features/roles/pages/RolesPage';
import { PermissionsPage } from '@/features/permissions/pages/PermissionsPage';
import { DebugPage } from '@/features/debug/pages/DebugPage';

export const AppRouter = () => {
    return (
        <Routes>
            <Route element={<AuthLayout />}>
                <Route path="/login" element={<LoginPage />} />
            </Route>

            <Route element={<ProtectedRoute />}>
                <Route element={<MainLayout />}>
                    <Route path="/" element={<Navigate to="/dashboard" replace />} />
                    <Route path="/dashboard" element={<DashboardPage />} />
                    <Route path="/companies" element={<CompaniesPage />} />
                    <Route path="/regions" element={<RegionsPage />} />
                    <Route path="/branches" element={<BranchesPage />} />
                    <Route path="/departments" element={<DepartmentsPage />} />
                    <Route path="/users" element={<UsersPage />} />
                    <Route path="/roles" element={<RolesPage />} />
                    <Route path="/permissions" element={<PermissionsPage />} />
                    <Route path="/debug" element={<DebugPage />} />

                </Route>
            </Route>
        </Routes>
    );
};
