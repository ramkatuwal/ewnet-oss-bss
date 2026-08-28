import React from 'react';
import { Card, CardContent, Typography, Box } from '@mui/material';
import { useNavigate } from 'react-router-dom';

interface StatCardProps {
    title: string;
    value: number | string;
    icon?: React.ReactNode;
    color?: string;
    link?: string;
}

export const StatCard: React.FC<StatCardProps> = ({
    title,
    value,
    icon,
    color = 'primary.main',
    link,
}) => {
    const navigate = useNavigate();

    const handleClick = () => {
        if (link) {
            navigate(link);
        }
    };

    return (
        <Card
            sx={{
                height: '100%',
                cursor: link ? 'pointer' : 'default',
                transition: 'transform 0.2s, box-shadow 0.2s',
                '&:hover': link ? {
                    transform: 'translateY(-2px)',
                    boxShadow: 3,
                } : {},
            }}
            onClick={handleClick}
        >
            <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box>
                        <Typography variant="body2" color="text.secondary" gutterBottom>
                            {title}
                        </Typography>
                        <Typography variant="h4" fontWeight={600}>
                            {value}
                        </Typography>
                    </Box>
                    {icon && (
                        <Box sx={{ color, fontSize: 40, opacity: 0.7 }}>
                            {icon}
                        </Box>
                    )}
                </Box>
            </CardContent>
        </Card>
    );
};
