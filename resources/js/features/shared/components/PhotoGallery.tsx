import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
    Box, Grid, Card, CardMedia, CardContent, Typography,
    IconButton, Chip, Stack, CircularProgress, Alert,
    Dialog, DialogTitle, DialogContent, DialogActions,
    Button, TextField, FormControl, InputLabel, Select, MenuItem
} from '@mui/material';
import { Delete, Upload, Image } from '@mui/icons-material';
import { ConfirmDialog } from '@/components/feedback/ConfirmDialog';
import toast from 'react-hot-toast';
import axios from 'axios';

interface Photo {
    id: number;
    path: string;
    url: string;
    title: string | null;
    category: string;
    description: string | null;
    taken_at: string | null;
    uploaded_by: number;
    uploader?: { id: number; name: string; email: string };
    created_at: string;
    updated_at: string;
}

interface PhotoGalleryProps {
    entityType: 'site' | 'asset';
    entityId: number;
    getPhotosUrl: string;
    uploadUrl: string;
    deleteUrl: (photoId: number) => string;
    categories: string[];
    defaultCategory: string;
    canUpload: boolean;
    canDelete: boolean;
}

export const PhotoGallery: React.FC<PhotoGalleryProps> = ({
    entityType,
    entityId,
    getPhotosUrl,
    uploadUrl,
    deleteUrl,
    categories,
    defaultCategory,
    canUpload,
    canDelete,
}) => {
    const queryClient = useQueryClient();
    const [uploadOpen, setUploadOpen] = useState(false);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [uploadData, setUploadData] = useState({
        title: '',
        category: defaultCategory,
        description: '',
    });
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const { data, isLoading, error } = useQuery({
        queryKey: ['photos', entityType, entityId],
        queryFn: () => axios.get(getPhotosUrl).then(res => res.data.data),
        enabled: !!entityId,
    });

    const uploadMutation = useMutation({
        mutationFn: async () => {
            const formData = new FormData();
            if (!selectedFile) throw new Error('No file selected');
            formData.append('photo', selectedFile);
            formData.append('title', uploadData.title || '');
            formData.append('category', uploadData.category);
            formData.append('description', uploadData.description || '');
            return axios.post(uploadUrl, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            }).then(res => res.data.data);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['photos', entityType, entityId] });
            toast.success('Photo uploaded successfully');
            setUploadOpen(false);
            setSelectedFile(null);
            setPreviewUrl(null);
            setUploadData({ title: '', category: defaultCategory, description: '' });
        },
        onError: (err: any) => {
            toast.error(err.response?.data?.message || 'Upload failed');
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (photoId: number) => axios.delete(deleteUrl(photoId)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['photos', entityType, entityId] });
            toast.success('Photo deleted successfully');
            setDeleteId(null);
        },
        onError: () => toast.error('Failed to delete photo'),
    });

    const photos: Photo[] = data || [];

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setSelectedFile(file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setPreviewUrl(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleUpload = () => {
        if (!selectedFile) return;
        uploadMutation.mutate();
    };

    if (isLoading) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', p: 4 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (error) {
        return (
            <Alert severity="error" sx={{ mt: 2 }}>
                Failed to load photos.
            </Alert>
        );
    }

    return (
        <Box>
            {/* Upload Button */}
            {canUpload && (
                <Box sx={{ mb: 2, display: 'flex', justifyContent: 'flex-end' }}>
                    <Button
                        variant="contained"
                        startIcon={<Upload />}
                        onClick={() => setUploadOpen(true)}
                    >
                        Upload Photo
                    </Button>
                </Box>
            )}

            {/* Photo Grid */}
            {photos.length === 0 ? (
                <Box sx={{ textAlign: 'center', py: 4 }}>
                    <Image sx={{ fontSize: 48, color: 'text.secondary', mb: 1 }} />
                    <Typography variant="body2" color="text.secondary">
                        No photos uploaded yet.
                    </Typography>
                </Box>
            ) : (
                <Grid container spacing={2}>
                    {photos.map((photo) => (
                        <Grid item xs={12} sm={6} md={4} lg={3} key={photo.id}>
                            <Card sx={{ position: 'relative', height: '100%' }}>
                                <CardMedia
                                    component="img"
                                    height="200"
                                    image={photo.url}
                                    alt={photo.title || 'Photo'}
                                    sx={{ objectFit: 'cover' }}
                                />
                                <CardContent sx={{ py: 1.5 }}>
                                    <Stack spacing={0.5}>
                                        {photo.title && (
                                            <Typography variant="subtitle2" noWrap>
                                                {photo.title}
                                            </Typography>
                                        )}
                                        <Chip
                                            label={photo.category}
                                            size="small"
                                            variant="outlined"
                                            sx={{ alignSelf: 'flex-start' }}
                                        />
                                        {photo.description && (
                                            <Typography variant="caption" color="text.secondary" noWrap>
                                                {photo.description}
                                            </Typography>
                                        )}
                                        {photo.uploader && (
                                            <Typography variant="caption" color="text.secondary">
                                                By: {photo.uploader.name}
                                            </Typography>
                                        )}
                                    </Stack>
                                </CardContent>
                                {canDelete && (
                                    <IconButton
                                        size="small"
                                        color="error"
                                        sx={{ position: 'absolute', top: 8, right: 8 }}
                                        onClick={() => setDeleteId(photo.id)}
                                    >
                                        <Delete fontSize="small" />
                                    </IconButton>
                                )}
                            </Card>
                        </Grid>
                    ))}
                </Grid>
            )}

            {/* Upload Dialog */}
            <Dialog open={uploadOpen} onClose={() => setUploadOpen(false)} maxWidth="sm" fullWidth>
                <DialogTitle>Upload Photo</DialogTitle>
                <DialogContent>
                    <Box sx={{ mt: 1, display: 'flex', flexDirection: 'column', gap: 2 }}>
                        {previewUrl && (
                            <Box sx={{ textAlign: 'center' }}>
                                <img
                                    src={previewUrl}
                                    alt="Preview"
                                    style={{ maxWidth: '100%', maxHeight: 200, objectFit: 'contain' }}
                                />
                            </Box>
                        )}
                        <Button
                            variant="outlined"
                            component="label"
                            startIcon={<Image />}
                            fullWidth
                        >
                            Choose Image
                            <input
                                type="file"
                                accept="image/*"
                                hidden
                                onChange={handleFileSelect}
                            />
                        </Button>
                        {selectedFile && (
                            <Typography variant="caption">
                                {selectedFile.name} ({(selectedFile.size / 1024).toFixed(1)} KB)
                            </Typography>
                        )}
                        <TextField
                            label="Title"
                            value={uploadData.title}
                            onChange={(e) => setUploadData({ ...uploadData, title: e.target.value })}
                            fullWidth
                        />
                        <FormControl fullWidth>
                            <InputLabel>Category</InputLabel>
                            <Select
                                value={uploadData.category}
                                label="Category"
                                onChange={(e) => setUploadData({ ...uploadData, category: e.target.value })}
                            >
                                {categories.map((cat) => (
                                    <MenuItem key={cat} value={cat}>
                                        {cat.charAt(0).toUpperCase() + cat.slice(1)}
                                    </MenuItem>
                                ))}
                            </Select>
                        </FormControl>
                        <TextField
                            label="Description"
                            value={uploadData.description}
                            onChange={(e) => setUploadData({ ...uploadData, description: e.target.value })}
                            multiline
                            rows={2}
                            fullWidth
                        />
                    </Box>
                </DialogContent>
                <DialogActions>
                    <Button onClick={() => setUploadOpen(false)}>Cancel</Button>
                    <Button
                        variant="contained"
                        onClick={handleUpload}
                        disabled={!selectedFile || uploadMutation.isPending}
                    >
                        {uploadMutation.isPending ? 'Uploading...' : 'Upload'}
                    </Button>
                </DialogActions>
            </Dialog>

            {/* Delete Confirmation */}
            <ConfirmDialog
                open={!!deleteId}
                title="Delete Photo"
                message="Are you sure you want to delete this photo? This action cannot be undone."
                onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
                onCancel={() => setDeleteId(null)}
            />
        </Box>
    );
};
