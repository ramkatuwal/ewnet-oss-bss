import { describe, expect, it, vi, beforeEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AssetDetailPage from './AssetDetailPage';
import { useAuthStore, AuthUser } from '@/stores/authStore';

const { getAsset, deleteAsset } = vi.hoisted(() => ({ getAsset: vi.fn(), deleteAsset: vi.fn() }));
vi.mock('@/features/assets/api/assets', () => ({ getAsset, deleteAsset }));
vi.mock('@/components/layout/PageHeader', () => ({
    PageHeader: ({ title, actions }: { title: string; actions: React.ReactNode }) => (
        <div>
            <h1>{title}</h1>
            <div data-testid="header-actions">{actions}</div>
        </div>
    ),
}));
vi.mock('@/components/feedback/ConfirmDialog', () => ({ ConfirmDialog: () => null }));
vi.mock('@/features/shared/components/PhotoGallery', () => ({ PhotoGallery: () => null }));
vi.mock('@/features/assets/components/AssetLifecycleTimeline', () => ({ AssetLifecycleTimeline: () => null }));
vi.mock('@/features/assets/components/AssetTransferDialog', () => ({ AssetTransferDialog: () => null }));
vi.mock('@/features/assets/components/AssetStatusChangeDialog', () => ({ AssetStatusChangeDialog: () => null }));
vi.mock('@/features/assets/components/AssetFormDrawer', () => ({ default: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetNetworkPortsTab', () => ({ AssetNetworkPortsTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetInterfacesTab', () => ({ AssetInterfacesTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetIpAddressesTab', () => ({ AssetIpAddressesTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetFimTab', () => ({ AssetFimTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetPonTab', () => ({ AssetPonTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetVlanTab', () => ({ AssetVlanTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetRoutingTab', () => ({ AssetRoutingTab: () => null }));
vi.mock('@/features/assets/components/assetTabs/AssetAuditTab', () => ({ AssetAuditTab: () => null }));

const superAdmin: AuthUser = {
    id: 1,
    name: 'Admin',
    email: 'admin@ewnet.test',
    is_active: true,
    roles: ['Super Admin'],
    permissions: [],
};

const viewer: AuthUser = {
    id: 9,
    name: 'Viewer',
    email: 'viewer@ewnet.test',
    is_active: true,
    roles: ['Viewer'],
    permissions: ['assets.view'],
};

const asset = {
    id: 8,
    site_id: 9,
    asset_tag: 'AST-000008',
    serial_number: 'SN-8',
    category: 'NETWORK',
    type: 'OLT',
    manufacturer: 'Huawei',
    model: 'MA5608T',
    quantity: 1,
    unit: 'pcs',
    status: 'OPERATIONAL',
    condition: 'GOOD',
    site: {
        id: 9,
        site_code: 'S-9',
        name: 'Site Nine',
        company_id: 1,
        region_id: 2,
        branch_id: 3,
        company: { id: 1, name: 'Co1' },
        region: { id: 2, name: 'Region East' },
        branch: { id: 3, name: 'Branch North' },
    },
    network_ports: [],
    interfaces: [],
    ip_addresses: [],
    routing_instances: [],
    passive_optical_ports: [],
    splitter_profile: null,
    pon_memberships: [],
    created_by: null,
    updated_by: null,
};

const renderPage = (user: AuthUser) => {
    useAuthStore.setState({ user, authState: 'authenticated' });
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    return render(
        <MemoryRouter initialEntries={['/network/assets/8']}>
            <QueryClientProvider client={client}>
                <Routes>
                    <Route path="/network/assets/:id" element={<AssetDetailPage />} />
                </Routes>
            </QueryClientProvider>
        </MemoryRouter>,
    );
};

beforeEach(() => {
    vi.clearAllMocks();
    getAsset.mockResolvedValue(asset);
});

describe('AssetDetailPage permission-aware actions', () => {
    it('shows Transfer, Retire, Dispose, Edit, and Delete for a super admin', async () => {
        renderPage(superAdmin);
        expect(await screen.findAllByText('AST-000008')).toHaveLength(2);
        const actions = within(screen.getByTestId('header-actions'));
        expect(actions.getByRole('button', { name: 'Transfer' })).toBeEnabled();
        expect(actions.getByRole('button', { name: 'Retire' })).toBeEnabled();
        expect(actions.getByRole('button', { name: 'Dispose' })).toBeDisabled();
        expect(actions.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
        expect(actions.getAllByRole('button')).toHaveLength(5);
    });

    it('hides all asset mutations for a viewer with only assets.view', async () => {
        renderPage(viewer);
        expect(await screen.findAllByText('AST-000008')).toHaveLength(2);
        const actions = within(screen.getByTestId('header-actions'));
        expect(actions.queryByRole('button', { name: 'Transfer' })).not.toBeInTheDocument();
        expect(actions.queryByRole('button', { name: 'Retire' })).not.toBeInTheDocument();
        expect(actions.queryByRole('button', { name: 'Dispose' })).not.toBeInTheDocument();
        expect(actions.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
        expect(actions.queryAllByRole('button')).toHaveLength(0);
    });

    it('renders the authoritative asset header with status and type context', async () => {
        renderPage(viewer);
        expect(await screen.findAllByText('AST-000008')).toHaveLength(2);
        expect(screen.getByText('OPERATIONAL')).toBeInTheDocument();
        expect(screen.getAllByText('NETWORK').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('Authoritative')).toBeInTheDocument();
        expect(screen.getByText('0 Ports')).toBeInTheDocument();
        expect(screen.getByText('Site Nine')).toBeInTheDocument();
    });
});