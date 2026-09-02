import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box,
  Button,
  Chip,
  Container,
  Grid,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
  Alert,
  CircularProgress,
  Tabs,
  Tab,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogContentText,
  DialogActions,
  Tooltip,
  Checkbox,
} from '@mui/material';
import {
  Refresh,
  PlayArrow,
  Warning,
} from '@mui/icons-material';
import { PageHeader } from '@/components/layout/PageHeader';
import { Can } from '@/components/auth/Can';
import toast from 'react-hot-toast';
import axios from 'axios';

// Note: AnalysisResult is used in the component via type inference
// Keeping it for documentation and future use

const getActionColor = (action: string): 'success' | 'primary' | 'info' | 'error' | 'warning' | 'default' => {
  const colors: Record<string, any> = {
    create: 'success',
    link: 'primary',
    update: 'info',
    conflict: 'error',
    skip: 'warning',
    error: 'error',
  };
  return colors[action] || 'default';
};

const getActionLabel = (action: string): string => {
  const labels: Record<string, string> = {
    create: 'CREATE',
    link: 'LINK',
    update: 'UPDATE',
    conflict: '⚠️ CONFLICT',
    skip: 'SKIP',
    error: 'ERROR',
  };
  return labels[action] || action.toUpperCase();
};

const ActionChip: React.FC<{ action: string }> = ({ action }) => {
  const color = getActionColor(action);
  const label = getActionLabel(action);
  return <Chip label={label} color={color} size="small" variant="outlined" />;
};

const UispImportPage: React.FC = () => {
  const queryClient = useQueryClient();
  const [selectedTab, setSelectedTab] = useState(0);
  const [filterAction, setFilterAction] = useState<string>('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [importDialogOpen, setImportDialogOpen] = useState(false);
  const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set());

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['uisp-import-preview'],
    queryFn: async () => {
      const response = await axios.post('/api/v1/integrations/uisp/import/preview');
      return response.data.data;
    },
    retry: false,
  });

  const importMutation = useMutation({
    mutationFn: async (selected: any) => {
      const response = await axios.post('/api/v1/integrations/uisp/import/execute', selected);
      return response.data;
    },
    onSuccess: () => {
      toast.success('Import completed successfully');
      setImportDialogOpen(false);
      queryClient.invalidateQueries({ queryKey: ['uisp-import-preview'] });
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.error || 'Import failed');
      setImportDialogOpen(false);
    },
  });

  const handleAnalyze = () => {
    refetch();
  };

  const handleSelectAll = (checked: boolean) => {
    const items = data?.devices?.analysis || [];
    const newSet = new Set<string>();
    if (checked) {
      items.forEach((_: any, idx: number) => {
        newSet.add(`device-${idx}`);
      });
    }
    setSelectedItems(newSet);
  };

  const handleToggleItem = (key: string) => {
    const newSet = new Set(selectedItems);
    if (newSet.has(key)) {
      newSet.delete(key);
    } else {
      newSet.add(key);
    }
    setSelectedItems(newSet);
  };

  const handleImport = () => {
    const selected: any = { sites: [], devices: [] };
    if (data) {
      data.devices?.analysis.forEach((item: any, idx: number) => {
        if (selectedItems.has(`device-${idx}`)) {
          selected.devices.push(item);
        }
      });
    }
    importMutation.mutate(selected);
  };

  const renderSummary = () => {
    if (!data) return null;

    const summary = data.devices?.summary || {};
    const total = data.devices?.total || 0;

    return (
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'primary.main', color: 'white' }}>
            <Typography variant="h4">{total}</Typography>
            <Typography variant="body2">Total Devices</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'success.main', color: 'white' }}>
            <Typography variant="h4">{summary.link || 0}</Typography>
            <Typography variant="body2">Link</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={2}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'info.main', color: 'white' }}>
            <Typography variant="h4">{summary.create || 0}</Typography>
            <Typography variant="body2">Create</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={2}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'error.main', color: 'white' }}>
            <Typography variant="h4">{summary.conflict || 0}</Typography>
            <Typography variant="body2">Conflict</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={2}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'warning.main', color: 'white' }}>
            <Typography variant="h4">{summary.skip || 0}</Typography>
            <Typography variant="body2">Skip</Typography>
          </Paper>
        </Grid>
      </Grid>
    );
  };

  const renderDeviceTable = () => {
    if (!data) return null;

    const devices = data.devices?.analysis || [];
    const filtered = devices.filter((item: any) => {
      const search = searchTerm.toLowerCase();
      const matchesSearch =
        item.name?.toLowerCase().includes(search) ||
        item.serial?.toLowerCase().includes(search) ||
        item.mac?.toLowerCase().includes(search) ||
        item.ip?.toLowerCase().includes(search) ||
        item.reason?.toLowerCase().includes(search);
      return matchesSearch && (filterAction === 'all' || item.action === filterAction);
    });

    return (
      <TableContainer component={Paper}>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell padding="checkbox">
                <Checkbox
                  checked={selectedItems.size === filtered.length}
                  indeterminate={selectedItems.size > 0 && selectedItems.size < filtered.length}
                  onChange={(e) => handleSelectAll(e.target.checked)}
                />
              </TableCell>
              <TableCell>Device</TableCell>
              <TableCell>IP</TableCell>
              <TableCell>MAC</TableCell>
              <TableCell>Serial</TableCell>
              <TableCell>Matches</TableCell>
              <TableCell>Action</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {filtered.map((item: any, index: number) => {
              const key = `device-${index}`;
              const isSelected = selectedItems.has(key);
              const matchKeys = Object.keys(item.matches || {});
              const isConflict = item.action === 'conflict';

              return (
                <TableRow key={key} selected={isSelected}>
                  <TableCell padding="checkbox">
                    <Checkbox
                      checked={isSelected}
                      onChange={() => handleToggleItem(key)}
                      disabled={isConflict}
                    />
                  </TableCell>
                  <TableCell>{item.name || 'N/A'}</TableCell>
                  <TableCell>{item.ip || 'N/A'}</TableCell>
                  <TableCell>
                    <Tooltip title={item.mac || 'N/A'}>
                      <span>{item.mac || 'N/A'}</span>
                    </Tooltip>
                  </TableCell>
                  <TableCell>{item.serial || 'N/A'}</TableCell>
                  <TableCell>
                    {matchKeys.map((k) => (
                      <Chip key={k} label={k} size="small" sx={{ mr: 0.5 }} />
                    ))}
                    {matchKeys.length === 0 && 'None'}
                  </TableCell>
                  <TableCell>
                    <ActionChip action={item.action} />
                    {isConflict && (
                      <Tooltip title={item.reason}>
                        <Warning color="error" fontSize="small" sx={{ ml: 1 }} />
                      </Tooltip>
                    )}
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </TableContainer>
    );
  };

  if (isLoading) {
    return (
      <Container maxWidth="lg" sx={{ py: 4 }}>
        <PageHeader title="UISP Import" />
        <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
          <CircularProgress />
        </Box>
      </Container>
    );
  }

  return (
    <Container maxWidth="lg" sx={{ py: 4 }}>
      <PageHeader title="UISP Import" />

      <Can permission="integration.uisp.import">
        <>
          <Box sx={{ display: 'flex', gap: 2, mb: 3 }}>
            <Button variant="contained" startIcon={<Refresh />} onClick={handleAnalyze}>
              Analyze UISP Data
            </Button>
            <Button
              variant="contained"
              color="success"
              startIcon={<PlayArrow />}
              onClick={() => setImportDialogOpen(true)}
              disabled={selectedItems.size === 0}
            >
              Import Selected ({selectedItems.size})
            </Button>
            <Button variant="outlined" onClick={() => setSelectedItems(new Set())}>
              Clear Selection
            </Button>
          </Box>

          {renderSummary()}

          <Tabs value={selectedTab} onChange={(_, v) => setSelectedTab(v)} sx={{ mb: 2 }}>
            <Tab label={`Devices (${data?.devices?.total || 0})`} />
            <Tab label={`Sites (${data?.sites?.total || 0})`} />
          </Tabs>

          <Box sx={{ display: 'flex', gap: 2, mb: 2 }}>
            <TextField
              size="small"
              label="Search"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Name, serial, MAC, IP..."
            />
            <TextField
              select
              size="small"
              label="Filter by Action"
              value={filterAction}
              onChange={(e) => setFilterAction(e.target.value)}
              sx={{ minWidth: 150 }}
              SelectProps={{ native: true }}
            >
              <option value="all">All</option>
              <option value="create">Create</option>
              <option value="link">Link</option>
              <option value="update">Update</option>
              <option value="conflict">⚠️ Conflict</option>
              <option value="skip">Skip</option>
            </TextField>
          </Box>

          {selectedTab === 0 && renderDeviceTable()}

          {selectedTab === 1 && (
            <Alert severity="info">
              Site import preview will be available soon.
              Currently {data?.sites?.total || 0} sites discovered.
            </Alert>
          )}

          <Dialog open={importDialogOpen} onClose={() => setImportDialogOpen(false)}>
            <DialogTitle>Confirm Import</DialogTitle>
            <DialogContent>
              <DialogContentText>
                You are about to import {selectedItems.size} devices.
                {data?.devices?.analysis.filter((d: any) => d.action === 'conflict').length > 0 && (
                  <Alert severity="warning" sx={{ mt: 2 }}>
                    Some items have conflicts. Please review before proceeding.
                  </Alert>
                )}
                <Box sx={{ mt: 2 }}>
                  <Typography variant="body2" color="text.secondary">
                    This operation will:
                  </Typography>
                  <ul>
                    <li>Create new Assets where needed</li>
                    <li>Link existing Assets to UISP</li>
                    <li>Update existing Asset information</li>
                    <li>NOT delete any existing data</li>
                  </ul>
                </Box>
              </DialogContentText>
            </DialogContent>
            <DialogActions>
              <Button onClick={() => setImportDialogOpen(false)}>Cancel</Button>
              <Button
                variant="contained"
                color="success"
                onClick={handleImport}
                disabled={importMutation.isPending}
              >
                {importMutation.isPending ? <CircularProgress size={24} /> : 'Confirm Import'}
              </Button>
            </DialogActions>
          </Dialog>
        </>
      </Can>

      <Can permission="integration.uisp.import" fallback={<Alert severity="error">You don't have permission to access UISP Import.</Alert>}>
        <></>
      </Can>
    </Container>
  );
};

export default UispImportPage;
