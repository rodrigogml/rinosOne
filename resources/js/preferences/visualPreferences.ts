import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { setApplicationLocale } from '../i18n';

export const VISUAL_PREFERENCES_VERSION = 1;
export const VISUAL_PREFERENCES_STORAGE_KEY = `rinos-one.visual-preferences.v${VISUAL_PREFERENCES_VERSION}`;

export const THEMES = ['system', 'light', 'dark'] as const;
export const PALETTES = ['amethyst-technical', 'architectural-teal', 'refined-copper', 'imperial-wine', 'sober-emerald', 'mineral-gold', 'orbital-indigo', 'industrial-ruby', 'deep-cyan', 'executive-coral'] as const;
export const LOCALES = ['pt-BR', 'en', 'es', 'fr'] as const;
export const SCALES = ['compact', 'default', 'comfortable'] as const;

export type ThemePreference = (typeof THEMES)[number];
export type PalettePreference = (typeof PALETTES)[number];
export type LocalePreference = (typeof LOCALES)[number];
export type DensityPreference = (typeof SCALES)[number];

export interface VisualPreferences {
    version: typeof VISUAL_PREFERENCES_VERSION;
    theme: ThemePreference;
    palette: PalettePreference;
    locale: LocalePreference;
    fontScale: DensityPreference;
    spacingScale: DensityPreference;
    componentScale: DensityPreference;
}

export const DEFAULT_VISUAL_PREFERENCES: Readonly<VisualPreferences> = Object.freeze({
    version: VISUAL_PREFERENCES_VERSION,
    theme: 'system',
    palette: 'industrial-ruby',
    locale: 'pt-BR',
    fontScale: 'default',
    spacingScale: 'default',
    componentScale: 'default',
});

type BrowserStorage = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

function isOneOf<T extends readonly string[]>(value: unknown, allowedValues: T): value is T[number] {
    return typeof value === 'string' && allowedValues.includes(value);
}

function readObject(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? value as Record<string, unknown>
        : null;
}

export function normalizeVisualPreferences(value: unknown): VisualPreferences {
    const source = readObject(value);

    if (source?.version !== VISUAL_PREFERENCES_VERSION) {
        return { ...DEFAULT_VISUAL_PREFERENCES };
    }

    return {
        version: VISUAL_PREFERENCES_VERSION,
        theme: isOneOf(source.theme, THEMES) ? source.theme : DEFAULT_VISUAL_PREFERENCES.theme,
        palette: isOneOf(source.palette, PALETTES) ? source.palette : DEFAULT_VISUAL_PREFERENCES.palette,
        locale: isOneOf(source.locale, LOCALES) ? source.locale : DEFAULT_VISUAL_PREFERENCES.locale,
        fontScale: isOneOf(source.fontScale, SCALES) ? source.fontScale : DEFAULT_VISUAL_PREFERENCES.fontScale,
        spacingScale: isOneOf(source.spacingScale, SCALES) ? source.spacingScale : DEFAULT_VISUAL_PREFERENCES.spacingScale,
        componentScale: isOneOf(source.componentScale, SCALES) ? source.componentScale : DEFAULT_VISUAL_PREFERENCES.componentScale,
    };
}

function hasSupportedVersion(value: unknown): boolean {
    return readObject(value)?.version === VISUAL_PREFERENCES_VERSION;
}

function getBrowserStorage(): BrowserStorage | null {
    return typeof window === 'undefined' ? null : window.localStorage;
}

export function loadVisualPreferences(storage: BrowserStorage | null = getBrowserStorage()): VisualPreferences {
    if (!storage) {
        return { ...DEFAULT_VISUAL_PREFERENCES };
    }

    try {
        const storedValue = storage.getItem(VISUAL_PREFERENCES_STORAGE_KEY);

        if (!storedValue) {
            return { ...DEFAULT_VISUAL_PREFERENCES };
        }

        const parsedValue: unknown = JSON.parse(storedValue);

        if (!hasSupportedVersion(parsedValue)) {
            storage.removeItem(VISUAL_PREFERENCES_STORAGE_KEY);

            return { ...DEFAULT_VISUAL_PREFERENCES };
        }

        const preferences = normalizeVisualPreferences(parsedValue);

        // Regrava o formato permitido para remover campos inesperados, inclusive dados sensíveis inseridos por engano.
        if (storedValue !== JSON.stringify(preferences)) {
            storage.setItem(VISUAL_PREFERENCES_STORAGE_KEY, JSON.stringify(preferences));
        }

        return preferences;
    } catch {
        return { ...DEFAULT_VISUAL_PREFERENCES };
    }
}

export function saveVisualPreferences(value: unknown, storage: BrowserStorage | null = getBrowserStorage()): VisualPreferences {
    const preferences = normalizeVisualPreferences(value);

    if (!storage) {
        return preferences;
    }

    try {
        storage.setItem(VISUAL_PREFERENCES_STORAGE_KEY, JSON.stringify(preferences));
    } catch {
        // A interface continua funcional quando o navegador bloqueia o armazenamento local.
    }

    return preferences;
}

export function applyVisualPreferences(preferences: VisualPreferences, documentReference: Document | null = typeof document === 'undefined' ? null : document): void {
    if (!documentReference) {
        return;
    }

    const root = documentReference.documentElement;

    root.dataset.theme = preferences.theme;
    root.dataset.palette = preferences.palette;
    root.dataset.fontScale = preferences.fontScale;
    root.dataset.spacingScale = preferences.spacingScale;
    root.dataset.componentScale = preferences.componentScale;
    setApplicationLocale(preferences.locale, documentReference);
}

export const useVisualPreferencesStore = defineStore('visual-preferences', () => {
    const current = ref<VisualPreferences>({ ...DEFAULT_VISUAL_PREFERENCES });
    const preferences = computed(() => current.value);

    function restore(): VisualPreferences {
        current.value = loadVisualPreferences();
        applyVisualPreferences(current.value);

        return current.value;
    }

    function update(changes: Partial<Omit<VisualPreferences, 'version'>>): VisualPreferences {
        current.value = saveVisualPreferences({ ...current.value, ...changes, version: VISUAL_PREFERENCES_VERSION });
        applyVisualPreferences(current.value);

        return current.value;
    }

    return { preferences, restore, update };
});
