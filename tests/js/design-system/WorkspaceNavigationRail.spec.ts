import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WorkspaceNavigationRail from '../../../resources/js/design-system/WorkspaceNavigationRail.vue';
import type { WorkspaceNavigationCategory } from '../../../resources/js/workspace/workspaceTypes';
import { i18n } from '../../../resources/js/i18n';

const categories: WorkspaceNavigationCategory[] = [
    { id: 'workspace', titleKey: 'access.workspace.navigation.title', icon: 'workspace' },
];

describe('WorkspaceNavigationRail', () => {
    it('keeps a textual accessible name when the rail is collapsed', async () => {
        const wrapper = mount(WorkspaceNavigationRail, {
            props: { categories, activeCategoryId: 'workspace', collapsed: true, collapseLabel: 'Recolher', expandLabel: 'Expandir' },
            global: { plugins: [i18n] },
        });

        const category = wrapper.get('.workspace-navigation-rail__category');
        expect(category.attributes('aria-label')).toBe('Navegação');
        expect(category.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find('.workspace-navigation-rail__category-label').exists()).toBe(false);

        await category.trigger('click');
        await wrapper.get('.workspace-navigation-rail__toggle').trigger('click');

        expect(wrapper.emitted('selectCategory')).toEqual([['workspace']]);
        expect(wrapper.emitted('toggleCollapsed')).toHaveLength(1);
    });
});
