import { afterEach, describe, expect, it } from "vitest";
import { trapFocus } from "../../../resources/js/accessibility/focusTrap";

describe("trapFocus", () => {
    afterEach(() => {
        document.body.replaceChildren();
    });

    it("cycles Tab and Shift+Tab inside the active dialog", () => {
        const dialog = document.createElement("section");
        dialog.tabIndex = -1;
        const first = document.createElement("button");
        const last = document.createElement("button");
        dialog.append(first, last);
        dialog.addEventListener("keydown", trapFocus);
        document.body.append(dialog);

        last.focus();
        last.dispatchEvent(
            new KeyboardEvent("keydown", {
                key: "Tab",
                bubbles: true,
                cancelable: true,
            }),
        );
        expect(document.activeElement).toBe(first);

        first.focus();
        first.dispatchEvent(
            new KeyboardEvent("keydown", {
                key: "Tab",
                shiftKey: true,
                bubbles: true,
                cancelable: true,
            }),
        );
        expect(document.activeElement).toBe(last);
    });

    it("moves focus into the dialog when a non-tabbable heading has focus", () => {
        const dialog = document.createElement("section");
        const heading = document.createElement("h2");
        heading.tabIndex = -1;
        const button = document.createElement("button");
        dialog.append(heading, button);
        dialog.addEventListener("keydown", trapFocus);
        document.body.append(dialog);

        heading.focus();
        heading.dispatchEvent(
            new KeyboardEvent("keydown", {
                key: "Tab",
                bubbles: true,
                cancelable: true,
            }),
        );

        expect(document.activeElement).toBe(button);
    });
});
