export type AvatarFallbackKind = 'initials' | 'unknown';

export interface AvatarFallback {
    kind: AvatarFallbackKind;
    label: string;
}

/**
 * Derives a compact, human-readable avatar fallback from a display name.
 */
export function deriveAvatarFallback(displayName?: string | null): AvatarFallback {
    const words = displayName?.trim().split(/\s+/u).filter(Boolean) ?? [];

    if (words.length === 0) {
        return { kind: 'unknown', label: '?' };
    }

    const firstWord = Array.from(words[0]);

    if (words.length > 1) {
        const lastWord = Array.from(words.at(-1) ?? '');

        return { kind: 'initials', label: `${(firstWord[0] ?? '').toLocaleUpperCase()}${(lastWord[0] ?? '').toLocaleUpperCase()}` };
    }

    const firstCharacter = (firstWord[0] ?? '').toLocaleUpperCase();
    const secondCharacter = (firstWord[1] ?? '').toLocaleLowerCase();

    return { kind: 'initials', label: `${firstCharacter}${secondCharacter}` };
}
