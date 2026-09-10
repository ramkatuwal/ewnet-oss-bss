import { useEffect, useState } from 'react';
import { Alert, Box, Button, Checkbox, CircularProgress, Divider, FormControlLabel, FormGroup, Paper, Stack, TextField, Typography } from '@mui/material';
import { GeoJSON, MapContainer, TileLayer, useMapEvents } from 'react-leaflet';
import { Link as RouterLink, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import type { LatLngBounds } from 'leaflet';
import { PageHeader } from '@/components/layout/PageHeader';
import { fimKeys } from '@/api/queryKeys';
import { fimApi, type FimMapFeature, type TopologyPath } from '../api/fim';
import { TopologyPathRenderer } from '../components/TopologyPathRenderer';
import 'leaflet/dist/leaflet.css';

type Bounds = { west: number; south: number; east: number; north: number };
const initialBounds: Bounds = { west: 83, south: 27.5, east: 84, north: 28.5 };

function Viewport({ onChange }: { onChange: (bounds: Bounds) => void }) {
    useMapEvents({ moveend: event => {
        const bounds: LatLngBounds = event.target.getBounds();
        onChange({ west: bounds.getWest(), south: bounds.getSouth(), east: bounds.getEast(), north: bounds.getNorth() });
    } });
    return null;
}

export default function FimMapPage() {
    const [search, setSearch] = useSearchParams();
    const [viewport, setViewport] = useState(initialBounds);
    const [bounds, setBounds] = useState(initialBounds);
    const [layers, setLayers] = useState<Array<'cables' | 'points'>>(['cables', 'points']);
    const [cableStatus, setCableStatus] = useState('');
    const [pointStatus, setPointStatus] = useState('');
    const [selected, setSelected] = useState<FimMapFeature | null>(null);
    const [topologyInput, setTopologyInput] = useState(search.get('topology') ?? '');
    const [topologyTarget, setTopologyTarget] = useState(search.get('topology') ?? '');

    useEffect(() => { const timer = window.setTimeout(() => setBounds(viewport), 250); return () => window.clearTimeout(timer); }, [viewport]);
    const params = { ...bounds, layers, ...(cableStatus ? { cable_status: cableStatus } : {}), ...(pointStatus ? { point_status: pointStatus } : {}) };
    const map = useQuery({ queryKey: fimKeys.map(params), queryFn: ({ signal }) => fimApi.map(params, signal), enabled: layers.length > 0, retry: false });
    const topology = useQuery<TopologyPath>({
        queryKey: ['fim', 'topology', topologyTarget],
        queryFn: () => {
            const [kind, rawId] = topologyTarget.split(':'); const id = Number(rawId);
            return kind === 'port' ? fimApi.topologyFromPort(id) : fimApi.topologyFromTermination(id);
        },
        enabled: /^(termination|port):[1-9]\d*$/.test(topologyTarget), retry: false,
    });

    useEffect(() => {
        const requested = search.get('feature');
        const feature = map.data?.features.find(item => item.id === requested) ?? null;
        if (feature) setSelected(feature);
    }, [map.data, search]);

    const toggleLayer = (layer: 'cables' | 'points') => setLayers(current => current.includes(layer) ? current.filter(item => item !== layer) : [...current, layer]);
    const select = (feature: FimMapFeature) => { setSelected(feature); setSearch(current => { current.set('feature', feature.id); return current; }); };
    const featureStyle = (feature?: GeoJSON.Feature) => ({ color: feature?.properties?.kind === 'cable' ? '#0369a1' : '#b45309', weight: feature?.id === selected?.id ? 6 : 3, radius: 7 });

    return <Box sx={{ p: 3, maxWidth: 1800, mx: 'auto' }}>
        <PageHeader title="Fiber Infrastructure Map" breadcrumbs={[{ label: 'FIM', path: '/fim' }, { label: 'Map' }]} />
        <Alert severity="info" sx={{ mb: 2 }}>Read-only map of authorized, explicit FIM geometries. Results are limited to 1,000 features; zoom in when a viewport is too dense.</Alert>
        <Stack direction={{ xs: 'column', lg: 'row' }} spacing={2}>
            <Paper sx={{ p: 2, width: { lg: 300 }, flexShrink: 0 }}><Typography variant="h6">Layers and filters</Typography><FormGroup>
                <FormControlLabel control={<Checkbox checked={layers.includes('cables')} onChange={() => toggleLayer('cables')} />} label="Fiber cables" />
                <FormControlLabel control={<Checkbox checked={layers.includes('points')} onChange={() => toggleLayer('points')} />} label="Connection points" />
            </FormGroup><TextField fullWidth size="small" label="Cable status" value={cableStatus} onChange={event => setCableStatus(event.target.value)} sx={{ mt: 1 }} /><TextField fullWidth size="small" label="Point status" value={pointStatus} onChange={event => setPointStatus(event.target.value)} sx={{ mt: 1 }} />
                <Divider sx={{ my: 2 }} /><Typography variant="subtitle2">Topology lookup</Typography><Stack direction="row" spacing={1} sx={{ mt: 1 }}><TextField size="small" value={topologyInput} onChange={event => setTopologyInput(event.target.value)} placeholder="termination:42" /><Button variant="outlined" onClick={() => setTopologyTarget(topologyInput)}>Trace</Button></Stack><Typography variant="caption" color="text.secondary">Use `termination:ID` or `port:ID`.</Typography>
                {topology.isError && <Alert severity="error" sx={{ mt: 1 }}>The requested topology is unavailable or unauthorized.</Alert>}{topology.data && <TopologyPathRenderer path={topology.data} />}
            </Paper>
            <Paper sx={{ height: { xs: 430, lg: 680 }, flex: 1, overflow: 'hidden' }}><MapContainer bounds={[[initialBounds.south, initialBounds.west], [initialBounds.north, initialBounds.east]]} style={{ height: '100%', width: '100%' }}><TileLayer attribution="&copy; OpenStreetMap contributors" url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png" /><Viewport onChange={setViewport} />{map.data && <GeoJSON data={map.data as GeoJSON.GeoJsonObject} style={featureStyle} onEachFeature={(feature, layer) => layer.on('click', () => select(feature as FimMapFeature))} />}</MapContainer></Paper>
            <Paper sx={{ p: 2, width: { lg: 300 }, flexShrink: 0 }}>{map.isLoading && <CircularProgress size={24} />}{map.isError && <Alert severity="warning">{(map.error as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Map data could not be loaded.'}</Alert>}{selected ? <><Typography variant="h6">{selected.properties.label}</Typography><Typography variant="body2">{selected.properties.kind} #{selected.properties.id}</Typography><Typography variant="body2">Status: {selected.properties.status}</Typography><Button component={RouterLink} to={`/fim?record=${selected.id}`} sx={{ mt: 1 }}>View FIM details</Button></> : <Typography color="text.secondary">Select an authorized map feature for its safe read details.</Typography>}</Paper>
        </Stack>
    </Box>;
}
