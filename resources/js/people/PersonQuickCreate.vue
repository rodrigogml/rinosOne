<script setup lang="ts">
import UIRinoButton from '../design-system/UIRinoButton.vue';
import axios from "axios";
import { computed, nextTick, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { trapFocus } from "../accessibility/focusTrap";
import { useOnlineStatus } from "../network/useOnlineStatus";
import type { PeopleCapabilities } from "../tenant/tenantTypes";
import { createPerson, type PersonDetail, type PersonType } from "./peopleApi";

const props = defineProps<{
    tenantId: number;
    capabilities: PeopleCapabilities;
}>();
const emit = defineEmits<{ cancel: []; saved: [person: PersonDetail] }>();
const { t } = useI18n();
const title = ref<HTMLElement | null>(null);
const saving = ref(false);
const error = ref("");
const fieldErrors = ref<Record<string, string>>({});
const { online } = useOnlineStatus();
const model = ref<{
    personType: PersonType;
    name: string;
    alias: string;
    cpf: string;
    cnpj: string;
}>({ personType: "PF", name: "", alias: "", cpf: "", cnpj: "" });
const canSave = computed(
    () => online.value && !saving.value && props.capabilities.canCreatePeople,
);

function fields(value: unknown): void {
    if (!value || typeof value !== "object") return;
    fieldErrors.value = Object.fromEntries(
        Object.entries(value as Record<string, unknown>).map(
            ([key, messages]) => [
                key,
                Array.isArray(messages)
                    ? String(messages[0] ?? "")
                    : String(messages),
            ],
        ),
    );
}

async function save(): Promise<void> {
    if (!canSave.value) return;
    error.value = "";
    fieldErrors.value = {};
    if (model.value.name.trim().length < 2) {
        fieldErrors.value = { name: t("access.people.nameRequired") };
        return;
    }
    saving.value = true;
    try {
        emit(
            "saved",
            await createPerson(props.tenantId, {
                personType: model.value.personType,
                name: model.value.name.trim(),
                alias: model.value.alias.trim() || null,
                cpf:
                    model.value.personType === "PF"
                        ? model.value.cpf.trim() || null
                        : null,
                cnpj:
                    model.value.personType === "PJ"
                        ? model.value.cnpj.trim() || null
                        : null,
            }),
        );
    } catch (reason) {
        const responseError = axios.isAxiosError(reason)
            ? reason.response?.data?.error
            : null;
        if (responseError?.code === "PERSON_VALIDATION_FAILED") {
            fields(responseError.fields);
            error.value = t("access.people.validationFailed");
        } else if (responseError?.code === "PERSON_DOCUMENT_CONFLICT")
            error.value = t("access.people.documentConflict");
        else error.value = t("access.people.saveFailed");
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    await nextTick();
    title.value?.focus();
});
</script>

<template>
    <section
        class="person-quick-create"
        role="dialog"
        aria-modal="true"
        aria-labelledby="person-quick-create-title"
        tabindex="-1"
        @keydown="trapFocus"
    >
        <header>
            <h3 id="person-quick-create-title" ref="title" tabindex="-1">
                {{ t("access.people.quickCreateTitle") }}
            </h3>
            <UIRinoButton type="button" :disabled="saving" @click="emit('cancel')" command="cancel" />
        </header>
        <p>{{ t("access.people.quickCreateDescription") }}</p>
        <form @submit.prevent="save">
            <p v-if="!online" role="status">
                {{ t("access.people.offline") }}
            </p>
            <p v-if="error" role="alert">{{ error }}</p>
            <label
                >{{ t("access.people.personType")
                }}<select v-model="model.personType">
                    <option value="PF">PF</option>
                    <option value="PJ">PJ</option>
                </select></label
            >
            <label
                >{{
                    model.personType === "PF"
                        ? t("access.people.name")
                        : t("access.people.legalName")
                }}<input
                    v-model="model.name"
                    :aria-invalid="Boolean(fieldErrors.name)"
                /><small v-if="fieldErrors.name" role="alert">{{
                    fieldErrors.name
                }}</small></label
            >
            <label
                >{{ t("access.people.alias")
                }}<input v-model="model.alias" /><small>{{
                    t("access.people.optional")
                }}</small></label
            >
            <label
                >{{
                    model.personType === "PF"
                        ? t("access.people.cpf")
                        : t("access.people.cnpj")
                }}<input
                    v-if="model.personType === 'PF'"
                    v-model="model.cpf"
                    :aria-invalid="Boolean(fieldErrors.cpf)"
                /><input
                    v-else
                    v-model="model.cnpj"
                    :aria-invalid="Boolean(fieldErrors.cnpj)"
                /><small>{{ t("access.people.optional") }}</small
                ><small
                    v-if="fieldErrors.cpf || fieldErrors.cnpj"
                    role="alert"
                    >{{ fieldErrors.cpf || fieldErrors.cnpj }}</small
                ></label
            >
            <footer>
                <UIRinoButton type="button" :disabled="saving" @click="emit('cancel')" command="cancel" /><UIRinoButton command="save" type="submit" :disabled="!canSave" :loading="saving" :label="saving ? 'access.people.saving' : undefined" />
            </footer>
        </form>
    </section>
</template>

<style scoped>
.person-quick-create {
    position: fixed;
    z-index: 20;
    inset: 50% auto auto 50%;
    width: min(34rem, calc(100vw - 2rem));
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
    transform: translate(-50%, -50%);
    border: var(--component-border-width) solid var(--color-border-subtle);
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
    box-shadow: var(--shadow-lg);
}
header,
footer {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}
header h3 {
    flex: 1;
}
form {
    display: grid;
    gap: var(--space-3);
}
label {
    display: grid;
    gap: var(--space-1);
}
footer {
    justify-content: flex-end;
}
@media (max-width: 700px) {
    .person-quick-create {
        inset: 0;
        width: 100%;
        min-height: 100dvh;
        overflow: auto;
        padding-bottom: max(var(--space-4), env(safe-area-inset-bottom));
        transform: none;
        border-radius: 0;
    }
    .person-quick-create input,
    .person-quick-create select {
        font-size: max(1rem, 16px);
    }
}
</style>
