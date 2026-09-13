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
vi.mock('@/features/assets/components/NetworkPortPreview', () => ({ NetworkPortPreview: () => null }));
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
    it('keeps authoritative identity separate from extended provider observations', async () => {
        getAsset.mockResolvedValue({ ...asset, device_name: 'Canonical Router', management_ip: '192.0.2.1',
            specifications: { observed_display: 'Do not read raw specs' },
            provider_observations: {
                provider: 'librenms', external_id: '42', observed_display: 'NMS Display',
                observed_sys_name: 'sys-core', observed_hostname: 'router.example', ip_address: '192.0.2.2',
                observed_os: 'RouterOS', observed_hardware: 'CCR-observed', observed_version: '7.20',
                provider_type: 'network', serial_number: 'OBS-SERIAL', provider_status: 'UP',
                integration_id: 6, last_poll: '2026-09-13T01:00:00Z',
            },
        });
        renderPage(viewer);
        expect(await screen.findByText('NMS Display')).toBeInTheDocument();
        for (const text of ['192.0.2.1', '192.0.2.2', 'sys-core', 'router.example', 'RouterOS', 'CCR-observed', '7.20', 'network', 'OBS-SERIAL', 'UP', '6', 'Site Nine', 'MA5608T']) {
            expect(screen.getByText(text)).toBeInTheDocument();
        }
        expect(screen.queryByText('S-9')).not.toBeInTheDocument();
        expect(screen.queryByText('Do not read raw specs')).not.toBeInTheDocument();
        expect(screen.queryByText('Integration Name')).not.toBeInTheDocument();
        expect(screen.getByText(new Date('2026-09-13T01:00:00Z').toLocaleString())).toBeInTheDocument();
    });
    it('shows Transfer, Retire, Dispose, Edit, and Delete for a super admin', async () => {
        renderPage(superAdmin);
        expect(await screen.findAllByText('AST-000008')).toHaveLength(3);
        const actions = within(screen.getByTestId('header-actions'));
        expect(actions.getByRole('button', { name: 'Transfer' })).toBeEnabled();
        expect(actions.getByRole('button', { name: 'Retire' })).toBeEnabled();
        expect(actions.getByRole('button', { name: 'Dispose' })).toBeDisabled();
        expect(actions.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
        expect(actions.getAllByRole('button')).toHaveLength(5);
    });

    it('hides all asset mutations for a viewer with only assets.view', async () => {
        renderPage(viewer);
        expect(await screen.findAllByText('AST-000008')).toHaveLength(3);
        const actions = within(screen.getByTestId('header-actions'));
        expect(actions.queryByRole('button', { name: 'Transfer' })).not.toBeInTheDocument();
        expect(actions.queryByRole('button', { name: 'Retire' })).not.toBeInTheDocument();
        expect(actions.queryByRole('button', { name: 'Dispose' })).not.toBeInTheDocument();
        expect(actions.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
        expect(actions.queryAllByRole('button')).toHaveLength(0);
    });

    it('renders the authoritative asset header with status and type context', async () => {
        renderPage(viewer);
        expect(await screen.findAllByText('AST-000008')).toHaveLength(3);
        expect(screen.getByText('OPERATIONAL')).toBeInTheDocument();
        expect(screen.getAllByText('NETWORK').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Authoritative').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('Site Nine')).toBeInTheDocument();
    });
});
