import { flushPromises, mount } from "@vue/test-utils";
import axios from "axios";
import { beforeEach, describe, expect, it, vi } from "vitest";
import PersonForm from "../../../resources/js/people/PersonForm.vue";
import { i18n } from "../../../resources/js/i18n";

vi.mock("axios", () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        isAxiosError: vi.fn(() => false),
    },
}));

describe("PersonForm", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        i18n.global.locale.value = "pt-BR";
        vi.mocked(axios.get).mockImplementation((url: string) =>
            Promise.resolve({
                data: url.endsWith("/countries")
                    ? { countries: [] }
                    : url.endsWith("/financial-institutions")
                      ? { financialInstitutions: [] }
                      : {
                            people: [],
                            pagination: {
                                page: 1,
                                perPage: 50,
                                total: 0,
                                lastPage: 1,
                            },
                        },
            }),
        );
    });

    it("creates a PF without an optional document and returns the saved person", async () => {
        vi.mocked(axios.post).mockResolvedValue({
            data: {
                person: {
                    id: 4,
                    personType: "PF",
                    displayName: "Ana",
                    document: null,
                    status: "ACTIVE",
                },
            },
        });
        const wrapper = mount(PersonForm, {
            attachTo: document.body,
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await wrapper.get("input").setValue("Ana");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people",
            expect.objectContaining({
                personType: "PF",
                name: "Ana",
                cpf: null,
            }),
            expect.objectContaining({
                headers: expect.objectContaining({
                    "Idempotency-Key": expect.any(String),
                }),
            }),
        );
        expect(wrapper.emitted("saved")?.[0][0]).toMatchObject({
            id: 4,
            displayName: "Ana",
        });
    });

    it("clears incompatible identity fields when the person type changes", async () => {
        vi.mocked(axios.post).mockResolvedValue({
            data: {
                person: {
                    id: 4,
                    personType: "PF",
                    displayName: "Ana",
                    document: null,
                    status: "ACTIVE",
                },
            },
        });
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        await wrapper.get('#person-type-PJ').trigger("click");
        await wrapper.findAll("input")[2].setValue("04.252.011/0001-10");
        await wrapper.get('#person-type-PF').trigger("click");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people",
            expect.objectContaining({
                personType: "PF",
                cpf: null,
                cnpj: null,
            }),
            expect.any(Object),
        );
    });

    it("loads and updates an existing PJ with its read version", async () => {
        vi.mocked(axios.get).mockImplementation((url: string) =>
            Promise.resolve({
                data: url.endsWith("/9")
                    ? {
                          person: {
                              id: 9,
                              personType: "PJ",
                              displayName: "Rubi",
                              document: "04.252.011/0001-10",
                              status: "ACTIVE",
                              name: "Rubi Ltda",
                              alias: null,
                              cpf: null,
                              cnpj: "04.252.011/0001-10",
                              rg: null,
                              rgIssuer: null,
                              pisNis: null,
                              passportNumber: null,
                              foreignDocumentNumber: null,
                              birthDate: null,
                              foundationDate: null,
                              notes: null,
                              version: 3,
                              addresses: [],
                              bankAccounts: [],
                              contacts: [],
                              pixKeys: [],
                              relationships: [],
                          },
                      }
                    : url.endsWith("/countries")
                      ? { countries: [] }
                      : url.endsWith("/financial-institutions")
                        ? { financialInstitutions: [] }
                        : {
                              people: [],
                              pagination: {
                                  page: 1,
                                  perPage: 50,
                                  total: 0,
                                  lastPage: 1,
                              },
                          },
            }),
        );
        vi.mocked(axios.put).mockResolvedValue({
            data: {
                person: {
                    id: 9,
                    personType: "PJ",
                    displayName: "Rubi Renovada",
                    document: null,
                    status: "ACTIVE",
                },
            },
        });
        const wrapper = mount(PersonForm, {
            props: {
                tenantId: 18,
                personId: 9,
                capabilities: { canUpdatePeople: true },
            },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Rubi Renovada");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();

        expect(axios.put).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people/9",
            expect.objectContaining({ name: "Rubi Renovada", version: 3 }),
            expect.any(Object),
        );
        expect(wrapper.emitted("saved")).toHaveLength(1);
    });

    it("opens the requested lifecycle confirmation after loading a catalog action", async () => {
        vi.mocked(axios.get).mockImplementation((url: string) =>
            Promise.resolve({
                data: url.endsWith("/9")
                    ? {
                          person: {
                              id: 9,
                              personType: "PF",
                              displayName: "Ana",
                              document: null,
                              status: "ACTIVE",
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
                              version: 2,
                              addresses: [],
                              bankAccounts: [],
                              contacts: [],
                              pixKeys: [],
                              relationships: [],
                          },
                      }
                    : url.endsWith("/countries")
                      ? { countries: [] }
                      : url.endsWith("/financial-institutions")
                        ? { financialInstitutions: [] }
                        : {
                              people: [],
                              pagination: {
                                  page: 1,
                                  perPage: 50,
                                  total: 0,
                                  lastPage: 1,
                              },
                          },
            }),
        );
        const source = document.createElement("button");
        document.body.append(source);
        const wrapper = mount(PersonForm, {
            attachTo: document.body,
            props: {
                tenantId: 18,
                personId: 9,
                initialAction: "inactivate",
                initialActionTrigger: source,
                capabilities: { canInactivatePeople: true },
            },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            "Inativar Pessoa",
        );
        expect(document.activeElement).toBe(
            wrapper.get('[role="dialog"] h4').element,
        );
        wrapper.unmount();
        source.remove();
    });

    it("renders an existing person as non-editable without update capability", async () => {
        vi.mocked(axios.get).mockImplementation((url: string) =>
            Promise.resolve({
                data: url.endsWith("/9")
                    ? {
                          person: {
                              id: 9,
                              personType: "PF",
                              displayName: "Ana",
                              document: null,
                              status: "ACTIVE",
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
                              version: 2,
                              addresses: [],
                              bankAccounts: [],
                              contacts: [],
                              pixKeys: [],
                              relationships: [],
                          },
                      }
                    : url.endsWith("/countries")
                      ? { countries: [] }
                      : url.endsWith("/financial-institutions")
                        ? { financialInstitutions: [] }
                        : {
                              people: [],
                              pagination: {
                                  page: 1,
                                  perPage: 50,
                                  total: 0,
                                  lastPage: 1,
                              },
                          },
            }),
        );
        const wrapper = mount(PersonForm, {
            props: {
                tenantId: 18,
                personId: 9,
                capabilities: { canReadPeople: true },
            },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        expect(wrapper.get("fieldset").attributes("disabled")).toBeDefined();
        expect(
            wrapper.get("header button:last-child").attributes("disabled"),
        ).toBeDefined();
    });

    it("shows the localized version conflict and does not expose the backend detail", async () => {
        vi.mocked(axios.post).mockRejectedValue({
            response: {
                data: {
                    error: {
                        code: "PERSON_VERSION_CONFLICT",
                        message: "internal detail",
                    },
                },
            },
        });
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        const wrapper = mount(PersonForm, {
            attachTo: document.body,
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await wrapper.get("input").setValue("Ana");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe(
            "Os dados foram alterados. Recarregue a Pessoa e refaça a alteração.",
        );
        expect(wrapper.text()).not.toContain("internal detail");
    });

    it("shows the localized document conflict without exposing backend detail", async () => {
        vi.mocked(axios.post).mockRejectedValue({
            response: {
                data: {
                    error: {
                        code: "PERSON_DOCUMENT_CONFLICT",
                        message: "internal detail",
                    },
                },
            },
        });
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe(
            "CPF ou CNPJ já pertence a outra Pessoa desta organização.",
        );
        expect(wrapper.text()).not.toContain("internal detail");
    });

    it("keeps values and identifies the document field when the server rejects it", async () => {
        vi.mocked(axios.post).mockRejectedValue({
            response: {
                data: {
                    error: {
                        code: "PERSON_VALIDATION_FAILED",
                        fields: { cpf: ["CPF inválido"] },
                    },
                },
            },
        });
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        await wrapper.findAll("input")[2].setValue("123");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();

        expect(wrapper.get("#person-document-error").text()).toBe(
            "CPF inválido",
        );
        expect(wrapper.findAll("input")[2].element.value).toBe("123");
        expect(
            wrapper.get('[aria-invalid="true"]').attributes("aria-describedby"),
        ).toBe("person-document-error");
    });

    it("shows a collection validation error on its affected contact item", async () => {
        vi.mocked(axios.post).mockRejectedValue({
            response: {
                data: {
                    error: {
                        code: "PERSON_VALIDATION_FAILED",
                        fields: { "contacts.0.value": ["Contato inválido"] },
                    },
                },
            },
        });
        vi.mocked(axios.isAxiosError).mockReturnValue(true);
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        const contacts = wrapper.findAll(".collection")[2];
        await contacts.get(".collection > button").trigger("click");
        await contacts.get("input").setValue("invalid");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();
        expect(contacts.get('[role="alert"]').text()).toBe("Contato inválido");
    });

    it("requires Brazilian state and municipality before submitting an address", async () => {
        vi.mocked(axios.get).mockImplementation((url: string) =>
            Promise.resolve({
                data: url.endsWith("/countries")
                    ? {
                          countries: [
                              { id: 1, isoAlpha2: "BR", name: "Brasil" },
                          ],
                      }
                    : url.includes("brazil-states")
                      ? { states: [] }
                      : url.endsWith("/financial-institutions")
                        ? { financialInstitutions: [] }
                        : {
                              people: [],
                              pagination: {
                                  page: 1,
                                  perPage: 50,
                                  total: 0,
                                  lastPage: 1,
                              },
                          },
            }),
        );
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        const addresses = wrapper.findAll(".collection")[0];
        await addresses.get(".collection > button").trigger("click");
        await addresses.findAll("select")[1].setValue("1");
        await wrapper.get("header button:last-child").trigger("click");

        expect(axios.post).not.toHaveBeenCalled();
        expect(addresses.get('[role="alert"]').text()).toContain(
            "Selecione a UF",
        );
    });

    it("submits multiple contacts and Pix keys without a primary marker", async () => {
        vi.mocked(axios.post).mockResolvedValue({
            data: {
                person: {
                    id: 5,
                    personType: "PF",
                    displayName: "Ana",
                    document: null,
                    status: "ACTIVE",
                },
            },
        });
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        const addButtons = wrapper.findAll(".collection > button");
        await addButtons[2].trigger("click");
        await addButtons[3].trigger("click");
        const collectionInputs = wrapper.findAll(".collection input");
        await collectionInputs[0].setValue("ana@example.test");
        await collectionInputs[1].setValue("ana.pix@example.test");
        await wrapper.get("header button:last-child").trigger("click");
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith(
            "/api/v1/tenants/18/people",
            expect.objectContaining({
                contacts: [
                    {
                        contactType: "EMAIL",
                        value: "ana@example.test",
                        description: null,
                    },
                ],
                pixKeys: [
                    {
                        keyType: "EMAIL",
                        value: "ana.pix@example.test",
                        status: "ACTIVE",
                    },
                ],
            }),
            expect.any(Object),
        );
    });

    it("does not offer the edited person as a relationship target", async () => {
        vi.mocked(axios.get).mockImplementation((url: string) =>
            Promise.resolve({
                data: url.endsWith("/9")
                    ? {
                          person: {
                              id: 9,
                              personType: "PF",
                              displayName: "Ana",
                              document: null,
                              status: "ACTIVE",
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
                              version: 2,
                              addresses: [],
                              bankAccounts: [],
                              contacts: [],
                              pixKeys: [],
                              relationships: [],
                          },
                      }
                    : url.endsWith("/countries")
                      ? { countries: [] }
                      : url.endsWith("/financial-institutions")
                        ? { financialInstitutions: [] }
                        : {
                              people: [
                                  { id: 9, displayName: "Ana" },
                                  { id: 10, displayName: "Bruno" },
                              ],
                              pagination: {
                                  page: 1,
                                  perPage: 50,
                                  total: 2,
                                  lastPage: 1,
                              },
                          },
            }),
        );
        const wrapper = mount(PersonForm, {
            props: {
                tenantId: 18,
                personId: 9,
                capabilities: { canUpdatePeople: true },
            },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        const relationshipSection = wrapper.findAll(".collection").at(-1)!;
        await relationshipSection.get(".collection > button").trigger("click");
        const options = relationshipSection
            .findAll("select")
            .at(0)!
            .findAll("option")
            .map((option) => option.text());
        expect(options).toContain("Bruno");
        expect(options).not.toContain("Ana");
    });

    it("shows the inverse relationship only as contextual presentation", async () => {
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        const relationships = wrapper.findAll(".collection").at(-1)!;
        await relationships.get(".collection > button").trigger("click");
        await relationships.findAll("select")[1].setValue("CHILD_OF");
        expect(relationships.text()).toContain("Na outra Pessoa: PARENT_OF");
    });

    it("searches active tenant people before choosing a relationship target", async () => {
        vi.useFakeTimers();
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await vi.runAllTimersAsync();
        await flushPromises();
        const search = wrapper.get(
            'input[aria-label="Buscar Pessoa para relacionamento"]',
        );
        await search.setValue("Bruno");
        await vi.advanceTimersByTimeAsync(300);
        await flushPromises();
        expect(axios.get).toHaveBeenLastCalledWith(
            "/api/v1/tenants/18/people",
            { params: { search: "Bruno", status: "ACTIVE", perPage: 200 } },
        );
        vi.useRealTimers();
    });

    it("asks before discarding a modified draft and emits cancellation only after confirmation", async () => {
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        await wrapper.get("input").setValue("Ana");
        await wrapper.get("header button").trigger("click");

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            "Alterações não salvas",
        );
        expect(wrapper.emitted("cancel")).toBeUndefined();
        await wrapper.get('[role="dialog"] button:last-child').trigger("click");
        expect(wrapper.emitted("cancel")).toHaveLength(1);
    });

    it("returns focus to the back action when canceling the discard confirmation", async () => {
        const wrapper = mount(PersonForm, {
            attachTo: document.body,
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        const back = wrapper.get("header button:first-child");
        await wrapper.get("input").setValue("Ana");
        await back.trigger("click");
        await flushPromises();

        expect(document.activeElement).toBe(
            wrapper.get('[role="dialog"] h4').element,
        );
        await wrapper
            .get('[role="dialog"] button:first-of-type')
            .trigger("click");
        expect(document.activeElement).toBe(back.element);
    });

    it("opens a collection as a focused mobile sheet and returns to its opener", async () => {
        const wrapper = mount(PersonForm, {
            attachTo: document.body,
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        const addresses = wrapper.findAll(".collection")[0];
        const opener = addresses.get(".collection__open");
        await opener.trigger("click");
        await flushPromises();

        expect(addresses.classes()).toContain("collection--active");
        expect(addresses.attributes("role")).toBe("dialog");
        expect(addresses.attributes("aria-labelledby")).toBe(
            "person-addresses-title",
        );
        expect(document.activeElement).toBe(addresses.element);
        await addresses.trigger("keydown", { key: "Escape" });
        expect(document.activeElement).toBe(opener.element);
    });

    it("shows the current item count in every related-data section", async () => {
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        expect(
            wrapper.findAll(".collection h4").map((heading) => heading.text()),
        ).toEqual([
            "Endereços (0)",
            "Contas bancárias (0)",
            "Contatos (0)",
            "Chaves Pix (0)",
            "Relacionamentos (0)",
        ]);
    });

    it("previews the display name using the same name and alias formula as the domain", async () => {
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        const inputs = wrapper.findAll("input");
        await inputs[0].setValue("Ana Martins");
        await inputs[1].setValue("Ana");

        expect(wrapper.get(".person-form__display-name").text()).toBe(
            "Nome de exibiçãoAna Martins (Ana)",
        );
    });

    it("provides desktop section navigation for basic data and every collection", async () => {
        const scrollIntoView = vi.fn();
        Object.defineProperty(HTMLElement.prototype, "scrollIntoView", {
            configurable: true,
            value: scrollIntoView,
        });
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        const navigation = wrapper.get('[aria-label="Seções da Pessoa"]');
        expect(navigation.text()).toContain("Dados básicos");
        const contacts = navigation
            .findAll("button")
            .find((button) => button.text() === "Contatos")!;
        await contacts.trigger("click");
        expect(scrollIntoView).toHaveBeenCalledOnce();
        expect(contacts.attributes("aria-current")).toBe("page");
        wrapper.unmount();
    });

    it("gives collection controls localized accessible names", async () => {
        const wrapper = mount(PersonForm, {
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();

        const addresses = wrapper.findAll(".collection")[0];
        await addresses.get(".collection > button").trigger("click");
        expect(
            addresses.get('input[aria-label="Identificação do endereço"]'),
        ).toBeDefined();
        expect(addresses.get('select[aria-label="País"]')).toBeDefined();
        expect(addresses.get('input[aria-label="Rua"]')).toBeDefined();
        expect(
            wrapper.get('input[aria-label="Buscar Pessoa para relacionamento"]'),
        ).toBeDefined();
    });

    it("removes an address only after confirmation", async () => {
        const wrapper = mount(PersonForm, {
            attachTo: document.body,
            props: { tenantId: 18, capabilities: { canCreatePeople: true } },
            global: { plugins: [i18n] },
        });
        await flushPromises();
        const addressSection = wrapper.findAll(".collection")[0];
        await addressSection.get(".collection > button").trigger("click");
        expect(
            addressSection.findAll('[aria-label="Remover item"]'),
        ).toHaveLength(1);
        const removeButton = addressSection.get('[aria-label="Remover item"]');
        await removeButton.trigger("click");
        expect(document.activeElement).toBe(
            wrapper.get('[role="dialog"] h4').element,
        );
        await wrapper.get('[role="dialog"] button').trigger("click");
        expect(
            addressSection.findAll('[aria-label="Remover item"]'),
        ).toHaveLength(1);
        expect(document.activeElement).toBe(removeButton.element);
        await addressSection
            .get('[aria-label="Remover item"]')
            .trigger("click");
        await wrapper.get('[role="dialog"] button:last-child').trigger("click");
        expect(
            addressSection.findAll('[aria-label="Remover item"]'),
        ).toHaveLength(0);
    });
});
