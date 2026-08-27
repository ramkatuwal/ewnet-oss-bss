import axios from 'axios';
import { useAuthStore } from '@/stores/authStore';

export const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL || '/',
    withCredentials: true,
    headers: {
        Accept: 'application/json',
    },
});

// CSRF token interceptor
apiClient.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    // Only set Content-Type for non-FormData requests
    // Axios auto-sets multipart/form-data with boundary for FormData
    if (!(config.data instanceof FormData)) {
        config.headers['Content-Type'] = 'application/json';
    }
    return config;
});

// Response interceptor for auth errors
apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
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
