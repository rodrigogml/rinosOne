<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { loadPerson, type PersonDetail } from './peopleApi';
const props = defineProps<{ tenantId: number; personId: number }>();
const emit = defineEmits<{ close: []; edit: [personId: number] }>();
const { t } = useI18n(); const person = ref<PersonDetail | null>(null); const error = ref('');
onMounted(async () => { try { person.value = await loadPerson(props.tenantId, props.personId); } catch { error.value = t('access.people.detailsLoadFailed'); } });
</script>
<template><aside class="person-details" :aria-label="t('access.people.view')"><header><h2>{{ t('access.people.view') }}</h2><button type="button" @click="emit('close')">{{ t('access.people.back') }}</button></header><p v-if="error" role="alert">{{ error }}</p><p v-else-if="!person" role="status">{{ t('access.people.loading') }}</p><template v-else><h3>{{ person.displayName }}</h3><dl><dt>{{ t('access.people.personType') }}</dt><dd>{{ person.personType }}</dd><dt>{{ t('access.people.document') }}</dt><dd>{{ person.document ?? '—' }}</dd><dt>{{ t('access.people.status') }}</dt><dd>{{ person.status === 'ACTIVE' ? t('access.people.active') : t('access.people.inactive') }}</dd><dt>{{ t('access.people.contacts') }}</dt><dd>{{ person.contacts.length }}</dd><dt>{{ t('access.people.addresses') }}</dt><dd>{{ person.addresses.length }}</dd><dt>{{ t('access.people.bankAccounts') }}</dt><dd>{{ person.bankAccounts.length }}</dd></dl><button type="button" @click="emit('edit', person.id)">{{ t('access.people.edit') }}</button></template></aside></template>
<style scoped>.person-details{position:absolute;z-index:3;inset-block:0;inset-inline-end:0;inline-size:min(100%,28rem);overflow:auto;padding:var(--space-4);border-inline-start:1px solid var(--color-border-subtle);background:var(--color-surface-raised);box-shadow:var(--shadow-lg)}header{display:flex;justify-content:space-between;gap:var(--space-2)}h2,h3{margin-block-start:0}dl{display:grid;grid-template-columns:1fr 2fr;gap:var(--space-2)}dt{font-weight:700}@media(max-width:700px){.person-details{inline-size:100%;border:0}}</style>
