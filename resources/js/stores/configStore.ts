import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import { systemApi } from '@/api/system';
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

const normalizeConfig = (config?: Partial<SystemConfig> | null): SystemConfig => ({
  branding: {
    app_name: config?.branding?.app_name || defaultConfig.branding.app_name,
    browser_title: config?.branding?.browser_title || defaultConfig.branding.browser_title,
    logo_path: config?.branding?.logo_path || null,
    favicon_path: config?.branding?.favicon_path || null,
    login_branding: config?.branding?.login_branding || defaultConfig.branding.login_branding,
  },
  navigation: {
    menu_visibility: config?.navigation?.menu_visibility || {},
    menu_ordering: config?.navigation?.menu_ordering || {},
  },
  header: {
    show_logo: config?.header?.show_logo ?? true,
    show_title: config?.header?.show_title ?? true,
    show_user_menu: config?.header?.show_user_menu ?? true,
    show_notifications: config?.header?.show_notifications ?? true,
  },
  theme: {
    compactness: ['compact', 'comfortable', 'spacious'].includes(config?.theme?.compactness || '')
      ? config!.theme!.compactness
      : 'compact',
    dark_mode: config?.theme?.dark_mode ?? false,
    primary_color: /^#[0-9a-fA-F]{6}$/.test(config?.theme?.primary_color || '')
      ? config!.theme!.primary_color
      : '#1976d2',
  },
});

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
    (set) => ({
      config: defaultConfig,
      loading: false,
      error: null,

      fetchConfig: async () => {
        set({ loading: true, error: null });

        try {
          const response = await systemApi.getConfig();
          const config = normalizeConfig(response);

          applyBrandingToDocument(config);

          set({
            config,
            loading: false,
            error: null,
          });
        } catch (error) {
          console.error('Failed to fetch system configuration:', error);
          set({
            loading: false,
            error: 'Failed to load system configuration',
          });
        }
      },

      setConfig: (partialConfig) => {
        set((state) => {
          const config = normalizeConfig({
            ...state.config,
            ...partialConfig,
            branding: {
              ...state.config.branding,
              ...partialConfig.branding,
            },
            navigation: {
              ...state.config.navigation,
              ...partialConfig.navigation,
            },
            header: {
              ...state.config.header,
              ...partialConfig.header,
            },
            theme: {
              ...state.config.theme,
              ...partialConfig.theme,
            },
          });

          applyBrandingToDocument(config);

          return { config };
        });
      },
    }),
    {
      name: 'ewnet-config-storage-v3',
      version: 2, // Bumped to force cache refresh for logo
    }
  )
);
