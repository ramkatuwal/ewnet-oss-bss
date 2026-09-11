import { describe, expect, it, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '@/components/feedback/ToastProvider';
import { useAuthStore } from '@/stores/authStore';
import BssOperationsPage from './BssOperationsPage';

const mockCustomers = { data: [{ id: 1, company_id: 1, customer_code: 'C-1', name: 'Acme', type: 'individual', status: 'active', email: 'a@test.com', phone: null, address: null }] };
const mockServices = { data: [{ id: 10, company_id: 1, service_code: 'S-10', name: 'Internet Basic', type: 'internet', status: 'active', description: null }] };
const mockLeads = { data: [{ id: 20, company_id: 1, lead_code: 'LD-20', name: 'Prospect', email: null, phone: null, status: 'new', qualification: null, converted_customer_id: null }] };

const mockApi = {
    customers: vi.fn(),
    services: vi.fn(),
    leads: vi.fn(),
    onboardCustomer: vi.fn(),
    createService: vi.fn(),
    createLead: vi.fn(),
    retireCustomer: vi.fn(),
    createCustomerService: vi.fn(),
};

vi.mock('../api/bss', () => ({
    bssApi: {
        customers: (...args: unknown[]) => mockApi.customers(...args),
        services: (...args: unknown[]) => mockApi.services(...args),
        leads: (...args: unknown[]) => mockApi.leads(...args),
        onboardCustomer: (...args: unknown[]) => mockApi.onboardCustomer(...args),
        createService: (...args: unknown[]) => mockApi.createService(...args),
        createLead: (...args: unknown[]) => mockApi.createLead(...args),
        retireCustomer: (...args: unknown[]) => mockApi.retireCustomer(...args),
        createCustomerService: (...args: unknown[]) => mockApi.createCustomerService(...args),
    },
}));

const wrapper = ({ children }: { children: React.ReactNode }) => (
    <MemoryRouter initialEntries={['/bss']}>
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <ToastProvider>{children}</ToastProvider>
        </QueryClientProvider>
    </MemoryRouter>
);

describe('BssOperationsPage', () => {
    beforeEach(() => {
        mockApi.customers.mockReset();
        mockApi.services.mockReset();
        mockApi.leads.mockReset();
        mockApi.onboardCustomer.mockReset();
        mockApi.createService.mockReset();
        mockApi.createLead.mockReset();
        mockApi.retireCustomer.mockReset();
        mockApi.createCustomerService.mockReset();
    });

    afterEach(() => {
        vi.clearAllMocks();
        useAuthStore.setState({ user: null, authState: 'anonymous', isLoading: false });
    });

    const adminPerms = ['bss.customers.view', 'bss.customers.create', 'bss.customers.retire', 'bss.services.view', 'bss.services.create', 'bss.leads.view', 'bss.leads.create', 'bss.customer-services.create'];
    const viewerPerms = ['bss.customers.view', 'bss.services.view', 'bss.leads.view'];

    const loadAdmin = async () => {
        mockApi.customers.mockResolvedValue(mockCustomers);
        mockApi.services.mockResolvedValue(mockServices);
        mockApi.leads.mockResolvedValue(mockLeads);
        useAuthStore.setState({ user: { id: 1, name: 'Admin', email: 'a@t.com', is_active: true, roles: ['Super Admin'], permissions: adminPerms }, authState: 'authenticated' });
        render(<BssOperationsPage />, { wrapper });
        await waitFor(() => expect(mockApi.customers).toHaveBeenCalled());
        await screen.findByText(/C-1 · Acme/);
    };

    const loadViewer = async () => {
        mockApi.customers.mockResolvedValue(mockCustomers);
        mockApi.services.mockResolvedValue(mockServices);
        mockApi.leads.mockResolvedValue(mockLeads);
        useAuthStore.setState({ user: { id: 2, name: 'Viewer', email: 'v@t.com', is_active: true, roles: [], permissions: viewerPerms }, authState: 'authenticated' });
        render(<BssOperationsPage />, { wrapper });
        await waitFor(() => expect(mockApi.customers).toHaveBeenCalled());
        await screen.findByText(/C-1 · Acme/);
    };

    it('renders BSS header and three tabs', async () => {
        await loadAdmin();
        expect(screen.getByText('Business Services')).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Customers' })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Services' })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Leads' })).toBeInTheDocument();
    });

    it('shows customer records on the customers tab by default', async () => {
        await loadAdmin();
        expect(screen.getByText(/C-1 · Acme/)).toBeInTheDocument();
        expect(screen.getByText(/Company #1/)).toBeInTheDocument();
    });

    it('switches to services tab and shows service records', async () => {
        await loadAdmin();
        fireEvent.click(screen.getByRole('tab', { name: 'Services' }));
        await waitFor(() => expect(screen.getByText(/S-10 · Internet Basic/)).toBeInTheDocument());
    });

    it('switches to leads tab and shows lead records', async () => {
        await loadAdmin();
        fireEvent.click(screen.getByRole('tab', { name: 'Leads' }));
        await waitFor(() => expect(screen.getByText(/LD-20 · Prospect/)).toBeInTheDocument());
    });

    it('shows Add customer button for users with bss.customers.create', async () => {
        await loadAdmin();
        expect(screen.getByRole('button', { name: 'Add customer' })).toBeInTheDocument();
    });

    it('hides Add customer button for viewers without bss.customers.create', async () => {
        await loadViewer();
        expect(screen.queryByRole('button', { name: 'Add customer' })).not.toBeInTheDocument();
    });

    it('opens the customer onboarding drawer when Add customer is clicked', async () => {
        await loadAdmin();
        fireEvent.click(screen.getByRole('button', { name: 'Add customer' }));
        await waitFor(() => expect(screen.getByText('New customer onboarding')).toBeInTheDocument());
    });

    it('opens the lead creation dialog when Add lead is clicked', async () => {
        await loadAdmin();
        fireEvent.click(screen.getByRole('tab', { name: 'Leads' }));
        await waitFor(() => expect(screen.getByText(/LD-20 · Prospect/)).toBeInTheDocument());
        fireEvent.click(screen.getByRole('button', { name: 'Add lead' }));
        await waitFor(() => expect(screen.getByText('New lead')).toBeInTheDocument());
    });

    it('hides all create and retire buttons for a viewer', async () => {
        await loadViewer();
        expect(screen.queryByRole('button', { name: 'Add customer' })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Retire' })).not.toBeInTheDocument();
        fireEvent.click(screen.getByRole('tab', { name: 'Leads' }));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Add lead' })).not.toBeInTheDocument());
    });

    it('hides service assignment button for viewers', async () => {
        await loadViewer();
        expect(screen.queryByRole('button', { name: 'Assign service' })).not.toBeInTheDocument();
    });
});
