import { beforeEach, describe, expect, it } from 'vitest';
import { i18n, setApplicationLocale } from '../../../resources/js/i18n';
import { en } from '../../../resources/js/i18n/messages/en';
import { es } from '../../../resources/js/i18n/messages/es';
import { fr } from '../../../resources/js/i18n/messages/fr';
import { ptBR } from '../../../resources/js/i18n/messages/pt-BR';

function collectKeys(value: Record<string, unknown>, prefix = ''): string[] {
    return Object.entries(value).flatMap(([key, nestedValue]) => {
        const path = prefix ? `${prefix}.${key}` : key;

        return typeof nestedValue === 'object' && nestedValue !== null
            ? collectKeys(nestedValue as Record<string, unknown>, path)
            : [path];
    });
}

describe('application internationalization', () => {
    beforeEach(() => {
        i18n.global.locale.value = 'pt-BR';
        document.documentElement.lang = 'pt-BR';
        window.history.replaceState({}, '', '/access/passwordless?challengeId=challenge-1');
        window.sessionStorage.clear();
    });

    it('keeps a complete, equivalent key set in the four approved catalogs', () => {
        const expectedKeys = collectKeys(ptBR).sort();

        expect(collectKeys(en).sort()).toEqual(expectedKeys);
        expect(collectKeys(es).sort()).toEqual(expectedKeys);
        expect(collectKeys(fr).sort()).toEqual(expectedKeys);
    });

    it('uses Brazilian Portuguese as the initial locale and fallback', () => {
        expect(i18n.global.locale.value).toBe('pt-BR');
        expect(i18n.global.t('access.entryTitle')).toBe('Acesse sua conta');
    });

    it('changes only presentation language and keeps the active journey location and session storage', () => {
        window.sessionStorage.setItem('session-marker', 'preserved');
        const locationBeforeChange = `${window.location.pathname}${window.location.search}`;

        setApplicationLocale('fr');

        expect(i18n.global.locale.value).toBe('fr');
        expect(document.documentElement.lang).toBe('fr');
        expect(i18n.global.t('access.entryTitle')).toBe('Accédez à votre compte');
        expect(`${window.location.pathname}${window.location.search}`).toBe(locationBeforeChange);
        expect(window.sessionStorage.getItem('session-marker')).toBe('preserved');
    });
});
