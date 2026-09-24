import { beforeEach, describe, expect, it } from 'vitest';
import {
    DEFAULT_VISUAL_PREFERENCES,
    VISUAL_PREFERENCES_STORAGE_KEY,
    applyVisualPreferences,
    loadVisualPreferences,
    saveVisualPreferences,
} from '../../../resources/js/preferences/visualPreferences';

describe('visual preferences', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.documentElement.removeAttribute('data-theme');
        document.documentElement.removeAttribute('data-font-scale');
        document.documentElement.removeAttribute('data-spacing-scale');
        document.documentElement.removeAttribute('data-component-scale');
        document.documentElement.lang = '';
    });

    it('uses the approved defaults when no stored preference exists', () => {
        expect(loadVisualPreferences()).toEqual(DEFAULT_VISUAL_PREFERENCES);
    });

    it('rejects unknown versions and removes an invalid persisted value', () => {
        window.localStorage.setItem(VISUAL_PREFERENCES_STORAGE_KEY, JSON.stringify({ version: 99, theme: 'dark' }));

        expect(loadVisualPreferences()).toEqual(DEFAULT_VISUAL_PREFERENCES);
        expect(window.localStorage.getItem(VISUAL_PREFERENCES_STORAGE_KEY)).toBeNull();
    });

    it('normalizes invalid fields without discarding valid independent choices', () => {
        window.localStorage.setItem(VISUAL_PREFERENCES_STORAGE_KEY, JSON.stringify({
            version: 1,
            theme: 'dark',
            locale: 'invalid',
            fontScale: 'comfortable',
            spacingScale: 'invalid',
            componentScale: 'compact',
        }));

        expect(loadVisualPreferences()).toEqual({
            version: 1,
            theme: 'dark',
            locale: 'pt-BR',
            fontScale: 'comfortable',
            spacingScale: 'default',
            componentScale: 'compact',
        });
    });

    it('persists only the closed preference contract and excludes sensitive fields', () => {
        saveVisualPreferences({
            version: 1,
            theme: 'light',
            locale: 'en',
            fontScale: 'compact',
            spacingScale: 'comfortable',
            componentScale: 'default',
            email: 'person@example.test',
            password: 'Secret#1',
            code: '123456',
            token: 'secret-token',
            session: 'session-id',
        });

        expect(JSON.parse(window.localStorage.getItem(VISUAL_PREFERENCES_STORAGE_KEY) ?? '{}')).toEqual({
            version: 1,
            theme: 'light',
            locale: 'en',
            fontScale: 'compact',
            spacingScale: 'comfortable',
            componentScale: 'default',
        });
    });

    it('applies every preference atomically to the document root', () => {
        applyVisualPreferences({
            version: 1,
            theme: 'dark',
            locale: 'fr',
            fontScale: 'comfortable',
            spacingScale: 'compact',
            componentScale: 'comfortable',
        });

        expect(document.documentElement.dataset).toMatchObject({
            theme: 'dark',
            fontScale: 'comfortable',
            spacingScale: 'compact',
            componentScale: 'comfortable',
        });
        expect(document.documentElement.lang).toBe('fr');
    });
});
