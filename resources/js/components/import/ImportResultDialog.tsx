import React from 'react';
import {
  Dialog, DialogTitle, DialogContent, DialogActions,
  Button, Typography, Box
} from '@mui/material';

interface ImportResult {
  created?: number;
  updated?: number;
  skipped?: number;
  failed?: number;
  conflicts?: number;
  history_id?: number;
}

interface ImportResultDialogProps {
  open: boolean;
  onClose: () => void;
  result?: ImportResult | null;
}

const ImportResultDialog: React.FC<ImportResultDialogProps> = ({ open, onClose, result }) => {
  const safeResult: ImportResult = result || {};

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>Import Complete</DialogTitle>
      <DialogContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-around', textAlign: 'center', py: 2 }}>
          <Box>
            <Typography variant="h5" color="success.main">{safeResult.created || 0}</Typography>
            <Typography variant="body2">Created</Typography>
          </Box>
          <Box>
            <Typography variant="h5" color="info.main">{safeResult.updated || 0}</Typography>
            <Typography variant="body2">Updated</Typography>
          </Box>
          <Box>
            <Typography variant="h5" color="warning.main">{safeResult.skipped || 0}</Typography>
            <Typography variant="body2">Skipped</Typography>
          </Box>
          <Box>
            <Typography variant="h5" color="error.main">{safeResult.failed || 0}</Typography>
            <Typography variant="body2">Failed</Typography>
          </Box>
        </Box>
        
        {(safeResult.conflicts || 0) > 0 && (
          <Box sx={{ mt: 2, textAlign: 'center' }}>
            <Typography variant="h6" color="error.main">{safeResult.conflicts}</Typography>
            <Typography variant="body2">Unresolved Conflicts</Typography>
          </Box>
        )}
      </DialogContent>
      <DialogActions>
        <Button variant="contained" onClick={onClose}>Close</Button>
      </DialogActions>
    </Dialog>
  );
};

export default ImportResultDialog;
