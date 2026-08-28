import type { ComponentType } from 'react';

export type WidgetSize = 'small' | 'medium' | 'large' | 'full';
export type WidgetCategory = 'organization' | 'security' | 'system' | 'account' | 'future';

export interface DashboardWidget {
    id: string;
    title: string;
    description?: string;
    category: WidgetCategory;
    order: number;
    size: WidgetSize;
    permission?: string;
    component: ComponentType<{ data?: unknown }>;
}

export interface WidgetRegistry {
    widgets: DashboardWidget[];
    register(widget: DashboardWidget): void;
    getByCategory(category: WidgetCategory): DashboardWidget[];
    getAll(): DashboardWidget[];
}
