<script setup lang="ts">
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import UiAlert from '../design-system/UiAlert.vue';
import UIRinoButton from '../design-system/UIRinoButton.vue';
import UiDialog from '../design-system/UiDialog.vue';
import type { ProfilePresentation } from './ProfileSettingsPanel.vue';

const props = defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; completed: [profile: ProfilePresentation] }>();
const { t } = useI18n();
const input = ref<HTMLInputElement | null>(null);
const file = ref<File | null>(null);
const sourceUrl = ref<string | null>(null);
const width = ref(0); const height = ref(0); const zoom = ref(1); const panX = ref(0); const panY = ref(0);
const error = ref(''); const processing = ref(false); const dragging = ref(false); let pointerX = 0; let pointerY = 0;
const allowed = ['image/jpeg', 'image/png', 'image/webp'];
const ready = computed(() => file.value !== null && sourceUrl.value !== null && width.value > 0 && height.value > 0);
const sourceSide = computed(() => Math.min(width.value, height.value));
const cropSize = computed(() => 1 / zoom.value);
const cropX = computed(() => ((width.value - sourceSide.value * cropSize.value) / width.value) * ((panX.value + 1) / 2));
const cropY = computed(() => ((height.value - sourceSide.value * cropSize.value) / height.value) * ((panY.value + 1) / 2));
const previewStyle = computed(() => {
    if (!ready.value) return {};
    const scale = zoom.value * 100;
    return { backgroundImage: `url("${sourceUrl.value}")`, backgroundSize: `${(width.value / sourceSide.value) * scale}% ${(height.value / sourceSide.value) * scale}%`, backgroundPosition: `${(panX.value + 1) * 50}% ${(panY.value + 1) * 50}%` };
});

function clearSource(): void { if (sourceUrl.value) URL.revokeObjectURL(sourceUrl.value); file.value = null; sourceUrl.value = null; width.value = 0; height.value = 0; zoom.value = 1; panX.value = 0; panY.value = 0; }
function close(): void { if (processing.value) return; clearSource(); error.value = ''; emit('update:modelValue', false); }
function clamp(value: number): number { return Math.max(-1, Math.min(1, value)); }
function center(): void { panX.value = 0; panY.value = 0; }
function move(horizontal: number, vertical: number): void { panX.value = clamp(panX.value + horizontal); panY.value = clamp(panY.value + vertical); }
async function select(candidate: File | null | undefined): Promise<void> {
    error.value = ''; clearSource();
    if (!candidate) return;
    if (!allowed.includes(candidate.type)) { error.value = t('access.profile.avatarFormatError'); return; }
    if (candidate.size > 10 * 1024 * 1024) { error.value = t('access.profile.avatarSizeError'); return; }
    const url = URL.createObjectURL(candidate); const image = new Image(); image.src = url;
    await new Promise<void>((resolve) => { image.onload = () => resolve(); image.onerror = () => resolve(); });
    if (!image.naturalWidth || !image.naturalHeight || image.naturalWidth < 400 || image.naturalHeight < 400) { URL.revokeObjectURL(url); error.value = t('access.profile.avatarDimensionError'); return; }
    file.value = candidate; sourceUrl.value = url; width.value = image.naturalWidth; height.value = image.naturalHeight;
}
function onFileChange(event: Event): void { void select((event.target as HTMLInputElement).files?.[0]); }
function drop(event: DragEvent): void { event.preventDefault(); void select(event.dataTransfer?.files?.[0]); }
function pointerDown(event: PointerEvent): void { if (!ready.value || processing.value) return; dragging.value = true; pointerX = event.clientX; pointerY = event.clientY; (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId); }
function pointerMove(event: PointerEvent): void { if (!dragging.value) return; move((pointerX - event.clientX) / 100, (pointerY - event.clientY) / 100); pointerX = event.clientX; pointerY = event.clientY; }
function pointerUp(): void { dragging.value = false; }
function keys(event: KeyboardEvent): void { const delta = event.shiftKey ? .2 : .06; const mapping: Record<string, [number, number]> = { ArrowLeft: [-delta, 0], ArrowRight: [delta, 0], ArrowUp: [0, -delta], ArrowDown: [0, delta] }; if (mapping[event.key]) { event.preventDefault(); move(...mapping[event.key]); } }
async function save(): Promise<void> {
    if (!file.value || processing.value) return; processing.value = true; error.value = '';
    const data = new FormData(); data.append('image', file.value); data.append('cropX', cropX.value.toFixed(6)); data.append('cropY', cropY.value.toFixed(6)); data.append('cropSize', cropSize.value.toFixed(6));
    try { emit('completed', (await axios.post('/api/v1/profile/avatar', data, { headers: { 'Content-Type': 'multipart/form-data' } })).data); clearSource(); emit('update:modelValue', false); }
    catch { error.value = navigator.onLine ? t('access.profile.avatarSaveFailed') : t('access.profile.offline'); }
    finally { processing.value = false; }
}
watch(() => props.modelValue, (open) => { if (!open) clearSource(); });
onBeforeUnmount(clearSource);
</script>

<template>
    <UiDialog :model-value="modelValue" contained :title="t('access.profile.identity')" :backdrop-dismissible="!processing" :escapable="!processing" @update:model-value="close">
        <div class="avatar-crop-dialog">
            <p class="avatar-crop-dialog__description">{{ t('access.profile.avatarRequirements') }}</p>
            <UiAlert v-if="error" tone="error">{{ error }}</UiAlert>
            <input ref="input" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" @change="onFileChange">
            <section v-if="!ready" class="avatar-crop-dialog__drop" @dragover.prevent @drop="drop"><p>{{ t('access.profile.avatarSelectionHint') }}</p><UIRinoButton @click="input?.click()" variant="primary" label="access.profile.avatarSelect" /></section>
            <template v-else>
                <div class="avatar-crop-dialog__preview" :style="previewStyle" role="slider" tabindex="0" :aria-label="t('access.profile.avatarPosition')" :aria-valuetext="t('access.profile.avatarZoomValue', { value: Math.round(zoom * 100) })" @keydown="keys" @pointerdown="pointerDown" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp" />
                <label class="avatar-crop-dialog__zoom"><span>{{ t('access.profile.avatarZoomValue', { value: Math.round(zoom * 100) }) }}</span><input v-model.number="zoom" type="range" min="1" max="4" step="0.01" :disabled="processing"></label>
                <div class="avatar-crop-dialog__tools"><UIRinoButton variant="secondary" :disabled="processing || zoom <= 1" @click="zoom = Math.max(1, zoom - .1)" label="access.profile.avatarZoomOut" /><UIRinoButton variant="secondary" :disabled="processing || zoom >= 4" @click="zoom = Math.min(4, zoom + .1)" label="access.profile.avatarZoomIn" /><UIRinoButton variant="secondary" :disabled="processing" @click="center" label="access.profile.avatarCenter" /><UIRinoButton variant="secondary" :disabled="processing" @click="input?.click()" label="access.profile.avatarChange" /></div>
            </template>
            <div class="dialog-actions"><UIRinoButton :disabled="processing" @click="close" command="cancel"  /><UIRinoButton :disabled="!ready" :loading="processing" @click="save" variant="primary" label="access.profile.avatarSave" /></div>
        </div>
    </UiDialog>
</template>
