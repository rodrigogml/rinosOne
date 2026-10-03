<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import UIRinoButton from '../UIRinoButton.vue';
import UiDialog from '../UiDialog.vue';
import { subscribeDialogs, type DialogRequest } from './dialogService';

const active = ref<DialogRequest | null>(null);
const queue = ref<DialogRequest[]>([]);
const validationExpanded = ref(false);
let unsubscribe: (() => void) | null = null;

const presentation = computed(() => active.value?.presentation ?? null);
const initialFocusSelector = computed(() => active.value ? `[data-dialog-action=${JSON.stringify(active.value.options.initialFocusActionId)}]` : '');
const validationIssues = computed(() => active.value?.options.model === 'validation' ? active.value.options.validationIssues : []);
const initialVisibleIssueCount = computed(() => active.value?.options.model === 'validation' ? active.value.options.initialVisibleIssueCount : 0);
const visibleValidationIssues = computed(() => validationExpanded.value ? validationIssues.value : validationIssues.value.slice(0, initialVisibleIssueCount.value));
const hasHiddenValidationIssues = computed(() => !validationExpanded.value && validationIssues.value.length > visibleValidationIssues.value.length);

function enqueue(request: DialogRequest): void {
    if (active.value === null) {
        active.value = request;
        return;
    }

    queue.value = [...queue.value, request];
}

function resolve(actionId: string, issueId?: string, fieldId?: string): void {
    if (!active.value) return;

    active.value.resolve({ actionId, issueId, fieldId });
    active.value = queue.value.shift() ?? null;
}

watch(() => active.value?.id, () => { validationExpanded.value = false; });

function resolveEscape(): void {
    const escapeActionId = active.value?.options.escapeActionId;
    if (escapeActionId !== undefined) resolve(escapeActionId);
}

onMounted(() => { unsubscribe = subscribeDialogs(enqueue); });
onBeforeUnmount(() => { unsubscribe?.(); });
</script>

<template>
    <UiDialog
        :key="active?.id"
        :model-value="active !== null"
        :title="active?.options.title ?? ''"
        :window-like="true"
        :icon-src="presentation?.iconSrc ?? ''"
        :tone="presentation?.tone ?? ''"
        :message-dialog="true"
        :destructive="presentation?.urgent === true"
        :escapable="active?.options.escapeActionId !== undefined"
        :backdrop-dismissible="false"
        :initial-focus-selector="initialFocusSelector"
        @update:model-value="resolveEscape"
    >
        <div v-if="active" class="dialog-host__message-region" :class="{ 'dialog-host__message-region--validation': active.options.model === 'validation' }">
            <template v-if="active.options.model === 'validation'">
                <p class="dialog-host__validation-summary">Corrija os campos indicados para continuar.</p>
                <ul class="dialog-host__validation-issues">
                    <li v-for="issue in visibleValidationIssues" :key="issue.id"><button type="button" class="dialog-host__validation-issue" @click="resolve('validation-issue', issue.id, issue.fieldId)">{{ issue.message }}</button></li>
                </ul>
                <UIRinoButton v-if="hasHiddenValidationIssues" class="dialog-host__validation-more" variant="secondary" @click="validationExpanded = true" label="rinoButtons.context.allValidationMessages" :label-params="{ value1: validationIssues.length }" />
            </template>
            <p v-else class="dialog-host__message">{{ active.options.message }}</p>
        </div>
        <footer v-if="active" class="dialog-host__actions">
            <UIRinoButton v-for="button in active.options.buttons" :key="button.id" :command="button.command" :variant="button.variant" :icon="button.icon" :accessible-label="button.accessibleLabel" :data-dialog-action="button.id" @click="resolve(button.id)" :label="button.label" />
        </footer>
    </UiDialog>
</template>
