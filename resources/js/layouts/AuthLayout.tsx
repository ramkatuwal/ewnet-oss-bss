import { Outlet } from 'react-router-dom';
import { Box, Container } from '@mui/material';

export const AuthLayout = () => {
    return (
        <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh' }}>
            <Container maxWidth="sm">
                <Outlet />
            </Container>
        </Box>
    );
};
