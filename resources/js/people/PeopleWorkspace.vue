<script setup lang="ts">
import { nextTick, ref } from "vue";
import { useI18n } from "vue-i18n";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import type { WorkspaceSurface } from "../workspace/workspaceTypes";
import PeopleCatalog from "./PeopleCatalog.vue";
import PersonForm from "./PersonForm.vue";
import PersonQuickCreate from "./PersonQuickCreate.vue";

type CatalogAction =
    "open" | "duplicate" | "inactivate" | "reactivate" | "delete";
type RequestedAction = Exclude<CatalogAction, "open">;
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

function openAction(
    personIdToOpen: number,
    action: CatalogAction,
    trigger: HTMLElement,
): void {
    if (action === "open") {
        personId.value = personIdToOpen;
        return;
    }
    requestedAction.value = action;
    requestedActionTrigger.value = trigger;
    personId.value = personIdToOpen;
}

function openDuplicatedPerson(person: { id: number }): void {
    announcement.value = t("access.people.duplicateOpened");
    requestedAction.value = null;
    requestedActionTrigger.value = null;
    personId.value = person.id;
}

async function close(message = ""): Promise<void> {
    announcement.value = message;
    personId.value = undefined;
    quickCreate.value = false;
    requestedAction.value = null;
    requestedActionTrigger.value = null;
    await nextTick();
    await catalog.value?.refresh();
    await catalog.value?.focus();
}
</script>
<template>
    <p v-if="announcement" role="status" aria-live="polite">
        {{ announcement }}
    </p>
    <PeopleCatalog
        v-show="personId === undefined"
        ref="catalog"
        :surface="surface"
        :capabilities="capabilities"
        :tenant-name="tenantName"
        @create="personId = null"
        @quick-create="quickCreate = true"
        @open="personId = $event"
        @action="openAction"
    />
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
</template>
