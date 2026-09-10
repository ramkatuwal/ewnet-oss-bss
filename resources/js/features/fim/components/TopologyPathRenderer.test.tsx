import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { TopologyPathRenderer } from './TopologyPathRenderer';

describe('TopologyPathRenderer', () => {
    it('renders the API edge order without inferring graph edges', () => {
        render(<TopologyPathRenderer path={{ start_node: 'FiberTermination:1', depth: 2, truncated: false, nodes: [{ type: 'FiberTermination', id: 1, data: {} }, { type: 'PassiveOpticalPort', id: 2, data: {} }], edges: [{ type: 'FiberTerminationPortAttachment', id: 9, from: 'FiberTermination:1', to: 'PassiveOpticalPort:2', data: {} }] }} />);
        expect(screen.getByText(/1\. FiberTermination #1 -> PassiveOpticalPort #2/)).toBeInTheDocument();
    });
});
