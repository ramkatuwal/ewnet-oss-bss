import { create } from 'zustand';
import { apiClient } from '@/api/client';
import { AuthUser } from '@/types';

export type AuthState = 'booting' | 'authenticated' | 'anonymous';

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
        } catch (error) {
            set({ user: null, authState: 'anonymous' });
        } finally {
            set({ isLoading: false });
        }
    },

    login: async (email: string, password: string) => {
        set({ isLoading: true });
        try {
            // Step 1: Get CSRF cookie
            await apiClient.get('/sanctum/csrf-cookie');

            // Step 2: Login via session (no token)
            await apiClient.post('/api/v1/auth/login', { email, password });

            // Step 3: Hydrate user
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
        try {
            await apiClient.post('/api/v1/auth/logout');
        } catch (error) {
            // Ignore errors on logout
        }
        set({
            user: null,
            authState: 'anonymous',
            isLoading: false,
        });
    },

    fetchUser: async () => {
        set({ isLoading: true });
        try {
            const response = await apiClient.get('/api/v1/auth/user');
            const userData = response.data.user || response.data;
            set({
                user: userData,
                authState: userData ? 'authenticated' : 'anonymous',
            });
        } catch (error) {
            set({
                user: null,
                authState: 'anonymous',
            });
            throw error;
        } finally {
            set({ isLoading: false });
        }
    },

    hasPermission: (permission: string) => {
        const { user } = get();
        if (!user) return false;
        
        // Super Admin bypass
        if (user.roles?.includes('Super Admin')) return true;
        
        // Check flat permission array
        if (Array.isArray(user.permissions)) {
            return user.permissions.includes(permission);
        }
        
        return false;
    },

    hasAnyPermission: (permissions: string[]) => {
        return permissions.some((p) => get().hasPermission(p));
    },

    getDisplayRole: () => {
        const { user } = get();
        if (!user?.roles?.length) return 'No Role';
        return user.roles.join(', ');
    },
}));
