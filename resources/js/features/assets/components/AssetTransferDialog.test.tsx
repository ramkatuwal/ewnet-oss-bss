import { describe, expect, it, vi } from 'vitest';
import { useEffect } from 'react';
import { render, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AssetTransferDialog } from './AssetTransferDialog';

const { list, options } = vi.hoisted(() => ({ list: vi.fn(), options: vi.fn() }));
vi.mock('@/api/sites', () => ({ sitesApi: { list } }));
vi.mock('@/components/forms/SearchableSelect', () => ({
    default: ({ loadOptions }: { loadOptions: (search: string) => Promise<unknown> }) => {
        useEffect(() => { void loadOptions('SITE-10').then(options); }, []);
        return null;
    },
}));

describe('asset transfer site selection', () => {
    it('searches code but labels names only and excludes the current site', async () => {
        list.mockResolvedValue({ data: [
            { id: 9, site_code: 'SITE-9', name: 'Current Site' },
            { id: 10, site_code: 'SITE-10', name: 'Destination' },
        ] });
        render(<QueryClientProvider client={new QueryClient()}><AssetTransferDialog open onClose={vi.fn()} assetId={8}
            currentSiteId={9} currentSiteName="Current Site" onSuccess={vi.fn()} /></QueryClientProvider>);
        await waitFor(() => expect(options).toHaveBeenCalledWith([
            expect.objectContaining({ value: 10, label: 'Destination' }),
        ]));
        expect(list).toHaveBeenCalledWith({ search: 'SITE-10', per_page: 50 });
    });
});
