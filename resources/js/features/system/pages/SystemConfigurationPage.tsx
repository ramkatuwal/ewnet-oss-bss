import React, { useState, useEffect } from 'react';
import {
  Box,
  Grid2 as Grid,
  Button,
  CircularProgress,
  Alert,
  Snackbar,
} from '@mui/material';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { systemApi } from '@/api/system';
import { useConfigStore } from '@/stores/configStore';
import { PageHeader } from '@/components/layout/PageHeader';
import {
  ConfigurationSection,
  ConfigTextField,
  ConfigSwitch,
  ConfigSelect,
  ConfigColorPicker,
} from '../components/ConfigurationSection';
import { SystemConfig } from '../types';

const defaultConfig: SystemConfig = {
  branding: {
    app_name: 'EWNET',
    browser_title: 'EWNET OSS/BSS',
    logo_path: null,
    favicon_path: null,
    login_branding: 'EWNET',
  },
  navigation: {
    menu_visibility: {},
    menu_ordering: {},
  },
  header: {
    show_logo: true,
    show_title: true,
    show_user_menu: true,
    show_notifications: true,
  },
  theme: {
    compactness: 'compact',
    dark_mode: false,
    primary_color: '#1976d2',
  },
};

export const SystemConfigurationPage: React.FC = () => {
  const queryClient = useQueryClient();
  const setGlobalConfig = useConfigStore((state) => state.setConfig);
  const [config, setConfig] = useState<SystemConfig>(defaultConfig);
  const [hasChanges, setHasChanges] = useState(false);
  const [snackbar, setSnackbar] = useState<{ open: boolean; message: string; severity: 'success' | 'error' }>({
    open: false,
    message: '',
    severity: 'success',
  });

  const { data, isLoading, error } = useQuery({
    queryKey: ['systemConfig'],
    queryFn: systemApi.getConfig,
  });

  useEffect(() => {
    if (data) {
      // Normalize data: ensure all fields have valid values
      setConfig({
        branding: {
          app_name: data.branding?.app_name || 'EWNET',
          browser_title: data.branding?.browser_title || 'EWNET OSS/BSS',
          logo_path: data.branding?.logo_path || null,
          favicon_path: data.branding?.favicon_path || null,
          login_branding: data.branding?.login_branding || 'EWNET',
        },
        navigation: {
          menu_visibility: data.navigation?.menu_visibility || {},
          menu_ordering: data.navigation?.menu_ordering || {},
        },
        header: {
          show_logo: data.header?.show_logo ?? true,
          show_title: data.header?.show_title ?? true,
          show_user_menu: data.header?.show_user_menu ?? true,
          show_notifications: data.header?.show_notifications ?? true,
        },
        theme: {
          compactness: data.theme?.compactness || 'compact',
          dark_mode: data.theme?.dark_mode ?? false,
          primary_color: data.theme?.primary_color || '#1976d2',
        },
      });
      setHasChanges(false);
    }
  }, [data]);

  const updateMutation = useMutation({
    mutationFn: systemApi.updateConfig,
    onSuccess: async (result) => {
      setGlobalConfig(config);

      await queryClient.invalidateQueries({ queryKey: ['systemConfig'] });
      await queryClient.refetchQueries({ queryKey: ['systemConfig'] });

      setHasChanges(false);
      setSnackbar({ open: true, message: result.message || 'Configuration updated successfully.', severity: 'success' });
    },
    onError: (err: unknown) => {
      const error = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const errorData = error?.response?.data;
      let message = errorData?.message || 'Failed to update configuration.';

      // Show detailed validation errors if available
      if (errorData?.errors) {
        const errorMessages = Object.values(errorData.errors).flat().join('. ');
        message = `${message}: ${errorMessages}`;
      }

      setSnackbar({
        open: true,
        message,
        severity: 'error',
      });
    },
  });

  const handleChange = <T extends keyof SystemConfig, K extends keyof SystemConfig[T]>(
    section: T,
    key: K,
    value: SystemConfig[T][K]
  ) => {
    setConfig((prev) => ({
      ...prev,
      [section]: {
        ...prev[section],
        [key]: value,
      },
    }));
    setHasChanges(true);
  };

  const handleSave = () => {
    // Strict validation: ensure critical fields have valid values
    const validCompactness = ['compact', 'comfortable', 'spacious'].includes(config.theme.compactness)
      ? config.theme.compactness
      : 'compact';
    
    const validPrimaryColor = /^#[0-9a-fA-F]{6}$/.test(config.theme.primary_color || '')
      ? config.theme.primary_color
      : '#1976d2';

    const saveData = {
      branding: {
        app_name: config.branding.app_name?.trim() || 'EWNET',
        browser_title: config.branding.browser_title?.trim() || 'EWNET OSS/BSS',
        logo_path: config.branding.logo_path?.trim() || null,
        favicon_path: config.branding.favicon_path?.trim() || null,
        login_branding: config.branding.login_branding?.trim() || 'EWNET',
      },
      navigation: {
        menu_visibility: config.navigation.menu_visibility || {},
        menu_ordering: config.navigation.menu_ordering || {},
      },
      header: {
        show_logo: config.header.show_logo ?? true,
        show_title: config.header.show_title ?? true,
        show_user_menu: config.header.show_user_menu ?? true,
        show_notifications: config.header.show_notifications ?? true,
      },
      theme: {
        compactness: validCompactness,
        dark_mode: config.theme.dark_mode ?? false,
        primary_color: validPrimaryColor,
      },
    };

    // Debug: log what we're sending
    console.log('Saving configuration:', JSON.stringify(saveData, null, 2));
    
    updateMutation.mutate(saveData);
  };

  const handleReset = () => {
    if (data) {
      setConfig({
        branding: {
          app_name: data.branding?.app_name || 'EWNET',
          browser_title: data.branding?.browser_title || 'EWNET OSS/BSS',
          logo_path: data.branding?.logo_path || null,
          favicon_path: data.branding?.favicon_path || null,
          login_branding: data.branding?.login_branding || 'EWNET',
        },
        navigation: {
          menu_visibility: data.navigation?.menu_visibility || {},
          menu_ordering: data.navigation?.menu_ordering || {},
        },
        header: {
          show_logo: data.header?.show_logo ?? true,
          show_title: data.header?.show_title ?? true,
          show_user_menu: data.header?.show_user_menu ?? true,
          show_notifications: data.header?.show_notifications ?? true,
        },
        theme: {
          compactness: data.theme?.compactness || 'compact',
          dark_mode: data.theme?.dark_mode ?? false,
          primary_color: data.theme?.primary_color || '#1976d2',
        },
      });
      setHasChanges(false);
    }
  };

  if (isLoading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: 400 }}>
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return (
      <Box sx={{ p: 3 }}>
        <Alert severity="error">Failed to load configuration.</Alert>
      </Box>
    );
  }

  return (
    <Box>
      <PageHeader
        title="System Configuration"
        subtitle="Manage application branding, navigation, header, and theme settings"
        actions={
          <Box sx={{ display: 'flex', gap: 1 }}>
            <Button
              variant="outlined"
              onClick={handleReset}
              disabled={!hasChanges || updateMutation.isPending}
            >
              Reset
            </Button>
            <Button
              variant="contained"
              onClick={handleSave}
              disabled={!hasChanges || updateMutation.isPending}
              startIcon={updateMutation.isPending ? <CircularProgress size={20} /> : null}
            >
              {updateMutation.isPending ? 'Saving...' : 'Save Changes'}
            </Button>
          </Box>
        }
      />

      <Grid container spacing={3}>
        {/* Branding */}
        <Grid size={{ xs: 12 }}>
          <ConfigurationSection title="Branding">
            <ConfigTextField
              label="Application Name"
              value={config.branding.app_name}
              onChange={(v) => handleChange('branding', 'app_name', v)}
            />
            <ConfigTextField
              label="Browser Title"
              value={config.branding.browser_title}
              onChange={(v) => handleChange('branding', 'browser_title', v)}
            />
            <ConfigTextField
              label="Login Branding"
              value={config.branding.login_branding}
              onChange={(v) => handleChange('branding', 'login_branding', v)}
            />
            <ConfigTextField
              label="Logo Path"
              value={config.branding.logo_path || ''}
              onChange={(v) => handleChange('branding', 'logo_path', v || null)}
              placeholder="/storage/logos/company.png"
            />
            <ConfigTextField
              label="Favicon Path"
              value={config.branding.favicon_path || ''}
              onChange={(v) => handleChange('branding', 'favicon_path', v || null)}
              placeholder="/storage/favicon.ico"
            />
          </ConfigurationSection>
        </Grid>

        {/* Header */}
        <Grid size={{ xs: 12, md: 6 }}>
          <ConfigurationSection title="Header">
            <ConfigSwitch
              label="Show Logo"
              value={config.header.show_logo}
              onChange={(v) => handleChange('header', 'show_logo', v)}
            />
            <ConfigSwitch
              label="Show Title"
              value={config.header.show_title}
              onChange={(v) => handleChange('header', 'show_title', v)}
            />
            <ConfigSwitch
              label="Show User Menu"
              value={config.header.show_user_menu}
              onChange={(v) => handleChange('header', 'show_user_menu', v)}
            />
            <ConfigSwitch
              label="Show Notifications"
              value={config.header.show_notifications}
              onChange={(v) => handleChange('header', 'show_notifications', v)}
            />
          </ConfigurationSection>
        </Grid>

        {/* Theme */}
        <Grid size={{ xs: 12, md: 6 }}>
          <ConfigurationSection title="Theme">
            <ConfigSelect
              label="Compactness"
              value={config.theme.compactness}
              onChange={(v) => handleChange('theme', 'compactness', v as 'compact' | 'comfortable' | 'spacious')}
              options={[
                { label: 'Compact', value: 'compact' },
                { label: 'Comfortable', value: 'comfortable' },
                { label: 'Spacious', value: 'spacious' },
              ]}
            />
            <ConfigSwitch
              label="Dark Mode"
              value={config.theme.dark_mode}
              onChange={(v) => handleChange('theme', 'dark_mode', v)}
            />
            <ConfigColorPicker
              label="Primary Color"
              value={config.theme.primary_color}
              onChange={(v) => handleChange('theme', 'primary_color', v)}
            />
          </ConfigurationSection>
        </Grid>
      </Grid>

      <Snackbar
        open={snackbar.open}
        autoHideDuration={6000}
        onClose={() => setSnackbar((prev) => ({ ...prev, open: false }))}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        <Alert severity={snackbar.severity} onClose={() => setSnackbar((prev) => ({ ...prev, open: false }))}>
          {snackbar.message}
        </Alert>
      </Snackbar>
    </Box>
  );
};
