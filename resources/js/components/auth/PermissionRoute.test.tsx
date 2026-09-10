import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { PermissionRoute } from './PermissionRoute';
import { useAuthStore } from '@/stores/authStore';

describe('PermissionRoute', () => {
    afterEach(() => {
        cleanup();
        useAuthStore.setState({ user: null, authState: 'anonymous', isLoading: false });
    });

    it('renders the permission-denied state when permission is absent', () => {
        render(
            <MemoryRouter initialEntries={['/protected']}>
                <Routes>
                    <Route element={<PermissionRoute permission="sites.view" />}>
                        <Route path="/protected" element={<div>Protected content</div>} />
                    </Route>
                </Routes>
            </MemoryRouter>,
        );

        expect(screen.getByText('You do not have permission to view this area.')).toBeInTheDocument();
        expect(screen.queryByText('Protected content')).not.toBeInTheDocument();
    });

    it('denies the Network Operations route without the exact net.network-ports.view permission', () => {
        useAuthStore.setState({
            user: { id: 2, name: 'Network Viewer', email: 'network-viewer@example.test', is_active: true, roles: [], permissions: ['network-ports.view'] },
            authState: 'authenticated',
        });

        render(<MemoryRouter initialEntries={['/network/operations']}><Routes><Route element={<PermissionRoute permission="net.network-ports.view" />}><Route path="/network/operations" element={<div>Network operations</div>} /></Route></Routes></MemoryRouter>);

        expect(screen.getByText('You do not have permission to view this area.')).toBeInTheDocument();
        expect(screen.queryByText('Network operations')).not.toBeInTheDocument();
    });

    it('renders the outlet when permission is granted', () => {
        useAuthStore.setState({
            user: {
                id: 1,
                name: 'Test Viewer',
                email: 'viewer@example.test',
                is_active: true,
                roles: [],
                permissions: ['sites.view'],
            },
            authState: 'authenticated',
        });

        render(
            <MemoryRouter initialEntries={['/protected']}>
                <Routes>
                    <Route element={<PermissionRoute permission="sites.view" />}>
                        <Route path="/protected" element={<div>Protected content</div>} />
                    </Route>
                </Routes>
            </MemoryRouter>,
        );

        expect(screen.getByText('Protected content')).toBeInTheDocument();
    });
});
