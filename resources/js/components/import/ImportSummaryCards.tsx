import React from 'react';
import { Grid, Paper, Typography } from '@mui/material';

interface SummaryData {
  total?: number;
  create?: number;
  update?: number;
  link?: number;
  skip?: number;
  conflicts?: number;
  errors?: number;
  review?: number;
}

interface ImportSummaryCardsProps {
  data?: SummaryData | null;
  loading?: boolean;
}

const SummaryCard: React.FC<{ title: string; count?: number; color: string; bgColor: string }> = ({ 
  title, count = 0, color, bgColor 
}) => (
  <Paper sx={{ p: 2, textAlign: 'center', bgcolor: bgColor }}>
    <Typography variant="h4" sx={{ color, fontWeight: 'bold' }}>{count}</Typography>
    <Typography variant="body2" color="text.secondary">{title}</Typography>
  </Paper>
);

const ImportSummaryCards: React.FC<ImportSummaryCardsProps> = ({ data, loading }) => {
  if (loading) return null;
  
  const safeData: SummaryData = data || {};

  return (
    <Grid container spacing={2} sx={{ mb: 3 }}>
      <Grid item xs={6} sm={3}>
        <SummaryCard title="Total Records" count={safeData.total} color="#1976d2" bgColor="#e3f2fd" />
      </Grid>
      <Grid item xs={6} sm={3}>
        <SummaryCard title="To Create" count={safeData.create} color="#2e7d32" bgColor="#e8f5e9" />
      </Grid>
      <Grid item xs={6} sm={3}>
        <SummaryCard title="To Update" count={safeData.update} color="#ed6c02" bgColor="#fff3e0" />
      </Grid>
      <Grid item xs={6} sm={3}>
        <SummaryCard title="Conflicts" count={safeData.conflicts} color="#d32f2f" bgColor="#ffebee" />
      </Grid>
    </Grid>
  );
};

export default ImportSummaryCards;
