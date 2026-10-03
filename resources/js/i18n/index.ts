import { createI18n } from 'vue-i18n';
import type { LocalePreference } from '../preferences/visualPreferences';
import { en } from './messages/en';
import { es } from './messages/es';
import { fr } from './messages/fr';
import { ptBR } from './messages/pt-BR';
import { rinoButtonMessages } from './rinoButtonMessages';

// New authorization labels default to Portuguese until each locale receives a reviewed translation.
const advancedAuthorizationLabels = ptBR.access.authorization as Record<string, string | Record<string, string>>;
for (const messages of [en, es, fr]) {
    const authorization = messages.access.authorization as Record<string, unknown>;
    Object.assign(authorization, {
        permissionId: advancedAuthorizationLabels.permissionId,
        startsAt: advancedAuthorizationLabels.startsAt,
        endsAt: advancedAuthorizationLabels.endsAt,
        state: advancedAuthorizationLabels.state,
        actions: advancedAuthorizationLabels.actions,
        approve: advancedAuthorizationLabels.approve,
        revoke: advancedAuthorizationLabels.revoke,
        temporaryAccess: advancedAuthorizationLabels.temporaryAccess,
        temporaryAccessHelp: advancedAuthorizationLabels.temporaryAccessHelp,
        requestTemporaryAccess: advancedAuthorizationLabels.requestTemporaryAccess,
        temporaryRequested: advancedAuthorizationLabels.temporaryRequested,
        temporaryApproved: advancedAuthorizationLabels.temporaryApproved,
        temporaryRevoked: advancedAuthorizationLabels.temporaryRevoked,
        accessRequestQueue: advancedAuthorizationLabels.accessRequestQueue,
        emptyAccessRequests: advancedAuthorizationLabels.emptyAccessRequests,
        advancedControls: advancedAuthorizationLabels.advancedControls,
    });
    Object.assign(authorization.tabs as Record<string, string>, advancedAuthorizationLabels.tabs as Record<string, string>);
}

const peopleLabels = (ptBR.access as unknown as { people: Record<string, string> }).people;
for (const messages of [en, es, fr]) {
    Object.assign((messages.access as unknown as { people: Record<string, string> }).people, peopleLabels);
}

export const i18n = createI18n({
    legacy: false,
    locale: 'pt-BR',
    fallbackLocale: 'pt-BR',
    messages: {
        'pt-BR': { ...ptBR, rinoButtons: rinoButtonMessages['pt-BR'] },
        en: { ...en, rinoButtons: rinoButtonMessages.en },
        es: { ...es, rinoButtons: rinoButtonMessages.es },
        fr: { ...fr, rinoButtons: rinoButtonMessages.fr },
    },
});

export function setApplicationLocale(locale: LocalePreference, documentReference: Document | null = typeof document === 'undefined' ? null : document): void {
    i18n.global.locale.value = locale;

    if (documentReference) {
        documentReference.documentElement.lang = locale;
    }
}
