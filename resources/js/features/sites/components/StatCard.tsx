import { Card, CardContent, Typography, Box } from '@mui/material';

interface StatCardProps {
    title: string;
    value: number | string;
    color?: string;
    icon?: React.ReactNode;
}

export const StatCard = ({ title, value, color = 'primary.main', icon }: StatCardProps) => (
    <Card sx={{ height: '100%' }}>
        <CardContent>
            <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <Typography variant="caption" color="text.secondary">{title}</Typography>
                {icon && <Box sx={{ color }}>{icon}</Box>}
            </Box>
            <Typography variant="h4" component="div" sx={{ mt: 1, fontWeight: 'bold', color }}>
                {value}
            </Typography>
        </CardContent>
    </Card>
);
