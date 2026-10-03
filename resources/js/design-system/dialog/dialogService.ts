import type { RinoButtonCommand } from '../rinoButtonCommands';

export type DialogModel = 'warning' | 'error' | 'question' | 'bug' | 'validation';
export type DialogButtonVariant = 'primary' | 'secondary' | 'destructive';

export interface DialogButton {
    id: string;
    /** Preset do botão; label é uma chave i18n opcional que sobrepõe o preset. */
    command?: RinoButtonCommand;
    label?: string | null;
    icon?: string | null;
    accessibleLabel?: string;
    variant?: DialogButtonVariant;
}

export interface DialogOptions {
    model: Exclude<DialogModel, 'validation'>;
    title: string;
    message: string;
    buttons: readonly DialogButton[];
    initialFocusActionId: string;
    escapeActionId?: string;
}

export interface ValidationIssue {
    id: string;
    message: string;
    fieldId?: string;
}

export interface ValidationDialogOptions {
    title?: string;
    issues: readonly ValidationIssue[];
    initialVisibleIssueCount?: number;
}

interface ValidationDialogRequestOptions {
    model: 'validation';
    title: string;
    message: '';
    buttons: readonly DialogButton[];
    initialFocusActionId: string;
    escapeActionId: string;
    validationIssues: readonly ValidationIssue[];
    initialVisibleIssueCount: number;
}

type QueuedDialogOptions = DialogOptions | ValidationDialogRequestOptions;

export interface DialogResult {
    actionId: string;
    issueId?: string;
    fieldId?: string;
}

export interface DialogRequest {
    id: string;
    options: QueuedDialogOptions;
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
    warning: { label: 'Warning', tone: 'warning', iconSrc: '/assets/icons/warning_512.png', urgent: true },
    error: { label: 'Error', tone: 'error', iconSrc: '/assets/icons/error_512.png', urgent: true },
    question: { label: 'Question', tone: 'question', iconSrc: '/assets/icons/question_512.png', urgent: false },
    bug: { label: 'BUG', tone: 'bug', iconSrc: '', urgent: true },
    validation: { label: 'Validation', tone: 'validation', iconSrc: '/assets/icons/validation_512.png', urgent: true },
};

export const bugDialogIconNames = ['ant', 'beetle_1', 'beetle', 'cricket', 'ladybug'] as const;

function createPresentation(model: DialogModel): DialogModelPresentation {
    const presentation = dialogModelPresentations[model];
    if (model !== 'bug') return presentation;

    const icon = bugDialogIconNames[Math.floor(Math.random() * bugDialogIconNames.length)];
    return { ...presentation, iconSrc: `/assets/icons/${icon}_512.png` };
}

type DialogListener = (request: DialogRequest) => void;

const listeners = new Set<DialogListener>();
const pendingBeforeHost: DialogRequest[] = [];
let sequence = 0;

function assertActionReference(options: QueuedDialogOptions, actionId: string, property: string): void {
    if (!options.buttons.some((button) => button.id === actionId)) {
        throw new Error(`Dialog ${property} must reference one of its buttons.`);
    }
}

function validate(options: QueuedDialogOptions): void {
    if (!options.title.trim()) throw new Error('Dialog title is required.');
    if (options.model !== 'validation' && !options.message.trim()) throw new Error('Dialog message is required.');
    if (options.model === 'validation') {
        if (!options.validationIssues.length) throw new Error('Validation dialog must provide at least one issue.');
        if (new Set(options.validationIssues.map((issue) => issue.id)).size !== options.validationIssues.length) throw new Error('Validation issue ids must be unique.');
        if (options.validationIssues.some((issue) => !issue.message.trim())) throw new Error('Validation issue messages are required.');
    }
    if (!options.buttons.length) throw new Error('Dialog must provide at least one button.');
    if (new Set(options.buttons.map((button) => button.id)).size !== options.buttons.length) throw new Error('Dialog button ids must be unique.');
    assertActionReference(options, options.initialFocusActionId, 'initialFocusActionId');
    if (options.escapeActionId !== undefined) assertActionReference(options, options.escapeActionId, 'escapeActionId');
}

function open(options: QueuedDialogOptions): Promise<DialogResult> {
    validate(options);

    return new Promise((resolve) => {
        const request: DialogRequest = { id: `dialog-${++sequence}`, options, presentation: createPresentation(options.model), resolve };
        if (listeners.size) listeners.forEach((listener) => listener(request));
        else pendingBeforeHost.push(request);
    });
}

/** Centraliza diálogos padronizados de mensagem e decisão da aplicação. */
export const dialog = {
    open(options: DialogOptions): Promise<DialogResult> {
        return open(options);
    },
    openValidation(options: ValidationDialogOptions): Promise<DialogResult> {
        return open({
            model: 'validation',
            title: options.title?.trim() || 'Revise os campos informados',
            message: '',
            buttons: [{ id: 'acknowledge', command: 'confirm', label: 'rinoButtons.context.acknowledge' }],
            initialFocusActionId: 'acknowledge',
            escapeActionId: 'acknowledge',
            validationIssues: options.issues,
            initialVisibleIssueCount: Math.max(1, Math.floor(options.initialVisibleIssueCount ?? 3)),
        });
    },
};

export function subscribeDialogs(listener: DialogListener): () => void {
    listeners.add(listener);
    pendingBeforeHost.splice(0).forEach((request) => listener(request));

    return () => listeners.delete(listener);
}
