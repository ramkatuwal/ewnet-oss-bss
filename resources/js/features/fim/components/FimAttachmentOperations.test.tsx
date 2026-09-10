import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { FimAttachmentOperations } from './FimAttachmentOperations';

vi.mock('../api/fim', () => ({ fimApi: {
    terminations: vi.fn().mockResolvedValue({ data: [{ id: 7, fiber_core_id: 1, network_connection_point_id: 12, segment_end: 'A', company_id: 1 }] }), passivePorts: vi.fn().mockResolvedValue({ data: [] }), networkPortAttachments: vi.fn().mockResolvedValue({ data: [] }), terminationAttachments: vi.fn().mockResolvedValue({ data: [] }), splitterProfile: vi.fn().mockRejectedValue(new Error('not found')), splitterBranches: vi.fn().mockResolvedValue({ data: [] }), attachPassive: vi.fn(), detachPassive: vi.fn(), attachNetworkPort: vi.fn(), detachNetworkPort: vi.fn(), createSplitterProfile: vi.fn(), generateSplitterPorts: vi.fn(), createSplitterBranch: vi.fn(), deleteSplitterBranch: vi.fn(),
} }));
vi.mock('@/features/assets/api/assets', () => ({ getNetworkPorts: vi.fn().mockResolvedValue({ data: [] }) }));

describe('FimAttachmentOperations', () => {
    it('renders explicit passive, active, and splitter workflows without inferred topology', async () => {
        render(<QueryClientProvider client={new QueryClient()}><FimAttachmentOperations cores={[{ id: 1, fiber_segment_id: 2, core_number: 3, status: 'available', color_code: null, company_id: 1 }]} /></QueryClientProvider>);
        expect(screen.getByText('Termination attachments')).toBeInTheDocument();
        fireEvent.mouseDown(screen.getAllByRole('combobox')[0]);
        fireEvent.click(await screen.findByText('Segment 2, core 3 (available)'));
        fireEvent.mouseDown(screen.getAllByRole('combobox')[1]);
        fireEvent.click(await screen.findByText('End A, NCP #12'));
        expect(screen.getByText('Passive optical port attachment')).toBeInTheDocument();
        expect(screen.getByText('Active network-port fiber attachment')).toBeInTheDocument();
        expect(screen.getByText('Splitter profile and branches')).toBeInTheDocument();
        expect(screen.getByText(/never inferred/i)).toBeInTheDocument();
    });
});
