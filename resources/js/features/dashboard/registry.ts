import { DashboardWidget, WidgetCategory, WidgetRegistry } from './types';

class WidgetRegistryImpl implements WidgetRegistry {
    private widgets: DashboardWidget[] = [];

    register(widget: DashboardWidget): void {
        this.widgets.push(widget);
        this.widgets.sort((a, b) => a.order - b.order);
    }

    getByCategory(category: WidgetCategory): DashboardWidget[] {
        return this.widgets.filter(w => w.category === category);
    }

    getAll(): DashboardWidget[] {
        return this.widgets;
    }
}

export const widgetRegistry = new WidgetRegistryImpl();
