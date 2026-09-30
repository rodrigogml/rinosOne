import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import PersonLifecycleActions from "../../../resources/js/people/PersonLifecycleActions.vue";
import { i18n } from "../../../resources/js/i18n";

vi.mock("axios", () => ({
    default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));
const person = {
    id: 2,
    version: 3,
    status: "ACTIVE" as const,
    personType: "PF" as const,
    displayName: "Ana",
    document: null,
    contactCount: 0,
    name: "Ana",
    alias: null,
    cpf: null,
    cnpj: null,
    rg: null,
    rgIssuer: null,
    pisNis: null,
    passportNumber: null,
    foreignDocumentNumber: null,
    birthDate: null,
    foundationDate: null,
    notes: null,
    addresses: [],
    contacts: [],
    bankAccounts: [],
    pixKeys: [],
    relationships: [],
};
describe("PersonLifecycleActions", () => {
    beforeEach(() => vi.clearAllMocks());
    it("inactivates only after confirmation with the read version", async () => {
        vi.mocked(axios.post).mockResolvedValue({
            data: { person: { ...person, status: "INACTIVE" } },
        });
        const wrapper = mount(PersonLifecycleActions, {
            attachTo: document.body,
            props: {
                tenantId: 18,
                person,
                capabilities: { canInactivatePeople: true },
            },
            global: { plugins: [i18n] },
        });
        const trigger = wrapper.get("button");
        await trigger.trigger("click");
        await wrapper.get('[role="dialog"] button:last-child').trigger("click");
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people/2/inactivate",
            { version: 3 },
            expect.any(Object),
        );
        expect(wrapper.emitted("changed")).toHaveLength(1);
        expect(document.activeElement).toBe(trigger.element);
        wrapper.unmount();
    });

    it("reactivates an inactive person with the read version", async () => {
        const inactive = { ...person, status: "INACTIVE" as const };
        vi.mocked(axios.post).mockResolvedValue({
            data: { person: { ...inactive, status: "ACTIVE" } },
        });
        const wrapper = mount(PersonLifecycleActions, {
            props: {
                tenantId: 18,
                person: inactive,
                capabilities: { canReactivatePeople: true },
            },
            global: { plugins: [i18n] },
        });
        await wrapper.get("button").trigger("click");
        await wrapper.get('[role="dialog"] button:last-child').trigger("click");
        await flushPromises();
        expect(axios.post).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people/2/reactivate",
            { version: 3 },
            expect.any(Object),
        );
        expect(wrapper.emitted("changed")?.[0][0]).toMatchObject({
            status: "ACTIVE",
        });
    });

    it("keeps destructive confirmation unavailable when known usages block deletion", async () => {
        vi.mocked(axios.get).mockResolvedValue({
            data: {
                usages: [
                    { module: "contracts", description: "Contratos ativos" },
                ],
            },
        });
        const wrapper = mount(PersonLifecycleActions, {
            props: {
                tenantId: 18,
                person,
                capabilities: { canDeletePeople: true },
            },
            global: { plugins: [i18n] },
        });

        await wrapper.get("button").trigger("click");
        await flushPromises();

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            "A exclusão está bloqueada por usos existentes.",
        );
        expect(
            wrapper
                .get('[role="dialog"] button:last-child')
                .attributes("disabled"),
        ).toBeDefined();
        expect(axios.delete).not.toHaveBeenCalled();
    });

    it("deletes only after an empty usage diagnosis and preserves the read version", async () => {
        vi.mocked(axios.get).mockResolvedValue({ data: { usages: [] } });
        vi.mocked(axios.delete).mockResolvedValue({ data: null });
        const wrapper = mount(PersonLifecycleActions, {
            props: {
                tenantId: 18,
                person,
                capabilities: { canDeletePeople: true },
            },
            global: { plugins: [i18n] },
        });

        await wrapper.get("button").trigger("click");
        await flushPromises();
        await wrapper.get('[role="dialog"] button:last-child').trigger("click");
        await flushPromises();

        expect(axios.delete).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people/2",
            expect.objectContaining({
                data: { version: 3 },
                headers: expect.objectContaining({
                    "Idempotency-Key": expect.any(String),
                }),
            }),
        );
        expect(wrapper.emitted("deleted")?.[0]).toEqual([2]);
    });

    it("keeps the confirmation open and reports a safe error when lifecycle update fails", async () => {
        vi.mocked(axios.post).mockRejectedValue(new Error("network detail"));
        const wrapper = mount(PersonLifecycleActions, {
            props: {
                tenantId: 18,
                person,
                capabilities: { canInactivatePeople: true },
            },
            global: { plugins: [i18n] },
        });
        await wrapper.get("button").trigger("click");
        await wrapper.get('[role="dialog"] button:last-child').trigger("click");
        await flushPromises();
        expect(wrapper.get('[role="dialog"] [role="alert"]').text()).toBe(
            "Não foi possível concluir a ação agora.",
        );
        expect(wrapper.emitted("changed")).toBeUndefined();
    });

    it("does not expose lifecycle actions after permission is absent", () => {
        const wrapper = mount(PersonLifecycleActions, {
            props: { tenantId: 18, person, capabilities: {} },
            global: { plugins: [i18n] },
        });

        expect(wrapper.findAll("button")).toHaveLength(0);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });
});
