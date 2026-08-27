/**
 * Format a date string for display
 */
export function formatDateTime(dateStr?: string | null): string {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleString('en-NP', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
}

/**
 * Parse Laravel validation errors from API response
 */
export function parseValidationErrors(error: unknown): Record<string, string> {
    const result: Record<string, string> = {};
    if (error && typeof error === 'object' && 'response' in error) {
        const resp = (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
        if (resp?.data?.errors) {
            Object.entries(resp.data.errors).forEach(([key, messages]) => {
                result[key] = Array.isArray(messages) ? messages[0] : String(messages);
            });
        }
    }
    return result;
}

/**
 * Extract error message from API error
 */
export function getErrorMessage(error: unknown, fallback = 'An unexpected error occurred'): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const resp = (error as { response?: { data?: { message?: string }; status?: number } }).response;
        if (resp?.data?.message) return resp.data.message;
        if (resp?.status === 403) return 'You do not have permission to perform this action.';
        if (resp?.status === 401) return 'Your session has expired. Please log in again.';
        if (resp?.status === 404) return 'The requested resource was not found.';
        if (resp?.status === 422) return 'Please check the form for errors.';
    }
    return fallback;
}
