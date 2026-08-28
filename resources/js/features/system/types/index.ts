export interface SystemInfo {
  application: {
    name: string;
    environment: string;
    url: string;
  };
  runtime: {
    laravel: string;
    php: string;
    node: string | null;
    composer: string | null;
  };
  container: {
    hostname: string;
    memory_limit: string;
    max_execution_time: string;
  };
  services: {
    postgresql: { status: 'healthy' | 'unhealthy' | 'unknown' };
    redis: { status: 'healthy' | 'unhealthy' | 'unknown' };
    horizon: { status: 'running' | 'stopped' | 'unknown' };
    nginx: { status: 'healthy' | 'unhealthy' | 'unknown' };
  };
  git: {
    commit: string | null;
    branch: string | null;
    tag: string | null;
  };
}

export interface SystemConfig {
  branding: {
    app_name: string;
    browser_title: string;
    logo_path: string | null;
    favicon_path: string | null;
    login_branding: string;
  };
  navigation: {
    menu_visibility: Record<string, boolean>;
    menu_ordering: Record<string, number>;
  };
  header: {
    show_logo: boolean;
    show_title: boolean;
    show_user_menu: boolean;
    show_notifications: boolean;
  };
  theme: {
    compactness: 'compact' | 'comfortable' | 'spacious';
    dark_mode: boolean;
    primary_color: string;
  };
}
