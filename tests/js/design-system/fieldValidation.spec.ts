import { describe, expect, it } from 'vitest';
import { formatCpf, isValidCpf, normalizeCpf } from '../../../resources/js/design-system/fieldValidation';

describe('CPF field validation', () => {
    it('retains only eleven digits and formats a CPF after focus leaves the control', () => {
        expect(normalizeCpf('529.982.247-25abc')).toBe('52998224725');
        expect(formatCpf('52998224725')).toBe('529.982.247-25');
    });

    it('accepts a valid CPF and rejects invalid or repeated values', () => {
        expect(isValidCpf('529.982.247-25')).toBe(true);
        expect(isValidCpf('123.456.789-00')).toBe(false);
        expect(isValidCpf('111.111.111-11')).toBe(false);
    });
});
