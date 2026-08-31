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
          // Step 1: Fetch public branding (no auth required, works on login page)
          const publicResponse = await apiClient.get<{ data: Partial<SystemConfig['branding']> }>('/api/v1/branding');
          const brandingData = publicResponse.data.data;

          const currentConfig = get().config;
          let newConfig: SystemConfig = {
            ...currentConfig,
            branding: {
              ...currentConfig.branding,
              app_name: brandingData.app_name || currentConfig.branding.app_name,
              logo_path: brandingData.logo_path ?? null,
              login_branding: brandingData.login_branding || currentConfig.branding.login_branding,
            },
          };

          // Step 2: Fetch full authenticated configuration (includes header, theme, navigation)
          try {
            const fullResponse = await apiClient.get<{ data: SystemConfig }>('/api/v1/system/configuration');
            const fullConfig = fullResponse.data.data;

            if (fullConfig) {
              newConfig = {
                branding: {
                  app_name: fullConfig.branding?.app_name || newConfig.branding.app_name,
                  browser_title: fullConfig.branding?.browser_title || newConfig.branding.browser_title,
                  logo_path: fullConfig.branding?.logo_path ?? newConfig.branding.logo_path,
                  favicon_path: fullConfig.branding?.favicon_path ?? null,
                  login_branding: fullConfig.branding?.login_branding || newConfig.branding.login_branding,
                },
                navigation: {
                  menu_visibility: fullConfig.navigation?.menu_visibility || {},
                  menu_ordering: fullConfig.navigation?.menu_ordering || {},
                },
                header: {
                  show_logo: fullConfig.header?.show_logo ?? true,
                  show_title: fullConfig.header?.show_title ?? true,
                  show_user_menu: fullConfig.header?.show_user_menu ?? true,
                  show_notifications: fullConfig.header?.show_notifications ?? true,
                },
                theme: {
                  compactness: fullConfig.theme?.compactness || 'compact',
                  dark_mode: fullConfig.theme?.dark_mode ?? false,
                  primary_color: fullConfig.theme?.primary_color || '#1976d2',
                },
              };
            }
          } catch {
            // Authenticated config fetch failed (user may not be logged in yet)
            // Public branding data is still valid; continue with defaults for other sections
          }

          applyBrandingToDocument(newConfig);

          set({
            config: newConfig,
            loading: false,
            error: null,
          });
        } catch (error) {
          console.warn('Failed to fetch configuration:', error);
          set({
            loading: false,
            error: null,
          });
        }
      },

      setConfig: (partialConfig) => {
        set((state) => {
          const config: SystemConfig = {
            branding: {
              ...state.config.branding,
              ...(partialConfig.branding || {}),
            },
            navigation: {
              ...state.config.navigation,
              ...(partialConfig.navigation || {}),
            },
            header: {
              ...state.config.header,
              ...(partialConfig.header || {}),
            },
            theme: {
              ...state.config.theme,
              ...(partialConfig.theme || {}),
            },
          };

          applyBrandingToDocument(config);

          return { config };
        });
      },
    }),
    {
      name: 'ewnet-config-storage-v5',
    }
  )
);
