import React from 'react';
import { Card, CardContent, Typography, Button, Stack } from '@mui/material';
import PersonAddIcon from '@mui/icons-material/PersonAdd';
import BusinessIcon from '@mui/icons-material/Business';
import LocationOnIcon from '@mui/icons-material/LocationOn';
import StorefrontIcon from '@mui/icons-material/Storefront';
import ApartmentIcon from '@mui/icons-material/Apartment';
import SecurityIcon from '@mui/icons-material/Security';
import { useNavigate } from 'react-router-dom';

export const QuickActions: React.FC = () => {
    const navigate = useNavigate();

    const actions = [
        { label: 'Company', icon: <BusinessIcon />, path: '/manage/companies?action=create', permission: 'companies.create' },
        { label: 'Region', icon: <LocationOnIcon />, path: '/manage/regions?action=create', permission: 'regions.create' },
        { label: 'Branch', icon: <StorefrontIcon />, path: '/manage/branches?action=create', permission: 'branches.create' },
        { label: 'Department', icon: <ApartmentIcon />, path: '/manage/departments?action=create', permission: 'departments.create' },
        { label: 'User', icon: <PersonAddIcon />, path: '/manage/users?action=create', permission: 'users.create' },
        { label: 'Role', icon: <SecurityIcon />, path: '/manage/roles?action=create', permission: 'roles.create' },
    ];

    return (
        <Card sx={{ height: '100%' }}>
            <CardContent>
                <Typography variant="h6" gutterBottom>Quick Actions</Typography>
                <Stack spacing={1}>
                    {actions.map((action) => (
                        <Button
                            key={action.label}
                            variant="outlined"
                            size="small"
                            startIcon={action.icon}
                            onClick={() => navigate(action.path)}
                            fullWidth
                            sx={{ justifyContent: 'flex-start' }}
                        >
                            Create {action.label}
                        </Button>
                    ))}
                </Stack>
            </CardContent>
        </Card>
    );
};
