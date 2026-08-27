import React from 'react';
import { Box, Typography, Button } from '@mui/material';

interface Props {
    children: React.ReactNode;
}

interface State {
    hasError: boolean;
    error?: Error;
}

export class ErrorBoundary extends React.Component<Props, State> {
    constructor(props: Props) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    render() {
        if (this.state.hasError) {
            return (
                <Box sx={{ p: 4, textAlign: 'center' }}>
                    <Typography variant="h5" gutterBottom>
                        Something went wrong
                    </Typography>
                    <Typography color="text.secondary" sx={{ mb: 2 }}>
                        {this.state.error?.message || 'An unexpected error occurred.'}
                    </Typography>
                    <Button variant="contained" onClick={() => window.location.reload()}>
                        Reload Page
                    </Button>
                </Box>
            );
        }
        return this.props.children;
    }
}
