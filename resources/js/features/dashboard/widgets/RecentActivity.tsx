import React from 'react';
import { Card, CardContent, Typography, List, ListItem, ListItemText, Chip, Box } from '@mui/material';
import { useNavigate } from 'react-router-dom';
import { formatDistanceToNow } from 'date-fns';

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
                                    secondary={formatDistanceToNow(new Date(activity.created_at), { addSuffix: true })}
                                />
                            </ListItem>
                        ))
                    )}
                </List>
            </CardContent>
        </Card>
    );
};
