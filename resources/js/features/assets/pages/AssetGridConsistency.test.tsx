import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import type { DataGridProps, GridRenderCellParams } from '@mui/x-data-grid';
import AssetsPage from './AssetsPage';
import { SiteAssetsTab } from '@/features/sites/components/SiteAssetsTab';

const mocks = vi.hoisted(() => ({ getAssets: vi.fn(), getSiteAssets: vi.fn() }));
vi.mock('../api/assets', () => ({ ...mocks, getAssetDashboard: vi.fn().mockResolvedValue(null), deleteAsset: vi.fn() }));
vi.mock('../components/AssetFormDrawer', () => ({ default: () => null }));
vi.mock('@/features/sites/components/SiteLibreNMSImportDialog', () => ({ SiteLibreNMSImportDialog: () => null }));
vi.mock('@/components/auth/Can', () => ({ Can: () => null }));
vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn().mockResolvedValue({ data: { data: [] } }) } }));
vi.mock('@/features/settings/api/assetModelSettings', () => ({ assetModelSettingsApi: {
    getCategories: vi.fn().mockResolvedValue({ data: { data: [{ id: 9, code: 'CUSTOM', name: 'Custom Category', is_active: true }] } }),
} }));
// Inspect the grid contract without depending on jsdom viewport virtualization.
vi.mock('@mui/x-data-grid', () => ({
    GridToolbarColumnsButton: () => null,
    DataGrid: (props: DataGridProps) => <div>
        <span>Density: {props.density}</span><span>Total: {props.rowCount}</span>
        <button onClick={() => props.onPaginationModelChange?.({ page: 1, pageSize: 10 }, {})}>Next page</button>
        {props.columns.filter(c => props.columnVisibilityModel?.[c.field] !== false).map(column => <section key={column.field}>
            <h2>{column.headerName}</h2>
            {props.rows.map(row => <div key={row.id}>{column.renderCell
                ? column.renderCell({ row, value: row[column.field] } as GridRenderCellParams)
                : String(row[column.field] ?? '')}</div>)}
        </section>)}
    </div>,
}));

const row = { id: 8, asset_tag: 'AST-8', device_name: 'Core Router', management_ip: '192.0.2.8',
    model: 'CCR2004', mac_address: '00:11:22:33:44:55', status: 'OPERATIONAL', category: 'CUSTOM',
    site: { id: 9, name: 'Site Nine', site_code: 'HIDDEN-CODE' } };
const wrap = (site: boolean) => render(<MemoryRouter><QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
    {site ? <SiteAssetsTab siteId={9} /> : <AssetsPage />}
</QueryClientProvider></MemoryRouter>);

beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mocks.getAssets.mockResolvedValue({ data: [row], total: 31 });
    mocks.getSiteAssets.mockResolvedValue({ data: [row], total: 31 });
});

describe('asset grid and site consistency', () => {
    it.each([false, true])('renders required compact columns and flattened totals (site=%s)', async site => {
        wrap(site);
        expect(await screen.findByText('Core Router')).toBeInTheDocument();
        for (const label of ['Asset Code', 'Device Name', 'Management IP', 'Model', 'Asset Status', 'MAC / Identifier']) {
            expect(screen.getByRole('heading', { name: label })).toBeInTheDocument();
        }
        for (const value of ['AST-8', '192.0.2.8', 'CCR2004', '00:11:22:33:44:55', 'OPERATIONAL', 'Density: compact', 'Total: 31']) {
            expect(screen.getByText(value)).toBeInTheDocument();
        }
        expect(screen.queryByText('HIDDEN-CODE')).not.toBeInTheDocument();
        if (!site) expect(screen.getByText('Site Nine')).toBeInTheDocument();
        fireEvent.mouseDown(screen.getByRole('combobox', { name: 'Category' }));
        fireEvent.click(await screen.findByRole('option', { name: 'Custom Category' }));
        await waitFor(() => expect(site ? mocks.getSiteAssets : mocks.getAssets).toHaveBeenLastCalledWith(
            ...(site ? [9, expect.objectContaining({ category: 'CUSTOM', page: 1 })] : [expect.objectContaining({ category: 'CUSTOM', page: 1 })]),
        ));
    });

    it('resets site pagination after filtering and uses bounded server requests', async () => {
        wrap(true);
        await screen.findByText('Core Router');
        fireEvent.click(screen.getByRole('button', { name: 'Next page' }));
        await waitFor(() => expect(mocks.getSiteAssets).toHaveBeenLastCalledWith(9, expect.objectContaining({ page: 2, per_page: 10 })));
        fireEvent.change(screen.getByPlaceholderText('Search assets...'), { target: { value: 'core' } });
        await waitFor(() => expect(mocks.getSiteAssets).toHaveBeenLastCalledWith(9, expect.objectContaining({ page: 1, search: 'core' })));
    });

    it('shows a retryable site error instead of an empty grid', async () => {
        mocks.getSiteAssets.mockRejectedValue(new Error('Unavailable'));
        wrap(true);
        expect(await screen.findByText('Unable to load site assets.')).toBeInTheDocument();
        expect(screen.queryByText('Total: 0')).not.toBeInTheDocument();
    });
});
