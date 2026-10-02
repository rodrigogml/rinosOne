<script setup lang="ts">
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{ occasionId: number | null }>();
const emit = defineEmits<{ cancel: []; saved: [] }>();
const loading = ref(false); const saving = ref(false); const error = ref('');
const title = computed(() => props.occasionId === null ? 'Inserindo Feriado' : 'Editando Feriado');
const form = ref({ name: '', occasionCategory: 'HOLIDAY', territorialScope: 'COUNTRY', locality: { countryId: '' }, validity: { from: '', to: '' }, recurrence: { type: 'ANNUAL_FIXED_DATE' } });
async function load(): Promise<void> { if (props.occasionId === null) return; loading.value = true; try { Object.assign(form.value, (await axios.get(`/api/v1/platform/calendar-occasions/${props.occasionId}`)).data); } catch { error.value = 'Não foi possível carregar esta definição.'; } finally { loading.value = false; } }
async function save(): Promise<void> { saving.value = true; error.value = ''; try { props.occasionId === null ? await axios.post('/api/v1/platform/calendar-occasions', form.value) : await axios.patch(`/api/v1/platform/calendar-occasions/${props.occasionId}`, form.value); emit('saved'); } catch { error.value = 'Não foi possível salvar a definição.'; } finally { saving.value = false; } }
onMounted(() => void load());
</script>

<template>
  <section class="calendar-occasion-form" aria-label="Editor de feriado">
    <header class="calendar-occasion-form__header"><h3>{{ title }}</h3></header>
    <form class="calendar-occasion-form__editor" @submit.prevent="save">
      <div class="calendar-occasion-form__feedback"><p v-if="loading" role="status">Carregando definição…</p><p v-if="error" role="alert">{{ error }}</p></div>
      <div class="calendar-occasion-form__content-frame">
        <fieldset class="calendar-occasion-form__fields" :disabled="loading || saving">
          <label class="calendar-occasion-form__name">Nome<input v-model="form.name" required></label>
          <div class="calendar-occasion-form__field-grid"><label>Categoria<select v-model="form.occasionCategory"><option value="HOLIDAY">Feriado</option><option value="OPTIONAL_DAY_OFF">Ponto facultativo</option><option value="COMMEMORATIVE_DATE">Data comemorativa</option></select></label><label>Esfera<select v-model="form.territorialScope"><option value="COUNTRY">País</option><option value="BRAZIL_STATE">Estado brasileiro</option><option value="BRAZIL_MUNICIPALITY">Município brasileiro</option></select></label><label>Tipo de recorrência<select v-model="form.recurrence.type"><option value="ANNUAL_FIXED_DATE">Anual em data fixa</option><option value="ONE_TIME_DATE">Data única</option><option value="ANNUAL_NTH_WEEKDAY">Dia ordinal da semana</option><option value="EASTER_OFFSET">Deslocamento da Páscoa</option></select></label><label>Início<input v-model="form.validity.from" type="date"></label><label>Fim<input v-model="form.validity.to" type="date"></label></div>
        </fieldset>
        <footer class="calendar-occasion-form__command-bar"><button class="ui-button ui-button--destructive" type="button" @click="emit('cancel')"><img :src="'/assets/icons/btCancel_24.png'" alt="">Cancelar</button><button class="ui-button ui-button--primary" type="submit" :disabled="saving"><img :src="'/assets/icons/floppyDisk_24.png'" alt="">{{ saving ? 'Salvando…' : 'Salvar' }}</button></footer>
      </div>
    </form>
  </section>
</template>

<style scoped>
.calendar-occasion-form{display:flex;flex-direction:column;min-block-size:100%;block-size:100%;min-height:0;overflow:hidden}.calendar-occasion-form__header{display:flex;flex:0 0 auto;align-items:center;min-block-size:var(--control-height-md);padding:var(--space-3) var(--space-4);border-bottom:var(--component-border-width) solid var(--color-border-subtle)}.calendar-occasion-form__header h3{margin:0;color:var(--color-text-primary);font-size:var(--font-size-lg);font-weight:var(--font-weight-semibold)}.calendar-occasion-form__editor{display:flex;flex:1 1 0;flex-direction:column;min-block-size:0;overflow:hidden}.calendar-occasion-form__feedback{display:grid;gap:var(--space-1);margin:var(--space-3) var(--space-4) 0}.calendar-occasion-form__feedback:empty{display:none}.calendar-occasion-form__feedback p{margin:0}.calendar-occasion-form__content-frame{display:flex;flex:1 1 0;flex-direction:column;gap:var(--space-3);min-width:0;min-height:0;box-sizing:border-box;padding:var(--component-workspace-surface-padding);overflow:hidden}.calendar-occasion-form__fields{display:grid;flex:1 1 0;align-content:start;gap:var(--space-3);min-height:0;overflow:auto;margin:0;padding:var(--space-4);border:var(--component-border-width) solid var(--color-border-subtle);border-radius:var(--radius-md);background:var(--color-surface-raised)}.calendar-occasion-form__field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-3)}label{display:grid;gap:var(--space-1)}input,select{width:100%;min-height:var(--control-height-sm);padding:var(--space-2) var(--space-3);border:var(--component-border-width) solid var(--color-border-subtle);border-radius:var(--radius-sm);background:var(--color-surface-raised);color:var(--color-text-primary);font:inherit}.calendar-occasion-form__command-bar{display:flex;flex:0 0 auto;justify-content:flex-end;gap:var(--space-2)}.calendar-occasion-form__command-bar .ui-button{display:inline-flex;align-items:center;gap:var(--space-2)}.calendar-occasion-form__command-bar img{width:var(--icon-size-md);height:var(--icon-size-md);object-fit:contain}@media(max-width:700px){.calendar-occasion-form__field-grid{grid-template-columns:1fr}input,select{font-size:max(1rem,16px)}.calendar-occasion-form__command-bar{position:sticky;bottom:0}}
</style>

<style scoped>
.calendar-occasion-form :is(input, select) { border-color: var(--component-field-border); background: var(--component-field-background); }
</style>
