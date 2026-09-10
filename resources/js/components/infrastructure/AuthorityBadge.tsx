import { Chip, Tooltip } from '@mui/material';

export const AuthorityBadge = ({ authoritative }: { authoritative: boolean }) => (
    <Tooltip title={authoritative ? 'This is canonical inventory data managed in EWNET.' : 'This value is an observation and is read-only.'}>
        <Chip label={authoritative ? 'Authoritative' : 'Observed'} size="small" variant="outlined" color={authoritative ? 'primary' : 'default'} />
    </Tooltip>
);
