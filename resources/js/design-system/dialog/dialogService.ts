export type DialogModel = 'warning' | 'error' | 'question' | 'bug';
export type DialogButtonVariant = 'primary' | 'secondary' | 'destructive';

export interface DialogButton {
    id: string;
    label: string;
    variant?: DialogButtonVariant;
}

export interface DialogOptions {
    model: DialogModel;
    title: string;
    message: string;
    buttons: readonly DialogButton[];
    initialFocusActionId: string;
    escapeActionId?: string;
}

export interface DialogResult {
    actionId: string;
}

export interface DialogRequest {
    id: string;
    options: DialogOptions;
    presentation: DialogModelPresentation;
    resolve: (result: DialogResult) => void;
}

export interface DialogModelPresentation {
    label: string;
    tone: DialogModel;
    iconSrc: string;
    urgent: boolean;
}

export const dialogModelPresentations: Readonly<Record<DialogModel, DialogModelPresentation>> = {
    warning: { label: 'Warning', tone: 'warning', iconSrc: '/assets/icons/warning_48.png', urgent: true },
    error: { label: 'Error', tone: 'error', iconSrc: '/assets/icons/error_48.png', urgent: true },
    question: { label: 'Question', tone: 'question', iconSrc: '/assets/icons/question_48.png', urgent: false },
    bug: { label: 'BUG', tone: 'bug', iconSrc: '', urgent: true },
};

export const bugDialogIconNames = ['ant', 'beetle_1', 'beetle', 'cricket', 'ladybug'] as const;

function createPresentation(model: DialogModel): DialogModelPresentation {
    const presentation = dialogModelPresentations[model];
    if (model !== 'bug') return presentation;

    const icon = bugDialogIconNames[Math.floor(Math.random() * bugDialogIconNames.length)];
    return { ...presentation, iconSrc: `/assets/icons/${icon}_48.png` };
}

type DialogListener = (request: DialogRequest) => void;

const listeners = new Set<DialogListener>();
const pendingBeforeHost: DialogRequest[] = [];
let sequence = 0;

function assertActionReference(options: DialogOptions, actionId: string, property: string): void {
    if (!options.buttons.some((button) => button.id === actionId)) {
        throw new Error(`Dialog ${property} must reference one of its buttons.`);
    }
}

function validate(options: DialogOptions): void {
    if (!options.title.trim() || !options.message.trim()) throw new Error('Dialog title and message are required.');
    if (!options.buttons.length) throw new Error('Dialog must provide at least one button.');
    if (new Set(options.buttons.map((button) => button.id)).size !== options.buttons.length) throw new Error('Dialog button ids must be unique.');
    assertActionReference(options, options.initialFocusActionId, 'initialFocusActionId');
    if (options.escapeActionId !== undefined) assertActionReference(options, options.escapeActionId, 'escapeActionId');
}

/** Centraliza diálogos padronizados de mensagem e decisão da aplicação. */
export const dialog = {
    open(options: DialogOptions): Promise<DialogResult> {
        validate(options);

        return new Promise((resolve) => {
            const request: DialogRequest = { id: `dialog-${++sequence}`, options, presentation: createPresentation(options.model), resolve };
            if (listeners.size) listeners.forEach((listener) => listener(request));
            else pendingBeforeHost.push(request);
        });
    },
};

export function subscribeDialogs(listener: DialogListener): () => void {
    listeners.add(listener);
    pendingBeforeHost.splice(0).forEach((request) => listener(request));

    return () => listeners.delete(listener);
}
