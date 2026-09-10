import { describe, expect, it } from 'vitest';
import { AxiosError } from 'axios';
import { normalizeApiError } from './errors';

describe('normalizeApiError', () => {
    it('maps Laravel 422 field errors to their first message', () => {
        const error = new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
            status: 422,
            statusText: 'Unprocessable Content',
            headers: {},
            config: { headers: {} } as never,
            data: { message: 'The given data was invalid.', errors: { site_id: ['The selected site is invalid.'] } },
        });

        expect(normalizeApiError(error)).toEqual({
            message: 'The given data was invalid.',
            fieldErrors: { site_id: 'The selected site is invalid.' },
            status: 422,
        });
    });
});
