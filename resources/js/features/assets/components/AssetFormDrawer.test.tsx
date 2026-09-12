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
const { companiesGetAll, regionsGetAll, branchesGetAll } = vi.hoisted(() => ({
    companiesGetAll: vi.fn(),
    regionsGetAll: vi.fn(),
    branchesGetAll: vi.fn(),
}));
const { successToast, errorToast } = vi.hoisted(() => ({ successToast: vi.fn(), errorToast: vi.fn() }));

vi.mock('@/features/assets/api/assets', () => ({ getAsset, createAsset, updateAsset }));
vi.mock('@/api/sites', () => ({ sitesApi: { get: sitesGet } }));
vi.mock('@/api/companies', () => ({ companiesApi: { getAll: companiesGetAll } }));
vi.mock('@/api/regions', () => ({ regionsApi: { getAll: regionsGetAll } }));
vi.mock('@/api/branches', () => ({ branchesApi: { getAll: branchesGetAll } }));
vi.mock('react-hot-toast', () => ({ default: { success: successToast, error: errorToast } }));

vi.mock('@/components/infrastructure/AsyncSitePicker', () => ({
    AsyncSitePicker: ({ control, error, disabled, companyId, regionId, branchId, selectedSite }: {
        control: any;
        error?: string;
        disabled?: boolean;
        companyId?: number;
        regionId?: number;
        branchId?: number;
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
                <span data-testid="site-scope">{`${companyId ?? ''}/${regionId ?? ''}/${branchId ?? ''}`}</span>
                <span data-testid="site-disabled">{disabled ? 'disabled' : 'enabled'}</span>
                <span data-testid="site-selected">{selectedSite?.name ?? ''}</span>
            </div>
        );
    },
}));

const wrap = () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const onClose = vi.fn();
    const utils = render(
        <QueryClientProvider client={client}>
            <AssetFormDrawer open onClose={onClose} assetId={null} />
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

const scopedOperator: AuthUser = {
    id: 5,
    name: 'Scoped Op',
    email: 'op@ewnet.test',
    is_active: true,
    company_id: 7,
    company: { id: 7, name: 'Company Seven' },
    roles: ['Operator'],
    permissions: ['assets.create'],
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
    });
    companiesGetAll.mockResolvedValue({ data: [], current_page: 1, last_page: 1, per_page: 100, total: 0, from: 0, to: 0 });
    regionsGetAll.mockResolvedValue({ data: [], current_page: 1, last_page: 1, per_page: 500, total: 0, from: 0, to: 0 });
    branchesGetAll.mockResolvedValue({ data: [], current_page: 1, last_page: 1, per_page: 500, total: 0, from: 0, to: 0 });
});

const enterType = (value: string) => {
    fireEvent.change(screen.getByLabelText('Type *'), { target: { value } });
};

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
        expect(await screen.findByText('Type is required.')).toBeInTheDocument();
        expect(createAsset).not.toHaveBeenCalled();
    });

    it('defaults and locks the scoped company for a non-super-admin user', () => {
        useAuthStore.setState({ user: scopedOperator, authState: 'authenticated' });
        wrap();
        expect(screen.getByText('Company Seven')).toBeInTheDocument();
        expect(screen.queryByText('All Companies')).not.toBeInTheDocument();
        expect(companiesGetAll).not.toHaveBeenCalled();
        expect(screen.getByTestId('site-scope').textContent).toMatch(/^7\//);
    });

    it('locks the cascade and preselected site when opened from a site, then submits create', async () => {
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
        expect(screen.getByTestId('site-scope')).toHaveTextContent('1/2/3');
        expect(companiesGetAll).not.toHaveBeenCalled();
        expect(regionsGetAll).not.toHaveBeenCalled();
        expect(branchesGetAll).not.toHaveBeenCalled();

        enterType('OLT');
        fireEvent.click(screen.getByRole('button', { name: 'Save' }));

        await waitFor(() => expect(createAsset).toHaveBeenCalledTimes(1));
        expect(createAsset).toHaveBeenCalledWith(expect.objectContaining({
            site_id: 9,
            category: 'POWER',
            type: 'OLT',
            quantity: 1,
            unit: 'pcs',
            status: 'OPERATIONAL',
        }));
        await waitFor(() => expect(onClose).toHaveBeenCalled());
    });

    it('prefills edit mode from the asset, keeps the immutable asset code, and submits update', async () => {
        useAuthStore.setState({ user: superAdmin, authState: 'authenticated' });
        getAsset.mockResolvedValue({
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
        });
        regionsGetAll.mockResolvedValue({ data: [{ id: 2, name: 'Region East', company_id: 1, code: 'RE', country: 'NP', is_active: true }], current_page: 1, last_page: 1, per_page: 500, total: 1, from: 1, to: 1 });
        branchesGetAll.mockResolvedValue({ data: [{ id: 3, name: 'Branch North', region_id: 2, code: 'BN', country: 'NP', is_active: true }], current_page: 1, last_page: 1, per_page: 500, total: 1, from: 1, to: 1 });
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
        expect(screen.getByLabelText('Type *')).toHaveValue('OLT');
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