import type { DashboardWidget, WidgetCategory, WidgetRegistry } from './types';

class WidgetRegistryImpl implements WidgetRegistry {
    private widgetList: DashboardWidget[] = [];

    register(widget: DashboardWidget): void {
        const existing = this.widgetList.filter((item) => item.id !== widget.id);
        this.widgetList = [...existing, widget].sort((a, b) => a.order - b.order);
    }

    getByCategory(category: WidgetCategory): DashboardWidget[] {
        return this.widgetList.filter((widget) => widget.category === category);
    }

    getAll(): DashboardWidget[] {
        return [...this.widgetList];
    }
}

export const widgetRegistry = new WidgetRegistryImpl();
