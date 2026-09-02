import React, { useState, useEffect } from 'react';
import {
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Chip,
  Container,
  FormControlLabel,
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
  TabPanel,
  useTheme,
  IconButton,
  Tooltip,
  Collapse,
} from '@mui/material';
import {
  Refresh,
  PlayArrow,
  CheckCircle,
  Cancel,
  Warning,
  Info,
  ExpandMore,
  ExpandLess,
} from '@mui/icons-material';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useSnackbar } from 'notistack';
import axios from 'axios';
import PageHeader from '../../../components/layout/PageHeader';

interface AnalysisResult {
  action: 'create' | 'link' | 'update' | 'conflict' | 'skip' | 'error';
  confidence: 'exact' | 'strong' | 'moderate' | 'weak' | 'none' | 'conflict';
  reason: string;
  asset_id: number | null;
  asset: any | null;
  matches: Record<string, boolean>;
  serial?: string | null;
  mac?: string | null;
  name?: string | null;
  ip?: string | null;
  site_id?: number | null;
  site?: any | null;
}

const getActionColor = (action: string): string => {
  const colors: Record<string, string> = {
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
  return <Chip label={label} color={color as any} size="small" variant="outlined" />;
};

const UispImportPage: React.FC = () => {
  const theme = useTheme();
  const { enqueueSnackbar } = useSnackbar();
  const [selectedTab, setSelectedTab] = useState(0);
  const [selectedActions, setSelectedActions] = useState<Set<string>>(new Set());
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
      enqueueSnackbar('Import completed successfully', { variant: 'success' });
      setImportDialogOpen(false);
    },
    onError: (error: any) => {
      enqueueSnackbar(error.response?.data?.error || 'Import failed', { variant: 'error' });
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
      items.forEach((item: any, index: number) => {
        if (item.action !== 'conflict') {
          newSet.add(`device-${index}`);
        }
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
      // Build selected records from selectedItems
      data.devices?.analysis.forEach((item: any, index: number) => {
        if (selectedItems.has(`device-${index}`)) {
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
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'success.light', color: 'white' }}>
            <Typography variant="h4">{total}</Typography>
            <Typography variant="body2">Total Devices</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={3}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'primary.light', color: 'white' }}>
            <Typography variant="h4">{summary.link || 0}</Typography>
            <Typography variant="body2">Link</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={2}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'info.light', color: 'white' }}>
            <Typography variant="h4">{summary.create || 0}</Typography>
            <Typography variant="body2">Create</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={2}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'error.light', color: 'white' }}>
            <Typography variant="h4">{summary.conflict || 0}</Typography>
            <Typography variant="body2">Conflict</Typography>
          </Paper>
        </Grid>
        <Grid item xs={12} md={2}>
          <Paper sx={{ p: 2, textAlign: 'center', bgcolor: 'warning.light', color: 'white' }}>
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
    const filtered = devices.filter((item: any, index: number) => {
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
              <TableCell>Type</TableCell>
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
                  <TableCell>
                    <Chip size="small" label="Device" />
                  </TableCell>
                  <TableCell>{item.ip || 'N/A'}</TableCell>
                  <TableCell>
                    <Tooltip title={item.mac || 'N/A'}>
                      <span>{item.mac || 'N/A'}</span>
                    </Tooltip>
                  </TableCell>
                  <TableCell>{item.serial || 'N/A'}</TableCell>
                  <TableCell>
                    {matchKeys.map((key) => (
                      <Chip key={key} label={key} size="small" sx={{ mr: 0.5 }} />
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

      <Tabs value={selectedTab} onChange={(e, v) => setSelectedTab(v)} sx={{ mb: 2 }}>
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
          Site import preview will be available in the next iteration.
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
                Some selected items have conflicts. Please review before proceeding.
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
    </Container>
  );
};

export default UispImportPage;
