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
    (response) => {
        // Flatten Laravel pagination meta into top-level for PaginatedResponse<T> compatibility
        // Laravel returns: { data: [...], meta: { current_page, last_page, per_page, total, ... }, links: {...} }
        // Frontend expects: { data: [...], current_page, last_page, per_page, total, ... }
        const d = response.data;
        if (d && typeof d === "object" && !Array.isArray(d) && "meta" in d && "data" in d) {
            const meta = d.meta as Record<string, unknown>;
            if (meta && typeof meta === "object" && "total" in meta) {
                response.data = {
                    ...d,
                    current_page: meta.current_page,
                    last_page: meta.last_page,
                    per_page: meta.per_page,
                    total: meta.total,
                    from: meta.from,
                    to: meta.to,
                };
            }
        }
        return response;
    },
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
