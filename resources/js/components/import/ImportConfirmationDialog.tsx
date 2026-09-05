import React from 'react';
import {
  Dialog, DialogTitle, DialogContent, DialogActions,
  Button, Typography, Box, Alert, Divider
} from '@mui/material';

interface Summary {
  total?: number;
  create?: number;
  update?: number;
  conflicts?: number;
  errors?: number;
  skip?: number;
}

interface ImportConfirmationDialogProps {
  open: boolean;
  onClose: () => void;
  onConfirm: () => void;
  itemCount?: number;
  summary?: Summary | null;
  title?: string;
}

const ImportConfirmationDialog: React.FC<ImportConfirmationDialogProps> = ({
  open, onClose, onConfirm, itemCount = 0, summary, title = 'Confirm Import'
}) => {
  const safeSummary: Summary = summary || {};
  const hasBlockingIssues = (safeSummary.conflicts || 0) > 0 || (safeSummary.errors || 0) > 0;

  return (
    <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>{title}</DialogTitle>
      <DialogContent>
        <Box sx={{ mb: 2 }}>
          <Typography variant="body1">
            You are about to import <strong>{itemCount}</strong> selected record(s).
          </Typography>
        </Box>

        {hasBlockingIssues && (
          <Alert severity="error" sx={{ mb: 2 }}>
            {(safeSummary.conflicts || 0) > 0 && `${safeSummary.conflicts} record(s) have blocking conflicts. `}
            {(safeSummary.errors || 0) > 0 && `${safeSummary.errors} record(s) have errors. `}
            Please resolve these issues before importing.
          </Alert>
        )}

        <Divider sx={{ my: 2 }} />

        <Box sx={{ display: 'flex', justifyContent: 'space-around', textAlign: 'center' }}>
          <Box>
            <Typography variant="h6" color="success.main">{safeSummary.create || 0}</Typography>
            <Typography variant="caption">Create</Typography>
          </Box>
          <Box>
            <Typography variant="h6" color="warning.main">{safeSummary.update || 0}</Typography>
            <Typography variant="caption">Update</Typography>
          </Box>
          <Box>
            <Typography variant="h6" color="error.main">{safeSummary.conflicts || 0}</Typography>
            <Typography variant="caption">Conflicts</Typography>
          </Box>
        </Box>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Cancel</Button>
        <Button 
          variant="contained" 
          onClick={onConfirm} 
          disabled={hasBlockingIssues || itemCount === 0}
        >
          Confirm Import
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default ImportConfirmationDialog;
