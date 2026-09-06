import React, { useEffect, useState } from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  Chip,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TablePagination,
  Tabs,
  Tab,
  Alert,
  TextField,
  MenuItem,
  Stack,
  InputAdornment,
  CircularProgress,
} from '@mui/material';
import { Search as SearchIcon } from '@mui/icons-material';
import { useQuery } from '@tanstack/react-query';
import { systemApi } from '@/api/system';
import { apiClient } from '@/api/client';
import { SystemLogEntry } from '../types';

interface NginxSummary {
  total: number;
  '2xx': number;
  '3xx': number;
  '4xx': number;
  '5xx': number;
  '401': number;
  '403': number;
  '404': number;
  '429': number;
  '500': number;
  '502': number;
  '503': number;
  '504': number;
}

const LOG_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

const STATUS_CLASSES = [
  { label: 'All', value: '' },
  { label: '2xx Success', value: '2xx' },
  { label: '3xx Redirect', value: '3xx' },
  { label: '4xx Client error', value: '4xx' },
  { label: '5xx Server error', value: '5xx' },
];

const levelColor = (level?: string | null): 'error' | 'warning' | 'info' | 'default' => {
  switch ((level || '').toLowerCase()) {
    case 'emergency':
    case 'alert':
    case 'critical':
    case 'error':
      return 'error';
    case 'warning':
    case 'notice':
      return 'warning';
    case 'info':
      return 'info';
    default:
      return 'default';
  }
};

const getStatusColor = (status?: number | null): 'success' | 'info' | 'warning' | 'error' | 'default' => {
  if (!status) return 'default';
  if (status < 300) return 'success';
  if (status < 400) return 'info';
  if (status < 500) return 'warning';
  return 'error';
};

const formatLevelLabel = (level?: string | null) => (level ? level.toUpperCase() : 'UNKNOWN');

export const SystemLogsPage: React.FC = () => {
  const [tab, setTab] = useState(0);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [level, setLevel] = useState('');
  const [statusClass, setStatusClass] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [page, setPage] = useState(0);
  const [perPage, setPerPage] = useState(50);
  const [summaryError, setSummaryError] = useState<string | null>(null);

  const type: 'nginx' | 'laravel' = tab === 0 ? 'nginx' : 'laravel';

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search), 350);
    return () => clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    setPage(0);
  }, [tab, debouncedSearch, level, statusClass, dateFrom, dateTo]);

  const nginxSummary = useQuery({
    queryKey: ['systemLogsSummary'],
    queryFn: async () => {
      try {
        const res = await apiClient.get<{ data: NginxSummary }>('/api/v1/debug/summary');
        return res.data.data ?? res.data;
      } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        setSummaryError(e?.response?.data?.message || 'Failed to load summary.');
        throw err;
      }
    },
    retry: false,
    refetchInterval: 30000,
  });

  const logs = useQuery({
    queryKey: ['systemLogs', type, page, perPage, debouncedSearch, level, statusClass, dateFrom, dateTo],
    queryFn: () =>
      systemApi.getLogs({
        type,
        page: page + 1,
        perPage,
        search: debouncedSearch || undefined,
        level: type === 'laravel' ? level || undefined : undefined,
        statusClass: type === 'nginx' ? statusClass || undefined : undefined,
        dateFrom: dateFrom || undefined,
        dateTo: dateTo || undefined,
      }),
    placeholderData: (prev) => prev,
  });

  const entries: SystemLogEntry[] = logs.data?.data ?? [];
  const meta = logs.data?.meta;

  const handleChangePage = (_event: React.MouseEvent<HTMLButtonElement> | null, newPage: number) => {
    setPage(newPage);
  };

  const handleChangeRowsPerPage = (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setPerPage(parseInt(event.target.value, 10));
    setPage(0);
  };

  const renderSummary = () => {
    if (!nginxSummary.data) return null;
    const s = nginxSummary.data;
    return (
      <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap', mb: 3 }}>
        <SummaryCard label="Total Requests" value={s.total} />
        <SummaryCard label="2xx" value={s['2xx'] ?? 0} color="success.main" />
        <SummaryCard label="3xx" value={s['3xx'] ?? 0} color="info.main" />
        <SummaryCard label="4xx" value={s['4xx'] ?? 0} color="warning.main" />
        <SummaryCard label="5xx" value={s['5xx'] ?? 0} color="error.main" />
      </Box>
    );
  };

  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4" gutterBottom>
        System Logs
      </Typography>

      {summaryError && <Alert severity="error" sx={{ mb: 2 }}>{summaryError}</Alert>}
      {logs.isError && <Alert severity="error" sx={{ mb: 2 }}>Failed to load logs.</Alert>}

      {renderSummary()}

      <Tabs value={tab} onChange={(_, v) => setTab(v)} sx={{ mb: 2 }}>
        <Tab label="Nginx Access" />
        <Tab label="Laravel Logs" />
      </Tabs>

      <Stack direction="row" spacing={2} alignItems="center" flexWrap="wrap" sx={{ mb: 2 }}>
        <TextField
          label="Search"
          size="small"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={type === 'laravel' ? 'Filter by message...' : 'Filter by IP, path, method, agent...'}
          sx={{ minWidth: 280 }}
          slotProps={{
            input: {
              startAdornment: (
                <InputAdornment position="start">
                  <SearchIcon fontSize="small" />
                </InputAdornment>
              ),
            },
          }}
        />

        {type === 'laravel' && (
          <TextField
            select
            label="Level"
            size="small"
            value={level}
            onChange={(e) => setLevel(e.target.value)}
            sx={{ minWidth: 160 }}
          >
            <MenuItem value="">All levels</MenuItem>
            {LOG_LEVELS.map((lvl) => (
              <MenuItem key={lvl} value={lvl}>
                {lvl.toUpperCase()}
              </MenuItem>
            ))}
          </TextField>
        )}

        {type === 'nginx' && (
          <TextField
            select
            label="Status"
            size="small"
            value={statusClass}
            onChange={(e) => setStatusClass(e.target.value)}
            sx={{ minWidth: 180 }}
          >
            {STATUS_CLASSES.map((opt) => (
              <MenuItem key={opt.value || 'all'} value={opt.value}>
                {opt.label}
              </MenuItem>
            ))}
          </TextField>
        )}

        <TextField
          label="From"
          type="date"
          size="small"
          value={dateFrom}
          onChange={(e) => setDateFrom(e.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
        />
        <TextField
          label="To"
          type="date"
          size="small"
          value={dateTo}
          onChange={(e) => setDateTo(e.target.value)}
          slotProps={{ inputLabel: { shrink: true } }}
        />
      </Stack>

      <Box sx={{ width: '100%', overflowX: 'auto', position: 'relative', minHeight: 120 }}>
        {logs.isFetching && (
          <Box sx={{ position: 'absolute', top: 8, right: 8, zIndex: 2 }}>
            <CircularProgress size={20} />
          </Box>
        )}

        {type === 'nginx' ? (
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>Time</TableCell>
                <TableCell>IP</TableCell>
                <TableCell>Method</TableCell>
                <TableCell>Path</TableCell>
                <TableCell>Status</TableCell>
                <TableCell>Classification</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {entries.length === 0 && !logs.isFetching ? (
                <TableRow>
                  <TableCell colSpan={6} align="center">
                    <Typography color="text.secondary">No Nginx entries match the current filters.</Typography>
                  </TableCell>
                </TableRow>
              ) : (
                entries.map((log, i) => (
                  <TableRow key={`${log.time}-${i}`}>
                    <TableCell>{log.time}</TableCell>
                    <TableCell>{log.ip}</TableCell>
                    <TableCell>{log.method}</TableCell>
                    <TableCell sx={{ maxWidth: 420, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {log.path}
                    </TableCell>
                    <TableCell>
                      <Chip label={log.status} color={getStatusColor(log.status)} size="small" />
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={log.classification}
                        size="small"
                        color={log.classification === 'SECURITY PROBE' ? 'error' : getStatusColor(log.status)}
                        variant="outlined"
                      />
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        ) : (
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell sx={{ width: 240 }}>Time</TableCell>
                <TableCell sx={{ width: 110 }}>Level</TableCell>
                <TableCell>Message</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {entries.length === 0 && !logs.isFetching ? (
                <TableRow>
                  <TableCell colSpan={3} align="center">
                    <Typography color="text.secondary">No Laravel entries match the current filters.</Typography>
                  </TableCell>
                </TableRow>
              ) : (
                entries.map((log, i) => (
                  <TableRow key={`${log.time}-${i}`}>
                    <TableCell sx={{ whiteSpace: 'nowrap' }}>{log.time}</TableCell>
                    <TableCell>
                      <Chip label={formatLevelLabel(log.level)} color={levelColor(log.level)} size="small" />
                    </TableCell>
                    <TableCell sx={{ fontFamily: 'monospace', fontSize: 12 }}>{log.message}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        )}
      </Box>

      <TablePagination
        component="div"
        count={meta?.total ?? 0}
        page={page}
        onPageChange={handleChangePage}
        rowsPerPage={perPage}
        onRowsPerPageChange={handleChangeRowsPerPage}
        rowsPerPageOptions={[25, 50, 100, 200]}
        labelRowsPerPage="Rows:"
      />
    </Box>
  );
};

const SummaryCard: React.FC<{ label: string; value: number; color?: string }> = ({ label, value, color }) => (
  <Card sx={{ flex: 1, minWidth: 150 }}>
    <CardContent>
      <Typography variant="caption" color="text.secondary">{label}</Typography>
      <Typography variant="h6" color={color}>{value}</Typography>
    </CardContent>
  </Card>
);