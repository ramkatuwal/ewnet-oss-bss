import { create } from 'zustand';
import { apiClient } from '@/api/client';

export type AuthState = 'booting' | 'authenticated' | 'anonymous';

export interface AuthOrgContext {
    id: number;
    name: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone_number?: string | null;
    avatar?: string | null;
    is_active: boolean;
    company_id?: number | null;
    branch_id?: number | null;
    department_id?: number | null;
    company?: AuthOrgContext | null;
    branch?: AuthOrgContext | null;
    department?: AuthOrgContext | null;
    roles: string[];
    permissions: string[];
}

interface AuthStore {
    user: AuthUser | null;
    authState: AuthState;
    isLoading: boolean;
    login: (email: string, password: string) => Promise<void>;
    logout: () => Promise<void>;
    fetchUser: () => Promise<void>;
    hydrate: () => Promise<void>;
    hasPermission: (permission: string) => boolean;
    hasAnyPermission: (permissions: string[]) => boolean;
    isSuperAdmin: () => boolean;
    getDisplayRole: () => string;
}

const initialState = {
    user: null,
    authState: 'booting' as AuthState,
    isLoading: false,
};

export const useAuthStore = create<AuthStore>((set, get) => ({
    ...initialState,

    hydrate: async () => {
        set({ isLoading: true, authState: 'booting' });
        try {
            await get().fetchUser();
            set({ authState: 'authenticated' });
        } catch {
            set({ user: null, authState: 'anonymous' });
        } finally {
            set({ isLoading: false });
        }
    },

    login: async (email: string, password: string) => {
        set({ isLoading: true });
        try {
            await apiClient.get('/sanctum/csrf-cookie');
            await apiClient.post('/api/v1/auth/login', { email, password });
            await get().fetchUser();
            set({ authState: 'authenticated' });
        } catch (error) {
            set({ user: null, authState: 'anonymous' });
            throw error;
        } finally {
            set({ isLoading: false });
        }
    },

    logout: async () => {
        set({ isLoading: true });
        try { await apiClient.post('/api/v1/auth/logout'); } catch {}
        set({ user: null, authState: 'anonymous', isLoading: false });
    },

    fetchUser: async () => {
        set({ isLoading: true });
        try {
            const response = await apiClient.get('/api/v1/auth/user');
            const userData = response.data.user || response.data;
            set({ user: userData, authState: userData ? 'authenticated' : 'anonymous' });
        } catch {
            set({ user: null, authState: 'anonymous' });
            throw new Error('Failed to fetch user');
        } finally {
            set({ isLoading: false });
        }
    },

    hasPermission: (permission: string) => {
        const { user } = get();
        if (!user) return false;
        if (user.roles?.includes('Super Admin')) return true;
        if (Array.isArray(user.permissions)) return user.permissions.includes(permission);
        return false;
    },

    hasAnyPermission: (permissions: string[]) => {
        return permissions.some((p) => get().hasPermission(p));
    },

    isSuperAdmin: () => {
        return get().user?.roles?.includes('Super Admin') ?? false;
    },

    getDisplayRole: () => {
        const { user } = get();
        if (!user?.roles?.length) return 'No Role';
        return user.roles.join(', ');
    },
}));
