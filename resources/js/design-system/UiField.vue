<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(defineProps<{ id: string; label: string; help?: string; error?: string; required?: boolean }>(), { help: undefined, error: undefined, required: false });
const describedBy = computed(() => (props.error ? `${props.id}-error` : undefined));
const helpOpen = ref(false);
const validationOpen = ref(false);
const field = ref<HTMLElement | null>(null);
const helpPopoverId = computed(() => `${props.id}-help-popover`);
const validationPopoverId = computed(() => `${props.id}-validation-popover`);

function toggleHelp(): void {
    helpOpen.value = !helpOpen.value;
    if (helpOpen.value) validationOpen.value = false;
}

function toggleValidation(): void {
    validationOpen.value = !validationOpen.value;
    if (validationOpen.value) helpOpen.value = false;
}

function closeHelpOnOutsidePointer(event: PointerEvent): void {
    if (field.value && !field.value.contains(event.target as Node)) {
        helpOpen.value = false;
        validationOpen.value = false;
    }
}

function closeHelpOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && (helpOpen.value || validationOpen.value)) {
        helpOpen.value = false;
        validationOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeHelpOnOutsidePointer);
    document.addEventListener('keydown', closeHelpOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeHelpOnOutsidePointer);
    document.removeEventListener('keydown', closeHelpOnEscape);
});
</script>

<template>
    <div ref="field" class="ui-field">
        <div class="ui-field__label-row">
            <span v-if="error" class="ui-field__help-control">
                <button
                    class="ui-field__validation-trigger"
                    type="button"
                    :aria-controls="validationPopoverId"
                    :aria-expanded="validationOpen"
                    :aria-label="`Erro de validação em ${label}`"
                    @click="toggleValidation"
                >!</button>
                <span v-if="validationOpen" :id="validationPopoverId" class="ui-field__validation-popover" role="tooltip">{{ error }}</span>
            </span>
            <span v-if="help" class="ui-field__help-control">
                <button
                    class="ui-field__help-trigger"
                    type="button"
                    :aria-controls="helpPopoverId"
                    :aria-expanded="helpOpen"
                    :aria-label="`Ajuda sobre ${label}`"
                    @click="toggleHelp"
                >?</button>
                <span v-if="helpOpen" :id="helpPopoverId" class="ui-field__help-popover" role="tooltip">{{ help }}</span>
            </span>
            <label class="ui-field__label" :for="id">{{ label }}<span v-if="required" class="ui-field__required" aria-hidden="true"> *</span></label>
        </div>
        <slot :described-by="describedBy" :invalid="Boolean(error)" />
        <span v-if="error" :id="`${id}-error`" class="ui-field__validation-message" role="alert">{{ error }}</span>
    </div>
</template>
