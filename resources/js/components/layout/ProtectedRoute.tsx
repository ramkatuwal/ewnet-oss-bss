import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '@/stores/authStore';

export const ProtectedRoute = () => {
    const { authState, isLoading } = useAuthStore();

    if (authState === 'booting' || isLoading) {
        return <div>Loading...</div>;
    }

    if (authState === 'anonymous') {
        return <Navigate to="/login" replace />;
    }

    return <Outlet />;
};
