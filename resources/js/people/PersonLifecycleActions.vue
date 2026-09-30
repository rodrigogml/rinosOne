<script setup lang="ts">
import { nextTick, ref } from "vue";
import { useI18n } from "vue-i18n";
import { trapFocus } from "../accessibility/focusTrap";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import {
    changePersonStatus,
    deletePerson,
    deletionUsage,
    type PersonDetail,
} from "./peopleApi";

const props = defineProps<{
    tenantId: number;
    person: PersonDetail;
    capabilities: PeopleCapabilities;
}>();
const emit = defineEmits<{
    changed: [person: PersonDetail];
    deleted: [personId: number];
}>();
const { t } = useI18n();
const mode = ref<"INACTIVATE" | "REACTIVATE" | "DELETE" | null>(null);
const usages = ref<{ module: string; description: string }[]>([]);
const processing = ref(false);
const error = ref("");
const title = ref<HTMLElement | null>(null);
let trigger: HTMLElement | null = null;

async function show(
    nextMode: "INACTIVATE" | "REACTIVATE",
    event: MouseEvent,
): Promise<void> {
    trigger = event.currentTarget as HTMLElement;
    await openFromCatalog(nextMode, trigger);
}

async function openFromCatalog(
    nextMode: "INACTIVATE" | "REACTIVATE" | "DELETE",
    source: HTMLElement | null,
): Promise<void> {
    trigger = source;
    if (nextMode === "DELETE") {
        await requestDeleteFromCatalog();
        return;
    }
    error.value = "";
    mode.value = nextMode;
    await nextTick();
    title.value?.focus();
}

async function requestDelete(event: MouseEvent): Promise<void> {
    trigger = event.currentTarget as HTMLElement;
    await requestDeleteFromCatalog();
}

async function requestDeleteFromCatalog(): Promise<void> {
    error.value = "";
    usages.value = [];
    try {
        usages.value = await deletionUsage(props.tenantId, props.person.id);
        mode.value = "DELETE";
        await nextTick();
        title.value?.focus();
    } catch {
        error.value = t("access.people.lifecycleLoadFailed");
    }
}

function close(): void {
    if (processing.value) return;
    mode.value = null;
    void nextTick(() => trigger?.focus());
}

async function confirm(): Promise<void> {
    if (mode.value === null || processing.value) return;
    processing.value = true;
    error.value = "";
    try {
        if (mode.value === "DELETE") {
            if (usages.value.length) return;
            await deletePerson(
                props.tenantId,
                props.person.id,
                props.person.version,
            );
            emit("deleted", props.person.id);
            return;
        }
        emit(
            "changed",
            await changePersonStatus(
                props.tenantId,
                props.person.id,
                props.person.version,
                mode.value === "INACTIVATE" ? "inactivate" : "reactivate",
            ),
        );
        mode.value = null;
        void nextTick(() => trigger?.focus());
    } catch {
        error.value = t("access.people.lifecycleActionFailed");
    } finally {
        processing.value = false;
    }
}
defineExpose({ openFromCatalog });
</script>

<template>
    <section
        class="person-lifecycle-actions"
        :aria-label="t('access.people.lifecycleActions')"
    >
        <button
            v-if="
                person.status === 'ACTIVE' && capabilities.canInactivatePeople
            "
            type="button"
            @click="show('INACTIVATE', $event)"
        >
            {{ t("access.people.inactivate") }}
        </button>
        <button
            v-if="
                person.status === 'INACTIVE' && capabilities.canReactivatePeople
            "
            type="button"
            @click="show('REACTIVATE', $event)"
        >
            {{ t("access.people.reactivate") }}
        </button>
        <button
            v-if="capabilities.canDeletePeople"
            type="button"
            @click="requestDelete($event)"
        >
            {{ t("access.people.delete") }}
        </button>
        <p v-if="error && !mode" role="alert">{{ error }}</p>
        <section
            v-if="mode"
            role="dialog"
            aria-modal="true"
            aria-labelledby="person-lifecycle-title"
            tabindex="-1"
            @keydown="trapFocus"
        >
            <h4 id="person-lifecycle-title" ref="title" tabindex="-1">
                {{
                    mode === "DELETE"
                        ? t("access.people.deleteTitle")
                        : mode === "INACTIVATE"
                          ? t("access.people.inactivateTitle")
                          : t("access.people.reactivateTitle")
                }}
            </h4>
            <p v-if="mode === 'DELETE' && usages.length">
                {{ t("access.people.deleteBlocked") }}
            </p>
            <ul v-if="mode === 'DELETE' && usages.length">
                <li
                    v-for="usage in usages"
                    :key="`${usage.module}-${usage.description}`"
                >
                    {{ usage.description }}
                </li>
            </ul>
            <p v-else>
                {{
                    mode === "DELETE"
                        ? t("access.people.deleteDescription")
                        : mode === "INACTIVATE"
                          ? t("access.people.inactivateDescription")
                          : t("access.people.reactivateDescription")
                }}
            </p>
            <p v-if="error" role="alert">{{ error }}</p>
            <button type="button" :disabled="processing" @click="close">
                {{ t("access.people.cancel") }}</button
            ><button
                type="button"
                :disabled="processing || usages.length > 0"
                @click="confirm"
            >
                {{ t("access.people.confirm") }}
            </button>
        </section>
    </section>
</template>

<style scoped>
.person-lifecycle-actions {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
}
.person-lifecycle-actions [role="dialog"] {
    position: fixed;
    z-index: 20;
    inset: 50% auto auto 50%;
    width: min(32rem, calc(100vw - 2rem));
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
    transform: translate(-50%, -50%);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
    box-shadow: var(--shadow-lg);
}
.person-lifecycle-actions [role="dialog"] ul {
    margin: 0;
    padding-inline-start: var(--space-5);
}
.person-lifecycle-actions [role="dialog"] button + button {
    margin-inline-start: var(--space-2);
}
@media (max-width: 700px) {
    .person-lifecycle-actions [role="dialog"] {
        inset: auto 0 0;
        width: 100%;
        max-height: 90dvh;
        overflow: auto;
        padding-bottom: max(var(--space-4), env(safe-area-inset-bottom));
        transform: none;
        border-radius: var(--radius-md) var(--radius-md) 0 0;
    }
}
</style>
