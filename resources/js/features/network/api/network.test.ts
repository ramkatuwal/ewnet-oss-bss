import { beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), del: vi.fn() }));

vi.mock('@/api/client', () => ({ apiClient: { get: mocks.get, post: mocks.post, put: mocks.put, delete: mocks.del } }));

import { networkApi } from './network';

describe('networkApi', () => {
    beforeEach(() => {
        mocks.get.mockReset(); mocks.post.mockReset(); mocks.put.mockReset(); mocks.del.mockReset();
        mocks.get.mockResolvedValue({ data: { data: [] } });
        mocks.post.mockResolvedValue({ data: { data: { id: 1 } } });
        mocks.put.mockResolvedValue({ data: { data: { id: 1 } } });
        mocks.del.mockResolvedValue({ data: { message: 'retired' } });
    });

    it('uses the asset-scoped port and routing authorities', async () => {
        await networkApi.ports(12);
        await networkApi.routingInstances(12);
        await networkApi.createRoutingInstance(12, { name: 'core', kind: 'default' });

        expect(mocks.get).toHaveBeenNthCalledWith(1, '/api/v1/assets/12/network-ports', { params: { per_page: 100 } });
        expect(mocks.get).toHaveBeenNthCalledWith(2, '/api/v1/assets/12/routing-instances');
        expect(mocks.post).toHaveBeenCalledWith('/api/v1/assets/12/routing-instances', { name: 'core', kind: 'default' });
    });

    it('uses PUT to replace the complete port switching configuration', async () => {
        const body = { mode: 'trunk' as const, memberships: [] };
        await networkApi.replaceSwitching(41, body);

        expect(mocks.put).toHaveBeenCalledWith('/api/v1/network-ports/41/switching-configuration', body);
    });

    it('keeps PON, address, and static route operations under their parent authority', async () => {
        await networkApi.createPonMembership(9, { onu_asset_id: 22, onu_id: 'ONU-22' });
        await networkApi.createAddress(7, { address: '192.0.2.1/24', address_role: 'primary' });
        await networkApi.createStaticRoute(3, { destination: '198.51.100.0/24', route_type: 'discard' });

        expect(mocks.post).toHaveBeenNthCalledWith(1, '/api/v1/pon-domains/9/memberships', { onu_asset_id: 22, onu_id: 'ONU-22' });
        expect(mocks.post).toHaveBeenNthCalledWith(2, '/api/v1/routing-l3-interfaces/7/addresses', { address: '192.0.2.1/24', address_role: 'primary' });
        expect(mocks.post).toHaveBeenNthCalledWith(3, '/api/v1/routing-instances/3/static-routes', { destination: '198.51.100.0/24', route_type: 'discard' });
    });
});
