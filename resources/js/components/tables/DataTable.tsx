import React from 'react';
import {
    Box,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Paper,
    Typography,

} from '@mui/material';

export interface Column<T = any> {
    id: string;
    label: string;
    minWidth?: number;
    align?: 'left' | 'center' | 'right';
    render?: (value: any, row: T) => React.ReactNode;
}

interface DataTableProps<T = any> {
    columns: Column<T>[];
    data: T[];
    loading?: boolean;
    emptyMessage?: string;
}

export function DataTable<T>({ columns, data, loading, emptyMessage = 'No data found' }: DataTableProps<T>) {
    if (loading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', p: 4 }}>
                <Typography>Loading...</Typography>
            </Box>
        );
    }

    if (!data || data.length === 0) {
        return (
            <Box sx={{ p: 4, textAlign: 'center' }}>
                <Typography color="text.secondary">{emptyMessage}</Typography>
            </Box>
        );
    }

    return (
        <Paper sx={{ overflow: 'hidden' }}>
            <TableContainer>
                <Table stickyHeader>
                    <TableHead>
                        <TableRow>
                            {columns.map((column) => (
                                <TableCell
                                    key={column.id}
                                    align={column.align || 'left'}
                                    style={{ minWidth: column.minWidth || 100 }}
                                >
                                    {column.label}
                                </TableCell>
                            ))}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {data.map((row, index) => (
                            <TableRow key={index}>
                                {columns.map((column) => (
                                    <TableCell key={column.id} align={column.align || 'left'}>
                                        {column.render ? column.render((row as any)[column.id], row) : (row as any)[column.id]}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </TableContainer>
        </Paper>
    );
}
