import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { CustomerFormDrawer } from './CustomerFormDrawer';

describe('CustomerFormDrawer', () => {
    it('validates and submits an authoritative customer identity', async () => {
        const onSubmit = vi.fn();
        render(<CustomerFormDrawer onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByLabelText('Company ID'), { target: { value: '12' } });
        fireEvent.change(screen.getByLabelText('Customer code'), { target: { value: 'CUS-12' } });
        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Ada Customer' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await vi.waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ company_id: 12, customer_code: 'CUS-12', name: 'Ada Customer', type: 'individual' })));
    });
});
