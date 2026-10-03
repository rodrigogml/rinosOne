export function normalizeCpf(value: string): string {
    return value.replace(/\D/g, '').slice(0, 11);
}

export function formatCpf(value: string): string {
    const digits = normalizeCpf(value);
    const parts = [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 9)].filter(Boolean);
    const base = parts.join('.');
    const checkDigits = digits.slice(9, 11);

    return checkDigits ? `${base}-${checkDigits}` : base;
}

export function isValidCpf(value: string): boolean {
    const digits = normalizeCpf(value);
    if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) return false;

    const digitAt = (length: number): number => {
        const sum = digits.slice(0, length).split('').reduce((total, digit, index) => total + Number(digit) * (length + 1 - index), 0);
        const remainder = (sum * 10) % 11;
        return remainder === 10 ? 0 : remainder;
    };

    return digitAt(9) === Number(digits[9]) && digitAt(10) === Number(digits[10]);
}
