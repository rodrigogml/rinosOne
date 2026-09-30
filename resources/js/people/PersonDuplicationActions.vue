<script setup lang="ts">
import { nextTick, ref } from "vue";
import { useI18n } from "vue-i18n";
import { trapFocus } from "../accessibility/focusTrap";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import { duplicatePerson, type PersonDetail } from "./peopleApi";

const props = defineProps<{
    tenantId: number;
    person: PersonDetail;
    capabilities: PeopleCapabilities;
}>();
const emit = defineEmits<{ duplicated: [person: PersonDetail] }>();
const { t } = useI18n();
const open = ref(false);
const processing = ref(false);
const error = ref("");
const copyAddresses = ref(false);
const copyContacts = ref(false);
const copyBankAccounts = ref(false);
const copyPixKeys = ref(false);
const title = ref<HTMLElement | null>(null);
let trigger: HTMLElement | null = null;

async function show(event: MouseEvent): Promise<void> {
    trigger = event.currentTarget as HTMLElement;
    await openFromCatalog(trigger);
}

async function openFromCatalog(source: HTMLElement | null): Promise<void> {
    trigger = source;
    error.value = "";
    open.value = true;
    await nextTick();
    title.value?.focus();
}

function close(): void {
    if (processing.value) return;
    open.value = false;
    void nextTick(() => trigger?.focus());
}

async function confirm(): Promise<void> {
    if (processing.value) return;

    processing.value = true;
    error.value = "";

    try {
        emit(
            "duplicated",
            await duplicatePerson(
                props.tenantId,
                props.person.id,
                props.person.version,
                {
                    copyAddresses: copyAddresses.value,
                    copyContacts: copyContacts.value,
                    copyBankAccounts: copyBankAccounts.value,
                    copyPixKeys: copyPixKeys.value,
                },
            ),
        );
        open.value = false;
    } catch {
        error.value = t("access.people.duplicateFailed");
    } finally {
        processing.value = false;
    }
}
defineExpose({ openFromCatalog });
</script>

<template>
    <section
        class="person-duplication-actions"
        :aria-label="t('access.people.duplicate')"
    >
        <button
            v-if="capabilities.canDuplicatePeople"
            type="button"
            @click="show($event)"
        >
            {{ t("access.people.duplicate") }}
        </button>
        <section
            v-if="open"
            class="person-duplication-actions__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="person-duplication-title"
            tabindex="-1"
            @keydown="trapFocus"
        >
            <h4 id="person-duplication-title" ref="title" tabindex="-1">
                {{ t("access.people.duplicateTitle") }}
            </h4>
            <p>{{ t("access.people.duplicateIdentityNotice") }}</p>
            <fieldset :disabled="processing">
                <legend>
                    {{ t("access.people.duplicateChooseCollections") }}
                </legend>
                <label
                    ><input v-model="copyAddresses" type="checkbox" />
                    {{ t("access.people.addresses") }}</label
                >
                <label
                    ><input v-model="copyContacts" type="checkbox" />
                    {{ t("access.people.contacts") }}</label
                >
                <label
                    ><input v-model="copyBankAccounts" type="checkbox" />
                    {{ t("access.people.bankAccounts") }}</label
                >
                <label
                    ><input v-model="copyPixKeys" type="checkbox" />
                    {{ t("access.people.pixKeys") }}</label
                >
            </fieldset>
            <p v-if="error" role="alert">{{ error }}</p>
            <footer>
                <button type="button" :disabled="processing" @click="close">
                    {{ t("access.people.cancel") }}
                </button>
                <button type="button" :disabled="processing" @click="confirm">
                    {{
                        processing
                            ? t("access.people.duplicating")
                            : t("access.people.duplicatePerson")
                    }}
                </button>
            </footer>
        </section>
    </section>
</template>

<style scoped>
.person-duplication-actions__dialog {
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
fieldset {
    display: grid;
    gap: var(--space-2);
    border: 0;
    padding: 0;
}
footer {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: var(--space-2);
}
@media (max-width: 700px) {
    .person-duplication-actions__dialog {
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
