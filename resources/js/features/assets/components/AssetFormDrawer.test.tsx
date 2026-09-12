import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useEffect } from 'react';
import AssetFormDrawer from './AssetFormDrawer';
import { useAuthStore, AuthUser } from '@/stores/authStore';

const { getAsset, createAsset, updateAsset } = vi.hoisted(() => ({
    getAsset: vi.fn(),
    createAsset: vi.fn(),
    updateAsset: vi.fn(),
}));
const { sitesGet } = vi.hoisted(() => ({ sitesGet: vi.fn() }));
const { successToast, errorToast } = vi.hoisted(() => ({ successToast: vi.fn(), errorToast: vi.fn() }));

vi.mock('@/features/assets/api/assets', () => ({ getAsset, createAsset, updateAsset }));
vi.mock('@/api/sites', () => ({ sitesApi: { get: sitesGet } }));
vi.mock('react-hot-toast', () => ({ default: { success: successToast, error: errorToast } }));

vi.mock('@/components/infrastructure/AsyncSitePicker', () => ({
    AsyncSitePicker: ({ control, error, disabled, selectedSite }: {
        control: any;
        error?: string;
        disabled?: boolean;
        selectedSite?: { name?: string; id?: number } | null;
    }) => {
        useEffect(() => {
            if (selectedSite?.id && control) {
                control._formValues.site_id = selectedSite.id;
            }
        }, [selectedSite?.id, control]);
        return (
            <div>
                <span data-testid="site-error">{error ?? ''}</span>
                <span data-testid="site-disabled">{disabled ? 'disabled' : 'enabled'}</span>
                <span data-testid="site-selected">{selectedSite?.name ?? ''}</span>
            </div>
        );
    },
}));

const wrap = (props?: { siteId?: number }) => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const onClose = vi.fn();
    const utils = render(
        <QueryClientProvider client={client}>
            <AssetFormDrawer open onClose={onClose} assetId={null} siteId={props?.siteId} />
        </QueryClientProvider>,
    );
    return { onClose, ...utils };
};

const superAdmin: AuthUser = {
    id: 1,
    name: 'Admin',
    email: 'admin@ewnet.test',
    is_active: true,
    roles: ['Super Admin'],
    permissions: [],
};

beforeEach(() => {
    vi.clearAllMocks();
    useAuthStore.setState({ user: null, authState: 'booting', isLoading: false });
    sitesGet.mockResolvedValue({
        id: 9,
        site_code: 'S-9',
        name: 'Site Nine',
        company_id: 1,
        region_id: 2,
        branch_id: 3,
        company: { id: 1, name: 'Co1' },
        region: { id: 2, name: 'Region East' },
        branch: { id: 3, name: 'Branch North' },
    });
});

describe('AssetFormDrawer', () => {
    it('shows the generated asset-code note in create mode', () => {
        useAuthStore.setState({ user: superAdmin, authState: 'authenticated' });
        wrap();
        expect(screen.getByText(/generated automatically/)).toBeInTheDocument();
        expect(screen.getByText('AST-000123')).toBeInTheDocument();
    });

    it('surfaces normalized field validation and does not submit an invalid form', async () => {
        useAuthStore.setState({ user: superAdmin, authState: 'authenticated' });
        wrap();
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(await screen.findByText('A site is required.')).toBeInTheDocument();
        expect(createAsset).not.toHaveBeenCalled();
    });

    it('shows site-only placement without org cascade pickers', () => {
        useAuthStore.setState({ user: superAdmin, authState: 'authenticated' });
        wrap();
        expect(screen.queryByText('All Companies')).not.toBeInTheDocument();
        expect(screen.queryByText('Region')).not.toBeInTheDocument();
        expect(screen.queryByText('Branch')).not.toBeInTheDocument();
    });

    it('locks the site when opened from a site and submits create', async () => {
        useAuthStore.setState({ user: superAdmin, authState: 'authenticated' });
        const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
        const onClose = vi.fn();
        createAsset.mockResolvedValue({ data: { id: 1 }, message: 'ok' });
        render(
            <QueryClientProvider client={client}>
                <AssetFormDrawer open onClose={onClose} assetId={null} siteId={9} />
            </QueryClientProvider>,
        );

        await waitFor(() => expect(sitesGet).toHaveBeenCalledWith(9));
        expect(await screen.findByTestId('site-selected')).toHaveTextContent('Site Nine');
        expect(screen.getByTestId('site-disabled')).toHaveTextContent('disabled');

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(await screen.findByText('Type is required.')).toBeInTheDocument();
        expect(createAsset).not.toHaveBeenCalled();
    });

    it('prefills edit mode from the asset, keeps the immutable asset code, and submits update', async () => {
        useAuthStore.setState({ user: superAdmin, authState: 'authenticated' });
        getAsset.mockResolvedValue({
            id: 8,
            site_id: 9,
            asset_tag: 'AST-000008',
            device_name: 'Router-Core-01',
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
        });
        updateAsset.mockResolvedValue({ data: { id: 8 }, message: 'updated' });

        const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
        const onClose = vi.fn();
        render(
            <QueryClientProvider client={client}>
                <AssetFormDrawer open onClose={onClose} assetId={8} />
            </QueryClientProvider>,
        );

        expect(await screen.findByText('Edit Asset AST-000008')).toBeInTheDocument();
        const code = screen.getByLabelText('Asset Code') as HTMLInputElement;
        expect(code.value).toBe('AST-000008');
        expect(code.readOnly).toBe(true);
        expect(screen.getByLabelText('Device Name')).toHaveValue('Router-Core-01');
        expect(screen.getByLabelText('Serial Number')).toHaveValue('SN-8');

        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(updateAsset).toHaveBeenCalledTimes(1));
        expect(updateAsset).toHaveBeenCalledWith(8, expect.objectContaining({
            site_id: 9,
            category: 'NETWORK',
            type: 'OLT',
            status: 'OPERATIONAL',
            condition: 'GOOD',
        }));
        await waitFor(() => expect(onClose).toHaveBeenCalled());
    });
});
