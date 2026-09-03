import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box, Button, Container, Grid, Paper, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Typography, Chip, Checkbox,
  FormControl, InputLabel, Select, MenuItem, Dialog, DialogTitle, DialogContent, DialogActions,
  TextField, InputAdornment, IconButton, Collapse
} from '@mui/material';
import { 
  Refresh, PlayArrow, Warning, CheckCircle, Error as ErrorIcon, 
  Search, Clear, ExpandMore, ExpandLess 
} from '@mui/icons-material';
import { PageHeader } from '@/components/layout/PageHeader';
import toast from 'react-hot-toast';
import { importApi, ImportItem, ImportProvider } from '@/api/import';

const getDecisionColor = (decision: string): 'success' | 'primary' | 'warning' | 'error' | 'default' => {
  switch (decision) {
    case 'CREATE': return 'success';
    case 'LINK': return 'primary';
    case 'REVIEW': return 'warning';
    case 'CONFLICT': return 'error';
    default: return 'default';
  }
};

const SummaryCard: React.FC<{ title: string; count: number; color: string }> = ({ title, count, color }) => (
  <Paper sx={{ p: 2, textAlign: 'center', bgcolor: color, color: 'white', height: '100%' }}>
    <Typography variant="h4">{count}</Typography>
    <Typography variant="body2">{title}</Typography>
  </Paper>
);

const ImportPage: React.FC = () => {
  const queryClient = useQueryClient();
  const [selectedProvider, setSelectedProvider] = useState<number | ''>('');
  const [sourceType, setSourceType] = useState<'devices' | 'sites'>('devices');
  const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set());
  const [resultDialog, setResultDialog] = useState<any>(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [filterDecision, setFilterDecision] = useState<string>('ALL');
  const [expandedRow, setExpandedRow] = useState<string | null>(null);

  const { data: providers } = useQuery({
    queryKey: ['import-providers'],
    queryFn: importApi.getProviders,
  });

  const { data: preview, isLoading: isPreviewing, refetch: runPreview } = useQuery({
    queryKey: ['import-preview', selectedProvider, sourceType],
    queryFn: () => importApi.preview(Number(selectedProvider), sourceType),
    enabled: !!selectedProvider,
    retry: false,
  });

  const importMutation = useMutation({
    mutationFn: () => {
      if (!preview || !selectedProvider) throw new Error('No preview data');
      const itemsToImport = preview.records.filter((_: ImportItem, idx: number) => 
        selectedItems.has(`item-${idx}`)
      );
      return importApi.execute(selectedProvider, itemsToImport);
    },
    onSuccess: (data) => {
      setResultDialog(data.data);
      queryClient.invalidateQueries({ queryKey: ['import-preview'] });
      setSelectedItems(new Set());
    },
    onError: (error: any) => {
      toast.error(error.response?.data?.message || 'Import request failed');
    },
  });

  const handleSelectAll = (checked: boolean) => {
    if (!preview) return;
    const newSet = new Set<string>();
    if (checked) {
      filteredRecords.forEach((item) => {
        const originalIdx = preview.records.indexOf(item);
        if (originalIdx !== -1 && item.analysis.decision !== 'CONFLICT') {
          newSet.add(`item-${originalIdx}`);
        }
      });
    }
    setSelectedItems(newSet);
  };

  const toggleItem = (key: string) => {
    const newSet = new Set(selectedItems);
    if (newSet.has(key)) newSet.delete(key);
    else newSet.add(key);
    setSelectedItems(newSet);
  };

  const toggleExpand = (key: string) => {
    setExpandedRow(expandedRow === key ? null : key);
  };

  // Filtering and Search Logic
  const filteredRecords = useMemo(() => {
    if (!preview) return [];
    return preview.records.filter((item) => {
      const matchesSearch = 
        item.record.name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.record.ip_address?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.record.mac_address?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.record.serial_number?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.record.model?.toLowerCase().includes(searchTerm.toLowerCase());
      
      const matchesFilter = filterDecision === 'ALL' || item.analysis.decision === filterDecision;
      
      return matchesSearch && matchesFilter;
    });
  }, [preview, searchTerm, filterDecision]);

  // Summary Counts
  const counts = useMemo(() => {
    if (!preview) return { total: 0, create: 0, link: 0, review: 0, conflict: 0 };
    const c = { total: preview.total, create: 0, link: 0, review: 0, conflict: 0 };
    preview.records.forEach(r => {
      if (r.analysis.decision === 'CREATE') c.create++;
      if (r.analysis.decision === 'LINK') c.link++;
      if (r.analysis.decision === 'REVIEW') c.review++;
      if (r.analysis.decision === 'CONFLICT') c.conflict++;
    });
    return c;
  }, [preview]);

  if (!providers) return null;

  const isSites = sourceType === 'sites';

  return (
    <Container maxWidth="lg" sx={{ py: 4 }}>
      <PageHeader title="System Import" />
      
      {/* Source & Destination Selection */}
      <Paper sx={{ p: 3, mb: 3 }}>
        <Grid container spacing={3} alignItems="center">
          <Grid item xs={12} md={4}>
            <FormControl fullWidth>
              <InputLabel>Source Provider</InputLabel>
              <Select
                value={selectedProvider}
                label="Source Provider"
                onChange={(e) => setSelectedProvider(e.target.value as number)}
              >
                {providers.map((p: ImportProvider) => (
                  <MenuItem key={p.id} value={p.id}>{p.name}</MenuItem>
                ))}
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12} md={4}>
            <FormControl fullWidth>
              <InputLabel>Destination Type</InputLabel>
              <Select
                value={sourceType}
                label="Destination Type"
                onChange={(e) => setSourceType(e.target.value as 'devices' | 'sites')}
              >
                <MenuItem value="devices">Assets (Devices)</MenuItem>
                <MenuItem value="sites">Sites</MenuItem>
              </Select>
            </FormControl>
          </Grid>
          <Grid item xs={12} md={4}>
            <Button 
              variant="contained" 
              startIcon={<Refresh />} 
              onClick={() => runPreview()}
              disabled={!selectedProvider || isPreviewing}
              fullWidth
            >
              {isPreviewing ? 'Loading Source Data...' : 'Load Source Data'}
            </Button>
          </Grid>
        </Grid>
      </Paper>

      {preview && (
        <>
          {/* Summary Cards */}
          <Grid container spacing={2} sx={{ mb: 3 }}>
            <Grid item xs={6} sm={2.4}><SummaryCard title="Total Records" count={counts.total} color="#1976d2" /></Grid>
            <Grid item xs={6} sm={2.4}><SummaryCard title="Create" count={counts.create} color="#2e7d32" /></Grid>
            <Grid item xs={6} sm={2.4}><SummaryCard title="Link" count={counts.link} color="#0288d1" /></Grid>
            <Grid item xs={6} sm={2.4}><SummaryCard title="Review" count={counts.review} color="#ed6c02" /></Grid>
            <Grid item xs={6} sm={2.4}><SummaryCard title="Conflict" count={counts.conflict} color="#d32f2f" /></Grid>
          </Grid>

          {/* Search & Filters */}
          <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2, flexWrap: 'wrap', gap: 2 }}>
            <TextField
              size="small"
              placeholder={`Search ${isSites ? 'sites' : 'devices'}...`}
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              InputProps={{
                startAdornment: <InputAdornment position="start"><Search /></InputAdornment>,
                endAdornment: searchTerm && (
                  <InputAdornment position="end">
                    <IconButton size="small" onClick={() => setSearchTerm('')}><Clear /></IconButton>
                  </InputAdornment>
                ),
              }}
              sx={{ minWidth: 250 }}
            />
            <Box sx={{ display: 'flex', gap: 1 }}>
              {['ALL', 'CREATE', 'LINK', 'REVIEW', 'CONFLICT'].map((dec) => (
                <Chip 
                  key={dec}
                  label={`${dec} (${dec === 'ALL' ? counts.total : counts[dec.toLowerCase() as keyof typeof counts]})`}
                  onClick={() => setFilterDecision(dec)}
                  color={filterDecision === dec ? 'primary' : 'default'}
                  variant={filterDecision === dec ? 'filled' : 'outlined'}
                  size="small"
                />
              ))}
            </Box>
          </Box>

          {/* Action Bar */}
          <Box sx={{ display: 'flex', justifyContent: 'flex-end', mb: 2 }}>
            <Button 
              variant="outlined" 
              onClick={() => setSelectedItems(new Set())}
              sx={{ mr: 1 }}
              disabled={selectedItems.size === 0}
            >
              Clear Selection
            </Button>
            <Button 
              variant="contained" 
              color="success" 
              startIcon={<PlayArrow />}
              onClick={() => importMutation.mutate()}
              disabled={selectedItems.size === 0 || importMutation.isPending}
            >
              {importMutation.isPending ? 'Importing...' : `Import Selected (${selectedItems.size})`}
            </Button>
          </Box>

          {/* Data Table */}
          <TableContainer component={Paper}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell padding="checkbox" width={50}>
                    <Checkbox
                      checked={filteredRecords.length > 0 && filteredRecords.every(r => {
                        const idx = preview.records.indexOf(r);
                        return selectedItems.has(`item-${idx}`);
                      })}
                      onChange={(e) => handleSelectAll(e.target.checked)}
                    />
                  </TableCell>
                  <TableCell width={40}></TableCell>
                  <TableCell>{isSites ? 'Site Name' : 'Device Name'}</TableCell>
                  {!isSites && <TableCell>IP Address</TableCell>}
                  {!isSites && <TableCell>MAC Address</TableCell>}
                  {!isSites && <TableCell>Serial</TableCell>}
                  {isSites && <TableCell>Address</TableCell>}
                  <TableCell>Decision</TableCell>
                  <TableCell>Destination</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {filteredRecords.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={9} align="center" sx={{ py: 4 }}>
                      <Typography color="text.secondary">
                        {searchTerm ? 'No matching records found.' : 'No records available for this source.'}
                      </Typography>
                    </TableCell>
                  </TableRow>
                ) : (
                  filteredRecords.map((item) => {
                    const originalIdx = preview.records.indexOf(item);
                    const key = `item-${originalIdx}`;
                    const isConflict = item.analysis.decision === 'CONFLICT';
                    const isExpanded = expandedRow === key;

                    return (
                      <React.Fragment key={key}>
                        <TableRow selected={selectedItems.has(key)}>
                          <TableCell padding="checkbox">
                            <Checkbox
                              checked={selectedItems.has(key)}
                              onChange={() => toggleItem(key)}
                              disabled={isConflict}
                            />
                          </TableCell>
                          <TableCell>
                            <IconButton size="small" onClick={() => toggleExpand(key)}>
                              {isExpanded ? <ExpandLess /> : <ExpandMore />}
                            </IconButton>
                          </TableCell>
                          <TableCell>{item.record.name || 'N/A'}</TableCell>
                          {isSites ? (
                            <TableCell>{String(item.record.metadata?.address || 'N/A')}</TableCell>
                          ) : (
                            <>
                              <TableCell>{item.record.ip_address || 'N/A'}</TableCell>
                              <TableCell>{item.record.mac_address || 'N/A'}</TableCell>
                              <TableCell>{item.record.serial_number || 'N/A'}</TableCell>
                            </>
                          )}
                          <TableCell>
                            <Chip 
                              label={item.analysis.decision} 
                              color={getDecisionColor(item.analysis.decision)}
                              size="small"
                              icon={item.analysis.decision === 'CONFLICT' ? <Warning /> : <CheckCircle />}
                            />
                          </TableCell>
                          <TableCell>
                            {item.analysis.destination_id ? `ID #${item.analysis.destination_id}` : '-'}
                          </TableCell>
                        </TableRow>
                        <TableRow>
                          <TableCell style={{ paddingBottom: 0, paddingTop: 0 }} colSpan={9}>
                            <Collapse in={isExpanded} timeout="auto" unmountOnExit>
                              <Box sx={{ margin: 2, p: 2, bgcolor: 'background.default' }}>
                                <Typography variant="subtitle2" gutterBottom>Source Details</Typography>
                                <Grid container spacing={2}>
                                  <Grid item xs={6}><Typography variant="caption">External ID:</Typography> <Typography variant="body2">{item.record.external_id}</Typography></Grid>
                                  <Grid item xs={6}><Typography variant="caption">Provider:</Typography> <Typography variant="body2">{item.record.provider}</Typography></Grid>
                                  {!isSites && <Grid item xs={6}><Typography variant="caption">Model:</Typography> <Typography variant="body2">{item.record.model || 'N/A'}</Typography></Grid>}
                                  {!isSites && <Grid item xs={6}><Typography variant="caption">Manufacturer:</Typography> <Typography variant="body2">{item.record.manufacturer || 'N/A'}</Typography></Grid>}
                                </Grid>
                                
                                <Typography variant="subtitle2" gutterBottom sx={{ mt: 2 }}>Reconciliation Evidence</Typography>
                                {item.analysis.evidence.length > 0 ? (
                                  item.analysis.evidence.map((ev, i) => (
                                    <Chip key={i} label={`${ev.field}: ${ev.value} (${ev.strength})`} size="small" sx={{ mr: 1, mb: 1 }} />
                                  ))
                                ) : (
                                  <Typography variant="body2" color="text.secondary">No matching evidence found.</Typography>
                                )}
                                
                                {item.analysis.reason && (
                                  <Box sx={{ mt: 1 }}>
                                    <Typography variant="caption" color="error.main">Reason:</Typography>
                                    <Typography variant="body2">{item.analysis.reason}</Typography>
                                  </Box>
                                )}
                              </Box>
                            </Collapse>
                          </TableCell>
                        </TableRow>
                      </React.Fragment>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </TableContainer>
        </>
      )}

      {/* Result Dialog */}
      <Dialog open={!!resultDialog} onClose={() => setResultDialog(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Import Results</DialogTitle>
        <DialogContent>
          {resultDialog && (
            <Box>
              <Typography variant="h6" gutterBottom>
                {resultDialog.failed > 0 ? 'Import Completed With Errors' : 'Import Completed Successfully'}
              </Typography>
              <Grid container spacing={2} sx={{ mb: 2 }}>
                <Grid item xs={4}><Typography color="text.secondary">Processed:</Typography> <Typography fontWeight="bold">{resultDialog.processed}</Typography></Grid>
                <Grid item xs={4}><Typography color="success.main">Created:</Typography> <Typography fontWeight="bold">{resultDialog.created}</Typography></Grid>
                <Grid item xs={4}><Typography color="primary.main">Linked:</Typography> <Typography fontWeight="bold">{resultDialog.linked}</Typography></Grid>
                <Grid item xs={4}><Typography color="error.main">Failed:</Typography> <Typography fontWeight="bold">{resultDialog.failed}</Typography></Grid>
              </Grid>
              
              {resultDialog.details && resultDialog.details.length > 0 && (
                <TableContainer component={Paper} variant="outlined">
                  <Table size="small">
                    <TableHead>
                      <TableRow>
                        <TableCell>Name</TableCell>
                        <TableCell>Status</TableCell>
                        <TableCell>Message</TableCell>
                      </TableRow>
                    </TableHead>
                    <TableBody>
                      {resultDialog.details.map((d: any, i: number) => (
                        <TableRow key={i}>
                          <TableCell>{d.name}</TableCell>
                          <TableCell>
                            <Chip 
                              label={d.status} 
                              color={d.status === 'success' ? 'success' : 'error'} 
                              size="small" 
                              icon={d.status === 'success' ? <CheckCircle /> : <ErrorIcon />}
                            />
                          </TableCell>
                          <TableCell>{d.message}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </TableContainer>
              )}
            </Box>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setResultDialog(null)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Container>
  );
};

export default ImportPage;
