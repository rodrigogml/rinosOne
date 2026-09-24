<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useVisualPreferencesStore, type LocalePreference } from '../preferences/visualPreferences';

const { t } = useI18n();
const store = useVisualPreferencesStore();
const root = ref<HTMLElement | null>(null);
const opener = ref<HTMLButtonElement | null>(null);
const menu = ref<HTMLElement | null>(null);
const open = ref(false);
const languages: { code: LocalePreference; flag: string; name: string }[] = [
    { code: 'pt-BR', flag: '🇧🇷', name: 'Português (Brasil)' }, { code: 'en', flag: '🇺🇸', name: 'English' },
    { code: 'es', flag: '🇪🇸', name: 'Español' }, { code: 'fr', flag: '🇫🇷', name: 'Français' },
];
const currentLanguage = computed(() => languages.find((language) => language.code === store.preferences.locale) ?? languages[0]);
function close() { open.value = false; }
function select(locale: LocalePreference) { store.update({ locale }); close(); }
function move(event: KeyboardEvent) {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End', 'Escape'].includes(event.key)) return;
    if (event.key === 'Escape') { event.preventDefault(); close(); return; }
    event.preventDefault();
    const currentIndex = languages.findIndex((language) => language.code === store.preferences.locale);
    const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? languages.length - 1 : (currentIndex + (event.key === 'ArrowDown' ? 1 : -1) + languages.length) % languages.length;
    document.getElementById(`language-option-${languages[nextIndex].code}`)?.focus();
}
function handlePointerDown(event: PointerEvent) { if (open.value && root.value && !root.value.contains(event.target as Node)) close(); }
watch(open, async (visible) => { if (visible) { await nextTick(); document.getElementById(`language-option-${store.preferences.locale}`)?.focus(); return; } opener.value?.focus(); });
onMounted(() => document.addEventListener('pointerdown', handlePointerDown));
onBeforeUnmount(() => document.removeEventListener('pointerdown', handlePointerDown));
</script>

<template><div ref="root" class="presentation-control"><button ref="opener" class="ui-icon-button" type="button" :aria-label="t('access.presentation.selectedLanguage', { language: currentLanguage.name })" :aria-expanded="open" aria-haspopup="listbox" @click="open = !open"><span aria-hidden="true">{{ currentLanguage.flag }}</span></button><ul v-if="open" ref="menu" class="language-menu" role="listbox" :aria-label="t('access.presentation.language')" @keydown="move"><li v-for="language in languages" :key="language.code"><button :id="`language-option-${language.code}`" type="button" role="option" class="language-menu__option" :aria-selected="store.preferences.locale === language.code" @click="select(language.code)"><span aria-hidden="true">{{ language.flag }}</span><span>{{ language.name }}</span></button></li></ul></div></template>
