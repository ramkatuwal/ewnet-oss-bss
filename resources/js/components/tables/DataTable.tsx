import React from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Paper,
    TablePagination,
    Box,
    Typography,
    CircularProgress,
    Chip,
    IconButton,
    Tooltip,
} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import VisibilityIcon from '@mui/icons-material/Visibility';
import InboxIcon from '@mui/icons-material/Inbox';

export interface Column<T> {
    key: string;
    label: string;
    render?: (row: T) => React.ReactNode;
    width?: number | string;
    align?: 'left' | 'center' | 'right';
}

export interface RowAction<T> {
    icon: React.ReactNode;
    label: string;
    onClick: (row: T) => void;
    color?: 'primary' | 'error' | 'default';
    visible?: (row: T) => boolean;
}

interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    loading?: boolean;
    page: number;
    rowsPerPage: number;
    total: number;
    onPageChange: (page: number) => void;
    onRowsPerPageChange: (rowsPerPage: number) => void;
    actions?: RowAction<T>[];
    emptyMessage?: string;
    onRowClick?: (row: T) => void;
}

export function DataTable<T extends { id: number }>({
    columns,
    data,
    loading,
    page,
    rowsPerPage,
    total,
    onPageChange,
    onRowsPerPageChange,
    actions,
    emptyMessage = 'No records found',
    onRowClick,
}: DataTableProps<T>) {
    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (!data.length) {
        return (
            <Paper sx={{ p: 6, textAlign: 'center' }}>
                <InboxIcon sx={{ fontSize: 48, color: 'text.disabled', mb: 2 }} />
                <Typography variant="h6" color="text.secondary">
                    {emptyMessage}
                </Typography>
            </Paper>
        );
    }

    return (
        <Paper elevation={0} sx={{ border: 1, borderColor: 'divider' }}>
            <TableContainer>
                <Table size="small">
                    <TableHead>
                        <TableRow sx={{ bgcolor: 'action.hover' }}>
                            {columns.map((col) => (
                                <TableCell
                                    key={col.key}
                                    align={col.align || 'left'}
                                    sx={{ fontWeight: 600, width: col.width }}
                                >
                                    {col.label}
                                </TableCell>
                            ))}
                            {actions && actions.length > 0 && (
                                <TableCell align="right" sx={{ fontWeight: 600, width: 120 }}>
                                    Actions
                                </TableCell>
                            )}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {data.map((row) => (
                            <TableRow
                                key={row.id}
                                hover
                                onClick={() => onRowClick?.(row)}
                                sx={{ cursor: onRowClick ? 'pointer' : 'default' }}
                            >
                                {columns.map((col) => (
                                    <TableCell key={col.key} align={col.align || 'left'}>
                                        {col.render ? col.render(row) : String((row as any)[col.key] ?? '')}
                                    </TableCell>
                                ))}
                                {actions && actions.length > 0 && (
                                    <TableCell align="right">
                                        {actions
                                            .filter((a) => !a.visible || a.visible(row))
                                            .map((action, idx) => (
                                                <Tooltip key={idx} title={action.label}>
                                                    <IconButton
                                                        size="small"
                                                        color={action.color || 'default'}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            action.onClick(row);
                                                        }}
                                                    >
                                                        {action.icon}
                                                    </IconButton>
                                                </Tooltip>
                                            ))}
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>
            <TablePagination
                component="div"
                count={total}
                page={page}
                rowsPerPage={rowsPerPage}
                onPageChange={(_, p) => onPageChange(p)}
                onRowsPerPageChange={(e) => onRowsPerPageChange(parseInt(e.target.value, 10))}
                rowsPerPageOptions={[10, 25, 50]}
            />
        </Paper>
    );
}

// Status badge helper
export const StatusChip: React.FC<{ active: boolean }> = ({ active }) => (
    <Chip
        label={active ? 'Active' : 'Inactive'}
        size="small"
        color={active ? 'success' : 'default'}
        variant={active ? 'filled' : 'outlined'}
    />
);
