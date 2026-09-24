const ULID_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

/** Generates a browser-local ULID suitable for one tenant creation intent. */
export function createTenantIdempotencyKey(): string {
    const bytes = new Uint8Array(26);
    crypto.getRandomValues(bytes);

    return Array.from(bytes, (byte, index) => ULID_ALPHABET[index === 0 ? byte % 8 : byte % ULID_ALPHABET.length]).join('');
}
