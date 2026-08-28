import React from 'react';
import { Card, CardContent, Typography, List, ListItem, ListItemText, Chip, Box } from '@mui/material';
import { useNavigate } from 'react-router-dom';

interface ActivityItem {
    id: number;
    action: string;
    result: string;
    actor: string;
    target_type: string | null;
    created_at: string;
}

interface RecentActivityProps {
    activities: ActivityItem[];
    onViewAll?: () => void;
}

export const RecentActivity: React.FC<RecentActivityProps> = ({ activities, onViewAll }) => {
    const navigate = useNavigate();

    const handleViewAll = () => {
        if (onViewAll) {
            onViewAll();
        } else {
            navigate('/audit/security');
        }
    };

    return (
        <Card sx={{ height: '100%' }}>
            <CardContent>
                <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                    <Typography variant="h6">Recent Activity</Typography>
                    <Typography
                        variant="body2"
                        color="primary"
                        sx={{ cursor: 'pointer', '&:hover': { textDecoration: 'underline' } }}
                        onClick={handleViewAll}
                    >
                        View All
                    </Typography>
                </Box>
                <List dense>
                    {activities.length === 0 ? (
                        <ListItem>
                            <ListItemText
                                primary="No recent activity"
                                primaryTypographyProps={{ color: 'text.secondary', align: 'center' }}
                            />
                        </ListItem>
                    ) : (
                        activities.slice(0, 8).map((activity) => (
                            <ListItem key={activity.id} sx={{ px: 0 }}>
                                <ListItemText
                                    primary={
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Chip
                                                label={activity.action}
                                                size="small"
                                                color={activity.result === 'success' ? 'success' : 'error'}
                                                variant="outlined"
                                            />
                                            <Typography variant="body2" color="text.secondary">
                                                {activity.actor}
                                                {activity.target_type && ` → ${activity.target_type}`}
                                            </Typography>
                                        </Box>
                                    }
                                    secondary={(() => {
                                                const seconds = Math.floor((Date.now() - new Date(activity.created_at).getTime()) / 1000);
                                                if (seconds < 60) return `${seconds}s ago`;
                                                const minutes = Math.floor(seconds / 60);
                                                if (minutes < 60) return `${minutes}m ago`;
                                                const hours = Math.floor(minutes / 60);
                                                if (hours < 24) return `${hours}h ago`;
                                                const days = Math.floor(hours / 24);
                                                if (days < 30) return `${days}d ago`;
                                                return new Date(activity.created_at).toLocaleDateString();
                                            })()}
                                />
                            </ListItem>
                        ))
                    )}
                </List>
            </CardContent>
        </Card>
    );
};
