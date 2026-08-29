import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { apiClient } from '@/api/client';
import type { SystemConfig } from '@/features/system/types';

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

const applyBrandingToDocument = (config: SystemConfig) => {
  if (config.branding.browser_title) {
    document.title = config.branding.browser_title;
  }

  if (config.branding.favicon_path) {
    let link = document.querySelector<HTMLLinkElement>("link[rel='icon'], link[rel='shortcut icon']");
    if (!link) {
      link = document.createElement('link');
      link.rel = 'icon';
      document.head.appendChild(link);
    }
    link.href = config.branding.favicon_path;
  }
};

interface ConfigState {
  config: SystemConfig;
  loading: boolean;
  error: string | null;
  fetchConfig: () => Promise<void>;
  setConfig: (config: Partial<SystemConfig>) => void;
}

export const useConfigStore = create<ConfigState>()(
  persist(
    (set, get) => ({
      config: defaultConfig,
      loading: false,
      error: null,

      fetchConfig: async () => {
        set({ loading: true, error: null });

        try {
          // Fetch from the PUBLIC branding endpoint (no auth required)
          const response = await apiClient.get<{ data: Partial<SystemConfig['branding']> }>('/api/v1/branding');
          const brandingData = response.data.data;

          const currentConfig = get().config;
          const newConfig: SystemConfig = {
            ...currentConfig,
            branding: {
              ...currentConfig.branding,
              app_name: brandingData.app_name || currentConfig.branding.app_name,
              logo_path: brandingData.logo_path || null,
              login_branding: brandingData.login_branding || currentConfig.branding.login_branding,
            },
          };

          applyBrandingToDocument(newConfig);

          set({
            config: newConfig,
            loading: false,
            error: null,
          });
        } catch (error) {
          console.warn('Failed to fetch public branding:', error);
          set({
            loading: false,
            error: null, // Don't show error for public branding failure
          });
        }
      },

      setConfig: (partialConfig) => {
        set((state) => {
          const config: SystemConfig = {
            ...state.config,
            ...partialConfig,
            branding: {
              ...state.config.branding,
              ...partialConfig.branding,
            },
          };

          applyBrandingToDocument(config);

          return { config };
        });
      },
    }),
    {
      name: 'ewnet-config-storage-v4', // Bumped version to clear old cache
    }
  )
);
