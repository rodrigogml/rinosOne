const focusableSelector =
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Keeps keyboard focus inside a modal container while it is visible.
 *
 * The container remains responsible for deciding when Escape or pointer
 * dismissal is allowed. This helper only handles the Tab cycle.
 */
export function trapFocus(event: KeyboardEvent): void {
    if (event.key !== "Tab" || !(event.currentTarget instanceof HTMLElement)) {
        return;
    }

    const container = event.currentTarget;
    const focusable = Array.from(
        container.querySelectorAll<HTMLElement>(focusableSelector),
    );
    if (!focusable.length) {
        event.preventDefault();
        container.focus();
        return;
    }

    const first = focusable[0]!;
    const last = focusable.at(-1)!;
    const active = document.activeElement;
    if (!focusable.includes(active as HTMLElement)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
        return;
    }
    if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}
