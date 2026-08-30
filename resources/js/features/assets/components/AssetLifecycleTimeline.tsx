import React from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Box, Typography, Chip, CircularProgress,
    Paper, Stack
} from '@mui/material';
import {
    Timeline, TimelineItem, TimelineSeparator,
    TimelineConnector, TimelineContent,
    TimelineDot, TimelineOppositeContent
} from '@mui/lab';
import {
    CheckCircle, Warning, SwapHoriz,
    Build, Delete, Pending, Done,
    Schedule
} from '@mui/icons-material';
import { getAssetLifecycle } from '../api/assets';
import { format } from 'date-fns';

interface AssetLifecycleEvent {
    id: number;
    asset_id: number;
    event_type: string;
    status_before: string | null;
    status_after: string | null;
    from_site_id: number | null;
    to_site_id: number | null;
    from_site?: { id: number; site_code: string; name: string };
    to_site?: { id: number; site_code: string; name: string };
    notes: string | null;
    created_by: number;
    created_by_user?: { id: number; name: string; email: string };
    event_date: string;
    created_at: string;
    updated_at: string;
}

interface AssetLifecycleTimelineProps {
    assetId: number;
}

const getEventIcon = (eventType: string) => {
    switch (eventType) {
        case 'RECEIVED': return <CheckCircle />;
        case 'INSTALLED': return <Build />;
        case 'STATUS_CHANGED': return <Pending />;
        case 'MAINTENANCE_STARTED': return <Build />;
        case 'MAINTENANCE_COMPLETED': return <Done />;
        case 'TRANSFERRED': return <SwapHoriz />;
        case 'RETIRED': return <Warning />;
        case 'DISPOSED': return <Delete />;
        default: return <Schedule />;
    }
};

const getEventColor = (eventType: string): 'success' | 'warning' | 'error' | 'info' | 'primary' => {
    switch (eventType) {
        case 'RECEIVED': return 'success';
        case 'INSTALLED': return 'success';
        case 'MAINTENANCE_STARTED': return 'warning';
        case 'MAINTENANCE_COMPLETED': return 'success';
        case 'TRANSFERRED': return 'info';
        case 'RETIRED': return 'warning';
        case 'DISPOSED': return 'error';
        case 'STATUS_CHANGED': return 'primary';
        default: return 'info';
    }
};

const getEventLabel = (eventType: string): string => {
    return eventType.split('_').map(word => 
        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
    ).join(' ');
};

export const AssetLifecycleTimeline: React.FC<AssetLifecycleTimelineProps> = ({ assetId }) => {
    const { data, isLoading, error } = useQuery({
        queryKey: ['asset-lifecycle', assetId],
        queryFn: () => getAssetLifecycle(assetId),
        enabled: !!assetId,
    });

    if (isLoading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', p: 4 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return (
            <Box sx={{ p: 3 }}>
                <Typography color="error">Failed to load lifecycle events.</Typography>
            </Box>
        );
    }

    const events = data?.data || [];

    if (events.length === 0) {
        return (
            <Box sx={{ p: 3, textAlign: 'center' }}>
                <Typography variant="body2" color="text.secondary">
                    No lifecycle events recorded for this asset.
                </Typography>
            </Box>
        );
    }

    return (
        <Box sx={{ py: 2 }}>
            <Timeline position="right">
                {events.map((event: AssetLifecycleEvent, index: number) => (
                    <TimelineItem key={event.id}>
                        <TimelineOppositeContent sx={{ flex: 0.3 }}>
                            <Typography variant="caption" color="text.secondary">
                                {format(new Date(event.event_date), 'MMM d, yyyy hh:mm a')}
                            </Typography>
                        </TimelineOppositeContent>
                        <TimelineSeparator>
                            <TimelineDot color={getEventColor(event.event_type)}>
                                {getEventIcon(event.event_type)}
                            </TimelineDot>
                            {index < events.length - 1 && <TimelineConnector />}
                        </TimelineSeparator>
                        <TimelineContent>
                            <Paper elevation={0} sx={{ p: 2, bgcolor: 'action.hover' }}>
                                <Stack spacing={1}>
                                    <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
                                        <Chip
                                            label={getEventLabel(event.event_type)}
                                            size="small"
                                            color={getEventColor(event.event_type)}
                                        />
                                        {event.status_before && event.status_after && (
                                            <>
                                                <Typography variant="caption">
                                                    {event.status_before}
                                                </Typography>
                                                <Typography variant="caption" color="text.secondary">→</Typography>
                                                <Typography variant="caption">
                                                    {event.status_after}
                                                </Typography>
                                            </>
                                        )}
                                    </Stack>

                                    {event.event_type === 'TRANSFERRED' && (
                                        <Stack direction="row" spacing={1} alignItems="center">
                                            <Typography variant="body2">
                                                From: {event.from_site?.site_code || 'Unknown'}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">→</Typography>
                                            <Typography variant="body2">
                                                To: {event.to_site?.site_code || 'Unknown'}
                                            </Typography>
                                        </Stack>
                                    )}

                                    {event.notes && (
                                        <Typography variant="body2" color="text.secondary">
                                            {event.notes}
                                        </Typography>
                                    )}

                                    <Typography variant="caption" color="text.secondary">
                                        By: {event.created_by_user?.name || 'Unknown'}
                                    </Typography>
                                </Stack>
                            </Paper>
                        </TimelineContent>
                    </TimelineItem>
                ))}
            </Timeline>
        </Box>
    );
};
