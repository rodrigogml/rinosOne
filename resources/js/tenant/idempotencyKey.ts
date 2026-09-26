/** Generates a browser-local UUID v4 suitable for one tenant creation intent. */
export function createTenantIdempotencyKey(): string {
    return crypto.randomUUID();
}
