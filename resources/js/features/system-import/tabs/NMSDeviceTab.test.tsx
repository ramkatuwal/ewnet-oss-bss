import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import NMSDeviceTab from './NMSDeviceTab';

const { execute } = vi.hoisted(() => ({ execute: vi.fn().mockResolvedValue({ data: { created: 1 } }) }));
vi.mock('@/api/integrations', () => ({
    integrationImportApi: { execute },
    integrationApi: { preview: vi.fn().mockResolvedValue({ data: { analysis: [{
        external_id: '42', name: 'NMS Display', ip: '192.0.2.42', vendor: null, os: 'RouterOS', model: 'CCR',
        serial: 'SN-42', status: 'UP', type: 'network', site_name: 'Site Nine', action: 'create',
    }] } }) },
}));
vi.mock('@/api/import', () => ({ importApi: { getProviders: vi.fn().mockResolvedValue([]) } }));
vi.mock('@/components/import/ImportSourceCard', () => ({ default: ({ onIntegrationSelect }: { onIntegrationSelect: (id: number) => void }) =>
    <button onClick={() => onIntegrationSelect(6)}>Select NMS</button> }));
vi.mock('@/components/import/ImportConfirmationDialog', () => ({ default: ({ open, onConfirm }: { open: boolean; onConfirm: () => void }) =>
    open ? <button onClick={onConfirm}>Confirm import</button> : null }));
vi.mock('@/components/import/ImportHistoryPanel', () => ({ default: () => null }));
vi.mock('@/components/import/ImportResultDialog', () => ({ default: () => null }));

describe('NMS device preview', () => {
    it('shows provider fields but submits only external identity', async () => {
        render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><NMSDeviceTab /></QueryClientProvider>);
        fireEvent.click(screen.getByRole('button', { name: 'Select NMS' }));
        expect(await screen.findByText('NMS Display')).toBeInTheDocument();
        for (const label of ['Display Name', 'Management IP', 'OS/Platform', 'Hardware / Model', 'Serial', 'Provider Status', 'Provider Type', 'Mapped Site']) {
            expect(screen.getByRole('columnheader', { name: new RegExp(label.replace('/', '\\/')) })).toBeInTheDocument();
        }
        for (const value of ['192.0.2.42', 'RouterOS', 'CCR', 'SN-42', 'UP', 'network', 'Site Nine']) {
            expect(screen.getByText(value)).toBeInTheDocument();
        }
        fireEvent.click(screen.getAllByRole('checkbox')[1]);
        fireEvent.click(screen.getByRole('button', { name: 'Import Selected (1)' }));
        fireEvent.click(screen.getByRole('button', { name: 'Confirm import' }));
        await waitFor(() => expect(execute).toHaveBeenCalledWith(6, { devices: [{ external_id: '42' }] }));
    });
});
