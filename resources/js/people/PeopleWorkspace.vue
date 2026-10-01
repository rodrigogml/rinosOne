<script setup lang="ts">
import { nextTick, ref } from "vue";
import { useI18n } from "vue-i18n";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import type { WorkspaceSurface } from "../workspace/workspaceTypes";
import PeopleCatalog from "./PeopleCatalog.vue";
import PersonForm from "./PersonForm.vue";
import PersonQuickCreate from "./PersonQuickCreate.vue";
import PersonDetailsPanel from "./PersonDetailsPanel.vue";

type CatalogAction =
    "open" | "duplicate" | "inactivate" | "reactivate" | "delete" | "edit" | "view";
type RequestedAction = Exclude<CatalogAction, "open" | "edit" | "view">;
const props = withDefaults(
    defineProps<{
        surface: WorkspaceSurface;
        capabilities?: PeopleCapabilities;
        tenantName?: string | null;
    }>(),
    { capabilities: () => ({}), tenantName: null },
);
const catalog = ref<InstanceType<typeof PeopleCatalog> | null>(null);
const { t } = useI18n();
const personId = ref<number | null | undefined>(undefined);
const announcement = ref("");
const quickCreate = ref(false);
const requestedAction = ref<RequestedAction | null>(null);
const requestedActionTrigger = ref<HTMLElement | null>(null);
const viewedPersonId = ref<number | null>(null);

function openAction(
    personIdToOpen: number,
    action: CatalogAction,
    trigger: HTMLElement,
): void {
    if (action === "open" || action === "edit") {
        requestedAction.value = null;
        personId.value = personIdToOpen;
        return;
    }
    if (action === "view") { viewedPersonId.value = personIdToOpen; return; }
    requestedAction.value = action;
    requestedActionTrigger.value = trigger;
    personId.value = personIdToOpen;
}

function openDuplicatedPerson(person: { id: number }): void {
    announcement.value = t("access.people.duplicateOpened");
    requestedAction.value = null;
    requestedActionTrigger.value = null;
    viewedPersonId.value = null;
    personId.value = person.id;
}

async function close(message = ""): Promise<void> {
    announcement.value = message;
    personId.value = undefined;
    quickCreate.value = false;
    requestedAction.value = null;
    requestedActionTrigger.value = null;
    viewedPersonId.value = null;
    await nextTick();
    await catalog.value?.refresh();
    await catalog.value?.focus();
}
</script>
<template>
    <section class="people-workspace">
        <p v-if="announcement" role="status" aria-live="polite">
            {{ announcement }}
        </p>
        <section v-show="personId === undefined" class="people-workspace__catalog-context">
            <PeopleCatalog
                ref="catalog"
                :surface="surface"
                :capabilities="capabilities"
                @create="personId = null"
                @quick-create="quickCreate = true"
                @open="personId = $event"
                @action="openAction"
            />
            <PersonDetailsPanel v-if="viewedPersonId !== null" :tenant-id="surface.tenantId!" :person-id="viewedPersonId" @close="viewedPersonId = null" @edit="(id) => { viewedPersonId = null; personId = id; }" />
        </section>
        <PersonQuickCreate
            v-if="quickCreate"
            :tenant-id="surface.tenantId!"
            :capabilities="capabilities"
            @cancel="close"
            @saved="close(t('access.people.savedSuccess'))"
        />
        <PersonForm
            v-if="personId !== undefined"
            :key="`${personId ?? 'new'}-${requestedAction ?? 'edit'}`"
            :tenant-id="surface.tenantId!"
            :person-id="personId"
            :capabilities="capabilities"
            :initial-action="requestedAction"
            :initial-action-trigger="requestedActionTrigger"
            @cancel="close"
            @saved="close(t('access.people.savedSuccess'))"
            @duplicated="openDuplicatedPerson"
        />
    </section>
</template>

<style scoped>
:global(.workspace-stage__surface-content:has(.people-workspace)) {
    align-content: stretch;
    align-items: stretch;
}

:global(.workspace-stage__surface-instance:has(.people-workspace)) {
    min-block-size: 100%;
    block-size: 100%;
}

.people-workspace {
    display: grid;
    min-block-size: 100%;
    block-size: 100%;
}
.people-workspace__catalog-context {
    position: relative;
    min-block-size: 100%;
    block-size: 100%;
}
</style>
