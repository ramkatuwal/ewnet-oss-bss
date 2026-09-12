import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { Branch, Company, Region } from '@/types';
import { OrgCascadePicker, OrgSelection } from './OrgCascadePicker';

const { companiesGetAll, regionsGetAll, branchesGetAll } = vi.hoisted(() => ({
    companiesGetAll: vi.fn(),
    regionsGetAll: vi.fn(),
    branchesGetAll: vi.fn(),
}));

vi.mock('@/api/companies', () => ({ companiesApi: { getAll: companiesGetAll } }));
vi.mock('@/api/regions', () => ({ regionsApi: { getAll: regionsGetAll } }));
vi.mock('@/api/branches', () => ({ branchesApi: { getAll: branchesGetAll } }));

const paginate = <T,>(data: T[]) => ({
    data,
    current_page: 1,
    last_page: 1,
    per_page: 500,
    total: data.length,
    from: 1,
    to: data.length,
});

const asCompany = (id: number, name: string) => ({ id, name } as Company);
const asRegion = (id: number, name: string, company_id: number) => ({ id, company_id, name } as Region);
const asBranch = (id: number, name: string, region_id: number) => ({ id, region_id, name } as Branch);

const renderPicker = (value: OrgSelection, props: Partial<Parameters<typeof OrgCascadePicker>[0]> = {}) => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
        <QueryClientProvider client={client}>
            <OrgCascadePicker value={value} onChange={vi.fn()} {...props} />
        </QueryClientProvider>,
    );
};

describe('OrgCascadePicker', () => {
    it('locks company to the scoped company label and hides the company picker for scoped users', () => {
        renderPicker({}, { showCompanyPicker: false, companyLocked: true, companyLockedLabel: 'Acme Networks Ltd' });
        expect(screen.getByText('Acme Networks Ltd')).toBeInTheDocument();
        expect(screen.queryByText('All Companies')).not.toBeInTheDocument();
        expect(companiesGetAll).not.toHaveBeenCalled();
    });

    it('disables region and branch until a company is selected', () => {
        renderPicker({});
        expect(screen.getByRole('combobox', { name: 'Region' })).toHaveAttribute('aria-disabled', 'true');
        expect(screen.getByRole('combobox', { name: 'Branch' })).toHaveAttribute('aria-disabled', 'true');
    });

    it('loads regions for the selected company and branches filtered by the selected region', async () => {
        companiesGetAll.mockResolvedValue(paginate([asCompany(1, 'Co1')]));
        regionsGetAll.mockResolvedValue(paginate([asRegion(2, 'Region East', 1), asRegion(3, 'Region West', 1)]));
        branchesGetAll.mockResolvedValue(paginate([asBranch(5, 'Branch North', 2)]));

        renderPicker({ company_id: 1, region_id: 2 });

        await vi.waitFor(() => expect(regionsGetAll).toHaveBeenCalledWith({ company_id: 1, per_page: 500 }));
        await vi.waitFor(() => expect(branchesGetAll).toHaveBeenCalledWith({ region_id: 2, per_page: 500 }));
        expect(screen.getByRole('combobox', { name: 'Region' })).toBeEnabled();
        expect(screen.getByRole('combobox', { name: 'Branch' })).toBeEnabled();
    });

    it('clears region and branch when the selected region is not available under the company', async () => {
        const onChange = vi.fn();
        regionsGetAll.mockResolvedValue(paginate([asRegion(2, 'Region East', 1)]));
        branchesGetAll.mockResolvedValue(paginate([asBranch(5, 'Branch North', 2)]));

        renderPicker({ company_id: 1, region_id: 99, branch_id: 7 }, { onChange });

        await vi.waitFor(() => expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ company_id: 1, region_id: undefined, branch_id: undefined }),
        ));
    });

    it('clears the branch when the selected branch is not available under the selected region', async () => {
        const onChange = vi.fn();
        regionsGetAll.mockResolvedValue(paginate([asRegion(2, 'Region East', 1)]));
        branchesGetAll.mockResolvedValue(paginate([asBranch(5, 'Branch North', 2)]));

        renderPicker({ company_id: 1, region_id: 2, branch_id: 7 }, { onChange });

        await vi.waitFor(() => expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ company_id: 1, region_id: 2, branch_id: undefined }),
        ));
    });
});