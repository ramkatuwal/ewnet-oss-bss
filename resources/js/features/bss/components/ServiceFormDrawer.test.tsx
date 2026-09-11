import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ServiceFormDrawer } from './ServiceFormDrawer';

const getTextBox = (name: string) => screen.getByRole('textbox', { name });

describe('ServiceFormDrawer', () => {
    it('validates and submits a new service with internet type default', async () => {
        const onSubmit = vi.fn();
        render(<ServiceFormDrawer onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Company ID' }), { target: { value: '1' } });
        fireEvent.change(getTextBox('Service code'), { target: { value: 'SVC-1' } });
        fireEvent.change(getTextBox('Name'), { target: { value: 'Fiber 100Mbps' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await vi.waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
            company_id: 1,
            service_code: 'SVC-1',
            name: 'Fiber 100Mbps',
            type: 'internet',
            description: null,
        })));
    });

    it('submits with description when provided', async () => {
        const onSubmit = vi.fn();
        render(<ServiceFormDrawer onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.change(screen.getByRole('spinbutton', { name: 'Company ID' }), { target: { value: '2' } });
        fireEvent.change(getTextBox('Service code'), { target: { value: 'SVC-2' } });
        fireEvent.change(getTextBox('Name'), { target: { value: 'IPTV Premium' } });
        fireEvent.change(getTextBox('Description'), { target: { value: 'HD channels' } });
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        await vi.waitFor(() => expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({
            company_id: 2,
            service_code: 'SVC-2',
            name: 'IPTV Premium',
            type: 'internet',
            description: 'HD channels',
        })));
    });

    it('prefills existing service data in edit mode', () => {
        const service = { id: 10, company_id: 3, service_code: 'SVC-10', name: 'Voice Basic', type: 'voice' as const, status: 'active' as const, description: 'VoIP calls' };
        render(<ServiceFormDrawer service={service} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.getByText('Edit service')).toBeInTheDocument();
        expect(screen.getByRole('spinbutton', { name: 'Company ID' })).toHaveValue(3);
        expect(getTextBox('Name')).toHaveValue('Voice Basic');
    });
});
