import { Navigate, Outlet } from 'react-router-dom';
import { PageErrorState } from '@/components/feedback/PageStates';
import { useAuthStore } from '@/stores/authStore';

export const PermissionRoute = ({ permission }: { permission: string }) => {
    const hasPermission = useAuthStore((state) => state.hasPermission);

    if (!hasPermission(permission)) {
        return <PageErrorState message="You do not have permission to view this area." />;
    }

    return <Outlet />;
};

export const PermissionRedirect = ({ permission, to }: { permission: string; to: string }) => {
    const hasPermission = useAuthStore((state) => state.hasPermission);
    return hasPermission(permission) ? <Outlet /> : <Navigate to={to} replace />;
};
