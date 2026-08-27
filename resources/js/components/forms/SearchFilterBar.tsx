import { Box, TextField, InputAdornment } from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';

interface SearchFilterBarProps {
    searchValue: string;
    onSearchChange: (value: string) => void;
    placeholder?: string;
    children?: React.ReactNode;
}

export const SearchFilterBar = ({
    searchValue,
    onSearchChange,
    placeholder = 'Search...',
    children,
}: SearchFilterBarProps) => {
    return (
        <Box sx={{ display: 'flex', gap: 2, mb: 3, flexWrap: 'wrap', alignItems: 'center' }}>
            <TextField
                size="small"
                placeholder={placeholder}
                value={searchValue}
                onChange={(e) => onSearchChange(e.target.value)}
                slotProps={{
                    input: {
                        startAdornment: (
                            <InputAdornment position="start">
                                <SearchIcon color="action" />
                            </InputAdornment>
                        ),
                    },
                }}
                sx={{ minWidth: 280 }}
            />
            {children}
        </Box>
    );
};
