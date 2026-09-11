import { describe, expect, it, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '@/components/feedback/ToastProvider';
import { useAuthStore } from '@/stores/authStore';
import LeadDetailPage from './LeadDetailPage';

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return { ...actual, useParams: () => ({ id: '55' }), useNavigate: () => vi.fn() };
});

const mockLeadNew = {
    id: 55, company_id: 1, lead_code: 'LD-55', name: 'Prospect Corp',
    email: 'sales@prospect.test', phone: '+977888', status: 'new' as const,
    qualification: null, converted_customer_id: null,
} as const;
const mockLeadQualified = { ...mockLeadNew, status: 'qualified' as const, qualification: { serviceable: true } } as const;
const mockLeadConverted = { ...mockLeadNew, status: 'converted' as const, converted_customer_id: 42 } as const;
const mockLeadLost = { ...mockLeadNew, status: 'lost' as const } as const;
const mockCandidates = [{ id: 10, customer_code: 'C-10', name: 'Prospect Inc' }, { id: 11, customer_code: 'C-11', name: 'Similar Corp' }];

const mockApi = {
    lead: vi.fn(),
    qualifyLead: vi.fn(),
    unqualifyLead: vi.fn(),
    loseLead: vi.fn(),
    duplicateCandidates: vi.fn(),
    convertLead: vi.fn(),
};

vi.mock('../api/bss', () => ({
    bssApi: {
        lead: (...args: unknown[]) => mockApi.lead(...args),
        qualifyLead: (...args: unknown[]) => mockApi.qualifyLead(...args),
        unqualifyLead: (...args: unknown[]) => mockApi.unqualifyLead(...args),
        loseLead: (...args: unknown[]) => mockApi.loseLead(...args),
        duplicateCandidates: (...args: unknown[]) => mockApi.duplicateCandidates(...args),
        convertLead: (...args: unknown[]) => mockApi.convertLead(...args),
    },
}));

const wrapper = ({ children }: { children: React.ReactNode }) => (
    <MemoryRouter initialEntries={['/bss/leads/55']}>
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <ToastProvider>{children}</ToastProvider>
        </QueryClientProvider>
    </MemoryRouter>
);

describe('LeadDetailPage', () => {
    beforeEach(() => {
        mockApi.lead.mockReset();
        mockApi.qualifyLead.mockReset();
        mockApi.unqualifyLead.mockReset();
        mockApi.loseLead.mockReset();
        mockApi.duplicateCandidates.mockReset();
        mockApi.convertLead.mockReset();
    });

    afterEach(() => {
        vi.clearAllMocks();
        useAuthStore.setState({ user: null, authState: 'anonymous', isLoading: false });
    });

    const loadLead = async (lead: { id: number; company_id: number; lead_code: string; name: string; email: string | null; phone: string | null; status: string; qualification: Record<string, unknown> | null; converted_customer_id: number | null }, permissions: string[], roles: string[] = ['Super Admin']) => {
        mockApi.lead.mockResolvedValue(lead);
        useAuthStore.setState({
            user: { id: 1, name: 'Admin', email: 'a@t.com', is_active: true, roles, permissions },
            authState: 'authenticated',
        });
        render(<LeadDetailPage />, { wrapper });
        await waitFor(() => expect(mockApi.lead).toHaveBeenCalled());
        await screen.findByText(/LD-55/);
    };

    it('renders lead header with code, name, status, and company', async () => {
        await loadLead(mockLeadNew, ['bss.leads.view']);
        expect(screen.getByText(/LD-55/)).toBeInTheDocument();
        expect(screen.getByText(/Company #1/)).toBeInTheDocument();
    });

    it('shows verify-and-qualify and mark-lost buttons when lead status is new', async () => {
        await loadLead(mockLeadNew, ['bss.leads.view', 'bss.leads.update']);
        expect(screen.getByRole('button', { name: 'Verify and qualify' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Mark lost' })).toBeInTheDocument();
    });

    it('shows qualification JSON input when verifying a new lead', async () => {
        await loadLead(mockLeadNew, ['bss.leads.view', 'bss.leads.update']);
        expect(screen.getByLabelText('Qualification JSON')).toBeInTheDocument();
    });

    it('qualifies a new lead', async () => {
        mockApi.qualifyLead.mockResolvedValue(mockLeadQualified);
        await loadLead(mockLeadNew, ['bss.leads.view', 'bss.leads.update']);
        fireEvent.click(screen.getByRole('button', { name: 'Verify and qualify' }));
        await waitFor(() => expect(mockApi.qualifyLead).toHaveBeenCalledWith(55, { serviceable: true }));
    });

    it('loses a new lead', async () => {
        mockApi.loseLead.mockResolvedValue(mockLeadLost);
        await loadLead(mockLeadNew, ['bss.leads.view', 'bss.leads.update']);
        fireEvent.click(screen.getByRole('button', { name: 'Mark lost' }));
        await waitFor(() => expect(mockApi.loseLead).toHaveBeenCalledWith(55));
    });

    it('shows duplicate candidates and conversion controls when qualified', async () => {
        mockApi.duplicateCandidates.mockResolvedValue(mockCandidates);
        await loadLead(mockLeadQualified, ['bss.leads.view', 'bss.leads.update', 'bss.leads.convert']);
        await waitFor(() => expect(screen.getByRole('button', { name: 'Convert' })).toBeInTheDocument());
        expect(screen.getByText('Duplicate candidates')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Unqualify' })).toBeInTheDocument();
    });

    it('converts a qualified lead with a new customer code', async () => {
        mockApi.duplicateCandidates.mockResolvedValue([]);
        mockApi.convertLead.mockResolvedValue({ id: 42, customer_code: 'C-42', name: 'Prospect Corp' });
        await loadLead(mockLeadQualified, ['bss.leads.view', 'bss.leads.convert']);
        fireEvent.change(screen.getByLabelText('New customer code'), { target: { value: 'C-42' } });
        fireEvent.click(screen.getByRole('button', { name: 'Convert' }));
        await waitFor(() => expect(mockApi.convertLead).toHaveBeenCalledWith(55, 'C-42'));
    });

    it('unqualifies a qualified lead', async () => {
        mockApi.duplicateCandidates.mockResolvedValue([]);
        mockApi.unqualifyLead.mockResolvedValue(mockLeadNew);
        await loadLead(mockLeadQualified, ['bss.leads.view', 'bss.leads.update', 'bss.leads.convert']);
        fireEvent.click(screen.getByRole('button', { name: 'Unqualify' }));
        await waitFor(() => expect(mockApi.unqualifyLead).toHaveBeenCalledWith(55));
    });

    it('hides verify/qualify controls for viewers without bss.leads.update', async () => {
        await loadLead(mockLeadNew, ['bss.leads.view'], []);
        expect(screen.queryByRole('button', { name: 'Verify and qualify' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Mark lost' })).not.toBeInTheDocument();
    });

    it('hides conversion controls for viewers without bss.leads.convert', async () => {
        mockApi.duplicateCandidates.mockResolvedValue([]);
        await loadLead(mockLeadQualified, ['bss.leads.view'], []);
        expect(screen.queryByRole('button', { name: 'Convert' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Unqualify' })).not.toBeInTheDocument();
    });

    it('shows converted lead without lifecycle controls', async () => {
        await loadLead(mockLeadConverted, ['bss.leads.view'], []);
        expect(screen.queryByRole('button', { name: 'Verify and qualify' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Convert' })).not.toBeInTheDocument();
    });

    it('shows lost lead without lifecycle controls', async () => {
        await loadLead(mockLeadLost, ['bss.leads.view'], []);
        expect(screen.queryByRole('button', { name: 'Verify and qualify' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Mark lost' })).not.toBeInTheDocument();
    });
});
