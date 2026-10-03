import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { describe, expect, it, vi } from "vitest";
import { i18n } from "../../../resources/js/i18n";
import PersonQuickCreate from "../../../resources/js/people/PersonQuickCreate.vue";

vi.mock("axios", () => ({
    default: { post: vi.fn(), isAxiosError: vi.fn(() => false) },
}));

describe("PersonQuickCreate", () => {
    it("creates the minimum PF record with an optional document", async () => {
        vi.mocked(axios.post).mockResolvedValue({
            data: {
                person: {
                    id: 8,
                    personType: "PF",
                    displayName: "Ana",
                    document: null,
                },
            },
        });
        const wrapper = mount(PersonQuickCreate, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await wrapper.findAll("input")[0].setValue("Ana");
        await wrapper.get("form").trigger("submit");
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people",
            expect.objectContaining({
                personType: "PF",
                name: "Ana",
                cpf: null,
            }),
            expect.any(Object),
        );
        expect(wrapper.emitted("saved")?.[0][0]).toMatchObject({ id: 8 });
    });

    it("announces local validation and does not submit an incomplete quick-create form", async () => {
        const wrapper = mount(PersonQuickCreate, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });

        await wrapper.get("form").trigger("submit");

        expect(axios.post).not.toHaveBeenCalled();
        expect(wrapper.get('[role="alert"]').text()).toBe(
            "Informe um nome com pelo menos dois caracteres.",
        );
        expect(wrapper.get("input").attributes("aria-invalid")).toBe("true");
        wrapper.unmount();
    });

    it("disables a pending submission and restores the action after success", async () => {
        let resolveRequest: ((value: unknown) => void) | undefined;
        vi.mocked(axios.post).mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveRequest = resolve;
                }),
        );
        const wrapper = mount(PersonQuickCreate, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await wrapper.get("input").setValue("Ana");
        await wrapper.get("form").trigger("submit");

        expect(wrapper.get('button[type="submit"]').attributes("disabled")).toBeDefined();
        expect(wrapper.get('button[type="submit"]').text()).toBe("Salvando…");

        resolveRequest?.({
            data: {
                person: {
                    id: 8,
                    personType: "PF",
                    displayName: "Ana",
                    document: null,
                },
            },
        });
        await flushPromises();

        expect(wrapper.emitted("saved")?.[0][0]).toMatchObject({ id: 8 });
        expect(wrapper.get('button[type="submit"]').text()).toBe(
            "Salvar",
        );
        wrapper.unmount();
    });

    it("keeps the quick-create draft editable while offline and disables only remote submission", async () => {
        const wrapper = mount(PersonQuickCreate, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        window.dispatchEvent(new Event("offline"));
        await wrapper.vm.$nextTick();

        const name = wrapper.get("input");
        await name.setValue("Ana");
        expect(name.attributes("disabled")).toBeUndefined();
        expect(
            wrapper.get('button[type="submit"]').attributes("disabled"),
        ).toBeDefined();
        expect(wrapper.get('[role="status"]').text()).toBe(
            "Você está sem conexão. Reconecte-se para consultar Pessoas.",
        );
        window.dispatchEvent(new Event("online"));
        wrapper.unmount();
    });
});
