import type { ptBR } from './pt-BR';

type DeepString<T> = {
    [Key in keyof T]: T[Key] extends Record<string, unknown> ? DeepString<T[Key]> : string;
};

export type AccessMessages = DeepString<typeof ptBR>;
