import { useAuthStore } from '@/stores/authStore';

interface CanProps {
    permission?: string;
    permissions?: string[];
    requireAll?: boolean;
    children: React.ReactNode;
    fallback?: React.ReactNode;
}

/**
 * Conditionally renders children based on user permissions.
 * 
 * Usage:
 *   <Can permission="companies.create"><Button>Create</Button></Can>
 *   <Can permissions={['roles.view', 'roles.update']}><EditRole /></Can>
 */
export const Can = ({ permission, permissions, requireAll = false, children, fallback = null }: CanProps) => {
    const { hasPermission, hasAnyPermission } = useAuthStore();

    if (permission) {
        return hasPermission(permission) ? <>{children}</> : <>{fallback}</>;
    }

    if (permissions && permissions.length > 0) {
        const allowed = requireAll
            ? permissions.every((p) => hasPermission(p))
            : hasAnyPermission(permissions);
        return allowed ? <>{children}</> : <>{fallback}</>;
    }

    return <>{children}</>;
};
