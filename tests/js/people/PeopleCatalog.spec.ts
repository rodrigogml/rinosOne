import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import PeopleCatalog from "../../../resources/js/people/PeopleCatalog.vue";
import { i18n } from "../../../resources/js/i18n";

vi.mock("axios", () => ({
    default: { get: vi.fn(), isAxiosError: vi.fn(() => false) },
}));

const surface = {
    id: "surface-people",
    destinationId: "tenant.people",
    scope: "tenant" as const,
    tenantId: 18,
    titleKey: "access.people.title",
    label: "Pessoas",
    icon: "contacts",
    dirty: false,
    status: "active" as const,
};

const firstPage = {
    people: [
        {
            id: 8,
            personType: "PF" as const,
            displayName: "Ana Martins",
            document: "123.456.789-09",
            contactCount: 2,
            status: "ACTIVE" as const,
        },
    ],
    pagination: { page: 1, perPage: 50, total: 2, lastPage: 2 },
};

describe("PeopleCatalog", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        i18n.global.locale.value = "pt-BR";
        vi.mocked(axios.get).mockResolvedValue({ data: firstPage });
    });

    function mountCatalog() {
        return mount(PeopleCatalog, {
            props: { surface },
            global: { plugins: [i18n] },
        });
    }

    it("loads active people for the current tenant and exposes filters and pagination", async () => {
        const wrapper = mountCatalog();
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith("/api/v1/tenants/18/people", {
            params: {
                search: undefined,
                status: "ACTIVE",
                personType: undefined,
                page: 1,
            },
        });
        expect(wrapper.text()).toContain("Ana Martins");
        expect(wrapper.text()).toContain("2 contatos");
        expect(wrapper.text()).toContain("2 Pessoas encontradas.");
        expect(
            wrapper.get('[aria-label="Paginação de Pessoas"]').text(),
        ).toContain("1 / 2");
        wrapper.unmount();
    });

    it("pluralizes the catalogue result count", async () => {
        vi.mocked(axios.get).mockResolvedValue({
            data: {
                ...firstPage,
                pagination: { ...firstPage.pagination, total: 1 },
            },
        });
        const wrapper = mountCatalog();
        await flushPromises();

        expect(wrapper.text()).toContain("1 Pessoa encontrada.");
        wrapper.unmount();
    });

    it("identifies the active organization when it is supplied by the workspace", async () => {
        const wrapper = mount(PeopleCatalog, {
            props: { surface, tenantName: "Organização de teste" },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        expect(wrapper.get(".people-catalog__tenant").text()).toBe(
            "Organização: Organização de teste",
        );
        wrapper.unmount();
    });

    it("opens and closes the accessible filter sheet", async () => {
        const wrapper = mount(PeopleCatalog, {
            attachTo: document.body,
            props: { surface },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        const trigger = wrapper.get(".people-catalog__filters-toggle");
        await trigger.trigger("click");
        expect(wrapper.get('[role="dialog"]').text()).toContain("Filtros");
        expect(wrapper.get(".people-catalog__filter-panel").attributes("role")).toBeUndefined();
        expect(trigger.attributes("aria-expanded")).toBe("true");

        await wrapper.get('[role="dialog"]').trigger("keydown", {
            key: "Escape",
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(document.activeElement).toBe(trigger.element);
        wrapper.unmount();
    });

    it("loads the next page without losing the active filters", async () => {
        const wrapper = mountCatalog();
        await flushPromises();
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                ...firstPage,
                people: [
                    {
                        ...firstPage.people[0],
                        id: 9,
                        displayName: "Bruno Lima",
                    },
                ],
                pagination: { ...firstPage.pagination, page: 2 },
            },
        });

        await wrapper
            .get(".people-catalog__pagination button:last-child")
            .trigger("click");
        await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith(
            "/api/v1/tenants/18/people",
            {
                params: {
                    search: undefined,
                    status: "ACTIVE",
                    personType: undefined,
                    page: 2,
                },
            },
        );
        expect(wrapper.text()).toContain("Bruno Lima");
        wrapper.unmount();
    });

    it("debounces a text search while preserving the controlled filters", async () => {
        vi.useFakeTimers();
        const wrapper = mountCatalog();
        await flushPromises();
        await wrapper.get('input[type="search"]').setValue("Ana");
        await vi.advanceTimersByTimeAsync(299);
        expect(axios.get).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1);
        await flushPromises();
        expect(axios.get).toHaveBeenLastCalledWith(
            "/api/v1/tenants/18/people",
            {
                params: {
                    search: "Ana",
                    status: "ACTIVE",
                    personType: undefined,
                    page: 1,
                },
            },
        );
        wrapper.unmount();
        vi.useRealTimers();
    });

    it("clears applied filters back to the active default", async () => {
        vi.useFakeTimers();
        const wrapper = mountCatalog();
        await flushPromises();
        await wrapper.get('input[type="search"]').setValue("Ana");
        await vi.runAllTimersAsync();
        await wrapper
            .findAll("button")
            .find((button) => button.text() === "Limpar filtros")!
            .trigger("click");
        await vi.runAllTimersAsync();
        await flushPromises();

        expect(axios.get).toHaveBeenLastCalledWith(
            "/api/v1/tenants/18/people",
            {
                params: {
                    search: undefined,
                    status: "ACTIVE",
                    personType: undefined,
                    page: 1,
                },
            },
        );
        wrapper.unmount();
        vi.useRealTimers();
    });

    it("shows the localized access-denied state without exposing an API message", async () => {
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        vi.mocked(axios.get).mockRejectedValue({
            response: { status: 403 },
            message: "internal detail",
        });
        const wrapper = mountCatalog();
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe(
            "Você não tem permissão para consultar Pessoas desta organização.",
        );
        expect(wrapper.text()).not.toContain("internal detail");
        wrapper.unmount();
    });

    it("presents loading, empty and offline catalog states without exposing technical details", async () => {
        Object.defineProperty(navigator, "onLine", {
            configurable: true,
            value: true,
        });
        let resolveRequest: ((value: unknown) => void) | undefined;
        vi.mocked(axios.get).mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveRequest = resolve;
                }),
        );
        const wrapper = mountCatalog();
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[role="status"]').text()).toBe(
            "Carregando Pessoas…",
        );
        resolveRequest?.({
            data: {
                people: [],
                pagination: { page: 1, perPage: 50, total: 0, lastPage: 1 },
            },
        });
        await flushPromises();
        expect(wrapper.text()).toContain("Nenhuma Pessoa foi encontrada.");

        window.dispatchEvent(new Event("offline"));
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[role="status"]').text()).toBe(
            "Você está sem conexão. Reconecte-se para consultar Pessoas.",
        );
        window.dispatchEvent(new Event("online"));
        wrapper.unmount();
    });

    it("preserves the last valid list and labels it stale after a refresh failure", async () => {
        const wrapper = mountCatalog();
        await flushPromises();
        vi.mocked(axios.get).mockRejectedValue(new Error("network detail"));
        await wrapper
            .get(".people-catalog__tools button:last-child")
            .trigger("click");
        await flushPromises();
        expect(wrapper.text()).toContain("Ana Martins");
        expect(wrapper.get('[role="alert"]').text()).toContain(
            "Os dados exibidos podem estar desatualizados.",
        );
        wrapper.unmount();
    });

    it("does not expose creation actions to a read-only user", async () => {
        const wrapper = mount(PeopleCatalog, {
            props: { surface, capabilities: { canReadPeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        expect(wrapper.text()).not.toContain("Nova Pessoa");
        expect(wrapper.text()).not.toContain("Cadastro rápido");
        wrapper.unmount();
    });

    it("offers allowed contextual actions in both catalog presentations", async () => {
        const wrapper = mount(PeopleCatalog, {
            props: {
                surface,
                capabilities: {
                    canDuplicatePeople: true,
                    canInactivatePeople: true,
                    canDeletePeople: true,
                },
            },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        expect(
            wrapper
                .findAll(".people-catalog__actions button")
                .map((button) => button.text()),
        ).toEqual(
            expect.arrayContaining([
                "Abrir",
                "Duplicar",
                "Inativar",
                "Excluir",
            ]),
        );
        await wrapper
            .find(
                ".people-catalog__table .people-catalog__actions button:nth-of-type(2)",
            )
            .trigger("click");
        expect(wrapper.emitted("action")).toEqual([
            [8, "duplicate", expect.any(HTMLElement)],
        ]);
        wrapper.unmount();
    });

    it("provides a safe catalog focus target after a child flow closes", async () => {
        const wrapper = mount(PeopleCatalog, {
            attachTo: document.body,
            props: { surface },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await (wrapper.vm as unknown as { focus: () => Promise<void> }).focus();
        expect(document.activeElement).toBe(
            wrapper.get(".people-catalog").element,
        );
        wrapper.unmount();
    });
});
