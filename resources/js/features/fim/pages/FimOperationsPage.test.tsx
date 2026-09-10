import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import FimOperationsPage from './FimOperationsPage';
import { useAuthStore } from '@/stores/authStore';

vi.mock('../api/fim', () => ({ fimApi: { cables: vi.fn().mockResolvedValue({ data: [{ id: 1, cable_code: 'FC-001', fiber_count: 24, status: 'active', company_id: 1, route_geometry_authority: 'cable' }] }), points: vi.fn().mockResolvedValue({ data: [] }), segments: vi.fn().mockResolvedValue({ data: [] }), cores: vi.fn().mockResolvedValue({ data: [] }), connections: vi.fn().mockResolvedValue({ data: [] }), passivePorts: vi.fn().mockResolvedValue({ data: [] }) } }));
describe('FimOperationsPage', () => { it('renders only authoritative FIM inventory fixtures', async () => { useAuthStore.setState({ user: { id: 1, name: 'FIM', email: 'fim@example.test', is_active: true, roles: [], permissions: ['fim.cables.view'] }, authState: 'authenticated' }); render(<QueryClientProvider client={new QueryClient()}><FimOperationsPage /></QueryClientProvider>); expect(await screen.findByText('FC-001')).toBeInTheDocument(); expect(screen.getByText(/does not infer edges/i)).toBeInTheDocument(); }); });
