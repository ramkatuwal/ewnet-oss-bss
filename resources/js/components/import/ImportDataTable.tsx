import React, { useState, useMemo } from 'react';
import {
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TablePagination,
  TableSortLabel,
  Checkbox,
  Chip,
  TextField,
  InputAdornment,
  IconButton,
  Box,
  Tooltip,
  Typography,
} from '@mui/material';
import { Search, Clear, VisibilityOff, Visibility } from '@mui/icons-material';

export interface Column {
  id: string;
  label: string;
  sortable?: boolean;
  render?: (row: any) => React.ReactNode;
  width?: number | string;
  align?: 'left' | 'center' | 'right';
}

export interface ImportDataTableProps {
  columns: Column[];
  rows: any[];
  loading?: boolean;
  onRowSelect?: (selectedIds: Set<string>) => void;
  selectedIds?: Set<string>;
  getRowId?: (row: any) => string;
  searchable?: boolean;
  searchFields?: string[];
  onSearch?: (query: string) => void;
  pagination?: boolean;
  rowsPerPageOptions?: number[];
  defaultRowsPerPage?: number;
}

const ImportDataTable: React.FC<ImportDataTableProps> = ({
  columns,
  rows,
  loading,
  onRowSelect,
  selectedIds = new Set(),
  getRowId = (row) => row.id || String(rows.indexOf(row)),
  searchable = true,
  searchFields = ['name'],
  onSearch,
  pagination = true,
  rowsPerPageOptions = [10, 25, 50, 100],
  defaultRowsPerPage = 25,
}) => {
  const [order, setOrder] = useState<'asc' | 'desc'>('asc');
  const [orderBy, setOrderBy] = useState<string>(columns[0]?.id || '');
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(defaultRowsPerPage);
  const [searchQuery, setSearchQuery] = useState('');
  const [visibleColumns, setVisibleColumns] = useState<Set<string>>(
    new Set(columns.map(c => c.id))
  );
  const [showColumnMenu, setShowColumnMenu] = useState(false);

  // Filter rows based on search
  const filteredRows = useMemo(() => {
    if (!searchQuery.trim()) return rows;
    return rows.filter(row => {
      return searchFields.some(field => {
        const value = String(row[field] || '').toLowerCase();
        return value.includes(searchQuery.toLowerCase());
      });
    });
  }, [rows, searchQuery, searchFields]);

  // Sort rows
  const sortedRows = useMemo(() => {
    const comparator = (a: any, b: any) => {
      const aVal = a[orderBy] || '';
      const bVal = b[orderBy] || '';
      if (aVal < bVal) return order === 'asc' ? -1 : 1;
      if (aVal > bVal) return order === 'asc' ? 1 : -1;
      return 0;
    };
    return [...filteredRows].sort(comparator);
  }, [filteredRows, order, orderBy]);

  // Paginate
  const paginatedRows = useMemo(() => {
    if (!pagination) return sortedRows;
    const start = page * rowsPerPage;
    return sortedRows.slice(start, start + rowsPerPage);
  }, [sortedRows, page, rowsPerPage, pagination]);

  const handleSort = (columnId: string) => {
    const isAsc = orderBy === columnId && order === 'asc';
    setOrder(isAsc ? 'desc' : 'asc');
    setOrderBy(columnId);
  };

  const handleSelectAll = (checked: boolean) => {
    if (onRowSelect) {
      const newSet = new Set<string>();
      if (checked) {
        paginatedRows.forEach(row => {
          newSet.add(getRowId(row));
        });
      }
      onRowSelect(newSet);
    }
  };

  const handleRowSelect = (row: any, checked: boolean) => {
    if (onRowSelect) {
      const newSet = new Set(selectedIds);
      const id = getRowId(row);
      if (checked) {
        newSet.add(id);
      } else {
        newSet.delete(id);
      }
      onRowSelect(newSet);
    }
  };

  const toggleColumn = (columnId: string) => {
    const newSet = new Set(visibleColumns);
    if (newSet.has(columnId)) {
      newSet.delete(columnId);
    } else {
      newSet.add(columnId);
    }
    setVisibleColumns(newSet);
  };

  if (loading) {
    return (
      <Paper sx={{ p: 4, textAlign: 'center' }}>
        <Typography color="text.secondary">Loading...</Typography>
      </Paper>
    );
  }

  return (
    <Paper>
      {/* Toolbar */}
      <Box sx={{ p: 2, display: 'flex', alignItems: 'center', gap: 2, flexWrap: 'wrap' }}>
        {searchable && (
          <TextField
            size="small"
            placeholder="Search..."
            value={searchQuery}
            onChange={(e) => {
              setSearchQuery(e.target.value);
              if (onSearch) onSearch(e.target.value);
            }}
            InputProps={{
              startAdornment: <InputAdornment position="start"><Search /></InputAdornment>,
              endAdornment: searchQuery && (
                <InputAdornment position="end">
                  <IconButton size="small" onClick={() => setSearchQuery('')}>
                    <Clear />
                  </IconButton>
                </InputAdornment>
              ),
            }}
            sx={{ minWidth: 250 }}
          />
        )}

        <Box sx={{ ml: 'auto', display: 'flex', gap: 1 }}>
          <Tooltip title="Toggle columns">
            <IconButton onClick={() => setShowColumnMenu(!showColumnMenu)}>
              <VisibilityOff />
            </IconButton>
          </Tooltip>
          {selectedIds.size > 0 && (
            <Chip label={`${selectedIds.size} selected`} color="primary" size="small" />
          )}
        </Box>
      </Box>

      {/* Column visibility menu */}
      {showColumnMenu && (
        <Box sx={{ p: 2, display: 'flex', flexWrap: 'wrap', gap: 1, borderBottom: '1px solid #eee' }}>
          {columns.map(col => (
            <Chip
              key={col.id}
              label={col.label}
              onClick={() => toggleColumn(col.id)}
              color={visibleColumns.has(col.id) ? 'primary' : 'default'}
              variant={visibleColumns.has(col.id) ? 'filled' : 'outlined'}
              size="small"
              icon={visibleColumns.has(col.id) ? <Visibility /> : <VisibilityOff />}
            />
          ))}
        </Box>
      )}

      {/* Table */}
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              {onRowSelect && (
                <TableCell padding="checkbox">
                  <Checkbox
                    checked={paginatedRows.length > 0 && paginatedRows.every(row => selectedIds.has(getRowId(row)))}
                    indeterminate={paginatedRows.some(row => selectedIds.has(getRowId(row))) && !paginatedRows.every(row => selectedIds.has(getRowId(row)))}
                    onChange={(e) => handleSelectAll(e.target.checked)}
                  />
                </TableCell>
              )}
              {columns.map(col => (
                visibleColumns.has(col.id) && (
                  <TableCell
                    key={col.id}
                    align={col.align || 'left'}
                    style={{ width: col.width }}
                  >
                    {col.sortable !== false ? (
                      <TableSortLabel
                        active={orderBy === col.id}
                        direction={orderBy === col.id ? order : 'asc'}
                        onClick={() => handleSort(col.id)}
                      >
                        {col.label}
                      </TableSortLabel>
                    ) : col.label}
                  </TableCell>
                )
              ))}
            </TableRow>
          </TableHead>
          <TableBody>
            {paginatedRows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={columns.length + (onRowSelect ? 1 : 0)} align="center" sx={{ py: 4 }}>
                  <Typography color="text.secondary">No records found</Typography>
                </TableCell>
              </TableRow>
            ) : (
              paginatedRows.map((row) => (
                <TableRow key={getRowId(row)}>
                  {onRowSelect && (
                    <TableCell padding="checkbox">
                      <Checkbox
                        checked={selectedIds.has(getRowId(row))}
                        onChange={(e) => handleRowSelect(row, e.target.checked)}
                      />
                    </TableCell>
                  )}
                  {columns.map(col => (
                    visibleColumns.has(col.id) && (
                      <TableCell key={col.id} align={col.align || 'left'}>
                        {col.render ? col.render(row) : row[col.id]}
                      </TableCell>
                    )
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </TableContainer>

      {pagination && (
        <TablePagination
          rowsPerPageOptions={rowsPerPageOptions}
          component="div"
          count={filteredRows.length}
          rowsPerPage={rowsPerPage}
          page={page}
          onPageChange={(_, newPage) => setPage(newPage)}
          onRowsPerPageChange={(e) => {
            setRowsPerPage(parseInt(e.target.value, 10));
            setPage(0);
          }}
        />
      )}
    </Paper>
  );
};

export default ImportDataTable;
