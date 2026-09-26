import type { ptBR } from './pt-BR';

type DeepString<T> = {
    [Key in keyof T]: T[Key] extends Record<string, unknown> ? DeepString<T[Key]> : string;
};

type DeepPartial<T> = {
    [Key in keyof T]?: T[Key] extends Record<string, unknown> ? DeepPartial<T[Key]> : T[Key];
};

/**
 * Idiomas em evolução podem recorrer ao português brasileiro até que a
 * tradução específica da nova superfície seja entregue.
 */
export type AccessMessages = { access: DeepPartial<DeepString<typeof ptBR.access>> };
