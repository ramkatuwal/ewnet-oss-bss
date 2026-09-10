import axios from 'axios';

export interface NormalizedApiError {
    message: string;
    fieldErrors: Record<string, string>;
    status?: number;
}

export const normalizeApiError = (error: unknown): NormalizedApiError => {
    if (!axios.isAxiosError(error)) {
        return { message: 'An unexpected error occurred.', fieldErrors: {} };
    }

    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined;
    const fieldErrors = Object.fromEntries(
        Object.entries(data?.errors ?? {}).map(([field, messages]) => [field, messages[0] ?? 'Invalid value.']),
    );

    return {
        message: data?.message ?? 'The request could not be completed.',
        fieldErrors,
        status: error.response?.status,
    };
};
