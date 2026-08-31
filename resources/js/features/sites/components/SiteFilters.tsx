import { Stack, TextField, MenuItem, FormControl, InputLabel, Select, Button, Box } from '@mui/material';
import { Search, Refresh } from '@mui/icons-material';

interface SiteFiltersProps {
    search: string;
    onSearchChange: (val: string) => void;
    status: string;
    onStatusChange: (val: string) => void;
    type: string;
    onTypeChange: (val: string) => void;
    onReset: () => void;
}

export const SiteFilters = ({ 
    search, onSearchChange, 
    status, onStatusChange, 
    type, onTypeChange, 
    onReset 
}: SiteFiltersProps) => {
    return (
        <Box sx={{ mb: 3, p: 2, bgcolor: 'background.paper', borderRadius: 1, border: '1px solid', borderColor: 'divider' }}>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={2} alignItems="center">
                <TextField
                    placeholder="Search by name or code..."
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    fullWidth
                    size="small"
                    InputProps={{
                        startAdornment: <Search color="action" sx={{ mr: 1 }} />,
                    }}
                />
                <FormControl size="small" sx={{ minWidth: 120 }}>
                    <InputLabel>Status</InputLabel>
                    <Select value={status} label="Status" onChange={(e) => onStatusChange(e.target.value)}>
                        <MenuItem value="">All</MenuItem>
                        <MenuItem value="active">Active</MenuItem>
                        <MenuItem value="planned">Planned</MenuItem>
                        <MenuItem value="maintenance">Maintenance</MenuItem>
                        <MenuItem value="inactive">Inactive</MenuItem>
                    </Select>
                </FormControl>
                <FormControl size="small" sx={{ minWidth: 120 }}>
                    <InputLabel>Type</InputLabel>
                    <Select value={type} label="Type" onChange={(e) => onTypeChange(e.target.value)}>
                        <MenuItem value="">All</MenuItem>
                        <MenuItem value="pop">POP</MenuItem>
                        <MenuItem value="tower">Tower</MenuItem>
                        <MenuItem value="office">Office</MenuItem>
                        <MenuItem value="datacenter">Datacenter</MenuItem>
                    </Select>
                </FormControl>
                <Button 
                    variant="outlined" 
                    startIcon={<Refresh />} 
                    onClick={onReset}
                    sx={{ minWidth: 100 }}
                >
                    Reset
                </Button>
            </Stack>
        </Box>
    );
};
