import { describe, expect, it, vi } from 'vitest';
import { useEffect } from 'react';
import { render, waitFor } from '@testing-library/react';
import { useForm } from 'react-hook-form';

const { list, loadedOptions } = vi.hoisted(() => ({ list: vi.fn(), loadedOptions: vi.fn() }));
vi.mock('@/api/sites', () => ({ sitesApi: { list } }));

vi.mock('@/components/forms/SearchableSelect', () => ({
    default: ({ loadOptions, disabled, label, placeholder, helperText }: {
        loadOptions?: (search: string) => Promise<unknown>;
        disabled?: boolean;
        label?: string;
        placeholder?: string;
        helperText?: string;
    }) => {
        useEffect(() => {
            loadOptions?.('S-9').then(loadedOptions);
            // stub: exercise the component's loadOptions once
        // eslint-disable-next-line react-hooks/exhaustive-deps
        }, []);
        return (
            <div>
                <span data-testid="site-label">{label}</span>
                <span data-testid="site-placeholder">{placeholder}</span>
                <span data-testid="site-disabled">{disabled ? 'disabled' : 'enabled'}</span>
                <span data-testid="site-helper">{helperText ?? ''}</span>
            </div>
        );
    },
}));

import { AsyncSitePicker } from './AsyncSitePicker';

interface PickerProps {
    companyId?: number;
    regionId?: number;
    branchId?: number;
    error?: string;
}

const renderPicker = (props: PickerProps) => {
    const Wrapper = () => {
        const methods = useForm();
        return <AsyncSitePicker control={methods.control} {...props} />;
    };
    return render(<Wrapper />);
};

describe('AsyncSitePicker', () => {
    it('queries sites scoped to the selected organization', async () => {
        list.mockResolvedValue({
            data: [{
                id: 9,
                site_code: 'S-9',
                name: 'Site Nine',
                company_id: 1,
                region_id: 2,
                branch_id: 3,
                company: { id: 1, name: 'Co1' },
                region: { id: 2, name: 'Region East' },
                branch: { id: 3, name: 'Branch North' },
            }],
            current_page: 1,
            last_page: 1,
            per_page: 50,
            total: 1,
            from: 1,
            to: 1,
        });

        renderPicker({ companyId: 1, regionId: 2, branchId: 3 });

        await waitFor(() => expect(list).toHaveBeenCalledTimes(1));
        expect(list).toHaveBeenCalledWith({
            search: 'S-9',
            per_page: 50,
            company_id: 1,
            region_id: 2,
            branch_id: 3,
        });
        await waitFor(() => expect(loadedOptions).toHaveBeenCalledWith([
            expect.objectContaining({ label: 'Site Nine', value: 9 }),
        ]));
    });

    it('passes the required error from the form to the select helper', () => {
        renderPicker({ error: 'A site is required.' });
        expect(document.querySelector('[data-testid="site-helper"]')?.textContent).toContain('A site is required.');
    });
});
