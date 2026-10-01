<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import UiButton from './UiButton.vue';
import UiDialog from './UiDialog.vue';
import ProfileSettingsPanel, { type ProfilePresentation } from '../profile/ProfileSettingsPanel.vue';
import AvatarCropDialog from '../profile/AvatarCropDialog.vue';
import { useVisualPreferencesStore, type DensityPreference, type PalettePreference, type ThemePreference } from '../preferences/visualPreferences';
import type { WorkspaceSurface } from '../workspace/workspaceTypes';
import { useWorkspaceStore } from '../workspace/workspaceStore';

const props = defineProps<{ surface: WorkspaceSurface }>();
const emit = defineEmits<{ profileUpdated: [profile: ProfilePresentation] }>();
const { t } = useI18n();

type SettingsSection = 'profile' | 'theme' | 'sessions' | 'security';
const activeSection = ref<SettingsSection>('profile');
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
const sectionTitle = computed(() => ({ profile: t('access.profile.section'), theme: 'Tema e aparência', sessions: 'Sessões ativas', security: 'Segurança da conta' })[activeSection.value]);
const scales: readonly { value: DensityPreference; label: string }[] = [
    { value: 'compact', label: 'Compacta' }, { value: 'default', label: 'Padrão' }, { value: 'comfortable', label: 'Confortável' },
];
const preferenceIcon = (name: string): string => `/assets/icons/${name}_24.png`;
const textScaleIcons: Record<DensityPreference, string> = { compact: preferenceIcon('themeTextSmall'), default: preferenceIcon('themeTextNormal'), comfortable: preferenceIcon('themeTextBig') };
const spacingScaleIcons: Record<DensityPreference, string> = { compact: preferenceIcon('padding-s'), default: preferenceIcon('padding-m'), comfortable: preferenceIcon('padding-l') };
const componentScaleIcons: Record<DensityPreference, string> = { compact: preferenceIcon('object-size-s'), default: preferenceIcon('object-size-m'), comfortable: preferenceIcon('object-size-g') };
const passwordDefined = ref(false);
const passwordDialogOpen = ref(false);
const removePasswordDialogOpen = ref(false);
const revokeDialogOpen = ref(false);
const newPassword = ref('');
const confirmationPassword = ref('');
const passwordError = ref('');
const actionError = ref('');
const processing = ref(false);
const profile = ref<ProfilePresentation | null>(null);
const profileLoading = ref(false);
const profileSavingName = ref(false);
const profileRemovingAvatar = ref(false);
const removeAvatarDialogOpen = ref(false);
const avatarEditorOpen = ref(false);
const discardProfileChangesDialogOpen = ref(false);
const requestedSection = ref<SettingsSection | null>(null);
const profileNameDirty = ref(false);
const profileNameError = ref<string | null>(null);
const profileLoadError = ref<string | null>(null);
const workspace = useWorkspaceStore();

function updatePalette(palette: PalettePreference): void { visualPreferences.update({ palette }); }
function updateTheme(theme: ThemePreference): void { visualPreferences.update({ theme }); }
function updateScale(field: 'fontScale' | 'spacingScale' | 'componentScale', value: DensityPreference): void { visualPreferences.update({ [field]: value }); }
function updateProfile(value: ProfilePresentation): void { profile.value = value; emit('profileUpdated', value); }
async function loadProfile(): Promise<void> {
    profileLoading.value = true;
    profileLoadError.value = null;
    try { updateProfile((await axios.get('/api/v1/profile')).data); }
    catch { profileLoadError.value = navigator.onLine ? t('access.profile.loadFailed') : t('access.profile.offline'); }
    finally { profileLoading.value = false; }
}
async function saveProfileName(displayName: string): Promise<void> {
    profileSavingName.value = true;
    profileNameError.value = null;
    try { updateProfile((await axios.patch('/api/v1/profile', { displayName })).data); }
    catch (error) {
        profileNameError.value = axios.isAxiosError(error) && error.response?.status === 422
            ? t('access.profile.invalidName')
            : navigator.onLine ? t('access.profile.saveFailed') : t('access.profile.offline');
    }
    finally { profileSavingName.value = false; }
}
async function removeProfileAvatar(): Promise<void> {
    profileRemovingAvatar.value = true;
    try {
        await axios.delete('/api/v1/profile/avatar');
        if (profile.value) updateProfile({ ...profile.value, avatar: { available: false, url: null, updatedAt: null } });
        removeAvatarDialogOpen.value = false;
    } finally { profileRemovingAvatar.value = false; }
}
function passwordStrengthIsValid(value: string): boolean {
    const categories = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z\d]/].filter((pattern) => pattern.test(value)).length;
    return value.length >= 6 && categories >= 2;
}
function requestSection(section: SettingsSection): void {
    if (section === activeSection.value) return;
    if (activeSection.value === 'profile' && profileNameDirty.value) {
        requestedSection.value = section;
        discardProfileChangesDialogOpen.value = true;
        return;
    }
    activeSection.value = section;
}
function discardProfileChanges(): void {
    profileNameDirty.value = false;
    workspace.setSurfaceDirty(props.surface.id, false);
    activeSection.value = requestedSection.value ?? activeSection.value;
    requestedSection.value = null;
    discardProfileChangesDialogOpen.value = false;
}
watch(profileNameDirty, (dirty) => workspace.setSurfaceDirty(props.surface.id, dirty));
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
    await loadProfile();
});
</script>

<template>
    <div class="workspace-settings">
        <aside class="workspace-settings__navigation" aria-label="Categorias de configurações">
            <h3>Configurações</h3>
            <nav class="workspace-settings__nav-list" aria-label="Seções de configurações">
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'profile' }" class="workspace-settings__nav-item" :aria-current="activeSection === 'profile' ? 'page' : undefined" @click="requestSection('profile')">{{ t('access.profile.section') }}</button>
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'theme' }" class="workspace-settings__nav-item" :aria-current="activeSection === 'theme' ? 'page' : undefined" @click="requestSection('theme')">Tema e aparência</button>
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'sessions' }" class="workspace-settings__nav-item" :aria-current="activeSection === 'sessions' ? 'page' : undefined" @click="requestSection('sessions')">Sessões</button>
                <button type="button" :class="{ 'workspace-settings__nav-item--active': activeSection === 'security' }" class="workspace-settings__nav-item" :aria-current="activeSection === 'security' ? 'page' : undefined" @click="requestSection('security')">Segurança</button>
            </nav>
        </aside>

        <section class="workspace-settings__content" :aria-label="sectionTitle">
            <header v-if="activeSection !== 'profile'" class="workspace-settings__content-header"><h3>{{ sectionTitle }}</h3></header>

            <ProfileSettingsPanel v-if="activeSection === 'profile'" :profile="profile" :loading="profileLoading" :saving-name="profileSavingName" :removing-avatar="profileRemovingAvatar" :name-error="profileNameError" :load-error="profileLoadError" @save-name="saveProfileName" @request-edit-avatar="avatarEditorOpen = true" @request-remove-avatar="removeAvatarDialogOpen = true" @dirty-changed="profileNameDirty = $event" @name-edited="profileNameError = null" @retry-load="loadProfile" @retry-name="saveProfileName" />

            <template v-else-if="activeSection === 'theme'">
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Família cromática</h4><p>Escolha a cor de destaque da sua interface.</p></div></div><div class="workspace-settings__theme-grid"><button v-for="(theme, index) in themes" :key="theme.id" type="button" class="workspace-settings__theme-card" :class="{ 'workspace-settings__theme-card--selected': preferences.palette === theme.id }" @click="updatePalette(theme.id)"><span class="workspace-settings__theme-swatch" :class="`workspace-settings__theme-swatch--${index}`" /><span>{{ theme.label }}</span><small v-if="preferences.palette === theme.id">Selecionado</small></button></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Luminosidade</h4><p>Defina a base clara ou escura; a cor escolhida permanece apenas como destaque.</p></div><div class="workspace-settings__mode-choice" role="group" aria-label="Preferência de luminosidade"><button type="button" :class="{ 'workspace-settings__mode--selected': effectiveTheme === 'light' }" class="workspace-settings__mode workspace-settings__mode--icon" aria-label="Claro" @click="updateTheme('light')"><img :src="preferenceIcon('lamp-on')" alt="" aria-hidden="true"></button><button type="button" :class="{ 'workspace-settings__mode--selected': effectiveTheme === 'dark' }" class="workspace-settings__mode workspace-settings__mode--icon" aria-label="Escuro" @click="updateTheme('dark')"><img :src="preferenceIcon('lamp-off')" alt="" aria-hidden="true"></button></div></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Tamanho do texto</h4><p>Ajuste a leitura sem alterar o conteúdo.</p></div><div class="workspace-settings__choice-row"><button v-for="scale in scales" :key="scale.value" type="button" class="workspace-settings__choice workspace-settings__choice--icon" :class="{ 'workspace-settings__choice--selected': preferences.fontScale === scale.value }" :aria-label="scale.label" @click="updateScale('fontScale', scale.value)"><img :src="textScaleIcons[scale.value]" alt="" aria-hidden="true"></button></div></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Espaçamento</h4><p>Escolha uma composição mais compacta ou confortável.</p></div><div class="workspace-settings__choice-row"><button v-for="scale in scales" :key="scale.value" type="button" class="workspace-settings__choice workspace-settings__choice--icon" :class="{ 'workspace-settings__choice--selected': preferences.spacingScale === scale.value }" :aria-label="scale.label" @click="updateScale('spacingScale', scale.value)"><img :src="spacingScaleIcons[scale.value]" alt="" aria-hidden="true"></button></div></div></section>
                <section class="workspace-settings__panel"><div class="workspace-settings__panel-heading"><div><h4>Tamanho dos elementos</h4><p>Controle ícones, campos e demais componentes visuais.</p></div><div class="workspace-settings__choice-row"><button v-for="scale in scales" :key="scale.value" type="button" class="workspace-settings__choice workspace-settings__choice--icon" :class="{ 'workspace-settings__choice--selected': preferences.componentScale === scale.value }" :aria-label="scale.label" @click="updateScale('componentScale', scale.value)"><img :src="componentScaleIcons[scale.value]" alt="" aria-hidden="true"></button></div></div></section>
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
        <UiDialog v-model="removeAvatarDialogOpen" contained destructive :title="t('access.profile.removeTitle')" :backdrop-dismissible="false"><p class="dialog-description">{{ t('access.profile.removeDescription') }}</p><div class="dialog-actions"><UiButton variant="secondary" :disabled="profileRemovingAvatar" @click="removeAvatarDialogOpen = false">{{ t('access.actions.cancel') }}</UiButton><UiButton variant="destructive" :loading="profileRemovingAvatar" @click="removeProfileAvatar">{{ t('access.profile.removeImage') }}</UiButton></div></UiDialog>
        <AvatarCropDialog v-model="avatarEditorOpen" @completed="updateProfile" />
        <UiDialog v-model="discardProfileChangesDialogOpen" contained :title="t('access.profile.discardTitle')" :backdrop-dismissible="false"><p class="dialog-description">{{ t('access.profile.discardDescription') }}</p><div class="dialog-actions"><UiButton variant="secondary" @click="discardProfileChangesDialogOpen = false; requestedSection = null">{{ t('access.profile.remain') }}</UiButton><UiButton variant="destructive" @click="discardProfileChanges">{{ t('access.profile.discard') }}</UiButton></div></UiDialog>
    </div>
</template>
