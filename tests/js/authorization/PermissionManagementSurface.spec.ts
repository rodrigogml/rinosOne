import { mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import { describe, expect, it } from 'vitest';
import { createI18n } from 'vue-i18n';
import PermissionManagementSurface from '../../../resources/js/authorization/PermissionManagementSurface.vue';
import type { WorkspaceSurface } from '../../../resources/js/workspace/workspaceTypes';

const messages = {
    'pt-BR': {
        access: {
            permissionManagement: {
                title: 'Usuários, permissões e acessos', description: 'Gerencie acessos.', tabList: 'Gerenciamento de permissões', scopeLocked: 'Escopo fixo.',
                scope: { personal: 'Espaço pessoal', personalName: 'Seu espaço pessoal', tenant: 'Organização', selectedTenant: 'Organização selecionada', domain: 'Domínio', domainName: 'Rinos One' },
                tabs: { roles: 'Papéis', groups: 'Grupos', users: 'Usuários' },
                roles: { title: 'Papéis', description: 'Papéis do escopo.' }, groups: { title: 'Grupos', description: 'Grupos do escopo.' }, users: { title: 'Usuários', description: 'Usuários do escopo.' }, empty: 'Pronta.',
            },
        },
    },
};
const i18n = createI18n({ legacy: false, locale: 'pt-BR', messages });

function surface(scope: WorkspaceSurface['scope']): WorkspaceSurface {
    return { id: `${scope}-permissions`, destinationId: `${scope}.permissions-access`, scope, tenantId: scope === 'tenant' ? 8 : null, titleKey: 'access.permissionManagement.title', label: 'Usuários, permissões e acessos', icon: 'settings', dirty: false, status: 'active' };
}

function mountSurface(scope: WorkspaceSurface['scope']) {
    return mount(PermissionManagementSurface, { props: { surface: surface(scope) }, global: { plugins: [createPinia(), i18n] } });
}

describe('PermissionManagementSurface', () => {
    it.each(['personal', 'tenant', 'domain'] as const)('renders the fixed %s scope passed by its menu entry without adding a scope selector', (scope) => {
        const wrapper = mountSurface(scope);

        expect(wrapper.attributes('data-scope')).toBe(scope);
        expect(wrapper.find('.permission-management__context').exists()).toBe(false);
        expect(wrapper.find('.permission-management__intro').exists()).toBe(false);
        expect(wrapper.get('[role="tablist"]').element).toBe(wrapper.element.firstElementChild);
        expect(wrapper.get('#permission-management-tab-roles img').attributes('src')).toBe('/assets/icons/secRoles_24.png');
        expect(wrapper.get('#permission-management-tab-groups img').attributes('src')).toBe('/assets/icons/secGroups_24.png');
        expect(wrapper.get('#permission-management-tab-users img').attributes('src')).toBe('/assets/icons/rinoUser_24.png');
    });

    it('changes among roles, groups and users without changing the active scope', async () => {
        const wrapper = mountSurface('tenant');

        await wrapper.get('#permission-management-tab-groups').trigger('click');
        expect(wrapper.get('[role="tabpanel"]').text()).toContain('Grupos do escopo.');
        expect(wrapper.attributes('data-scope')).toBe('tenant');

        await wrapper.get('#permission-management-tab-users').trigger('click');
        expect(wrapper.get('[role="tabpanel"]').text()).toContain('Usuários do escopo.');
        expect(wrapper.get('#permission-management-tab-users').attributes('aria-selected')).toBe('true');
    });
});
