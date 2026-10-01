<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import WorkspaceSurfaceIcon from '../design-system/WorkspaceSurfaceIcon.vue';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';

type PermissionManagementTab = 'roles' | 'groups' | 'users';

const props = defineProps<{ surface: WorkspaceSurface }>();
const { t } = useI18n();
const activeTab = ref<PermissionManagementTab>('roles');

const tabs = computed((): ReadonlyArray<{ id: PermissionManagementTab; icon: string; label: string; title: string; description: string }> => [
    { id: 'roles', icon: 'secRoles', label: t('access.permissionManagement.tabs.roles'), title: t('access.permissionManagement.roles.title'), description: t('access.permissionManagement.roles.description') },
    { id: 'groups', icon: 'secGroups', label: t('access.permissionManagement.tabs.groups'), title: t('access.permissionManagement.groups.title'), description: t('access.permissionManagement.groups.description') },
    { id: 'users', icon: 'rinoUser', label: t('access.permissionManagement.tabs.users'), title: t('access.permissionManagement.users.title'), description: t('access.permissionManagement.users.description') },
]);
const selectedTab = computed(() => tabs.value.find((tab) => tab.id === activeTab.value) ?? tabs.value[0]!);

async function selectTab(tab: PermissionManagementTab): Promise<void> {
    activeTab.value = tab;
    await nextTick();
    document.getElementById(`permission-management-panel-${tab}`)?.focus();
}

</script>

<template>
    <section class="permission-management" :data-scope="surface.scope">
        <div class="permission-management__tabs" role="tablist" :aria-label="t('access.permissionManagement.tabList')">
            <button v-for="tab in tabs" :id="`permission-management-tab-${tab.id}`" :key="tab.id" type="button" role="tab" :aria-controls="`permission-management-panel-${tab.id}`" :aria-selected="activeTab === tab.id" :class="{ 'permission-management__tab--active': activeTab === tab.id }" @click="selectTab(tab.id)"><WorkspaceSurfaceIcon :name="tab.icon" size="sm" />{{ tab.label }}</button>
        </div>
        <section :id="`permission-management-panel-${selectedTab.id}`" class="permission-management__panel" role="tabpanel" :aria-labelledby="`permission-management-tab-${selectedTab.id}`" tabindex="-1"><h3>{{ selectedTab.title }}</h3><p>{{ selectedTab.description }}</p><p class="permission-management__empty">{{ t('access.permissionManagement.empty') }}</p></section>
    </section>
</template>
