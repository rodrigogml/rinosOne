<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { rinoButtonCommands, type RinoButtonCommand, type RinoButtonVariant } from './rinoButtonCommands';
import { ensureRinoToggleGroupSelection, type RinoToggleGroup, type RinoToggleGroupMember } from './rinoToggleGroup';

defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{
    command?: RinoButtonCommand | null;
    variant?: RinoButtonVariant;
    label?: string | null;
    labelParams?: Record<string, string | number>;
    icon?: string | null;
    accessibleLabel?: string | null;
    toggle?: boolean;
    toggleGroup?: RinoToggleGroup | null;
    selected?: boolean;
    disabled?: boolean;
    loading?: boolean;
    type?: 'button' | 'submit' | 'reset';
}>(), { toggle: undefined, selected: undefined });
const emit = defineEmits<{ click: [event: MouseEvent]; 'update:selected': [selected: boolean] }>();
const { t } = useI18n();
const button = ref<HTMLButtonElement | null>(null);
const storedSelected = ref(false);
const definition = computed(() => {
    if (props.command == null) return undefined;
    if (!Object.hasOwn(rinoButtonCommands, props.command)) throw new Error(`UIRinoButton: comando desconhecido "${props.command}".`);
    const value = rinoButtonCommands[props.command];
    return value;
});
const config = computed(() => {
    const preset = definition.value;
    const label = props.label !== undefined ? props.label : preset?.label ?? null;
    const icon = props.icon !== undefined ? props.icon : preset?.icon ?? null;
    const accessibleLabel = props.accessibleLabel !== undefined ? props.accessibleLabel : preset?.accessibleLabel ?? label;
    if (!label && !icon) throw new Error('UIRinoButton: label ou icon é obrigatório.');
    if (!label && !accessibleLabel) throw new Error('UIRinoButton: accessibleLabel é obrigatório para botão somente com ícone.');
    return { label, icon, accessibleLabel, variant: props.variant ?? preset?.variant ?? 'secondary', toggle: !!props.toggleGroup || (props.toggle !== undefined ? props.toggle : preset?.toggle ?? false) };
});
const selected = computed(() => props.selected !== undefined ? props.selected : storedSelected.value);
const blocked = computed(() => props.disabled || props.loading);
const translatedLabel = computed(() => config.value.label ? t(config.value.label, props.labelParams ?? {}) : null);
const translatedAccessibleLabel = computed(() => config.value.accessibleLabel ? t(config.value.accessibleLabel, props.labelParams ?? {}) : undefined);

function setSelected(value: boolean): void {
    if (selected.value === value) return;
    storedSelected.value = value;
    emit('update:selected', value);
}
function deselectPeers(): void {
    props.toggleGroup?.members.forEach(peer => { if (peer !== member) peer.setSelected(false); });
}
const member: RinoToggleGroupMember = { isSelected: () => selected.value, isAvailable: () => !blocked.value, setSelected };
watch(() => props.toggleGroup, (group, previous) => {
    if (group && !(group.members instanceof Set)) throw new Error('UIRinoButton: toggleGroup deve ser uma referência criada por createRinoToggleGroup().');
    previous?.members.delete(member);
    ensureRinoToggleGroupSelection(previous);
    group?.members.add(member);
    if (config.value.toggle && selected.value) deselectPeers();
    ensureRinoToggleGroupSelection(group);
}, { immediate: true });
watch([selected, () => config.value.toggle, blocked], ([value, toggle]) => {
    if (value && toggle) deselectPeers();
    ensureRinoToggleGroupSelection(props.toggleGroup);
});
onBeforeUnmount(() => {
    props.toggleGroup?.members.delete(member);
    ensureRinoToggleGroupSelection(props.toggleGroup);
});
function click(event: MouseEvent): void {
    if (blocked.value) return;
    if (config.value.toggle) {
        const next = !selected.value;
        if (!next && props.toggleGroup?.required) {
            emit('click', event);
            return;
        }
        if (next) deselectPeers();
        setSelected(next);
    }
    emit('click', event);
}
defineExpose({ focus: () => button.value?.focus() });
</script>

<template>
    <button ref="button" v-bind="$attrs" :class="[translatedLabel ? 'ui-button' : 'ui-icon-button', `ui-button--${config.variant}`]" :type="type ?? 'button'" :disabled="blocked" :aria-busy="loading || undefined" :aria-label="props.accessibleLabel || !translatedLabel ? translatedAccessibleLabel : undefined" :title="translatedLabel ? undefined : translatedAccessibleLabel" :aria-pressed="config.toggle ? selected : undefined" @click="click">
        <svg v-if="config.icon === 'moreActions'" class="ui-rino-button__icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/></svg>
        <img v-else-if="config.icon" class="ui-rino-button__icon" :src="`/assets/icons/${config.icon}_48.png`" :srcset="`/assets/icons/${config.icon}_48.png 1x, /assets/icons/${config.icon}_512.png 2x`" alt="" aria-hidden="true">
        <span v-if="translatedLabel">{{ translatedLabel }}</span>
    </button>
</template>
