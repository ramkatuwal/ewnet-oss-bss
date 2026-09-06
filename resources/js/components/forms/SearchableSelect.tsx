import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
    Autocomplete,
    AutocompleteChangeReason,
    AutocompleteRenderInputParams,
    AutocompleteRenderOptionState,
    Box,
    TextField,
} from '@mui/material';
import type { HTMLAttributes } from 'react';

export interface SearchableSelectOption<T = unknown> {
    value: T;
    label: string;
    secondary?: string;
    disabled?: boolean;
}

export type SearchableSelectValue<T = unknown> = SearchableSelectOption<T> | null;

interface SearchableSelectProps<T extends SearchableSelectOption = SearchableSelectOption> {
    label: string;
    value: T | null;
    onChange: (value: T | null) => void;
    options?: T[];
    loadOptions?: (query: string) => Promise<T[]>;
    getOptionLabel?: (option: T) => string;
    getOptionSecondary?: (option: T) => string | undefined;
    valueLabel?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    loading?: boolean;
    error?: boolean;
    helperText?: string;
    fullWidth?: boolean;
    clearable?: boolean;
    size?: 'small' | 'medium';
    minWidth?: number | string;
    filterOptions?: boolean;
    autoHighlight?: boolean;
    renderOption?: (props: HTMLAttributes<HTMLLIElement>, option: T, state: AutocompleteRenderOptionState) => React.ReactNode;
    onOpen?: () => void;
}

const defaultGetLabel = (option: SearchableSelectOption): string => option.label;

const SearchableSelect = <T extends SearchableSelectOption = SearchableSelectOption>({
    label,
    value,
    onChange,
    options = [],
    loadOptions,
    getOptionLabel = defaultGetLabel,
    getOptionSecondary,
    valueLabel,
    placeholder = 'Type to search...',
    required = false,
    disabled = false,
    loading = false,
    error = false,
    helperText,
    fullWidth = false,
    clearable = true,
    size = 'small',
    minWidth,
    filterOptions = true,
    autoHighlight = true,
    renderOption,
    onOpen,
}: SearchableSelectProps<T>): React.JSX.Element => {
    const [query, setQuery] = useState('');
    const [loadedOptions, setLoadedOptions] = useState<T[]>([]);
    const [isLoadingOptions, setIsLoadingOptions] = useState(false);
    const [open, setOpen] = useState(false);
    const debounceRef = useRef<number | null>(null);
    const lastResolveRef = useRef(0);

    const isAsync = !!loadOptions;

    // Reset loaded options on open so the dropdown begins from a minimal fetch.
    useEffect(() => {
        if (!isAsync) return;
        if (open && loadedOptions.length === 0 && !value) {
            loadOptionsQuery('');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const loadOptionsQuery = async (q: string) => {
        if (!loadOptions) return;
        const requestId = ++lastResolveRef.current;
        setIsLoadingOptions(true);
        try {
            const results = await loadOptions(q);
            if (requestId === lastResolveRef.current) {
                setLoadedOptions(results);
            }
        } finally {
            if (requestId === lastResolveRef.current) {
                setIsLoadingOptions(false);
            }
        }
    };

    const handleInputChange = (_event: unknown, newQuery: string) => {
        setQuery(newQuery);
        if (!isAsync) return;
        if (debounceRef.current !== null) {
            window.clearTimeout(debounceRef.current);
        }
        debounceRef.current = window.setTimeout(() => {
            loadOptionsQuery(newQuery);
        }, 300);
    };

    useEffect(() => () => {
        if (debounceRef.current !== null) {
            window.clearTimeout(debounceRef.current);
        }
    }, []);

    const displayValue = useMemo(() => {
        if (value) return value;
        if (valueLabel) {
            return { value: (value as T | null)?.value, label: valueLabel, secondary: undefined } as T;
        }
        return null;
    }, [value, valueLabel]) as T | null;

    // For async loads, "options" are the server-filtered results; for static mode
    // the caller-supplied list is filtered client-side by the internal query.
    const effectiveOptions = isAsync ? loadedOptions : query.trim() && filterOptions
        ? options.filter((o) => {
            const labelText = getOptionLabel(o).toLowerCase();
            const secondaryText = getOptionSecondary?.(o)?.toLowerCase() ?? '';
            return labelText.includes(query.toLowerCase()) || secondaryText.includes(query.toLowerCase());
        })
        : options;

    const isOptionEqualToValue = (option: T, candidate: T) => {
        const optionValue = (option as SearchableSelectOption).value;
        const candidateValue = (candidate as SearchableSelectOption).value;
        return optionValue !== undefined && candidateValue !== undefined && optionValue === candidateValue;
    };

    const renderTextInput = (params: AutocompleteRenderInputParams) => (
        <TextField
            {...params}
            label={label}
            placeholder={placeholder}
            required={required}
            error={error}
            helperText={helperText}
            size={size}
            inputProps={{
                ...params.inputProps,
                'aria-label': label,
            }}
        />
    );

    const fallbackRenderOption = (props: HTMLAttributes<HTMLLIElement>, option: T) => {
        const secondary = getOptionSecondary?.(option);
        return (
            <li {...props}>
                <Box>
                    <Box component="span">{getOptionLabel(option)}</Box>
                    {secondary && (
                        <Box component="span" sx={{ ml: 1, color: 'text.secondary', fontSize: '0.75rem' }}>
                            {secondary}
                        </Box>
                    )}
                </Box>
            </li>
        );
    };

    return (
        <Autocomplete<T, false, boolean, false>
            options={effectiveOptions}
            value={displayValue}
            onChange={(_event, newValue, reason: AutocompleteChangeReason) => {
                const next = newValue ?? null;
                onChange(next);
                if (reason === 'clear') {
                    setQuery('');
                }
            }}
            getOptionLabel={getOptionLabel}
            isOptionEqualToValue={isOptionEqualToValue}
            freeSolo={false}
            autoHighlight={autoHighlight}
            clearOnBlur
            disableClearable={!clearable}
            disabled={disabled}
            loading={isLoadingOptions || loading}
            onOpen={() => { setOpen(true); onOpen?.(); }}
            onClose={() => setOpen(false)}
            onInputChange={handleInputChange}
            filterOptions={(list) => list}
            renderInput={renderTextInput}
            renderOption={renderOption ?? fallbackRenderOption}
            noOptionsText={disabled ? '' : (query ? 'No matching options' : 'Type to search...')}
            loadingText="Loading..."
            size={size}
            fullWidth={fullWidth}
            sx={minWidth !== undefined ? { minWidth } : undefined}
        />
    );
};

export default SearchableSelect;