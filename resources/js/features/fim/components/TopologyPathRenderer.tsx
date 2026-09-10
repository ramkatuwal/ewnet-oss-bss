import { Alert, List, ListItem, ListItemText, Typography } from '@mui/material';
import type { TopologyPath } from '../api/fim';

// This is a deterministic path readout, not a graph: every item comes from an explicit API edge.
export function TopologyPathRenderer({ path }: { path: TopologyPath }) {
    const labels = new Map(path.nodes.map(node => [`${node.type}:${node.id}`, `${node.type} #${node.id}`]));
    const ordered = path.edges.map(edge => `${labels.get(edge.from) ?? edge.from} -> ${labels.get(edge.to) ?? edge.to} (${edge.type})`);
    return <>
        <Typography variant="subtitle2">Explicit topology path from {path.start_node}</Typography>
        {path.truncated && <Alert severity="warning" sx={{ mt: 1 }}>Traversal reached its configured depth limit.</Alert>}
        <List dense>{ordered.length ? ordered.map((edge, index) => <ListItem key={`${index}-${edge}`}><ListItemText primary={`${index + 1}. ${edge}`} /></ListItem>) : <ListItem><ListItemText primary="No explicit physical edges were found." /></ListItem>}</List>
    </>;
}
