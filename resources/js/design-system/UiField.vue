<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ id: string; label: string; help?: string; error?: string; required?: boolean }>(), { help: undefined, error: undefined, required: false });
const describedBy = computed(() => [props.help ? `${props.id}-help` : null, props.error ? `${props.id}-error` : null].filter(Boolean).join(' ') || undefined);
</script>

<template><div class="ui-field"><label class="ui-field__label" :for="id">{{ label }}<span v-if="required" aria-hidden="true"> *</span></label><slot :described-by="describedBy" :invalid="Boolean(error)" /><p v-if="help" :id="`${id}-help`" class="ui-field__help">{{ help }}</p><p v-if="error" :id="`${id}-error`" class="ui-field__error" role="alert">{{ error }}</p></div></template>
