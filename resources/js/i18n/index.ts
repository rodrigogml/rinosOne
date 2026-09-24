import { createI18n } from 'vue-i18n';
import type { LocalePreference } from '../preferences/visualPreferences';
import { en } from './messages/en';
import { es } from './messages/es';
import { fr } from './messages/fr';
import { ptBR } from './messages/pt-BR';

export const i18n = createI18n({
    legacy: false,
    locale: 'pt-BR',
    fallbackLocale: 'pt-BR',
    messages: { 'pt-BR': ptBR, en, es, fr },
});

export function setApplicationLocale(locale: LocalePreference, documentReference: Document | null = typeof document === 'undefined' ? null : document): void {
    i18n.global.locale.value = locale;

    if (documentReference) {
        documentReference.documentElement.lang = locale;
    }
}
