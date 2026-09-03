import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box, Button, Container, Grid, Paper, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Typography, Chip, Checkbox, Tooltip,
  FormControl, InputLabel, Select, MenuItem, Dialog, DialogTitle, DialogContent, DialogActions
} from '@mui/material';
import { Refresh, PlayArrow, Warning, CheckCircle, Error as ErrorIcon } from '@mui/icons-material';
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

const ImportPage: React.FC = () => {
  const queryClient = useQueryClient();
  const [selectedProvider, setSelectedProvider] = useState<number | ''>('');
  const [sourceType, setSourceType] = useState<'devices' | 'sites'>('devices');
  const [selectedItems, setSelectedItems] = useState<Set<string>>(new Set());
  const [resultDialog, setResultDialog] = useState<any>(null);

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
      preview.records.forEach((_: ImportItem, idx: number) => {
        const item = preview.records[idx];
        if (item.analysis.decision !== 'CONFLICT') {
          newSet.add(`item-${idx}`);
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

  if (!providers) return null;

  return (
    <Container maxWidth="lg" sx={{ py: 4 }}>
      <PageHeader title="System Import" />
      
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
              {isPreviewing ? 'Fetching...' : 'Analyze Source'}
            </Button>
          </Grid>
        </Grid>
      </Paper>

      {preview && (
        <>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', mb: 2 }}>
            <Typography variant="h6">
              Results: {preview.total} records found from {preview.source}
            </Typography>
            <Box>
              <Button 
                variant="outlined" 
                onClick={() => setSelectedItems(new Set())}
                sx={{ mr: 1 }}
              >
                Clear
              </Button>
              <Button 
                variant="contained" 
                color="success" 
                startIcon={<PlayArrow />}
                onClick={() => importMutation.mutate()}
                disabled={selectedItems.size === 0 || importMutation.isPending}
              >
                Import Selected ({selectedItems.size})
              </Button>
            </Box>
          </Box>

          <TableContainer component={Paper}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell padding="checkbox">
                    <Checkbox
                      checked={selectedItems.size > 0 && selectedItems.size === preview.records.filter((r: ImportItem) => r.analysis.decision !== 'CONFLICT').length}
                      onChange={(e) => handleSelectAll(e.target.checked)}
                    />
                  </TableCell>
                  <TableCell>Name</TableCell>
                  <TableCell>IP Address</TableCell>
                  <TableCell>MAC Address</TableCell>
                  <TableCell>Serial</TableCell>
                  <TableCell>Decision</TableCell>
                  <TableCell>Evidence</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {preview.records.map((item: ImportItem, idx: number) => {
                  const key = `item-${idx}`;
                  const isConflict = item.analysis.decision === 'CONFLICT';
                  return (
                    <TableRow key={key} selected={selectedItems.has(key)}>
                      <TableCell padding="checkbox">
                        <Checkbox
                          checked={selectedItems.has(key)}
                          onChange={() => toggleItem(key)}
                          disabled={isConflict}
                        />
                      </TableCell>
                      <TableCell>{item.record.name || 'N/A'}</TableCell>
                      <TableCell>{item.record.ip_address || 'N/A'}</TableCell>
                      <TableCell>{item.record.mac_address || 'N/A'}</TableCell>
                      <TableCell>{item.record.serial_number || 'N/A'}</TableCell>
                      <TableCell>
                        <Chip 
                          label={item.analysis.decision} 
                          color={getDecisionColor(item.analysis.decision)}
                          size="small"
                          icon={item.analysis.decision === 'CONFLICT' ? <Warning /> : <CheckCircle />}
                        />
                      </TableCell>
                      <TableCell>
                        {item.analysis.evidence.map((ev, i) => (
                          <Tooltip key={i} title={`${ev.field}: ${ev.value}`}>
                            <Chip label={ev.field} size="small" sx={{ mr: 0.5 }} />
                          </Tooltip>
                        ))}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </TableContainer>
        </>
      )}

      <Dialog open={!!resultDialog} onClose={() => setResultDialog(null)} maxWidth="sm" fullWidth>
        <DialogTitle>Import Results</DialogTitle>
        <DialogContent>
          {resultDialog && (
            <Box>
              <Typography variant="body1" gutterBottom>
                Processed: {resultDialog.processed}
              </Typography>
              <Typography variant="body1" color="success.main">
                Created: {resultDialog.created}
              </Typography>
              <Typography variant="body1" color="primary.main">
                Linked: {resultDialog.linked}
              </Typography>
              <Typography variant="body1" color="error.main">
                Failed: {resultDialog.failed}
              </Typography>
              
              {resultDialog.details && resultDialog.details.length > 0 && (
                <TableContainer component={Paper} sx={{ mt: 2 }}>
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
