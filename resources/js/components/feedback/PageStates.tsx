import { Box, Button, CircularProgress, Typography } from '@mui/material';

export const PageLoadingState = ({ label = 'Loading...' }: { label?: string }) => (
    <Box sx={{ minHeight: 240, display: 'grid', placeItems: 'center', gap: 1 }}>
        <CircularProgress size={28} />
        <Typography color="text.secondary">{label}</Typography>
    </Box>
);

export const PageErrorState = ({ message = 'Unable to load this page.', onRetry }: { message?: string; onRetry?: () => void }) => (
    <Box sx={{ py: 8, textAlign: 'center' }}>
        <Typography color="error" variant="h6">{message}</Typography>
        {onRetry && <Button sx={{ mt: 1 }} onClick={onRetry}>Retry</Button>}
    </Box>
);

export const PageEmptyState = ({ message, action }: { message: string; action?: React.ReactNode }) => (
    <Box sx={{ py: 7, textAlign: 'center' }}>
        <Typography color="text.secondary">{message}</Typography>
        {action && <Box sx={{ mt: 1 }}>{action}</Box>}
    </Box>
);
