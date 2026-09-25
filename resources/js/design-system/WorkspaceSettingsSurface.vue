<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import UiButton from './UiButton.vue';
import UiDialog from './UiDialog.vue';
import { useVisualPreferencesStore, type DensityPreference, type PalettePreference, type ThemePreference } from '../preferences/visualPreferences';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';

defineProps<{ surface: WorkspaceSurface }>();

type SettingsSection = 'theme' | 'sessions' | 'security';
const activeSection = ref<SettingsSection>('theme');
const visualPreferences = useVisualPreferencesStore();
const preferences = computed(() => visualPreferences.preferences);
const effectiveTheme = computed<'light' | 'dark'>(() => preferences.value.theme === 'system'
    ? window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    : preferences.value.theme);
const themes: readonly { id: PalettePreference; label: string }[] = [
    { id: 'amethyst-technical', label: 'Ametista Técnica' }, { id: 'architectural-teal', label: 'Teal Arquitetônico' },
    { id: 'refined-copper', label: 'Cobre Refinado' }, { id: 'imperial-wine', label: 'Vinho Imperial' },
    { id: 'sober-emerald', label: 'Esmeralda Sóbria' }, { id: 'mineral-gold', label: 'Ouro Mineral' },
    { id: 'orbital-indigo', label: 'Índigo Orbital' }, { id: 'industrial-ruby', label: 'Rubi Industrial' },
    { id: 'deep-cyan', label: 'Ciano Profundo' }, { id: 'executive-coral', label: 'Coral Executivo' },
];
const sessionRows = [
    { name: 'Este navegador', detail: 'Sessão atual · ativa agora', state: 'Atual' },
];
const sectionTitle = computed(() => ({ theme: 'Tema e aparência', sessions: 'Sessões ativas', security: 'Segurança da conta' })[activeSection.value]);
const scales: readonly { value: DensityPreference; label: string }[] = [
    { value: 'compact', label: 'Compacta' }, { value: 'default', label: 'Padrão' }, { value: 'comfortable', label: 'Confortável' },
];
const passwordDefined = ref(false);
const passwordDialogOpen = ref(false);
const removePasswordDialogOpen = ref(false);
const revokeDialogOpen = ref(false);
const newPassword = ref('');
const confirmationPassword = ref('');
const passwordError = ref('');
const actionError = ref('');
const processing = ref(false);

function updatePalette(palette: PalettePreference): void { visualPreferences.update({ palette }); }
function updateTheme(theme: ThemePreference): void { visualPreferences.update({ theme }); }
function updateScale(field: 'fontScale' | 'spacingScale' | 'componentScale', value: DensityPreference): void { visualPreferences.update({ [field]: value }); }
function passwordStrengthIsValid(value: string): boolean {
    const categories = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z\d]/].filter((pattern) => pattern.test(value)).length;
    return value.length >= 6 && categories >= 2;
}
function openPasswordDialog(): void { passwordError.value = ''; newPassword.value = ''; confirmationPassword.value = ''; passwordDialogOpen.value = true; }
async function savePassword(): Promise<void> {
    passwordError.value = '';
    if (!passwordStrengthIsValid(newPassword.value)) { passwordError.value = 'Use ao menos 6 caracteres e 2 entre letras maiúsculas, minúsculas, números e símbolos.'; return; }
    if (newPassword.value !== confirmationPassword.value) { passwordError.value = 'A confirmação não corresponde à nova senha.'; return; }
    processing.value = true;
    try { await axios.put('/api/v1/auth/password', { password: newPassword.value }); passwordDefined.value = true; passwordDialogOpen.value = false; }
    catch { passwordError.value = 'Não foi possível salvar a senha agora. Tente novamente.'; }
    finally { processing.value = false; }
}
async function removePassword(): Promise<void> {
    processing.value = true; actionError.value = '';
    try { await axios.delete('/api/v1/auth/password'); passwordDefined.value = false; removePasswordDialogOpen.value = false; }
    catch { actionError.value = 'Não foi possível remover a senha agora. Tente novamente.'; }
    finally { processing.value = false; }
}
async function revokeOtherSessions(): Promise<void> {
    processing.value = true; actionError.value = '';
    try { await axios.delete('/api/v1/auth/other-sessions'); revokeDialogOpen.value = false; }
    catch { actionError.value = 'Não foi possível encerrar as outras sessões agora. Tente novamente.'; }
    finally { processing.value = false; }
}
onMounted(async () => {
    try { passwordDefined.value = Boolean((await axios.get('/api/v1/auth/session')).data?.user?.passwordDefined); } catch { /* A casca autenticada preserva a sessão já estabelecida. */ }
});
</script>

<template>
    <div class="workspace-settings">
        <aside class="workspace-settings__navigation" aria-label="Categorias de configurações">
            <h3>Configurações</h3>
            <nav class="workspace-settings__nav-list" aria-label="Seções de configurações">
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'theme' }" class="workspace-settings__nav-item" @click="activeSection = 'theme'">Tema e aparência</button>
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'sessions' }" class="workspace-settings__nav-item" @click="activeSection = 'sessions'">Sessões</button>
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'security' }" class="workspace-settings__nav-item" @click="activeSection = 'security'">Segurança</button>
            </nav>
        </aside>

        <section class="workspace-settings__content" :aria-label="sectionTitle">
            <header class="workspace-settings__content-header"><h3>{{ sectionTitle }}</h3></header>

            <template v-if="activeSection === 'theme'">
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Família cromática</h4><p>Escolha a cor de destaque da sua interface.</p></div></div><div class="workspace-settings__theme-grid"><button v-for="(theme, index) in themes" :key="theme.id" type="button" class="workspace-settings__theme-card" :class="{ 'workspace-settings__theme-card--selected': preferences.palette === theme.id }" @click="updatePalette(theme.id)"><span class="workspace-settings__theme-swatch" :class="`workspace-settings__theme-swatch--${index}`" /><span>{{ theme.label }}</span><small v-if="preferences.palette === theme.id">Selecionado</small></button></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Luminosidade</h4><p>Defina a base clara ou escura; a cor escolhida permanece apenas como destaque.</p></div><div class="workspace-settings__mode-choice" role="group" aria-label="Preferência de luminosidade"><button type="button" :class="{ 'workspace-settings__mode--selected': effectiveTheme === 'light' }" class="workspace-settings__mode" @click="updateTheme('light')">Claro</button><button type="button" :class="{ 'workspace-settings__mode--selected': effectiveTheme === 'dark' }" class="workspace-settings__mode" @click="updateTheme('dark')">Escuro</button></div></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Tamanho do texto</h4><p>Ajuste a leitura sem alterar o conteúdo.</p></div><div class="workspace-settings__choice-row"><button v-for="scale in scales" :key="scale.value" type="button" class="workspace-settings__choice" :class="{ 'workspace-settings__choice--selected': preferences.fontScale === scale.value }" @click="updateScale('fontScale', scale.value)">{{ scale.label }}</button></div></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Espaçamento</h4><p>Escolha uma composição mais compacta ou confortável.</p></div><div class="workspace-settings__choice-row"><button v-for="scale in scales" :key="scale.value" type="button" class="workspace-settings__choice" :class="{ 'workspace-settings__choice--selected': preferences.spacingScale === scale.value }" @click="updateScale('spacingScale', scale.value)">{{ scale.label }}</button></div></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Tamanho dos elementos</h4><p>Controle ícones, campos e demais componentes visuais.</p></div><div class="workspace-settings__choice-row"><button v-for="scale in scales" :key="scale.value" type="button" class="workspace-settings__choice" :class="{ 'workspace-settings__choice--selected': preferences.componentScale === scale.value }" @click="updateScale('componentScale', scale.value)">{{ scale.label }}</button></div></div></section>
            </template>

            <template v-else-if="activeSection === 'sessions'">
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Dispositivos conectados</h4><p>Revise a sessão atual e encerre acessos mantidos em outros dispositivos.</p></div><UiButton variant="secondary" @click="revokeDialogOpen = true">Encerrar demais sessões</UiButton></div><div class="workspace-settings__list"><article v-for="session in sessionRows" :key="session.name" class="workspace-settings__list-item"><span class="workspace-settings__device-icon">▣</span><div><strong>{{ session.name }}</strong><p>{{ session.detail }}</p></div><span class="workspace-settings__state workspace-settings__state--current">{{ session.state }}</span></article></div><p v-if="actionError" class="workspace-settings__error" role="alert">{{ actionError }}</p></section>
            </template>

            <template v-else>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Métodos de acesso</h4><p>Defina como a sua conta poderá ser autenticada.</p></div></div><div class="workspace-settings__setting-row"><div><strong>Senha</strong><p>Use senha junto do acesso sem senha por e-mail.</p></div><span class="workspace-settings__status-chip">{{ passwordDefined ? 'Definida' : 'Não definida' }}</span><div class="workspace-settings__setting-actions"><UiButton variant="secondary" @click="openPasswordDialog">{{ passwordDefined ? 'Alterar senha' : 'Definir senha' }}</UiButton><UiButton v-if="passwordDefined" variant="destructive" @click="removePasswordDialogOpen = true">Remover senha</UiButton></div></div><div class="workspace-settings__setting-row"><div><strong>Autenticação em dois fatores</strong><p>Solicite uma confirmação adicional em acessos sensíveis.</p></div><span class="workspace-settings__status-chip">Em breve</span><UiButton variant="secondary" disabled>Configurar</UiButton></div><p v-if="actionError" class="workspace-settings__error" role="alert">{{ actionError }}</p></section>
            </template>
        </section>
        <UiDialog v-model="revokeDialogOpen" contained title="Encerrar demais sessões" :backdrop-dismissible="false"><p class="dialog-description">Os demais dispositivos perderão o acesso e precisarão autenticar novamente. Esta sessão continuará aberta.</p><div class="dialog-actions"><UiButton variant="secondary" :disabled="processing" @click="revokeDialogOpen = false">Cancelar</UiButton><UiButton :loading="processing" @click="revokeOtherSessions">Encerrar sessões</UiButton></div></UiDialog>
        <UiDialog v-model="passwordDialogOpen" contained :title="passwordDefined ? 'Alterar senha' : 'Definir senha'" :backdrop-dismissible="false"><form class="workspace-settings__password-form" @submit.prevent="savePassword"><label>Nova senha<input v-model="newPassword" type="password" autocomplete="new-password" /></label><label>Confirme a nova senha<input v-model="confirmationPassword" type="password" autocomplete="new-password" /></label><p class="workspace-settings__password-help">Mínimo de 6 caracteres e 2 entre letras maiúsculas, minúsculas, números e símbolos.</p><p v-if="passwordError" class="workspace-settings__error" role="alert">{{ passwordError }}</p><div class="dialog-actions"><UiButton variant="secondary" :disabled="processing" @click="passwordDialogOpen = false">Cancelar</UiButton><UiButton type="submit" :loading="processing">Salvar senha</UiButton></div></form></UiDialog>
        <UiDialog v-model="removePasswordDialogOpen" contained title="Remover senha" :backdrop-dismissible="false"><p class="dialog-description">Sem senha, o acesso à sua conta deverá ser feito por outro método de autenticação válido, como o código ou link enviado por e-mail.</p><div class="dialog-actions"><UiButton variant="secondary" :disabled="processing" @click="removePasswordDialogOpen = false">Cancelar</UiButton><UiButton variant="destructive" :loading="processing" @click="removePassword">Remover senha</UiButton></div></UiDialog>
    </div>
</template>
