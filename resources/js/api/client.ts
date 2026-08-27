import axios from 'axios';
import { useAuthStore } from '@/stores/authStore';

export const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/',
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

// CSRF token interceptor
apiClient.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

// Response interceptor for auth errors
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Clear auth state and redirect to login
            const store = useAuthStore.getState();
            if (store.authState === 'authenticated') {
                store.logout().catch(() => {});
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export const csrfApi = {
    getCookie: () => apiClient.get('/sanctum/csrf-cookie'),
};
