<script setup lang="ts">
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

type Mode = 'register' | 'password' | 'passwordless';
type FeedbackKind = 'success' | 'error';
const mode = ref<Mode>('register');
const email = ref(''); const password = ref(''); const rememberMe = ref(false); const processing = ref(false);
const message = ref(''); const feedbackKind = ref<FeedbackKind>('success'); const emailError = ref('');
const challengeId = ref<string | null>(null); const code = ref(''); const displayName = ref(''); const linkToken = ref<string | null>(null);
const security = ref(false); const session = ref<{ user: { displayName: string; passwordDefined: boolean }; persistentAuthentication: boolean } | null>(null);
const newPassword = ref(''); const showPasswordForm = ref(false); const remainingSeconds = ref(0); const offline = ref(!navigator.onLine); const showInvalidateConfirmation = ref(false);
const emailInput = ref<HTMLInputElement | null>(null);
const codeInput = ref<HTMLInputElement | null>(null);
const displayNameInput = ref<HTMLInputElement | null>(null);
let expiryTimer: number | undefined;
const remainingLabel = computed(() => `${Math.floor(remainingSeconds.value / 60)}:${String(remainingSeconds.value % 60).padStart(2, '0')}`);
const actionLabel = computed(() => mode.value === 'register' ? 'Criar conta' : mode.value === 'password' ? 'Entrar' : 'Enviar código de acesso');
function setFeedback(nextMessage: string, kind: FeedbackKind) { message.value = nextMessage; feedbackKind.value = kind; }
function startExpiry() { remainingSeconds.value = 600; window.clearInterval(expiryTimer); expiryTimer = window.setInterval(() => { if (remainingSeconds.value > 0) remainingSeconds.value -= 1; if (remainingSeconds.value === 0) { window.clearInterval(expiryTimer); setFeedback('Este código expirou. Solicite uma nova mensagem para continuar.', 'error'); } }, 1000); }
function selectMode(next: Mode) { mode.value = next; message.value = ''; emailError.value = ''; }
async function moveMode(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const modes: Mode[] = ['register', 'password', 'passwordless'];
    const currentIndex = modes.indexOf(mode.value);
    const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? modes.length - 1 : (currentIndex + (event.key === 'ArrowRight' ? 1 : -1) + modes.length) % modes.length;
    selectMode(modes[nextIndex]);
    await nextTick();
    document.getElementById(`access-mode-${modes[nextIndex]}`)?.focus();
}
async function focusActiveField() {
    await nextTick();
    if (!challengeId.value) { emailInput.value?.focus(); return; }
    if (mode.value === 'register') { displayNameInput.value?.focus(); return; }
    if (!linkToken.value) codeInput.value?.focus();
}
function apiErrorMessage(error: unknown, fallback: string) { return axios.isAxiosError(error) ? error.response?.data?.message ?? fallback : fallback; }
function confirmationErrorMessage(error: unknown, fallback: string) {
    if (axios.isAxiosError(error) && error.response?.status === 400) return 'Este código ou link não é mais válido. Solicite uma nova mensagem para continuar.';
    if (axios.isAxiosError(error) && error.response?.status === 429) return 'Não é possível concluir esta ação agora. Aguarde alguns minutos e tente novamente.';
    return apiErrorMessage(error, fallback);
}
async function returnToAccess() { window.clearInterval(expiryTimer); challengeId.value = null; linkToken.value = null; code.value = ''; displayName.value = ''; message.value = ''; await focusActiveField(); }
async function submit() {
    emailError.value = ''; message.value = '';
    if (!/^\S+@\S+\.\S+$/.test(email.value)) { emailError.value = 'Informe um e-mail válido.'; return; }
    if (offline.value) { setFeedback('Você está sem conexão. Verifique a rede e tente novamente.', 'error'); return; }
    processing.value = true;
    try {
        const path = mode.value === 'register' ? '/api/v1/auth/registrations' : mode.value === 'password' ? '/api/v1/auth/password-sessions' : '/api/v1/auth/passwordless-sessions';
        const payload = mode.value === 'password' ? { email: email.value, password: password.value, rememberMe: rememberMe.value } : { email: email.value, rememberMe: rememberMe.value };
        const response = await axios.post(path, payload); setFeedback(response.data.message ?? 'Acesso concluído com sucesso.', 'success');
        if (response.data.user) { security.value = true; await loadSession(); }
        challengeId.value = response.data.challengeId ?? null; if (challengeId.value) { startExpiry(); await focusActiveField(); }
    } catch (error) { setFeedback(apiErrorMessage(error, 'Não foi possível concluir esta ação.'), 'error'); }
    finally { processing.value = false; }
}
async function confirmCode() {
    if (!challengeId.value || offline.value) return; processing.value = true;
    try {
        const path = mode.value === 'register' ? '/api/v1/auth/email-verifications' : '/api/v1/auth/passwordless-sessions/confirmations';
        const payload = mode.value === 'register' ? { challengeId: challengeId.value, code: code.value, displayName: displayName.value } : { challengeId: challengeId.value, code: code.value };
        await axios.post(path, payload); setFeedback('Acesso confirmado com sucesso.', 'success'); challengeId.value = null; security.value = true; await loadSession();
    } catch (error) { setFeedback(confirmationErrorMessage(error, 'Não foi possível confirmar este código.'), 'error'); await focusActiveField(); }
    finally { processing.value = false; }
}
async function confirmLink() {
    if (!challengeId.value || !linkToken.value || offline.value) return; processing.value = true;
    try {
        const path = mode.value === 'register' ? '/api/v1/auth/email-verifications/link-confirmations' : '/api/v1/auth/passwordless-sessions/link-confirmations';
        const payload = mode.value === 'register' ? { challengeId: challengeId.value, token: linkToken.value, displayName: displayName.value } : { challengeId: challengeId.value, token: linkToken.value };
        await axios.post(path, payload); setFeedback('Acesso confirmado com sucesso.', 'success'); challengeId.value = null; linkToken.value = null; security.value = true; await loadSession();
    } catch (error) { setFeedback(confirmationErrorMessage(error, 'Não foi possível confirmar este link.'), 'error'); await focusActiveField(); }
    finally { processing.value = false; }
}
async function loadSession() { try { session.value = (await axios.get('/api/v1/auth/session')).data; security.value = true; } catch { security.value = false; } }
async function setPassword() { if (!newPassword.value || offline.value) return; try { await axios.put('/api/v1/auth/password', { password: newPassword.value }); newPassword.value = ''; showPasswordForm.value = false; await loadSession(); setFeedback('Senha definida.', 'success'); } catch (error) { setFeedback(apiErrorMessage(error, 'A senha não atende aos critérios: mínimo de 6 caracteres e duas categorias.'), 'error'); } }
async function logout() { if (offline.value) return; await axios.delete('/api/v1/auth/session'); security.value = false; session.value = null; message.value = ''; }
async function invalidateOthers() { if (offline.value) return; processing.value = true; try { await axios.delete('/api/v1/auth/other-sessions'); showInvalidateConfirmation.value = false; setFeedback('Outras sessões foram invalidadas.', 'success'); await loadSession(); } catch (error) { setFeedback(apiErrorMessage(error, 'Não foi possível invalidar as outras sessões.'), 'error'); } finally { processing.value = false; } }
function markOnline() { offline.value = false; } function markOffline() { offline.value = true; }
onMounted(async () => { window.addEventListener('online', markOnline); window.addEventListener('offline', markOffline); await loadSession(); if (security.value) return; const params = new URLSearchParams(window.location.search); const id = params.get('challengeId'); const token = params.get('token'); if (id && token) { challengeId.value = id; linkToken.value = token; mode.value = window.location.pathname.includes('passwordless') ? 'passwordless' : 'register'; startExpiry(); window.history.replaceState({}, '', window.location.pathname); } await focusActiveField(); if (mode.value === 'passwordless' && challengeId.value !== null && linkToken.value !== null) await confirmLink(); });
onUnmounted(() => { window.clearInterval(expiryTimer); window.removeEventListener('online', markOnline); window.removeEventListener('offline', markOffline); });
</script>

<template>
    <main class="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:flex sm:items-center sm:justify-center">
        <section v-if="!security" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm sm:p-8" aria-labelledby="access-title">
            <p class="text-sm font-semibold text-indigo-700">Rinos One</p><h1 id="access-title" tabindex="-1" class="mt-2 text-3xl font-bold tracking-tight">Acesse sua conta</h1><p class="mt-2 text-slate-600">Crie uma conta ou escolha como deseja entrar.</p>
            <p v-if="offline" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="alert">Você está sem conexão. As ações estarão disponíveis quando a rede voltar.</p>
            <div class="mt-6 grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1" role="tablist" aria-label="Modo de acesso" @keydown="moveMode"><button v-for="item in [{ id: 'register', label: 'Criar conta' }, { id: 'password', label: 'Entrar' }, { id: 'passwordless', label: 'Sem senha' }]" :id="`access-mode-${item.id}`" :key="item.id" type="button" role="tab" :tabindex="mode === item.id ? 0 : -1" :aria-selected="mode === item.id" class="min-h-11 rounded-md px-2 text-sm font-medium" :class="mode === item.id ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600'" @click="selectMode(item.id as Mode)">{{ item.label }}</button></div>
            <form v-if="!challengeId" class="mt-6 space-y-5" @submit.prevent="submit" novalidate>
                <div><label for="email" class="block text-sm font-medium">E-mail</label><input id="email" ref="emailInput" v-model.trim="email" type="email" autocomplete="email" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3 outline-none focus:border-indigo-600 focus:ring-2 focus:ring-indigo-200" :aria-invalid="Boolean(emailError)" :aria-describedby="emailError ? 'email-error' : undefined" required /><p v-if="emailError" id="email-error" class="mt-1 text-sm text-red-700" role="alert">{{ emailError }}</p></div>
                <div v-if="mode === 'password'"><label for="password" class="block text-sm font-medium">Senha</label><input id="password" v-model="password" type="password" autocomplete="current-password" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3" required /></div><label class="flex min-h-11 items-center gap-3 text-sm"><input v-model="rememberMe" type="checkbox" class="size-5 rounded border-slate-400 text-indigo-600" /> Manter-me conectado</label>
                <p v-if="message" class="rounded-lg p-3 text-sm" :class="feedbackKind === 'error' ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-900'" :role="feedbackKind === 'error' ? 'alert' : 'status'" aria-live="polite">{{ message }}</p><button type="submit" class="min-h-11 w-full rounded-lg bg-indigo-700 px-4 font-semibold text-white hover:bg-indigo-800 disabled:opacity-60" :disabled="processing || offline">{{ processing ? 'Processando…' : actionLabel }}</button>
            </form>
            <form v-else class="mt-6 space-y-5" @submit.prevent="linkToken ? confirmLink() : confirmCode()"><h2 class="text-xl font-semibold">Confirme seu e-mail</h2><p class="text-sm text-slate-600">Digite o código de 6 dígitos enviado na mensagem. <span aria-live="off">Válido por {{ remainingLabel }}.</span></p><p v-if="rememberMe" class="rounded-lg bg-indigo-50 p-3 text-sm text-indigo-950">Você escolheu permanecer conectado neste navegador.</p><label v-if="!linkToken" class="block text-sm font-medium" for="code">Código de confirmação</label><input v-if="!linkToken" id="code" ref="codeInput" v-model="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="min-h-11 w-full rounded-lg border border-slate-300 px-3" required /><div v-if="mode === 'register'"><label class="block text-sm font-medium" for="display-name">Nome de exibição</label><input id="display-name" ref="displayNameInput" v-model.trim="displayName" autocomplete="name" class="mt-1 min-h-11 w-full rounded-lg border border-slate-300 px-3" required /></div><p v-if="message" class="rounded-lg p-3 text-sm" :class="feedbackKind === 'error' ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-900'" :role="feedbackKind === 'error' ? 'alert' : 'status'" aria-live="polite">{{ message }}</p><button type="submit" class="min-h-11 w-full rounded-lg bg-indigo-700 px-4 font-semibold text-white disabled:opacity-60" :disabled="processing || offline || remainingSeconds === 0">{{ processing ? 'Confirmando…' : linkToken ? 'Confirmar link' : 'Concluir acesso' }}</button><button v-if="!linkToken" type="button" class="min-h-11 w-full rounded-lg border border-slate-300 px-4 disabled:opacity-60" :disabled="processing || offline" @click="submit">Reenviar mensagem</button><button v-else type="button" class="min-h-11 w-full rounded-lg border border-slate-300 px-4" @click="returnToAccess">Voltar ao acesso</button></form>
        </section>
        <section v-else class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm sm:p-8" aria-labelledby="security-title"><p class="text-sm font-semibold text-indigo-700">Rinos One</p><h1 id="security-title" tabindex="-1" class="mt-2 text-3xl font-bold">Segurança de acesso</h1><p class="mt-2 text-slate-600">Olá, {{ session?.user.displayName }}.</p><p v-if="offline" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" role="alert">Você está sem conexão. As ações estarão disponíveis quando a rede voltar.</p><p v-if="message" class="mt-4 rounded-lg p-3 text-sm" :class="feedbackKind === 'error' ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-900'" :role="feedbackKind === 'error' ? 'alert' : 'status'" aria-live="polite">{{ message }}</p><div class="mt-6 space-y-4"><section class="rounded-lg border border-slate-200 p-4"><h2 class="font-semibold">Senha</h2><p class="mt-1 text-sm text-slate-600">{{ session?.user.passwordDefined ? 'Senha definida.' : 'Você pode definir uma senha opcional.' }}</p><button v-if="!session?.user.passwordDefined && !showPasswordForm" type="button" class="mt-3 min-h-11 rounded-lg bg-indigo-700 px-4 text-white disabled:opacity-60" :disabled="offline" @click="showPasswordForm = true">Definir senha</button><form v-if="showPasswordForm" class="mt-3 space-y-3" @submit.prevent="setPassword"><label class="block text-sm font-medium" for="new-password">Nova senha</label><input id="new-password" v-model="newPassword" type="password" autocomplete="new-password" class="min-h-11 w-full rounded-lg border border-slate-300 px-3" required /><p class="text-xs text-slate-600">Mínimo de 6 caracteres e duas categorias: minúscula, maiúscula, número ou símbolo.</p><button class="min-h-11 rounded-lg bg-indigo-700 px-4 text-white disabled:opacity-60" :disabled="offline">Salvar senha</button></form></section><section class="rounded-lg border border-slate-200 p-4"><h2 class="font-semibold">Sessões</h2><p class="mt-1 text-sm text-slate-600">{{ session?.persistentAuthentication ? 'Você permanecerá conectado neste navegador.' : 'Sessão atual ativa.' }}</p><button type="button" class="mt-3 min-h-11 rounded-lg border border-slate-300 px-4 disabled:opacity-60" :disabled="offline" @click="showInvalidateConfirmation = true">Invalidar outras sessões</button><button type="button" class="mt-3 min-h-11 rounded-lg px-4 text-red-700 disabled:opacity-60" :disabled="offline" @click="logout">Encerrar esta sessão</button></section></div></section>
        <div v-if="showInvalidateConfirmation" class="fixed inset-0 grid place-items-center bg-slate-950/40 p-4" role="presentation"><section class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" role="alertdialog" aria-modal="true" aria-labelledby="invalidate-title" aria-describedby="invalidate-description"><h2 id="invalidate-title" class="text-xl font-bold">Invalidar outras sessões?</h2><p id="invalidate-description" class="mt-2 text-slate-600">Os outros navegadores e dispositivos precisarão entrar novamente.</p><div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" class="min-h-11 rounded-lg border border-slate-300 px-4" :disabled="processing" @click="showInvalidateConfirmation = false">Cancelar</button><button type="button" class="min-h-11 rounded-lg bg-red-700 px-4 font-semibold text-white disabled:opacity-60" :disabled="processing || offline" @click="invalidateOthers">Invalidar sessões</button></div></section></div>
    </main>
</template>
