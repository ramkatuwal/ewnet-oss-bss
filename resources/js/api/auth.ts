import { apiClient } from './client';
import { User, LoginResponse } from '@/types';

export const authApi = {
    login: (email: string, password: string): Promise<LoginResponse> =>
        apiClient.post('/api/v1/auth/login', { email, password }).then((res) => res.data),

    logout: (): Promise<void> =>
        apiClient.post('/api/v1/auth/logout'),

    user: (): Promise<{ user: User }> =>
        apiClient.get('/api/v1/auth/user').then((res) => res.data),
};
