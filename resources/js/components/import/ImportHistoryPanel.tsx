import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  Paper, Table, TableBody, TableCell, TableContainer, TableHead, TableRow,
  TablePagination, Chip, Typography, Box, IconButton, Collapse
} from '@mui/material';
import { ExpandMore, ExpandLess } from '@mui/icons-material';
import { importApi } from '@/api/import';

interface ImportHistoryRecord {
  id: number;
  source: string;
  type: string;
  status: string;
  started_by_name?: string;
  started_at: string;
  completed_at: string | null;
  total_records: number;
  created_records: number;
  updated_records: number;
  skipped_records: number;
  conflict_records: number;
  error_records: number;
}

interface ImportHistoryPanelProps {
  source?: string;
  type?: string;
}

const ImportHistoryPanel: React.FC<ImportHistoryPanelProps> = ({ source, type }) => {
  const [page, setPage] = useState(0);
  const [rowsPerPage, setRowsPerPage] = useState(5);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const { data: historyData } = useQuery({
    queryKey: ['import-history', source, type, page, rowsPerPage],
    queryFn: () => importApi.getHistory({ source, type, page: page + 1, per_page: rowsPerPage }),
  });

  const records = historyData?.data || [];
  const total = historyData?.meta?.total || 0;

  return (
    <Paper sx={{ p: 2 }}>
      <Typography variant="h6" gutterBottom>Import History</Typography>
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell />
              <TableCell>Source</TableCell>
              <TableCell>Type</TableCell>
              <TableCell>Status</TableCell>
              <TableCell>Started By</TableCell>
              <TableCell>Started At</TableCell>
              <TableCell align="right">Records</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {records.map((row: ImportHistoryRecord) => (
              <React.Fragment key={row.id}>
                <TableRow>
                  <TableCell>
                    <IconButton size="small" onClick={() => setExpandedId(expandedId === row.id ? null : row.id)}>
                      {expandedId === row.id ? <ExpandLess /> : <ExpandMore />}
                    </IconButton>
                  </TableCell>
                  <TableCell>{row.source}</TableCell>
                  <TableCell>{row.type}</TableCell>
                  <TableCell>
                    <Chip 
                      label={row.status} 
                      size="small" 
                      color={row.status === 'completed' ? 'success' : row.status === 'failed' ? 'error' : 'default'} 
                    />
                  </TableCell>
                  <TableCell>{row.started_by_name || '-'}</TableCell>
                  <TableCell>{new Date(row.started_at).toLocaleString()}</TableCell>
                  <TableCell align="right">{row.total_records}</TableCell>
                </TableRow>
                <TableRow>
                  <TableCell style={{ paddingBottom: 0, paddingTop: 0 }} colSpan={7}>
                    <Collapse in={expandedId === row.id} timeout="auto" unmountOnExit>
                      <Box sx={{ margin: 1 }}>
                        <Typography variant="subtitle2">Details:</Typography>
                        Created: {row.created_records}, Updated: {row.updated_records}, 
                        Skipped: {row.skipped_records}, Conflicts: {row.conflict_records}, 
                        Errors: {row.error_records}
                      </Box>
                    </Collapse>
                  </TableCell>
                </TableRow>
              </React.Fragment>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
      <TablePagination
        component="div"
        count={total}
        page={page}
        onPageChange={(_, newPage) => setPage(newPage)}
        rowsPerPage={rowsPerPage}
        onRowsPerPageChange={(e) => { setRowsPerPage(parseInt(e.target.value, 10)); setPage(0); }}
      />
    </Paper>
  );
};

export default ImportHistoryPanel;
