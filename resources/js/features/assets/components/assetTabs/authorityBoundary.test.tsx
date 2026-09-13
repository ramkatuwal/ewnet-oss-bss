import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { AssetInterface, IpAddress, NetworkPort } from '@/types';
import { AssetInterfacesTab } from './AssetInterfacesTab';
import { AssetIpAddressesTab } from './AssetIpAddressesTab';
import { AssetNetworkPortsTab } from './AssetNetworkPortsTab';

vi.mock('@/features/assets/api/assets', () => ({
    getAssetInterfaces: vi.fn().mockResolvedValue({ data: [], total: 0, per_page: 10, current_page: 1 }),
}));

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

const networkPort: NetworkPort = {
    id: 1,
    asset_id: 8,
    company_id: 1,
    port_key: '0/1',
    name: 'GE0/1',
    slot: null,
    card: null,
    port_number: 1,
    connector_type: 'SC',
    port_direction: 'downlink',
    technology: 'GPON',
    metadata: null,
};

const assetInterface: AssetInterface = {
    id: 1,
    asset_id: 8,
    name: 'eth0',
    display_name: 'WAN',
    description: null,
    type: 'ethernet',
    mac_address: 'aa:bb:cc:dd:ee:ff',
    speed: 1000000,
    status: 'up',
    is_management: true,
    provider: 'librenms',
    external_type: 'interface',
    external_id: 'librenms:1:2',
    metadata: null,
    first_seen_at: '2026-01-01T00:00:00Z',
    last_seen_at: '2026-01-02T00:00:00Z',
};

const ipAddress: IpAddress = {
    id: 1,
    asset_interface_id: 1,
    ip_address: '10.0.0.1',
    family: 'IPv4',
    prefix_length: 24,
    is_primary: true,
    is_management: false,
    provider: 'uisp',
    external_type: 'ip',
    external_id: 'uisp:1',
    first_seen_at: '2026-01-01T00:00:00Z',
    last_seen_at: '2026-01-02T00:00:00Z',
};

describe('Asset authority boundary tabs', () => {
    it('renders authoritative physical network ports with the Authoritative badge', () => {
        render(<AssetNetworkPortsTab ports={[networkPort]} />);
        expect(screen.getByText(/Authoritative physical port records/)).toBeInTheDocument();
        expect(screen.getByText('Authoritative')).toBeInTheDocument();
        const table = screen.getByRole('table');
        expect(within(table).getByText('0/1')).toBeInTheDocument();
        expect(within(table).getByText('GE0/1')).toBeInTheDocument();
        expect(within(table).getByText('GPON')).toBeInTheDocument();
        expect(screen.queryByText('Observed')).not.toBeInTheDocument();
    });

    it('renders observed interfaces as read-only with the Observed badge', async () => {
        const { getAssetInterfaces } = await import('@/features/assets/api/assets');
        (getAssetInterfaces as ReturnType<typeof vi.fn>).mockResolvedValue({
            data: [assetInterface], total: 1, per_page: 10, current_page: 1,
        });
        render(
            <QueryClientProvider client={queryClient}>
                <AssetInterfacesTab assetId={8} />
            </QueryClientProvider>,
        );
        expect(screen.getByText(/Observed interfaces/)).toBeInTheDocument();
        expect(screen.getByText('Observed')).toBeInTheDocument();
        const cell = await screen.findByText('eth0');
        const table = cell.closest('table');
        expect(table).not.toBeNull();
        expect(table && within(table).getByText('aa:bb:cc:dd:ee:ff')).toBeInTheDocument();
        expect(table && within(table).getByText('librenms')).toBeInTheDocument();
        expect(screen.queryByText('Authoritative')).not.toBeInTheDocument();
    });

    it('renders observed IP addresses as read-only with the Observed badge', () => {
        render(<AssetIpAddressesTab addresses={[ipAddress]} />);
        expect(screen.getByText(/Observed IP addresses/)).toBeInTheDocument();
        expect(screen.getByText('Observed')).toBeInTheDocument();
        const table = screen.getByRole('table');
        expect(within(table).getByText('10.0.0.1/24')).toBeInTheDocument();
        expect(within(table).getByText('IPv4')).toBeInTheDocument();
        expect(within(table).getByText('uisp')).toBeInTheDocument();
    });
});