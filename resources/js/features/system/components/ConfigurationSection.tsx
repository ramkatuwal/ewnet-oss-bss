import React from 'react';
import {
  Box,
  Card,
  CardContent,
  Typography,
  TextField,
  Switch,
  FormControlLabel,
  MenuItem,
  Divider,
  FormControl,
  InputLabel,
  Select,
  SelectChangeEvent,
} from '@mui/material';

interface ConfigurationSectionProps {
  title: string;
  children: React.ReactNode;
}

export const ConfigurationSection: React.FC<ConfigurationSectionProps> = ({ title, children }) => (
  <Card>
    <CardContent>
      <Typography variant="subtitle2" color="text.secondary" gutterBottom>
        {title}
      </Typography>
      <Divider sx={{ mb: 2 }} />
      <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {children}
      </Box>
    </CardContent>
  </Card>
);

export const ConfigTextField: React.FC<{
  label: string;
  value: string;
  onChange: (value: string) => void;
  required?: boolean;
  placeholder?: string;
}> = ({ label, value, onChange, required, placeholder }) => (
  <TextField
    label={label}
    value={value || ''}
    onChange={(e) => onChange(e.target.value)}
    required={required}
    placeholder={placeholder}
    fullWidth
    size="small"
  />
);

export const ConfigSwitch: React.FC<{
  label: string;
  value: boolean;
  onChange: (value: boolean) => void;
}> = ({ label, value, onChange }) => (
  <FormControlLabel
    control={<Switch checked={value} onChange={(e) => onChange(e.target.checked)} size="small" />}
    label={label}
  />
);

export const ConfigSelect: React.FC<{
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: { label: string; value: string }[];
}> = ({ label, value, onChange, options }) => (
  <FormControl fullWidth size="small">
    <InputLabel>{label}</InputLabel>
    <Select
      value={value}
      label={label}
      onChange={(e: SelectChangeEvent) => onChange(e.target.value)}
    >
      {options.map((opt) => (
        <MenuItem key={opt.value} value={opt.value}>
          {opt.label}
        </MenuItem>
      ))}
    </Select>
  </FormControl>
);

export const ConfigColorPicker: React.FC<{
  label: string;
  value: string;
  onChange: (value: string) => void;
}> = ({ label, value, onChange }) => (
  <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
    <TextField
      label={label}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      size="small"
      placeholder="#1976d2"
      sx={{ flex: 1 }}
    />
    <Box
      component="input"
      type="color"
      value={value}
      onChange={(e) => onChange(e.target.value)}
      sx={{
        width: 48,
        height: 48,
        padding: 0,
        border: '1px solid',
        borderColor: 'divider',
        borderRadius: 1,
        cursor: 'pointer',
      }}
    />
  </Box>
);
