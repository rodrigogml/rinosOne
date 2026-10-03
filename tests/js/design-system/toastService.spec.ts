import { afterEach, describe, expect, it, vi } from 'vitest';
import { subscribeToasts, toast } from '../../../resources/js/design-system/toast/toastService';

describe('toast service', () => {
    const unsubscriptions: Array<() => void> = [];

    afterEach(() => {
        unsubscriptions.splice(0).forEach((unsubscribe) => unsubscribe());
    });

    it('publishes only the success and info messages emitted through its central API', () => {
        const listener = vi.fn();
        unsubscriptions.push(subscribeToasts(listener));

        const success = toast.success('Cadastro salvo com sucesso.');
        const info = toast.info('A importação terminou.');

        expect(success).toMatchObject({ kind: 'success', message: 'Cadastro salvo com sucesso.' });
        expect(info).toMatchObject({ kind: 'info', message: 'A importação terminou.' });
        expect(success.id).not.toBe(info.id);
        expect(listener).toHaveBeenCalledWith(success);
        expect(listener).toHaveBeenCalledWith(info);
    });

    it('delivers a message emitted before the visual host subscribes', () => {
        const pending = toast.info('A rotina terminou antes de a tela estar pronta.');
        const listener = vi.fn();
        unsubscriptions.push(subscribeToasts(listener));

        expect(listener).toHaveBeenCalledWith(pending);
    });
});
