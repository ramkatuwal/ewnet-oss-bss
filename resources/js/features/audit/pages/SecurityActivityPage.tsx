import React, { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
    Box,
    Typography,
    Chip,
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    Button,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    TextField,
    Stack,
    Paper,
} from '@mui/material';
import {
    Visibility as ViewIcon,
    FilterList as FilterIcon,
    Clear as ClearIcon,
} from '@mui/icons-material';
import { PageHeader } from '@/components/layout/PageHeader';
import { DataTable, type Column } from '@/components/tables/DataTable';
import { SearchFilterBar } from '@/components/forms/SearchFilterBar';
import { useToast } from '@/components/feedback/ToastProvider';
import { getErrorMessage, formatDateTime } from '@/utils';
import { auditApi, type AuditLog, type AuditLogFilters } from '@/api/audit';

export const SecurityActivityPage = () => {
    const { showToast } = useToast();

    const [page, setPage] = useState(0);
    const [rowsPerPage, setRowsPerPage] = useState(25);
    const [search, setSearch] = useState('');
    const [filters, setFilters] = useState<AuditLogFilters>({});
    const [showFilters, setShowFilters] = useState(false);
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);

    const { data, isLoading, error } = useQuery({
        queryKey: ['audit-logs', page, rowsPerPage, search, filters],
        queryFn: () =>
            auditApi.getAll({
                page: page + 1,
                per_page: rowsPerPage,
                search: search || undefined,
                ...filters,
            }),
    });

    const handleViewDetail = (log: AuditLog) => {
        setSelectedLog(log);
    };

    const handleClearFilters = () => {
        setFilters({});
        setSearch('');
        setPage(0);
    };

    const handleApplyFilters = (newFilters: AuditLogFilters) => {
        setFilters(newFilters);
        setPage(0);
        setShowFilters(false);
    };

    const columns: Column<AuditLog>[] = useMemo(
        () => [
            {
                key: 'created_at',
                label: 'Timestamp',
                width: '15%',
                render: (row) => (
                    <Typography variant="body2">
                        {formatDateTime(row.created_at)}
                    </Typography>
                ),
            },
            {
                key: 'actor',
                label: 'Actor',
                width: '20%',
                render: (row) => (
                    <Box>
                        <Typography variant="body2" fontWeight={500}>
                            {row.actor.name || row.actor.email || 'System'}
                        </Typography>
                        {row.actor.email && row.actor.name && (
                            <Typography variant="caption" color="text.secondary">
                                {row.actor.email}
                            </Typography>
                        )}
                    </Box>
                ),
            },
            {
                key: 'action',
                label: 'Event',
                width: '20%',
                render: (row) => (
                    <Chip
                        label={row.action}
                        size="small"
                        color="primary"
                        variant="outlined"
                    />
                ),
            },
            {
                key: 'target',
                label: 'Target',
                width: '20%',
                render: (row) => (
                    <Box>
                        {row.target.name ? (
                            <Typography variant="body2">{row.target.name}</Typography>
                        ) : row.target.type ? (
                            <Typography variant="caption" color="text.secondary">
                                {row.target.type.split('\\').pop()} #{row.target.id}
                            </Typography>
                        ) : (
                            <Typography variant="caption" color="text.secondary">
                                —
                            </Typography>
                        )}
                    </Box>
                ),
            },
            {
                key: 'result',
                label: 'Result',
                align: 'center',
                width: '10%',
                render: (row) => (
                    <Chip
                        label={row.result}
                        size="small"
                        color={row.result === 'success' ? 'success' : 'error'}
                        variant="filled"
                    />
                ),
            },
            {
                key: 'ip_address',
                label: 'IP Address',
                width: '10%',
                render: (row) => (
                    <Typography variant="caption" fontFamily="monospace">
                        {row.ip_address || '—'}
                    </Typography>
                ),
            },
        ],
        []
    );

    const actions = useMemo(
        () => [
            {
                icon: <ViewIcon fontSize="small" />,
                label: 'View Details',
                onClick: handleViewDetail,
            },
        ],
        []
    );

    const activeFilterCount = Object.keys(filters).filter((k) => filters[k as keyof AuditLogFilters]).length;

    if (error) {
        showToast(getErrorMessage(error), 'error');
    }

    return (
        <>
            <PageHeader
                title="Security Activity"
                subtitle="Audit log of security-sensitive operations"
                breadcrumbs={[
                    { label: 'Audit' },
                    { label: 'Security Activity' },
                ]}
            />

            <SearchFilterBar
                searchValue={search}
                onSearchChange={(v) => {
                    setSearch(v);
                    setPage(0);
                }}
                placeholder="Search by action, actor, IP, metadata..."
            >
                <Button
                    variant={activeFilterCount > 0 ? 'contained' : 'outlined'}
                    startIcon={<FilterIcon />}
                    onClick={() => setShowFilters(true)}
                >
                    Filters {activeFilterCount > 0 && `(${activeFilterCount})`}
                </Button>
                {activeFilterCount > 0 && (
                    <Button
                        variant="text"
                        startIcon={<ClearIcon />}
                        onClick={handleClearFilters}
                    >
                        Clear
                    </Button>
                )}
            </SearchFilterBar>

            <DataTable<AuditLog>
                columns={columns}
                data={data?.data ?? []}
                loading={isLoading}
                page={page}
                rowsPerPage={rowsPerPage}
                total={data?.total ?? 0}
                onPageChange={setPage}
                onRowsPerPageChange={(rpp) => {
                    setRowsPerPage(rpp);
                    setPage(0);
                }}
                actions={actions}
                emptyMessage="No security activity found."
            />

            <FilterDialog
                open={showFilters}
                onClose={() => setShowFilters(false)}
                currentFilters={filters}
                onApply={handleApplyFilters}
            />

            <AuditDetailDialog
                log={selectedLog}
                onClose={() => setSelectedLog(null)}
            />
        </>
    );
};

interface FilterDialogProps {
    open: boolean;
    onClose: () => void;
    currentFilters: AuditLogFilters;
    onApply: (filters: AuditLogFilters) => void;
}

const FilterDialog = ({ open, onClose, currentFilters, onApply }: FilterDialogProps) => {
    const [localFilters, setLocalFilters] = useState<AuditLogFilters>(currentFilters);

    React.useEffect(() => {
        if (open) {
            setLocalFilters(currentFilters);
        }
    }, [open, currentFilters]);

    const handleApply = () => {
        onApply(localFilters);
    };

    const handleClear = () => {
        setLocalFilters({});
    };

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>Filter Audit Logs</DialogTitle>
            <DialogContent>
                <Stack spacing={2} sx={{ mt: 1 }}>
                    <FormControl fullWidth size="small">
                        <InputLabel>Action</InputLabel>
                        <Select
                            value={localFilters.action || ''}
                            label="Action"
                            onChange={(e) =>
                                setLocalFilters({ ...localFilters, action: e.target.value || undefined })
                            }
                        >
                            <MenuItem value="">All Actions</MenuItem>
                            <MenuItem value="auth.login.success">Login Success</MenuItem>
                            <MenuItem value="auth.login.failure">Login Failure</MenuItem>
                            <MenuItem value="auth.logout">Logout</MenuItem>
                            <MenuItem value="user.update">User Update</MenuItem>
                            <MenuItem value="user.role.assign">Role Assignment</MenuItem>
                            <MenuItem value="scope.assign">Scope Assignment</MenuItem>
                            <MenuItem value="scope.revoke">Scope Revocation</MenuItem>
                            <MenuItem value="company.create">Company Create</MenuItem>
                            <MenuItem value="company.update">Company Update</MenuItem>
                            <MenuItem value="company.delete">Company Delete</MenuItem>
                            <MenuItem value="branch.create">Branch Create</MenuItem>
                            <MenuItem value="branch.update">Branch Update</MenuItem>
                            <MenuItem value="branch.delete">Branch Delete</MenuItem>
                            <MenuItem value="department.create">Department Create</MenuItem>
                            <MenuItem value="department.update">Department Update</MenuItem>
                            <MenuItem value="department.delete">Department Delete</MenuItem>
                            <MenuItem value="role.create">Role Create</MenuItem>
                            <MenuItem value="role.update">Role Update</MenuItem>
                            <MenuItem value="role.delete">Role Delete</MenuItem>
                            <MenuItem value="permission.create">Permission Create</MenuItem>
                            <MenuItem value="permission.update">Permission Update</MenuItem>
                            <MenuItem value="permission.delete">Permission Delete</MenuItem>
                        </Select>
                    </FormControl>

                    <FormControl fullWidth size="small">
                        <InputLabel>Result</InputLabel>
                        <Select
                            value={localFilters.result || ''}
                            label="Result"
                            onChange={(e) =>
                                setLocalFilters({ ...localFilters, result: e.target.value || undefined })
                            }
                        >
                            <MenuItem value="">All Results</MenuItem>
                            <MenuItem value="success">Success</MenuItem>
                            <MenuItem value="failure">Failure</MenuItem>
                        </Select>
                    </FormControl>

                    <TextField
                        label="Date From"
                        type="datetime-local"
                        size="small"
                        value={localFilters.date_from || ''}
                        onChange={(e) =>
                            setLocalFilters({ ...localFilters, date_from: e.target.value || undefined })
                        }
                        InputLabelProps={{ shrink: true }}
                    />

                    <TextField
                        label="Date To"
                        type="datetime-local"
                        size="small"
                        value={localFilters.date_to || ''}
                        onChange={(e) =>
                            setLocalFilters({ ...localFilters, date_to: e.target.value || undefined })
                        }
                        InputLabelProps={{ shrink: true }}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={handleClear}>Clear All</Button>
                <Button onClick={onClose}>Cancel</Button>
                <Button onClick={handleApply} variant="contained">
                    Apply Filters
                </Button>
            </DialogActions>
        </Dialog>
    );
};

interface AuditDetailDialogProps {
    log: AuditLog | null;
    onClose: () => void;
}

const AuditDetailDialog = ({ log, onClose }: AuditDetailDialogProps) => {
    if (!log) return null;

    return (
        <Dialog open={!!log} onClose={onClose} maxWidth="md" fullWidth>
            <DialogTitle>Audit Log Details</DialogTitle>
            <DialogContent>
                <Stack spacing={2} sx={{ mt: 1 }}>
                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            Event
                        </Typography>
                        <Chip label={log.action} color="primary" variant="outlined" />
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            Timestamp
                        </Typography>
                        <Typography variant="body1">{formatDateTime(log.created_at)}</Typography>
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            Result
                        </Typography>
                        <Chip
                            label={log.result}
                            color={log.result === 'success' ? 'success' : 'error'}
                            variant="filled"
                        />
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            Actor
                        </Typography>
                        <Paper variant="outlined" sx={{ p: 2 }}>
                            <Typography variant="body2">
                                <strong>Name:</strong> {log.actor.name || 'System'}
                            </Typography>
                            {log.actor.email && (
                                <Typography variant="body2">
                                    <strong>Email:</strong> {log.actor.email}
                                </Typography>
                            )}
                            <Typography variant="body2">
                                <strong>Type:</strong> {log.actor.type}
                            </Typography>
                            <Typography variant="body2">
                                <strong>ID:</strong> {log.actor.id || '—'}
                            </Typography>
                        </Paper>
                    </Box>

                    {log.target.type && (
                        <Box>
                            <Typography variant="subtitle2" color="text.secondary">
                                Target
                            </Typography>
                            <Paper variant="outlined" sx={{ p: 2 }}>
                                <Typography variant="body2">
                                    <strong>Name:</strong> {log.target.name || '—'}
                                </Typography>
                                <Typography variant="body2">
                                    <strong>Type:</strong> {log.target.type}
                                </Typography>
                                <Typography variant="body2">
                                    <strong>ID:</strong> {log.target.id || '—'}
                                </Typography>
                            </Paper>
                        </Box>
                    )}

                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            IP Address
                        </Typography>
                        <Typography variant="body1" fontFamily="monospace">
                            {log.ip_address || '—'}
                        </Typography>
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            User Agent
                        </Typography>
                        <Typography variant="body2" sx={{ wordBreak: 'break-word' }}>
                            {log.user_agent || '—'}
                        </Typography>
                    </Box>

                    <Box>
                        <Typography variant="subtitle2" color="text.secondary">
                            Correlation ID
                        </Typography>
                        <Typography variant="body2" fontFamily="monospace">
                            {log.correlation_id}
                        </Typography>
                    </Box>

                    {log.organization_context && (
                        <Box>
                            <Typography variant="subtitle2" color="text.secondary">
                                Organization Context
                            </Typography>
                            <Paper variant="outlined" sx={{ p: 2 }}>
                                <pre style={{ margin: 0, fontSize: '0.875rem' }}>
                                    {JSON.stringify(log.organization_context, null, 2)}
                                </pre>
                            </Paper>
                        </Box>
                    )}

                    {log.metadata && Object.keys(log.metadata).length > 0 && (
                        <Box>
                            <Typography variant="subtitle2" color="text.secondary">
                                Metadata
                            </Typography>
                            <Paper variant="outlined" sx={{ p: 2 }}>
                                <pre style={{ margin: 0, fontSize: '0.875rem', maxHeight: '300px', overflow: 'auto' }}>
                                    {JSON.stringify(log.metadata, null, 2)}
                                </pre>
                            </Paper>
                        </Box>
                    )}
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose} variant="contained">
                    Close
                </Button>
            </DialogActions>
        </Dialog>
    );
};
