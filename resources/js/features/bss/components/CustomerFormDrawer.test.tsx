import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { CustomerFormDrawer } from './CustomerFormDrawer';

const getTextBox = (name: string) => screen.getByRole('textbox', { name });

describe('CustomerFormDrawer', () => {
    it('validates and submits an authoritative customer identity', async () => {
        const onSubmit = vi.fn();
        render(<CustomerFormDrawer onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Company ID' }), { target: { value: '12' } });
        fireEvent.change(getTextBox('Customer code'), { target: { value: 'CUS-12' } });
        fireEvent.change(getTextBox('Name'), { target: { value: 'Ada Customer' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await vi.waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ company_id: 12, customer_code: 'CUS-12', name: 'Ada Customer', type: 'individual' })));
    });

    it('captures direct onboarding source, primary records, and verification reference', async () => {
        const onSubmit = vi.fn();
        render(<CustomerFormDrawer onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Company ID' }), { target: { value: '12' } });
        fireEvent.change(getTextBox('Customer code'), { target: { value: 'CUS-13' } });
        fireEvent.change(getTextBox('Name'), { target: { value: 'Onboarded' } });
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Source ID' }), { target: { value: '9' } });
        fireEvent.change(getTextBox('Primary contact email'), { target: { value: 'onboard@example.test' } });
        fireEvent.change(getTextBox('Primary address'), { target: { value: 'Main Street' } });
        fireEvent.change(getTextBox('Verification reference'), { target: { value: 'KYC-9' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await vi.waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
            source_id: 9,
            initial_contact: { kind: 'email', value: 'onboard@example.test' },
            initial_address: { line1: 'Main Street' },
            verification: expect.objectContaining({ reference: 'KYC-9' }),
        })));
    });

    it('submits without optional onboarding fields when omitted', async () => {
        const onSubmit = vi.fn();
        render(<CustomerFormDrawer onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Company ID' }), { target: { value: '5' } });
        fireEvent.change(getTextBox('Customer code'), { target: { value: 'C-5' } });
        fireEvent.change(getTextBox('Name'), { target: { value: 'Minimal' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await vi.waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
            company_id: 5,
            customer_code: 'C-5',
            name: 'Minimal',
            source_id: undefined,
            initial_contact: undefined,
            initial_address: undefined,
            verification: undefined,
        })));
    });

    it('prefills existing customer data in edit mode', () => {
        const customer = { id: 1, company_id: 7, customer_code: 'C-1', name: 'Existing', type: 'organization' as const, status: 'active' as const, email: 'ex@test.com', phone: null, address: null };
        render(<CustomerFormDrawer customer={customer} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.getByText('Edit customer')).toBeInTheDocument();
        expect(screen.getByRole('spinbutton', { name: 'Company ID' })).toHaveValue(7);
        expect(getTextBox('Customer code')).toHaveValue('C-1');
        expect(getTextBox('Name')).toHaveValue('Existing');
    });
});
