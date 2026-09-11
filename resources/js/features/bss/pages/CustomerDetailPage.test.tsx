import { describe, expect, it, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { ToastProvider } from '@/components/feedback/ToastProvider';
import { useAuthStore } from '@/stores/authStore';
import CustomerDetailPage from './CustomerDetailPage';

vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return { ...actual, useParams: () => ({ id: '42' }), useNavigate: () => vi.fn() };
});

const mockCustomer = {
    id: 42, company_id: 1, customer_code: 'C-42', name: 'Acme Corp', type: 'organization', status: 'active',
    email: 'info@acme.test', phone: '+97712345', address: null,
};

const mock360 = {
    ...mockCustomer,
    contacts: [
        { id: 1, kind: 'email', value: 'primary@acme.test', verified: true },
        { id: 2, kind: 'phone', value: '+977999', verified: false },
    ],
    contact_persons: [
        { id: 10, name: 'Alice', role: 'CTO', email: 'alice@acme.test', phone: null },
    ],
    addresses: [
        { id: 20, kind: 'billing', line1: '123 Street', city: 'Kathmandu', country_code: 'NP' },
    ],
    business_profile: { legal_name: 'Acme Pvt Ltd', registration_number: 'REG-123', tax_number: 'TAX-456', industry: 'Telecom' },
    verifications: [
        { id: 30, kind: 'kyc', status: 'verified', reference: 'KYC-REF-1', reason: null },
    ],
    notes: [
        { id: 40, body: 'Important customer note', created_at: '2026-09-01T00:00:00Z' },
    ],
};

const mockApi = {
    customer: vi.fn(),
    customer360: vi.fn(),
    addContact: vi.fn(),
    addPerson: vi.fn(),
    addAddress: vi.fn(),
    saveBusinessProfile: vi.fn(),
    addVerification: vi.fn(),
    addNote: vi.fn(),
};

vi.mock('../api/bss', () => ({
    bssApi: {
        customer: (...args: unknown[]) => mockApi.customer(...args),
        customer360: (...args: unknown[]) => mockApi.customer360(...args),
        addContact: (...args: unknown[]) => mockApi.addContact(...args),
        addPerson: (...args: unknown[]) => mockApi.addPerson(...args),
        addAddress: (...args: unknown[]) => mockApi.addAddress(...args),
        saveBusinessProfile: (...args: unknown[]) => mockApi.saveBusinessProfile(...args),
        addVerification: (...args: unknown[]) => mockApi.addVerification(...args),
        addNote: (...args: unknown[]) => mockApi.addNote(...args),
    },
}));

const wrapper = ({ children }: { children: React.ReactNode }) => (
    <MemoryRouter initialEntries={['/bss/customers/42']}>
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
            <ToastProvider>{children}</ToastProvider>
        </QueryClientProvider>
    </MemoryRouter>
);

describe('CustomerDetailPage', () => {
    beforeEach(() => {
        mockApi.customer.mockReset();
        mockApi.customer360.mockReset();
        mockApi.addContact.mockReset();
        mockApi.addPerson.mockReset();
        mockApi.addAddress.mockReset();
        mockApi.saveBusinessProfile.mockReset();
        mockApi.addVerification.mockReset();
        mockApi.addNote.mockReset();
    });

    afterEach(() => {
        vi.clearAllMocks();
        useAuthStore.setState({ user: null, authState: 'anonymous', isLoading: false });
    });

    const adminUser = {
        user: { id: 1, name: 'Admin', email: 'admin@test.com', is_active: true, roles: ['Super Admin'], permissions: ['bss.customers.view', 'bss.customers.update'] },
        authState: 'authenticated' as const,
    };
    const viewerUser = {
        user: { id: 2, name: 'Viewer', email: 'viewer@test.com', is_active: true, roles: [], permissions: ['bss.customers.view'] },
        authState: 'authenticated' as const,
    };

    const loadPage = async () => {
        mockApi.customer.mockResolvedValue(mockCustomer);
        mockApi.customer360.mockResolvedValue(mock360);
        useAuthStore.setState(adminUser);
        render(<CustomerDetailPage />, { wrapper });
        await waitFor(() => expect(mockApi.customer).toHaveBeenCalled());
        await screen.findByText(/C-42/);
    };

    const loadAsViewer = async () => {
        mockApi.customer.mockResolvedValue(mockCustomer);
        mockApi.customer360.mockResolvedValue(mock360);
        useAuthStore.setState(viewerUser);
        render(<CustomerDetailPage />, { wrapper });
        await waitFor(() => expect(mockApi.customer).toHaveBeenCalled());
        await screen.findByText(/C-42/);
    };

    it('renders customer 360 header with code and company', async () => {
        await loadPage();
        expect(screen.getByText(/Acme Corp/)).toBeInTheDocument();
        expect(screen.getByText(/C-42/)).toBeInTheDocument();
        expect(screen.getByText(/Company #1/)).toBeInTheDocument();
    });

    it('displays all six Customer 360 tabs', async () => {
        await loadPage();
        for (const label of ['Contacts', 'Contact persons', 'Addresses', 'Business profile', 'Verification', 'Notes']) {
            expect(screen.getByRole('tab', { name: label })).toBeInTheDocument();
        }
    });

    it('shows contacts tab by default with primary and secondary contacts', async () => {
        await loadPage();
        expect(screen.getByText(/kind: email · value: primary@acme\.test · verified: true/)).toBeInTheDocument();
        expect(screen.getByText(/kind: phone · value: \+977999 · verified: false/)).toBeInTheDocument();
    });

    it('navigates to contact persons tab and displays role information', async () => {
        await loadPage();
        fireEvent.click(screen.getByRole('tab', { name: 'Contact persons' }));
        await waitFor(() => expect(screen.getByText(/name: Alice · role: CTO/)).toBeInTheDocument());
    });

    it('navigates to addresses tab and displays city and country', async () => {
        await loadPage();
        fireEvent.click(screen.getByRole('tab', { name: 'Addresses' }));
        await waitFor(() => expect(screen.getByText(/123 Street/)).toBeInTheDocument());
    });

    it('navigates to business profile tab and displays legal name', async () => {
        await loadPage();
        fireEvent.click(screen.getByRole('tab', { name: 'Business profile' }));
        await waitFor(() => expect(screen.getByText(/legal name: Acme Pvt Ltd/)).toBeInTheDocument());
        expect(screen.getByText(/registration number: REG-123/)).toBeInTheDocument();
        expect(screen.getByText(/tax number: TAX-456/)).toBeInTheDocument();
    });

    it('navigates to verification tab and displays KYC reference', async () => {
        await loadPage();
        fireEvent.click(screen.getByRole('tab', { name: 'Verification' }));
        await waitFor(() => expect(screen.getByText(/reference: KYC-REF-1/)).toBeInTheDocument());
    });

    it('navigates to notes tab and displays note body', async () => {
        await loadPage();
        fireEvent.click(screen.getByRole('tab', { name: 'Notes' }));
        await waitFor(() => expect(screen.getByText(/Important customer note/)).toBeInTheDocument());
    });

    it('shows empty state when a child list has no records', async () => {
        mockApi.customer.mockResolvedValue(mockCustomer);
        mockApi.customer360.mockResolvedValue({ ...mock360, contacts: [], contact_persons: [], addresses: [], business_profile: null, verifications: [], notes: [] });
        useAuthStore.setState(adminUser);
        render(<CustomerDetailPage />, { wrapper });
        await waitFor(() => expect(mockApi.customer).toHaveBeenCalled());
        await screen.findByText('No records recorded.');
    });

    it('hides add/update button for viewers without bss.customers.update', async () => {
        await loadAsViewer();
        expect(screen.queryByRole('button', { name: 'Add / update' })).not.toBeInTheDocument();
    });

    it('shows add/update button for users with bss.customers.update', async () => {
        await loadPage();
        expect(screen.getByRole('button', { name: 'Add / update' })).toBeInTheDocument();
    });
});
