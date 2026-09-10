import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AuthorityBadge } from './AuthorityBadge';

describe('AuthorityBadge', () => {
    it('labels canonical inventory data as authoritative', () => {
        render(<AuthorityBadge authoritative />);

        expect(screen.getByText('Authoritative')).toBeInTheDocument();
    });

    it('labels read-only observations as observed', () => {
        render(<AuthorityBadge authoritative={false} />);

        expect(screen.getByText('Observed')).toBeInTheDocument();
    });
});
