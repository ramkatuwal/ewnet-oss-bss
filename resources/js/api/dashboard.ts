import { apiClient } from './client';

export interface DashboardData {
    organization: {
        companies: number;
        regions: number;
        branches: number;
        departments: number;
        users: number;
    };
    security: {
        roles: number;
        permissions: number;
    };
    activity: Array<{
        id: number;
        action: string;
        result: string;
        actor: string;
        target_type: string | null;
        created_at: string;
    }>;
    account: {
        user: {
            id: number;
            name: string;
            email: string;
            roles: string[];
        };
        membership: {
            company: string | null;
            branch: string | null;
            department: string | null;
        };
        is_super_admin: boolean;
    };
}

export const dashboardApi = {
    getData: (): Promise<DashboardData> =>
        apiClient.get('/api/v1/dashboard').then(res => res.data),
};
