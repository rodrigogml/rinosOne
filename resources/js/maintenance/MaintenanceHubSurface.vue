<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import UiAlert from '../design-system/UiAlert.vue';
import UiButton from '../design-system/UiButton.vue';
import UiDialog from '../design-system/UiDialog.vue';

interface Execution { state: string; triggerType: string; startedAt: string; completedAt: string | null; summary: string | null; createdCount: number | null; updatedCount: number | null; }
interface Audit { performedByUserId: number; action: string; outcome: string; occurredAt: string; }
interface Routine { routineKey: string; title: string; description: string; state: string; scheduleDescription: string; capabilities: { canSynchronize: boolean }; lastExecution?: Execution | null; executionHistory?: Execution[]; administrativeAudits?: Audit[]; }

const routines = ref<Routine[]>([]); const selected = ref<Routine | null>(null); const loading = ref(true); const processing = ref(false); const confirmationOpen = ref(false); const error = ref(''); const feedback = ref('');
const date = (value: string | null | undefined) => value ? new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
const selectedHistory = computed(() => selected.value?.executionHistory ?? []);
async function loadDetail(key: string) { selected.value = (await axios.get(`/api/v1/platform/maintenance/routines/${encodeURIComponent(key)}`)).data.routine; }
async function reload() { loading.value = true; error.value = ''; try { routines.value = (await axios.get('/api/v1/platform/maintenance/routines')).data.routines; if (routines.value.length) await loadDetail(selected.value?.routineKey ?? routines.value[0]!.routineKey); else selected.value = null; } catch { error.value = 'Não foi possível atualizar as informações de manutenção.'; } finally { loading.value = false; } }
async function synchronize() { if (!selected.value) return; processing.value = true; feedback.value = ''; try { const response = await axios.post(`/api/v1/platform/maintenance/routines/${encodeURIComponent(selected.value.routineKey)}/actions/SYNCHRONIZE`); feedback.value = `Atualização concluída: ${response.data.execution.summary ?? 'resultado disponível no histórico.'}`; confirmationOpen.value = false; await reload(); } catch (reason) { const code = axios.isAxiosError(reason) ? reason.response?.data?.error?.code : ''; feedback.value = code === 'MAINTENANCE_ALREADY_RUNNING' ? 'A rotina já está em execução.' : 'A solicitação não pôde ser concluída.'; } finally { processing.value = false; } }
onMounted(reload);
</script>

<template>
  <main class="maintenance-hub" aria-labelledby="maintenance-title">
    <header><div><p class="maintenance-hub__eyebrow">Administração da Plataforma</p><h2 id="maintenance-title" tabindex="-1">Central de Manutenções</h2></div><UiButton variant="secondary" :loading="loading" @click="reload">Recarregar</UiButton></header>
    <UiAlert v-if="error" tone="error">{{ error }}</UiAlert><UiAlert v-if="feedback" tone="success">{{ feedback }}</UiAlert>
    <p v-if="loading" aria-live="polite">Carregando rotinas…</p><p v-else-if="!routines.length">Nenhuma rotina de manutenção está disponível para seu acesso.</p>
    <div v-else class="maintenance-hub__layout"><nav aria-label="Rotinas de manutenção"><button v-for="routine in routines" :key="routine.routineKey" type="button" :class="{ selected: selected?.routineKey === routine.routineKey }" @click="loadDetail(routine.routineKey)"><strong>{{ routine.title }}</strong><span>{{ routine.state }}</span></button></nav>
      <section v-if="selected" aria-live="polite"><header><div><h3>{{ selected.title }}</h3><p>{{ selected.description }}</p></div><UiButton v-if="selected.capabilities.canSynchronize" :loading="processing" @click="confirmationOpen = true">Atualizar agora</UiButton></header><dl><dt>Estado</dt><dd>{{ selected.state }}</dd><dt>Agenda</dt><dd>{{ selected.scheduleDescription }}</dd><dt>Última execução</dt><dd>{{ date(selected.lastExecution?.completedAt ?? selected.lastExecution?.startedAt) }}</dd></dl><h4>Histórico técnico</h4><div class="maintenance-hub__table"><div v-for="execution in selectedHistory" :key="`${execution.startedAt}-${execution.triggerType}`"><strong>{{ execution.state }}</strong><span>{{ execution.triggerType }} · {{ date(execution.startedAt) }}</span><p>{{ execution.summary }}</p></div></div><h4>Auditoria administrativa</h4><div class="maintenance-hub__table"><div v-for="audit in selected.administrativeAudits ?? []" :key="`${audit.performedByUserId}-${audit.occurredAt}`"><strong>{{ audit.action }}</strong><span>{{ audit.outcome }} · {{ date(audit.occurredAt) }}</span></div><p v-if="!(selected.administrativeAudits?.length)">Nenhuma ação administrativa registrada.</p></div></section>
    </div>
    <UiDialog v-model="confirmationOpen" title="Atualizar instituições financeiras" contained><p>Solicita uma atualização do catálogo oficial pelo Banco Central. A rotina pode recusar a solicitação caso já esteja em execução.</p><div class="dialog-actions"><UiButton variant="secondary" @click="confirmationOpen = false">Cancelar</UiButton><UiButton :loading="processing" @click="synchronize">Confirmar atualização</UiButton></div></UiDialog>
  </main>
</template>

<style scoped>
.maintenance-hub { display:grid; gap:1rem; max-width:1200px; margin:auto; padding:1rem; } .maintenance-hub header { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; } .maintenance-hub__eyebrow { margin:0; font-size:.82rem; color:var(--color-text-muted, #64748b); } h2,h3,h4 { margin:.15rem 0; } .maintenance-hub__layout { display:grid; grid-template-columns:minmax(15rem, .7fr) 2fr; gap:1rem; } nav { display:grid; align-content:start; gap:.5rem; } nav button { text-align:left; border:1px solid var(--color-border, #cbd5e1); border-radius:.6rem; background:var(--color-surface, #fff); padding:.75rem; } nav button.selected { border-color:var(--color-primary, #2563eb); box-shadow:0 0 0 2px color-mix(in srgb, var(--color-primary, #2563eb) 20%, transparent); } nav span,.maintenance-hub__table span { display:block; color:var(--color-text-muted, #64748b); font-size:.85rem; } dl { display:grid; grid-template-columns:max-content 1fr; gap:.45rem 1rem; } dt { font-weight:600; } dd { margin:0; } .maintenance-hub__table { display:grid; gap:.5rem; } .maintenance-hub__table > div { border-top:1px solid var(--color-border, #cbd5e1); padding:.65rem 0; } .maintenance-hub__table p { margin:.25rem 0 0; } @media (max-width: 700px) { .maintenance-hub__layout { grid-template-columns:1fr; } .maintenance-hub header { flex-wrap:wrap; } }
</style>
