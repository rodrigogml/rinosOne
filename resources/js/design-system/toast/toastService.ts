export type ToastKind = 'success' | 'info';

export interface ToastMessage {
    id: string;
    kind: ToastKind;
    message: string;
}

type ToastListener = (toast: ToastMessage) => void;

const listeners = new Set<ToastListener>();
const pendingBeforeHost: ToastMessage[] = [];
let sequence = 0;

function publish(kind: ToastKind, message: string): ToastMessage {
    const toast: ToastMessage = { id: `toast-${++sequence}`, kind, message };

    if (listeners.size) listeners.forEach((listener) => listener(toast));
    else pendingBeforeHost.push(toast);

    return toast;
}

/**
 * Ponto único de emissão das mensagens transitórias da aplicação.
 *
 * A fila e o ciclo de vida visual pertencem ao ToastHost; módulos de negócio
 * somente declaram o tipo permitido e o texto a apresentar à pessoa usuária.
 */
export const toast = {
    success(message: string): ToastMessage {
        return publish('success', message);
    },
    info(message: string): ToastMessage {
        return publish('info', message);
    },
};

export function subscribeToasts(listener: ToastListener): () => void {
    listeners.add(listener);
    pendingBeforeHost.splice(0).forEach((toast) => listener(toast));

    return () => listeners.delete(listener);
}
