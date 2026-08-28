import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  TextField,
  Button,
  Avatar,
  IconButton,
  Alert,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
} from '@mui/material';
import {
  PhotoCamera,
  Delete as DeleteIcon,
  Lock as LockIcon,
  Save as SaveIcon,
} from '@mui/icons-material';
import { useAuthStore } from '@/stores/authStore';
import { apiClient } from '@/api/client';
import { PageHeader } from '@/components/layout/PageHeader';
import { useToast } from '@/components/feedback/ToastProvider';

export const ProfilePage = () => {
  const { user, fetchUser } = useAuthStore();
  const queryClient = useQueryClient();
  const { showToast } = useToast();

  const [profileData, setProfileData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    phone_number: user?.phone_number || '',
  });

  const [passwordData, setPasswordData] = useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  const [showPasswordDialog, setShowPasswordDialog] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Update profile mutation
  const updateProfileMutation = useMutation({
    mutationFn: async (data: typeof profileData) => {
      const response = await apiClient.put('/api/v1/profile', data);
      return response.data;
    },
    onSuccess: async () => {
      await fetchUser();
      queryClient.invalidateQueries({ queryKey: ['user'] });
      showToast('Profile updated successfully', 'success');
      setErrors({});
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
      showToast(error.response?.data?.message || 'Failed to update profile', 'error');
    },
  });

  // Change password mutation
  const changePasswordMutation = useMutation({
    mutationFn: async (data: typeof passwordData) => {
      const response = await apiClient.post('/api/v1/profile/password', data);
      return response.data;
    },
    onSuccess: () => {
      showToast('Password changed successfully', 'success');
      setShowPasswordDialog(false);
      setPasswordData({
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      });
      setErrors({});
    },
    onError: (error: any) => {
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
      showToast(error.response?.data?.message || 'Failed to change password', 'error');
    },
  });

  // Upload avatar mutation
  const uploadAvatarMutation = useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData();
      formData.append('avatar', file);
      const response = await apiClient.post('/api/v1/profile/avatar', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return response.data;
    },
    onSuccess: async () => {
      await fetchUser();
      queryClient.invalidateQueries({ queryKey: ['user'] });
      showToast('Avatar uploaded successfully', 'success');
    },
    onError: (error: any) => {
      showToast(error.response?.data?.message || 'Failed to upload avatar', 'error');
    },
  });

  // Remove avatar mutation
  const removeAvatarMutation = useMutation({
    mutationFn: async () => {
      const response = await apiClient.delete('/api/v1/profile/avatar');
      return response.data;
    },
    onSuccess: async () => {
      await fetchUser();
      queryClient.invalidateQueries({ queryKey: ['user'] });
      showToast('Avatar removed successfully', 'success');
    },
    onError: (error: any) => {
      showToast(error.response?.data?.message || 'Failed to remove avatar', 'error');
    },
  });

  const handleProfileUpdate = (e: React.FormEvent) => {
    e.preventDefault();
    updateProfileMutation.mutate(profileData);
  };

  const handlePasswordChange = (e: React.FormEvent) => {
    e.preventDefault();
    changePasswordMutation.mutate(passwordData);
  };

  const handleAvatarUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      uploadAvatarMutation.mutate(file);
    }
  };

  const handleAvatarRemove = () => {
    if (window.confirm('Are you sure you want to remove your avatar?')) {
      removeAvatarMutation.mutate();
    }
  };

  if (!user) {
    return (
      <>
        <PageHeader
          title="My Profile"
          breadcrumbs={[
            { label: 'Dashboard', path: '/dashboard' },
            { label: 'My Profile' },
          ]}
        />
        <Box sx={{ p: 3 }}>
          <Alert severity="error">User not authenticated</Alert>
        </Box>
      </>
    );
  }

  const avatarUrl = user.avatar ? `/storage/${user.avatar}` : undefined;

  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4" gutterBottom>
        My Profile
      </Typography>

      <Grid container spacing={3}>
        {/* Avatar Section */}
        <Grid item xs={12} md={4}>
          <Card>
            <CardContent sx={{ textAlign: 'center' }}>
              <Box sx={{ position: 'relative', display: 'inline-block', mb: 2 }}>
                <Avatar
                  src={avatarUrl}
                  sx={{ width: 150, height: 150, fontSize: 48 }}
                >
                  {user.name.charAt(0).toUpperCase()}
                </Avatar>
                <Box sx={{ position: 'absolute', bottom: 0, right: 0, display: 'flex', gap: 0.5 }}>
                  <IconButton
                    component="label"
                    sx={{ bgcolor: 'background.paper', '&:hover': { bgcolor: 'action.hover' } }}
                    size="small"
                  >
                    <PhotoCamera fontSize="small" />
                    <input
                      type="file"
                      hidden
                      accept="image/*"
                      onChange={handleAvatarUpload}
                    />
                  </IconButton>
                  {user.avatar && (
                    <IconButton
                      onClick={handleAvatarRemove}
                      sx={{ bgcolor: 'background.paper', '&:hover': { bgcolor: 'action.hover' } }}
                      size="small"
                      color="error"
                    >
                      <DeleteIcon fontSize="small" />
                    </IconButton>
                  )}
                </Box>
              </Box>
              <Typography variant="body2" color="text.secondary">
                Click camera icon to upload avatar
              </Typography>
              <Typography variant="caption" color="text.secondary" display="block">
                Max 2MB, 100x100 to 2000x2000 pixels
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        {/* Profile Information */}
        <Grid item xs={12} md={8}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Profile Information
              </Typography>
              <form onSubmit={handleProfileUpdate}>
                <Grid container spacing={2}>
                  <Grid item xs={12}>
                    <TextField
                      fullWidth
                      label="Full Name"
                      value={profileData.name}
                      onChange={(e) => setProfileData({ ...profileData, name: e.target.value })}
                      error={!!errors.name}
                      helperText={errors.name}
                      required
                    />
                  </Grid>
                  <Grid item xs={12}>
                    <TextField
                      fullWidth
                      label="Email Address"
                      type="email"
                      value={profileData.email}
                      onChange={(e) => setProfileData({ ...profileData, email: e.target.value })}
                      error={!!errors.email}
                      helperText={errors.email}
                      required
                    />
                  </Grid>
                  <Grid item xs={12}>
                    <TextField
                      fullWidth
                      label="Phone Number"
                      value={profileData.phone_number}
                      onChange={(e) => setProfileData({ ...profileData, phone_number: e.target.value })}
                      error={!!errors.phone_number}
                      helperText={errors.phone_number}
                    />
                  </Grid>
                  <Grid item xs={12}>
                    <Button
                      type="submit"
                      variant="contained"
                      startIcon={<SaveIcon />}
                      disabled={updateProfileMutation.isPending}
                    >
                      {updateProfileMutation.isPending ? 'Saving...' : 'Save Changes'}
                    </Button>
                  </Grid>
                </Grid>
              </form>
            </CardContent>
          </Card>

          {/* Security Section */}
          <Card sx={{ mt: 3 }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Security
              </Typography>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                <LockIcon color="action" />
                <Box sx={{ flex: 1 }}>
                  <Typography variant="body1">Password</Typography>
                  <Typography variant="body2" color="text.secondary">
                    {(user as any).password_changed_at
                      ? `Last changed ${new Date((user as any).password_changed_at).toLocaleDateString()}`
                      : 'Set a strong password to protect your account'}
                  </Typography>
                </Box>
                <Button
                  variant="outlined"
                  onClick={() => setShowPasswordDialog(true)}
                >
                  Change Password
                </Button>
              </Box>
            </CardContent>
          </Card>

          {/* Account Information */}
          <Card sx={{ mt: 3 }}>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Account Information
              </Typography>
              <Grid container spacing={2}>
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="text.secondary">
                    Company
                  </Typography>
                  <Typography variant="body1">
                    {user.company?.name || 'Not assigned'}
                  </Typography>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="text.secondary">
                    Branch
                  </Typography>
                  <Typography variant="body1">
                    {user.branch?.name || 'Not assigned'}
                  </Typography>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="text.secondary">
                    Department
                  </Typography>
                  <Typography variant="body1">
                    {user.department?.name || 'Not assigned'}
                  </Typography>
                </Grid>
                <Grid item xs={12} sm={6}>
                  <Typography variant="body2" color="text.secondary">
                    Last Login
                  </Typography>
                  <Typography variant="body1">
                    {(user as any).last_login_at
                      ? new Date((user as any).last_login_at).toLocaleString()
                      : 'Never'}
                  </Typography>
                </Grid>
              </Grid>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Password Change Dialog */}
      <Dialog open={showPasswordDialog} onClose={() => setShowPasswordDialog(false)} maxWidth="sm" fullWidth>
        <form onSubmit={handlePasswordChange}>
          <DialogTitle>Change Password</DialogTitle>
          <DialogContent>
            <Grid container spacing={2} sx={{ mt: 1 }}>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Current Password"
                  type="password"
                  value={passwordData.current_password}
                  onChange={(e) => setPasswordData({ ...passwordData, current_password: e.target.value })}
                  error={!!errors.current_password}
                  helperText={errors.current_password}
                  required
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="New Password"
                  type="password"
                  value={passwordData.new_password}
                  onChange={(e) => setPasswordData({ ...passwordData, new_password: e.target.value })}
                  error={!!errors.new_password}
                  helperText={errors.new_password || 'Minimum 8 characters'}
                  required
                />
              </Grid>
              <Grid item xs={12}>
                <TextField
                  fullWidth
                  label="Confirm New Password"
                  type="password"
                  value={passwordData.new_password_confirmation}
                  onChange={(e) => setPasswordData({ ...passwordData, new_password_confirmation: e.target.value })}
                  error={!!errors.new_password_confirmation}
                  helperText={errors.new_password_confirmation}
                  required
                />
              </Grid>
            </Grid>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setShowPasswordDialog(false)}>Cancel</Button>
            <Button
              type="submit"
              variant="contained"
              disabled={changePasswordMutation.isPending}
            >
              {changePasswordMutation.isPending ? 'Changing...' : 'Change Password'}
            </Button>
          </DialogActions>
        </form>
      </Dialog>
    </Box>
  );
};
