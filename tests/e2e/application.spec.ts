import {
    expect,
    test,
    type Page,
    type Route,
    type TestInfo,
} from "@playwright/test";

const user = { id: "user-1", displayName: "Pessoa", passwordDefined: false };

async function mockUnauthenticatedSession(page: Page): Promise<void> {
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            status: 401,
            body: JSON.stringify({ code: "UNAUTHENTICATED" }),
        });
    });
}

async function mockAuthenticatedSession(
    page: Page,
    persistentAuthentication = true,
): Promise<void> {
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ persistentAuthentication, user }),
        });
    });
}

async function mockPeopleWorkspace(
    page: Page,
    { readOnly = false }: { readOnly?: boolean } = {},
): Promise<void> {
    const tenant = {
        id: 71,
        displayName: "Organização de teste",
        state: "ACTIVE",
        selectable: true,
        canManageAvailability: false,
        canReadAuthorization: false,
    };
    const capabilities = {
        canManageAvailability: false,
        canReadAuthorization: false,
        canReadPeople: true,
        canCreatePeople: !readOnly,
        canUpdatePeople: !readOnly,
        canDuplicatePeople: !readOnly,
        canInactivatePeople: !readOnly,
        canReactivatePeople: !readOnly,
        canDeletePeople: !readOnly,
    };
    const person = {
        id: 101,
        personType: "PF",
        displayName: "Pessoa existente",
        document: null,
        status: "ACTIVE",
    };
    const detail = {
        ...person,
        name: "Pessoa existente",
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
        version: 1,
        addresses: [],
        contacts: [],
        bankAccounts: [],
        pixKeys: [],
        relationships: [],
    };

    await mockAuthenticatedSession(page);
    await page.route("**/api/v1/tenants", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ tenants: [{ ...tenant, ...capabilities }] }),
        });
    });
    await page.route("**/api/v1/tenants/71/contexts", async (route) => {
        if (route.request().method() === "DELETE") {
            await route.fulfill({ status: 204 });
            return;
        }
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                context: {
                    tenant: { id: tenant.id, displayName: tenant.displayName },
                    membership: { id: 1 },
                    capabilities,
                    availableModules: [],
                },
            }),
        });
    });
    await page.route("**/api/v1/tenants/71/people**", async (route) => {
        const path = new URL(route.request().url()).pathname;
        if (
            path.endsWith("/people/101/duplicate") &&
            route.request().method() === "POST"
        ) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    person: {
                        ...detail,
                        id: 102,
                        displayName: "Cópia de Pessoa existente",
                    },
                }),
            });
            return;
        }
        if (
            path.endsWith("/people/101/inactivate") &&
            route.request().method() === "POST"
        ) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    person: { ...detail, status: "INACTIVE", version: 2 },
                }),
            });
            return;
        }
        if (path.endsWith("/people/101/deletion-usage")) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ usages: [] }),
            });
            return;
        }
        if (path.endsWith("/people")) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    people: [person],
                    pagination: {
                        page: 1,
                        perPage: 50,
                        total: 1,
                        lastPage: 1,
                    },
                }),
            });
            return;
        }
        if (path.endsWith("/references/countries")) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ countries: [] }),
            });
            return;
        }
        if (path.endsWith("/references/financial-institutions")) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ financialInstitutions: [] }),
            });
            return;
        }
        if (path.endsWith("/people/101")) {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ person: detail }),
            });
            return;
        }
        await route.fulfill({
            contentType: "application/json",
            status: 404,
            body: JSON.stringify({ error: { code: "NOT_FOUND" } }),
        });
    });
    await page.route("**/api/v1/tenants/71/people", async (route) => {
        if (route.request().method() === "POST") {
            const request = route.request().postDataJSON() as {
                personType: string;
                name: string;
            };
            expect(request).toMatchObject({ personType: "PF" });
            await route.fulfill({
                contentType: "application/json",
                status: 201,
                body: JSON.stringify({
                    person: {
                        ...detail,
                        id: 102,
                        name: request.name,
                        displayName: request.name,
                    },
                }),
            });
            return;
        }
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                people: [person],
                pagination: { page: 1, perPage: 50, total: 1, lastPage: 1 },
            }),
        });
    });
    await page.route(
        "**/api/v1/platform/maintenance/routines",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ routines: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/platform/authorization/context",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                status: 403,
                body: JSON.stringify({ error: { code: "FORBIDDEN" } }),
            });
        },
    );
}

async function openPeopleWorkspace(page: Page): Promise<void> {
    await page.getByRole("button", { name: "Selecionar organização" }).click();
    await page
        .getByRole("button", { name: "Organização de teste", exact: true })
        .click();
    if ((page.viewportSize()?.width ?? 0) < 700) {
        await page.getByRole("button", { name: "Abrir navegação" }).click();
        await page
            .getByRole("button", { name: "Workspace", exact: true })
            .click();
    } else {
        await page
            .getByRole("button", { name: "Organização de teste: Workspace" })
            .click();
    }
    await page.getByRole("button", { name: "Pessoas", exact: true }).click();
}

async function openExistingPerson(page: Page): Promise<void> {
    const actions = page.locator(
        (page.viewportSize()?.width ?? 0) < 700
            ? ".people-catalog__cards .people-catalog__actions"
            : ".people-catalog__table .people-catalog__actions",
    );
    await actions.locator("summary").click();
    await actions.getByRole("button", { name: "Abrir", exact: true }).click();
}

async function openCatalogAction(
    page: Page,
    action: "Duplicar" | "Inativar",
): Promise<void> {
    const actions = page.locator(
        (page.viewportSize()?.width ?? 0) < 700
            ? ".people-catalog__cards .people-catalog__actions"
            : ".people-catalog__table .people-catalog__actions",
    );
    await actions.locator("summary").click();
    await actions.getByRole("button", { name: action, exact: true }).click();
}

async function captureState(
    page: Page,
    testInfo: TestInfo,
    name: string,
): Promise<void> {
    await page.screenshot({
        path: testInfo.outputPath(`${name}.png`),
        fullPage: true,
    });
}

/**
 * Allows browser-level UI tests to keep exercising the production bundle when
 * a local PHP server cannot bootstrap its dynamic document.
 */
async function mockProductionDocument(page: Page): Promise<void> {
    const manifestResponse = await page.request.get("/build/manifest.json");
    const manifest = (await manifestResponse.json()) as Record<
        string,
        { file: string; css?: string[] }
    >;
    const entry = manifest["resources/js/app.ts"];
    if (!entry)
        throw new Error("Production entry was not found in the Vite manifest.");

    const styleFiles = [
        ...new Set(
            Object.values(manifest)
                .flatMap((asset) => [
                    asset.file.endsWith(".css") ? asset.file : null,
                    ...(asset.css ?? []),
                ])
                .filter((file): file is string => file !== null),
        ),
    ];
    const styles = styleFiles
        .map((file) => `<link rel="stylesheet" href="/build/${file}">`)
        .join("");
    const fulfillDocument = (route: Route) =>
        route.fulfill({
            contentType: "text/html",
            body: `<!doctype html><html lang="pt-BR"><head><meta name="viewport" content="width=device-width, initial-scale=1">${styles}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`,
        });
    await page.route("**/", fulfillDocument);
    await page.route("**/access/**", fulfillDocument);
}

test.beforeEach(async ({ page }) => {
    await mockProductionDocument(page);
});

test("starts passwordless access and completes its documented API contract", async ({
    page,
}) => {
    let authenticated = false;
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill(
            authenticated
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          persistentAuthentication: true,
                          user,
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 401,
                      body: JSON.stringify({ code: "UNAUTHENTICATED" }),
                  },
        );
    });
    await page.route("**/api/v1/auth/passwordless-sessions", async (route) => {
        expect(route.request().method()).toBe("POST");
        expect(route.request().postDataJSON()).toEqual({
            email: "person@example.test",
            rememberMe: true,
        });
        await route.fulfill({
            contentType: "application/json",
            status: 202,
            body: JSON.stringify({
                message: "accepted",
                challengeId: "challenge-passwordless",
                resendAvailableInSeconds: 180,
            }),
        });
    });
    await page.route(
        "**/api/v1/auth/passwordless-sessions/confirmations",
        async (route) => {
            expect(route.request().postDataJSON()).toEqual({
                challengeId: "challenge-passwordless",
                code: "123456",
            });
            authenticated = true;
            await route.fulfill({
                contentType: "application/json",
                status: 201,
                body: JSON.stringify({ user }),
            });
        },
    );

    await page.goto("/");
    await page.getByLabel("E-mail").fill("person@example.test");
    await page.getByLabel("Manter-me conectado").check();
    await expect(
        page.getByRole("button", { name: "Entrar sem senha" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Entrar sem senha" }).click();

    await expect(
        page.getByRole("heading", { name: "Confirme seu e-mail" }),
    ).toBeVisible();
    await expect(
        page.getByText(
            "Se existir uma conta para person@example.test, enviaremos um e-mail de acesso sem senha.",
        ),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: /Reenviar em 3:00/ }),
    ).toBeDisabled();
    await page.getByLabel("Código de confirmação").fill("123456");
    await page.getByRole("button", { name: "Concluir acesso" }).click();
    await expect(
        page.getByRole("main", { name: "Área de trabalho", exact: true }),
    ).toBeVisible();
});

test("starts password access with its documented API contract", async ({
    page,
}) => {
    let authenticated = false;
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill(
            authenticated
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          persistentAuthentication: false,
                          user,
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 401,
                      body: JSON.stringify({ code: "UNAUTHENTICATED" }),
                  },
        );
    });
    await page.route("**/api/v1/auth/password-sessions", async (route) => {
        expect(route.request().postDataJSON()).toEqual({
            email: "person@example.test",
            password: "Secret#1",
            rememberMe: false,
        });
        authenticated = true;
        await route.fulfill({
            contentType: "application/json",
            status: 201,
            body: JSON.stringify({ user }),
        });
    });

    await page.goto("/");
    await page.getByLabel("E-mail").fill("person@example.test");
    await page.getByLabel("Senha").fill("Secret#1");
    await expect(
        page.getByRole("button", { name: "Entrar", exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Entrar", exact: true }).click();
    await expect(
        page.getByRole("main", { name: "Área de trabalho", exact: true }),
    ).toBeVisible();
});

test("creates an account, confirms the e-mail code and enters the authenticated area", async ({
    page,
}) => {
    let authenticated = false;
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill(
            authenticated
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          persistentAuthentication: false,
                          user,
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 401,
                      body: JSON.stringify({ code: "UNAUTHENTICATED" }),
                  },
        );
    });
    await page.route("**/api/v1/auth/registrations", async (route) => {
        expect(route.request().postDataJSON()).toEqual({
            email: "person@example.test",
            displayName: "Pessoa",
            rememberMe: false,
        });
        await route.fulfill({
            contentType: "application/json",
            status: 202,
            body: JSON.stringify({
                message: "accepted",
                challengeId: "challenge-registration",
                resendAvailableInSeconds: 180,
            }),
        });
    });
    await page.route("**/api/v1/auth/email-verifications", async (route) => {
        expect(route.request().postDataJSON()).toEqual({
            challengeId: "challenge-registration",
            code: "654321",
        });
        authenticated = true;
        await route.fulfill({
            contentType: "application/json",
            status: 201,
            body: JSON.stringify({ user }),
        });
    });

    await page.goto("/");
    await page.getByRole("button", { name: "Criar conta" }).click();
    await expect(page).toHaveURL(/\/access\/register$/);
    await page.getByLabel("Nome").fill("Pessoa");
    await page.getByLabel("E-mail").fill("person@example.test");
    await page.getByRole("button", { name: "Criar conta" }).click();
    await page.getByLabel("Código de confirmação").fill("654321");
    await page.getByRole("button", { name: "Concluir acesso" }).click();
    await expect(
        page.getByRole("main", { name: "Área de trabalho", exact: true }),
    ).toBeVisible();
});

test("continues passwordless access from an e-mail link in a new tab", async ({
    page,
}) => {
    let authenticated = false;
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill(
            authenticated
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          persistentAuthentication: true,
                          user,
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 401,
                      body: JSON.stringify({ code: "UNAUTHENTICATED" }),
                  },
        );
    });
    await page.route(
        "**/api/v1/auth/passwordless-sessions/link-confirmations",
        async (route) => {
            expect(route.request().postDataJSON()).toEqual({
                challengeId: 3,
                token: "secret-token",
            });
            authenticated = true;
            await route.fulfill({
                contentType: "application/json",
                status: 201,
                body: JSON.stringify({ user }),
            });
        },
    );

    await page.goto("/access/passwordless?challengeId=3&token=secret-token");
    await expect(page).toHaveURL(/\/access\/passwordless$/);
    await expect(
        page.getByRole("main", { name: "Área de trabalho", exact: true }),
    ).toBeVisible();
});

test("confirms a registration link directly without requesting name or code again", async ({
    page,
}) => {
    let authenticated = false;
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill(
            authenticated
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          persistentAuthentication: false,
                          user,
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 401,
                      body: JSON.stringify({ code: "UNAUTHENTICATED" }),
                  },
        );
    });
    await page.route(
        "**/api/v1/auth/email-verifications/link-confirmations",
        async (route) => {
            expect(route.request().postDataJSON()).toEqual({
                challengeId: 4,
                token: "secret-token",
            });
            authenticated = true;
            await route.fulfill({
                contentType: "application/json",
                status: 201,
                body: JSON.stringify({ user }),
            });
        },
    );

    await page.goto(
        "/access/email-verification?challengeId=4&token=secret-token",
    );
    await expect(
        page.getByRole("main", { name: "Área de trabalho", exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel("Código de confirmação")).toHaveCount(0);
    await expect(page.getByLabel("Nome")).toHaveCount(0);
});

test("preserves safe form state, route and session while changing presentation and language", async ({
    page,
}) => {
    await mockUnauthenticatedSession(page);
    await page.goto("/");
    await page.getByRole("button", { name: "Criar conta" }).click();
    await page.getByLabel("Nome").fill("Pessoa");
    await page.getByLabel("E-mail").fill("person@example.test");

    await page.getByRole("button", { name: "Preferências visuais" }).click();
    await page.locator("#theme-choice-dark").click();
    await page.locator("#font-scale-choice-comfortable").click();
    await page.locator("#spacing-scale-choice-compact").click();
    await page.locator("#component-scale-choice-comfortable").click();
    await expect(page.locator("html")).toHaveAttribute("data-theme", "dark");
    await expect(page.locator("html")).toHaveAttribute(
        "data-font-scale",
        "comfortable",
    );
    await expect(page.locator("html")).toHaveAttribute(
        "data-spacing-scale",
        "compact",
    );
    await expect(page.locator("html")).toHaveAttribute(
        "data-component-scale",
        "comfortable",
    );

    await page.keyboard.press("Escape");
    await page
        .getByRole("button", { name: "Idioma atual: Português (Brasil)" })
        .click();
    await page.getByRole("option", { name: "English" }).click();
    await expect(
        page.getByRole("heading", { name: "Create account" }),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/access\/register$/);
    await expect(page.getByLabel("Name")).toHaveValue("Pessoa");
    await expect(page.getByLabel("Email")).toHaveValue("person@example.test");
    await expect(page.locator("html")).toHaveAttribute("lang", "en");
    await expect
        .poll(async () =>
            page.evaluate(() =>
                localStorage.getItem("rinos-one.visual-preferences.v1"),
            ),
        )
        .not.toContain("person@example.test");
});

test("keeps the authenticated shell controls keyboard accessible", async ({
    page,
}) => {
    await mockAuthenticatedSession(page);
    await page.goto("/");

    await page.getByRole("button", { name: "Menu pessoal de Pessoa" }).click();
    const preferences = page
        .getByRole("dialog", { name: "Menu pessoal" })
        .getByRole("button", { name: "Preferências visuais" });
    await preferences.focus();
    await page.keyboard.press("Enter");
    await expect(
        page.getByRole("dialog", { name: "Preferências visuais" }),
    ).toBeFocused();
    await page.keyboard.press("Escape");
    await expect(preferences).toBeFocused();
});

test("keeps tenant context isolated by tab and clears it after a page reload", async ({
    page,
    context,
}) => {
    const tenants = [
        {
            id: 1,
            displayName: "Ateliê Norte",
            state: "ACTIVE",
            selectable: true,
            canManageAvailability: true,
            canReadAuthorization: true,
        },
        {
            id: 2,
            displayName: "Ateliê Sul",
            state: "ACTIVE",
            selectable: true,
            canManageAvailability: true,
            canReadAuthorization: true,
        },
    ];

    await context.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ persistentAuthentication: true, user }),
        });
    });
    await context.route("**/api/v1/tenants", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ tenants }),
        });
    });
    await context.route("**/api/v1/tenants/*/contexts", async (route) => {
        if (route.request().method() === "DELETE") {
            await route.fulfill({ status: 204 });
            return;
        }
        const tenantId = Number(
            new URL(route.request().url()).pathname.match(
                /\/tenants\/(\d+)\/contexts$/,
            )?.[1],
        );
        const tenant = tenants.find((candidate) => candidate.id === tenantId);
        await route.fulfill(
            tenant
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          context: {
                              tenant: {
                                  id: tenant.id,
                                  displayName: tenant.displayName,
                              },
                              membership: { id: tenant.id },
                              capabilities: {
                                  canManageAvailability:
                                      tenant.canManageAvailability,
                                  canReadAuthorization:
                                      tenant.canReadAuthorization,
                              },
                              availableModules: [],
                          },
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 404,
                      body: JSON.stringify({
                          error: {
                              code: "TENANT_NOT_AVAILABLE",
                              message: "Unavailable",
                          },
                      }),
                  },
        );
    });

    const secondTab = await context.newPage();
    await mockProductionDocument(secondTab);
    await page.goto("/");
    await secondTab.goto("/");

    await page.getByRole("button", { name: "Selecionar organização" }).click();
    await page
        .getByRole("button", { name: "Ateliê Norte", exact: true })
        .click();
    await expect(
        page.getByRole("button", { name: "Organização atual: Ateliê Norte" }),
    ).toBeVisible();

    await secondTab
        .getByRole("button", { name: "Selecionar organização" })
        .click();
    await secondTab
        .getByRole("button", { name: "Ateliê Sul", exact: true })
        .click();
    await expect(
        secondTab.getByRole("button", {
            name: "Organização atual: Ateliê Sul",
        }),
    ).toBeVisible();

    await page
        .getByRole("button", { name: "Organização atual: Ateliê Norte" })
        .click();
    await page.getByRole("button", { name: "Usar somente meu espaço" }).click();
    await expect(
        page.getByRole("button", { name: "Selecionar organização" }),
    ).toBeVisible();
    await expect(
        secondTab.getByRole("button", {
            name: "Organização atual: Ateliê Sul",
        }),
    ).toBeVisible();

    await secondTab.reload();
    await expect(
        secondTab.getByRole("button", { name: "Selecionar organização" }),
    ).toBeVisible();
    await secondTab.close();
});

test("revalidates a revoked tenant capability before the next browser operation", async ({
    page,
}) => {
    let grantActive = true;
    const tenant = {
        id: 1,
        displayName: "Ateliê Norte",
        state: "ACTIVE",
        selectable: true,
    };

    await mockAuthenticatedSession(page);
    await page.route("**/api/v1/tenants", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                tenants: [
                    {
                        ...tenant,
                        canManageAvailability: grantActive,
                        canReadAuthorization: grantActive,
                    },
                ],
            }),
        });
    });
    await page.route("**/api/v1/tenants/1/contexts", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                context: {
                    tenant: { id: tenant.id, displayName: tenant.displayName },
                    membership: { id: 1 },
                    capabilities: {
                        canManageAvailability: grantActive,
                        canReadAuthorization: grantActive,
                    },
                    availableModules: [],
                },
            }),
        });
    });
    await page.route("**/api/v1/tenants/1/availability", async (route) => {
        expect(route.request().method()).toBe("POST");
        expect(route.request().postDataJSON()).toEqual({ state: "INACTIVE" });
        await route.fulfill(
            grantActive
                ? {
                      contentType: "application/json",
                      body: JSON.stringify({
                          tenant: {
                              ...tenant,
                              state: "INACTIVE",
                              selectable: false,
                              canManageAvailability: true,
                              canReadAuthorization: true,
                          },
                      }),
                  }
                : {
                      contentType: "application/json",
                      status: 403,
                      body: JSON.stringify({
                          error: {
                              code: "TENANT_ADMINISTRATOR_REQUIRED",
                              message: "Unavailable",
                          },
                      }),
                  },
        );
    });

    await page.goto("/");
    await page.getByRole("button", { name: "Selecionar organização" }).click();
    await page
        .getByRole("button", { name: tenant.displayName, exact: true })
        .click();
    await expect(
        page.getByRole("button", { name: "Organização atual: Ateliê Norte" }),
    ).toBeVisible();

    grantActive = false;
    const result = await page.evaluate(async () => {
        const response = await fetch("/api/v1/tenants/1/availability", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ state: "INACTIVE" }),
        });

        return { status: response.status, body: await response.json() };
    });

    expect(result).toEqual({
        status: 403,
        body: {
            error: {
                code: "TENANT_ADMINISTRATOR_REQUIRED",
                message: "Unavailable",
            },
        },
    });
});

test("opens user settings as a single personal workspace surface", async ({
    page,
}) => {
    await mockAuthenticatedSession(page);
    let otherSessionsRevoked = 0;
    let savedPassword = "";
    await page.route("**/api/v1/auth/other-sessions", async (route) => {
        expect(route.request().method()).toBe("DELETE");
        otherSessionsRevoked += 1;
        await route.fulfill({ status: 204 });
    });
    await page.route("**/api/v1/auth/password", async (route) => {
        if (route.request().method() === "DELETE") {
            await route.fulfill({ status: 204 });
            return;
        }
        expect(route.request().method()).toBe("PUT");
        savedPassword = route.request().postDataJSON().password;
        await route.fulfill({ status: 204 });
    });
    await page.goto("/");

    await page.getByRole("button", { name: "Menu pessoal de Pessoa" }).click();
    await page
        .getByRole("button", { name: "Configurações do usuário", exact: true })
        .click();
    await expect(
        page.getByRole("tab", { name: "Configurações do usuário" }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Tema e aparência", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Tema e aparência" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Ametista Técnica" }).click();
    await page.getByRole("button", { name: "Claro" }).click();
    await page
        .locator(".workspace-settings__panel")
        .nth(2)
        .getByRole("button", { name: "Confortável" })
        .click();
    await page
        .locator(".workspace-settings__panel")
        .nth(3)
        .getByRole("button", { name: "Compacta" })
        .click();
    await page
        .locator(".workspace-settings__panel")
        .nth(4)
        .getByRole("button", { name: "Compacta" })
        .click();
    await expect(page.locator("html")).toHaveAttribute(
        "data-palette",
        "amethyst-technical",
    );
    await expect(page.locator("html")).toHaveAttribute("data-theme", "light");
    await expect(page.locator("html")).toHaveAttribute(
        "data-font-scale",
        "comfortable",
    );
    await expect(page.locator("html")).toHaveAttribute(
        "data-spacing-scale",
        "compact",
    );
    await expect(page.locator("html")).toHaveAttribute(
        "data-component-scale",
        "compact",
    );
    expect(
        await page.evaluate(() =>
            JSON.parse(
                localStorage.getItem("rinos-one.visual-preferences.v1") ?? "{}",
            ),
        ),
    ).toMatchObject({
        palette: "amethyst-technical",
        theme: "light",
        fontScale: "comfortable",
        spacingScale: "compact",
        componentScale: "compact",
    });
    await page.getByRole("button", { name: "Escuro" }).click();
    const darkSurface = await page
        .locator("html")
        .evaluate((element) =>
            getComputedStyle(element).getPropertyValue("--color-surface"),
        );
    await page.getByRole("button", { name: "Esmeralda Sóbria" }).click();
    await expect
        .poll(() =>
            page
                .locator("html")
                .evaluate((element) =>
                    getComputedStyle(element).getPropertyValue(
                        "--color-surface",
                    ),
                ),
        )
        .toBe(darkSurface);
    await page.getByRole("button", { name: "Sessões" }).click();
    await expect(
        page.getByRole("heading", { name: "Sessões ativas" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Encerrar demais sessões" }).click();
    await page
        .getByRole("dialog", { name: "Encerrar demais sessões" })
        .getByRole("button", { name: "Encerrar sessões" })
        .click();
    await expect.poll(() => otherSessionsRevoked).toBe(1);
    await page.getByRole("button", { name: "Segurança" }).click();
    await expect(
        page.getByRole("heading", { name: "Segurança da conta" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Definir senha" }).click();
    const passwordDialog = page.getByRole("dialog", { name: "Definir senha" });
    await passwordDialog
        .getByLabel("Nova senha", { exact: true })
        .fill("Nova#Senha1");
    await passwordDialog
        .getByLabel("Confirme a nova senha")
        .fill("Nova#Senha1");
    await passwordDialog.getByRole("button", { name: "Salvar senha" }).click();
    await expect.poll(() => savedPassword).toBe("Nova#Senha1");
    await expect(
        page.getByRole("button", { name: "Alterar senha" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Remover senha" }).click();
    const removePasswordDialog = page.getByRole("dialog", {
        name: "Remover senha",
    });
    await expect(
        removePasswordDialog.getByText(
            "Sem senha, o acesso à sua conta deverá ser feito por outro método de autenticação válido",
        ),
    ).toBeVisible();
    await removePasswordDialog
        .getByRole("button", { name: "Remover senha" })
        .click();
    await expect(
        page.getByRole("button", { name: "Definir senha" }),
    ).toBeVisible();

    await page.getByRole("button", { name: "Menu pessoal de Pessoa" }).click();
    await page
        .getByRole("button", { name: "Configurações do usuário", exact: true })
        .click();
    await expect(
        page.getByRole("tab", { name: "Configurações do usuário" }),
    ).toHaveCount(1);
});

for (const viewport of [
    { name: "telefone", width: 375, height: 667 },
    { name: "tablet", width: 768, height: 1024 },
    { name: "desktop", width: 1440, height: 900 },
]) {
    test(`renders the access journey without horizontal overflow on ${viewport.name} in extreme presentations`, async ({
        page,
    }, testInfo) => {
        await mockUnauthenticatedSession(page);
        await page.setViewportSize({
            width: viewport.width,
            height: viewport.height,
        });
        await page.goto("/");
        const crest = await page.locator('.access-frame__crest').boundingBox();
        const logo = await page.locator('.access-frame__brand').boundingBox();
        const accessCard = await page.locator('.access-frame .ui-card').boundingBox();
        expect(crest!.y).toBeGreaterThanOrEqual(0);
        expect(crest!.y + crest!.height).toBeLessThanOrEqual(logo!.y);
        expect(logo!.y + logo!.height).toBeLessThanOrEqual(accessCard!.y);
        if (viewport.width < 640) {
            const accessFrame = page.locator(".access-frame");
            const viewportHeight = await page.evaluate(
                () => window.innerHeight,
            );
            const bounds = await accessFrame.boundingBox();
            expect(bounds!.y + bounds!.height / 2).toBeGreaterThan(
                viewportHeight * 0.4,
            );
            expect(bounds!.y + bounds!.height / 2).toBeLessThan(
                viewportHeight * 0.6,
            );
        }
        await page
            .getByRole("button", { name: "Preferências visuais" })
            .click();
        await page.locator("#theme-choice-dark").click();
        await page.locator("#font-scale-choice-comfortable").click();
        await page.locator("#spacing-scale-choice-comfortable").click();
        await page.locator("#component-scale-choice-comfortable").click();
        await captureState(page, testInfo, `${viewport.name}-dark-comfortable`);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        expect(
            await page
                .getByRole("button", { name: "Entrar sem senha" })
                .evaluate((element) =>
                    Number.parseFloat(getComputedStyle(element).minHeight),
                ),
        ).toBeGreaterThanOrEqual(44);

        await page.locator("#theme-choice-light").click();
        await page.locator("#font-scale-choice-compact").press("Enter");
        await page.locator("#spacing-scale-choice-compact").press("Enter");
        await page.locator("#component-scale-choice-compact").press("Enter");
        await captureState(page, testInfo, `${viewport.name}-light-compact`);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
    });

    test(`keeps the authenticated shell usable on ${viewport.name}`, async ({
        page,
    }, testInfo) => {
        let signedOut = false;
        await page.route("**/api/v1/auth/session", async (route) => {
            if (route.request().method() === "DELETE") {
                signedOut = true;
                await route.fulfill({ status: 204 });
                return;
            }
            await route.fulfill(
                signedOut
                    ? {
                          contentType: "application/json",
                          status: 401,
                          body: JSON.stringify({ code: "UNAUTHENTICATED" }),
                      }
                    : {
                          contentType: "application/json",
                          body: JSON.stringify({
                              persistentAuthentication: true,
                              user,
                          }),
                      },
            );
        });
        await page.setViewportSize({
            width: viewport.width,
            height: viewport.height,
        });
        await page.goto("/");

        await expect(
            page.getByRole("main", { name: "Área de trabalho", exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole("button", { name: "Menu pessoal de Pessoa" }),
        ).toBeVisible();
        expect(
            await page.evaluate(
                () =>
                    document.scrollingElement!.scrollHeight <=
                    window.innerHeight,
            ),
        ).toBe(true);

        if (viewport.width < 640) {
            const navigationOpener = page.getByRole("button", {
                name: "Abrir navegação",
            });
            await expect(navigationOpener).toBeVisible();
            await navigationOpener.focus();
            await page.keyboard.press("Enter");
            const drawer = page.getByRole("dialog", { name: "Navegação" });
            await expect(drawer).toBeVisible();
            await expect(drawer.getByRole("link")).toHaveCount(0);
            await page.keyboard.press("Escape");
            await expect(navigationOpener).toBeFocused();
        } else {
            await expect(
                page.locator(".application-top-bar__desktop-brand"),
            ).toBeVisible();
            await expect(
                page.getByRole("button", { name: "Abrir navegação" }),
            ).toBeHidden();

            const stage = page.locator(".workspace-stage--empty");
            const stageBefore = await stage.boundingBox();
            await page
                .locator(".workspace-navigation-rail__category")
                .first()
                .click();
            await expect(page.locator("#workspace-mega-menu")).toBeVisible();
            const stageAfter = await stage.boundingBox();
            const megaMenu = await page
                .locator("#workspace-mega-menu")
                .boundingBox();
            expect(stageAfter).toEqual(stageBefore);
            expect(megaMenu?.x).toBe(stageBefore?.x);
            expect(megaMenu?.width).toBe(stageBefore?.width);
            await page.keyboard.press("Escape");
        }

        const personalMenuOpener = page.getByRole("button", {
            name: "Menu pessoal de Pessoa",
        });
        await personalMenuOpener.focus();
        await page.keyboard.press("Enter");
        const personalMenu = page.getByRole("dialog", { name: "Menu pessoal" });
        await expect(personalMenu).toBeVisible();
        await expect(
            personalMenu.getByRole("button", {
                name: "Configurações do usuário",
            }),
        ).toBeEnabled();

        await personalMenu
            .getByRole("button", { name: "Preferências visuais" })
            .click();
        await page.locator("#theme-choice-dark").click();
        await expect(page.locator("html")).toHaveAttribute(
            "data-theme",
            "dark",
        );
        await page.keyboard.press("Escape");
        await personalMenu
            .getByRole("button", { name: "Idioma atual: Português (Brasil)" })
            .click();
        await personalMenu.getByRole("option", { name: "English" }).click();
        await expect(
            page.getByRole("main", { name: "Workspace", exact: true }),
        ).toBeVisible();
        await captureState(
            page,
            testInfo,
            `${viewport.name}-authenticated-shell`,
        );

        await page.getByRole("button", { name: "Sign out" }).click();
        await expect(
            page.getByRole("heading", { name: "Access your account" }),
        ).toBeVisible();
    });
}

test("opens the authorized maintenance hub, confirms its action and reflows on a telephone", async ({
    page,
}, testInfo) => {
    const routine = {
        routineKey: "financial-institution-catalog",
        title: "Instituições financeiras",
        description: "Catálogo oficial do Banco Central.",
        state: "READY",
        scheduleDescription: "Diariamente",
        capabilities: { canSynchronize: true },
        lastExecution: {
            state: "SUCCEEDED",
            triggerType: "SCHEDULED",
            startedAt: "2026-09-26T10:00:00Z",
            completedAt: "2026-09-26T10:01:00Z",
            summary: "Concluída.",
            createdCount: 1,
            updatedCount: 2,
        },
        executionHistory: [],
        administrativeAudits: [],
    };
    await mockAuthenticatedSession(page);
    await page.route(
        "**/api/v1/platform/maintenance/routines**",
        async (route) => {
            const request = route.request();
            if (request.method() === "POST") {
                await route.fulfill({
                    contentType: "application/json",
                    body: JSON.stringify({
                        execution: { summary: "Atualização aceita." },
                    }),
                });
                return;
            }
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify(
                    request.url().endsWith("/routines")
                        ? { routines: [routine] }
                        : { routine },
                ),
            });
        },
    );
    await mockProductionDocument(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/");
    await page.getByRole("button", { name: "Administração" }).click();
    await page.getByRole("button", { name: "Manutenções" }).click();
    await expect(page.locator("#maintenance-title")).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Instituições financeiras" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Atualizar agora" }).click();
    const dialog = page.getByRole("dialog", {
        name: "Atualizar instituições financeiras",
    });
    await expect(dialog).toBeVisible();
    await dialog.getByRole("button", { name: "Confirmar atualização" }).click();
    await expect(page.getByText("Atualização aceita.")).toBeVisible();

    await page.setViewportSize({ width: 375, height: 667 });
    await expect(
        page.getByRole("button", { name: "Voltar às rotinas" }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await captureState(page, testInfo, "maintenance-hub-phone");
    const surfaceContent = page.locator(".workspace-stage__surface-content");
    await surfaceContent.evaluate((element) => {
        element.scrollTop = element.scrollHeight;
    });
    await expect(
        page.getByRole("heading", { name: "Histórico técnico" }),
    ).toBeVisible();
    await captureState(page, testInfo, "maintenance-hub-phone-history");
});

test("rechecks a Drive folder location and preserves the workspace after revocation on desktop and telephone", async ({
    page,
}, testInfo) => {
    let allowed = true;
    const capabilities = { read: true, edit: false, trash: false };
    const sharedFolder = {
        id: 7,
        kind: "folder",
        displayName: "Compartilhada",
        parentFolderId: null,
        logicalSizeBytes: null,
        detectedMimeType: null,
        modifiedAt: null,
        capabilities,
    };
    const rootProjection = {
        location: {
            kind: "root",
            id: null,
            displayName: "Meus arquivos",
            parentFolderId: null,
        },
        breadcrumbs: [],
        folders: [sharedFolder],
        files: [],
        capabilities,
        usage: {
            workspaceBytes: 0,
            systemManagedBytes: 0,
            trashBytes: 0,
            totalBytes: 0,
        },
    };
    await mockAuthenticatedSession(page);
    await page.route("**/api/v1/drive/personal/tree", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ folders: [sharedFolder] }),
        });
    });
    await page.route(
        "**/api/v1/drive/personal/locations/root",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify(rootProjection),
            });
        },
    );
    await page.route("**/api/v1/drive/personal/folders/7", async (route) => {
        if (!allowed) {
            await route.fulfill({
                contentType: "application/json",
                status: 403,
                body: JSON.stringify({ code: "FORBIDDEN" }),
            });
            return;
        }
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                ...rootProjection,
                location: {
                    kind: "folder",
                    id: 7,
                    displayName: "Compartilhada",
                    parentFolderId: null,
                },
                breadcrumbs: [
                    {
                        kind: "folder",
                        id: 7,
                        displayName: "Compartilhada",
                        parentFolderId: null,
                    },
                ],
            }),
        });
    });
    await mockProductionDocument(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/");
    await page.getByRole("button", { name: "Biblioteca" }).click();
    await page.getByRole("button", { name: "Arquivos" }).click();
    await expect(
        page
            .locator(".drive-explorer")
            .getByRole("heading", { name: "Rinos Drive Pessoal" }),
    ).toBeVisible();
    const sharedFolderItem = page
        .locator(".drive-explorer__item")
        .filter({ hasText: "Compartilhada" });
    await sharedFolderItem.dblclick();
    await expect(page.locator(".drive-explorer__collection-name")).toHaveText(
        "Compartilhada",
    );
    await captureState(page, testInfo, "authorized-folder-desktop");

    allowed = false;
    await page
        .getByRole("navigation", { name: "Caminho atual" })
        .getByRole("button", { name: "Meus arquivos", exact: true })
        .click();
    await expect(sharedFolderItem).toBeVisible();
    await sharedFolderItem.dblclick();
    await expect(
        page.getByText("Você não tem mais acesso a este local."),
    ).toBeVisible();
    await expect(page.locator(".drive-explorer__collection-name")).toHaveText(
        "Meus arquivos",
    );

    await page.setViewportSize({ width: 375, height: 667 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await captureState(page, testInfo, "authorized-folder-phone-revoked");
});

test("shares a personal Drive folder through the contextual panel without selecting another workspace", async ({ page }, testInfo) => {
    const capabilities = { read: true, edit: true, trash: true };
    const folder = { id: 7, kind: "folder", displayName: "Projetos", parentFolderId: null, logicalSizeBytes: null, detectedMimeType: null, modifiedAt: null, capabilities };
    const projection = { location: { kind: "root", id: null, displayName: "Meus arquivos", parentFolderId: null }, breadcrumbs: [], folders: [folder], files: [], capabilities, usage: { workspaceBytes: 0, systemManagedBytes: 0, trashBytes: 0, totalBytes: 0 } };
    let shares: unknown[] = [{ id: 12, resourceType: "FOLDER", resourceId: 7, grantee: { subjectId: 8, subjectType: "USER", displayName: "Bruno", accessSources: [], effectiveCapabilities: [], expiresAt: null }, relation: "READ", origin: "INHERITED", inheritedFrom: { resourceType: "FOLDER", resourceId: 2 } }];
    await mockAuthenticatedSession(page);
    await page.route("**/api/v1/drive/personal/tree", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ folders: [folder] }) }));
    await page.route("**/api/v1/drive/personal/locations/root", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify(projection) }));
    await page.route("**/api/v1/drive/personal/items/folder/7/details", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ item: folder, location: projection.location, capabilities, metadata: [] }) }));
    await page.route("**/api/v1/authorization/personal/share-recipients?query=Ana", async (route) => route.fulfill({ contentType: "application/json", body: JSON.stringify({ recipients: [{ subjectId: 3, displayName: "Ana" }] }) }));
    await page.route("**/api/v1/authorization/personal/resources/FOLDER/7/shares", async (route) => {
        if (route.request().method() === "POST") {
            expect(route.request().postDataJSON()).toEqual({ subjectId: 3, relation: "READ", expectedContextVersion: "5" });
            shares = [{ id: 13, resourceType: "FOLDER", resourceId: 7, grantee: { subjectId: 3, subjectType: "USER", displayName: "Ana", accessSources: [], effectiveCapabilities: [], expiresAt: null }, relation: "READ", origin: "DIRECT", inheritedFrom: null }, ...shares];
            await route.fulfill({ contentType: "application/json", status: 201, body: JSON.stringify({ share: shares[0], contextVersion: "6" }) });
            return;
        }
        if (route.request().method() === "DELETE") {
            shares = shares.filter((share) => (share as { id: number }).id !== 13);
            await route.fulfill({ status: 204 });
            return;
        }
        await route.fulfill({ contentType: "application/json", body: JSON.stringify({ workspaceResponsible: { type: "USER", id: 1, displayName: "Pessoa" }, contextVersion: "5", shares }) });
    });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto("/");
    await page.getByRole("button", { name: "Biblioteca" }).click();
    await page.getByRole("button", { name: "Arquivos" }).click();
    await page.locator(".drive-explorer__item").filter({ hasText: "Projetos" }).click();
    await page.getByRole("button", { name: "Detalhes", exact: true }).click();
    await page.getByRole("button", { name: "Compartilhar" }).click();
    const dialog = page.getByRole("dialog", { name: "Projetos" });
    await expect(dialog.getByText("Responsável pelo workspace: Pessoa.")).toBeVisible();
    await expect(dialog.getByText("Acesso herdado da pasta #2")).toBeVisible();
    await dialog.locator("#share-recipient-query").fill("Ana");
    await dialog.getByLabel("Destinatário encontrado").selectOption("3");
    await dialog.getByRole("button", { name: "Confirmar compartilhamento" }).click();
    await expect(dialog.getByText("Ana")).toBeVisible();
    await dialog.getByRole("button", { name: "Revogar" }).click();
    await dialog.getByRole("alertdialog").getByRole("button", { name: "Confirmar revogação" }).click();
    await expect(dialog.getByText("Ana")).not.toBeVisible();
    await captureState(page, testInfo, "resource-sharing-desktop");
    await page.setViewportSize({ width: 768, height: 1024 });
    await captureState(page, testInfo, "resource-sharing-tablet");
    await page.setViewportSize({ width: 375, height: 667 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await captureState(page, testInfo, "resource-sharing-phone");
});

test("publishes an advanced authorization policy from the tenant security surface", async ({
    page,
}, testInfo) => {
    const tenant = {
        id: 18,
        displayName: "Ateliê Norte",
        state: "ACTIVE",
        selectable: true,
        canManageAvailability: false,
        canReadAuthorization: true,
    };
    await mockAuthenticatedSession(page);
    await page.route("**/api/v1/tenants", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ tenants: [tenant] }),
        });
    });
    await page.route("**/api/v1/tenants/18/contexts", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                context: {
                    tenant: { id: tenant.id, displayName: tenant.displayName },
                    membership: { id: 1 },
                    capabilities: {
                        canManageAvailability: false,
                        canReadAuthorization: true,
                    },
                    availableModules: [],
                },
            }),
        });
    });
    await page.route(
        "**/api/v1/tenants/18/authorization/context",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    context: {
                        scope: "TENANT",
                        tenantId: 18,
                        displayName: tenant.displayName,
                        workspaceKind: "TENANT",
                        capabilities: {
                            canReadAccess: true,
                            canManageRoles: true,
                            canManageSharing: true,
                            canUseAdvancedControls: true,
                        },
                    },
                    contextVersion: "1",
                }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/subjects?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    subjects: [],
                    pagination: { page: 1, lastPage: 1 },
                }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/roles?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ roles: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/groups/contextual?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ groups: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/audit-events/contextual?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ events: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/advanced/access-requests",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ accessRequests: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/advanced/policies",
        async (route) => {
            expect(route.request().postDataJSON()).toEqual({
                key: "invoice-limit",
                definition: { all: [{ type: "AMOUNT_MAXIMUM", maximum: 100 }] },
            });
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ policy: { id: 44 } }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/18/authorization/advanced/policies/44/bindings",
        async (route) => {
            expect(route.request().postDataJSON()).toEqual({
                permissionId: 12,
            });
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({}),
            });
        },
    );

    await page.goto("/");
    await page.getByRole("button", { name: "Selecionar organização" }).click();
    await page
        .getByRole("button", { name: tenant.displayName, exact: true })
        .click();
    await page.getByRole("button", { name: "Segurança" }).click();
    await page.getByRole("button", { name: "Usuários e acessos" }).click();
    await page.locator("summary", { hasText: "Controles avançados" }).click();
    await expect(
        page.getByRole("heading", { name: "Controles avançados" }),
    ).toBeVisible();
    await page.getByLabel("Chave da política").fill("invoice-limit");
    await page.getByLabel("ID da permission").first().fill("12");
    await page.getByLabel("Valor máximo").fill("100");
    await page.getByRole("button", { name: "Publicar política" }).click();
    await expect(
        page.getByText("Política publicada e vinculada."),
    ).toBeVisible();
    await page.setViewportSize({ width: 375, height: 667 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await captureState(page, testInfo, "advanced-authorization-phone");
});

test("requests and independently approves temporary access without exposing the approver chain", async ({
    page,
}) => {
    const tenant = {
        id: 19,
        displayName: "Ateliê Sul",
        state: "ACTIVE",
        selectable: true,
        canManageAvailability: false,
        canReadAuthorization: true,
    };
    let activeUser = {
        id: "requester-1",
        displayName: "Solicitante",
        passwordDefined: false,
    };
    let requests: Array<Record<string, unknown>> = [];
    await page.route("**/api/v1/auth/session", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                persistentAuthentication: true,
                user: activeUser,
            }),
        });
    });
    await page.route("**/api/v1/tenants", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({ tenants: [tenant] }),
        });
    });
    await page.route("**/api/v1/tenants/19/contexts", async (route) => {
        await route.fulfill({
            contentType: "application/json",
            body: JSON.stringify({
                context: {
                    tenant: { id: tenant.id, displayName: tenant.displayName },
                    membership: { id: 1 },
                    capabilities: {
                        canManageAvailability: false,
                        canReadAuthorization: true,
                    },
                    availableModules: [],
                },
            }),
        });
    });
    await page.route(
        "**/api/v1/tenants/19/authorization/context",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    context: {
                        scope: "TENANT",
                        tenantId: 19,
                        displayName: tenant.displayName,
                        workspaceKind: "TENANT",
                        capabilities: {
                            canReadAccess: true,
                            canManageRoles: true,
                            canManageSharing: true,
                            canUseAdvancedControls: true,
                        },
                    },
                    contextVersion: "1",
                }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/19/authorization/subjects?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({
                    subjects: [],
                    pagination: { page: 1, lastPage: 1 },
                }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/19/authorization/roles?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ roles: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/19/authorization/groups/contextual?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ groups: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/19/authorization/audit-events/contextual?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ events: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/19/authorization/audit-events?**",
        async (route) => {
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({ events: [] }),
            });
        },
    );
    await page.route(
        "**/api/v1/tenants/19/authorization/advanced/access-requests**",
        async (route) => {
            const url = new URL(route.request().url());
            if (route.request().method() === "GET") {
                await route.fulfill({
                    contentType: "application/json",
                    body: JSON.stringify({ accessRequests: requests }),
                });
                return;
            }
            if (url.pathname.endsWith("/approval")) {
                requests = requests.map((request) => ({
                    ...request,
                    state: "APPROVED",
                }));
                await route.fulfill({
                    contentType: "application/json",
                    body: JSON.stringify({}),
                });
                return;
            }
            if (url.pathname.endsWith("/revocation")) {
                requests = requests.map((request) => ({
                    ...request,
                    state: "REVOKED",
                }));
                await route.fulfill({
                    contentType: "application/json",
                    body: JSON.stringify({}),
                });
                return;
            }
            expect(route.request().postDataJSON()).toMatchObject({
                permissionId: 12,
                startsAt: expect.stringMatching(/Z$/),
                endsAt: expect.stringMatching(/Z$/),
            });
            requests = [
                {
                    id: 90,
                    permissionId: 12,
                    state: "PENDING",
                    endsAt: "2026-09-28T11:00:00Z",
                },
            ];
            await route.fulfill({
                contentType: "application/json",
                body: JSON.stringify({}),
            });
        },
    );

    await page.goto("/");
    await page.getByRole("button", { name: "Selecionar organização" }).click();
    await page
        .getByRole("button", { name: tenant.displayName, exact: true })
        .click();
    await page.getByRole("button", { name: "Segurança" }).click();
    await page.getByRole("button", { name: "Usuários e acessos" }).click();
    await page.locator("summary", { hasText: "Controles avançados" }).click();
    await page.locator("#authorization-temporary-permission").fill("12");
    await page
        .locator("#authorization-temporary-starts-at")
        .fill("2026-09-28T10:00");
    await page
        .locator("#authorization-temporary-ends-at")
        .fill("2026-09-28T11:00");
    await page.getByRole("button", { name: "Solicitar acesso" }).click();
    await expect(
        page.getByText("Solicitação de acesso enviada."),
    ).toBeVisible();

    activeUser = {
        id: "approver-2",
        displayName: "Aprovador",
        passwordDefined: false,
    };
    await page.reload();
    await page.getByRole("button", { name: "Selecionar organização" }).click();
    await page
        .getByRole("button", { name: tenant.displayName, exact: true })
        .click();
    await page.getByRole("button", { name: "Segurança" }).click();
    await page.getByRole("button", { name: "Usuários e acessos" }).click();
    await page.locator("summary", { hasText: "Controles avançados" }).click();
    await expect(page.getByText("PENDING")).toBeVisible();
    await expect(page.getByText("Solicitante")).not.toBeVisible();
    await page.getByRole("button", { name: "Aprovar" }).click();
    await expect(page.getByText("Solicitação aprovada.")).toBeVisible();
    await expect(page.getByText("APPROVED")).toBeVisible();
    await page.getByRole("button", { name: "Revogar" }).click();
    await expect(page.getByText("Solicitação revogada.")).toBeVisible();
    await expect(page.getByText("REVOKED")).toBeVisible();
});

test("honours reduced motion and ships the web installation manifest", async ({
    page,
}) => {
    await mockUnauthenticatedSession(page);
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/");
    await expect(
        page.getByRole("heading", { name: "Acesse sua conta" }),
    ).toBeVisible();
    expect(
        await page
            .getByRole("button", { name: "Entrar sem senha" })
            .evaluate(
                (element) => getComputedStyle(element).transitionDuration,
            ),
    ).toBe("0s");

    const manifest = await page.request.get("/manifest.webmanifest");
    expect(manifest.ok()).toBe(true);
    await expect(manifest.json()).resolves.toMatchObject({
        name: "Rinos One",
        start_url: "/",
        display: "standalone",
    });
});

test("keeps People contrast tokens accessible in light and dark themes", async ({
    page,
}) => {
    await mockPeopleWorkspace(page);

    for (const colorScheme of ["light", "dark"] as const) {
        await page.emulateMedia({ colorScheme });
        await page.goto("/");
        await openPeopleWorkspace(page);

        const contrasts = await page.evaluate(() => {
            const resolvedColor = (token: string): number[] => {
                const element = document.createElement("span");
                element.style.color = `var(${token})`;
                document.body.append(element);
                const [red, green, blue] = getComputedStyle(element)
                    .color.match(/\d+/g)!
                    .slice(0, 3)
                    .map(Number);
                element.remove();
                return [red!, green!, blue!];
            };
            const luminance = ([red, green, blue]: number[]): number => {
                const channel = (value: number): number => {
                    const normalized = value / 255;
                    return normalized <= 0.03928
                        ? normalized / 12.92
                        : ((normalized + 0.055) / 1.055) ** 2.4;
                };
                return (
                    0.2126 * channel(red!) +
                    0.7152 * channel(green!) +
                    0.0722 * channel(blue!)
                );
            };
            const ratio = (foreground: string, background: string): number => {
                const first = luminance(resolvedColor(foreground));
                const second = luminance(resolvedColor(background));
                return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05);
            };

            return {
                text: ratio("--color-text-primary", "--color-surface"),
                action: ratio(
                    "--color-action-primary-content",
                    "--color-action-primary",
                ),
                danger: ratio("--color-danger", "--color-surface"),
            };
        });

        expect(contrasts.text).toBeGreaterThanOrEqual(4.5);
        expect(contrasts.action).toBeGreaterThanOrEqual(4.5);
        expect(contrasts.danger).toBeGreaterThanOrEqual(4.5);
    }
});

test("opens People through the tenant workspace and creates a document-optional person", async ({
    page,
}) => {
    await mockPeopleWorkspace(page);
    await page.goto("/");
    await openPeopleWorkspace(page);

    await expect(page.locator(".people-catalog")).toContainText(
        "Pessoa existente",
    );
    await page.getByRole("button", { name: "Nova Pessoa" }).click();
    await page
        .getByRole("textbox", { name: "Nome", exact: true })
        .fill("Pessoa criada");
    await page.getByRole("button", { name: "Salvar", exact: true }).click();

    await expect(
        page.getByText("Pessoa salva com sucesso.", { exact: true }),
    ).toBeVisible();
    await expect(page.locator(".people-catalog")).toBeVisible();
});

test("keeps People catalogue actions usable in the phone presentation", async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await mockPeopleWorkspace(page);
    await page.goto("/");
    await openPeopleWorkspace(page);

    await expect(page.locator(".people-catalog__cards")).toContainText(
        "Pessoa existente",
    );
    await page.locator(".people-catalog__cards summary").click();
    await page
        .getByRole("button", { name: "Abrir", exact: true })
        .last()
        .click();
    await expect(
        page.getByRole("region", { name: "Editar Pessoa" }),
    ).toBeVisible();
});

const peopleViewports = [
    { name: "desktop", width: 1440, height: 900 },
    { name: "tablet", width: 900, height: 1024 },
    { name: "phone", width: 390, height: 844 },
] as const;

for (const viewport of peopleViewports) {
    test(`validates the People catalogue flow on ${viewport.name}`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);

        await page
            .getByRole("searchbox", { name: "Buscar Pessoas" })
            .fill("existente");
        await expect(page.locator(".people-catalog")).toContainText(
            "Pessoa existente",
        );
    });

    test(`validates the People form and related-data flow on ${viewport.name}`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);
        await page.getByRole("button", { name: "Nova Pessoa" }).click();

        const form = page.getByRole("region", { name: "Nova Pessoa" });
        await expect(form).toBeVisible();
        await form
            .getByRole("textbox", { name: "Nome", exact: true })
            .fill("Pessoa com contato");
        const contacts = form.locator(".collection").nth(2);
        if (viewport.width < 700) {
            await contacts
                .getByRole("button", { name: "Abrir", exact: true })
                .click();
            await expect(contacts).toHaveAttribute("role", "dialog");
        }
        await contacts
            .getByRole("button", { name: "Adicionar", exact: true })
            .click();
        await expect(
            contacts.getByRole("combobox", { name: "Tipo de contato" }),
        ).toBeVisible();
    });

    test(`validates quick creation on ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);
        await page.getByRole("button", { name: "Cadastro rápido" }).click();

        const dialog = page.getByRole("dialog", {
            name: "Cadastrar Pessoa rapidamente",
        });
        await dialog
            .getByRole("textbox", { name: "Nome", exact: true })
            .fill("Pessoa rápida");
        await dialog.getByRole("button", { name: "Criar Pessoa" }).click();
        await expect(
            page.getByText("Pessoa salva com sucesso.", { exact: true }),
        ).toBeVisible();
    });

    test(`validates duplication on ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);
        await openCatalogAction(page, "Duplicar");

        const dialog = page.getByRole("dialog", { name: "Duplicar Pessoa" });
        await expect(dialog).toBeVisible();
        await dialog.getByRole("checkbox", { name: "Endereços" }).check();
        await dialog.getByRole("button", { name: "Duplicar Pessoa" }).click();
        await expect(
            page.getByText("A cópia foi criada e está aberta para revisão.", {
                exact: true,
            }),
        ).toBeVisible();
    });

    test(`validates lifecycle confirmation on ${viewport.name}`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);
        await openCatalogAction(page, "Inativar");

        const dialog = page.getByRole("dialog", { name: "Inativar Pessoa" });
        await expect(dialog).toBeVisible();
        await dialog.getByRole("button", { name: "Confirmar" }).click();
        await expect(
            page.getByText("A situação da Pessoa foi atualizada.", {
                exact: true,
            }),
        ).toBeVisible();
    });

    test(`validates opening an existing person on ${viewport.name}`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);
        await openExistingPerson(page);

        await expect(
            page.getByRole("region", { name: "Editar Pessoa" }),
        ).toBeVisible();
    });

    test(`does not expose People mutations to a read-only user on ${viewport.name}`, async ({
        page,
    }) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page, { readOnly: true });
        await page.goto("/");
        await openPeopleWorkspace(page);

        await expect(
            page.getByRole("button", { name: "Nova Pessoa" }),
        ).toHaveCount(0);
        await expect(
            page.getByRole("button", { name: "Cadastro rápido" }),
        ).toHaveCount(0);
        const actions = page.locator(
            viewport.width < 700
                ? ".people-catalog__cards .people-catalog__actions"
                : ".people-catalog__table .people-catalog__actions",
        );
        await actions.locator("summary").click();
        await expect(
            actions.getByRole("button", { name: "Duplicar", exact: true }),
        ).toHaveCount(0);
        await expect(
            actions.getByRole("button", { name: "Inativar", exact: true }),
        ).toHaveCount(0);
        await expect(
            actions.getByRole("button", { name: "Excluir", exact: true }),
        ).toHaveCount(0);
    });
}

for (const viewport of peopleViewports) {
    test(`captures People visual review on ${viewport.name}`, async ({
        page,
    }, testInfo) => {
        await page.setViewportSize(viewport);
        await mockPeopleWorkspace(page);
        await page.goto("/");
        await openPeopleWorkspace(page);
        await expect(page.locator(".people-catalog")).toBeVisible();
        await expect(page.locator(".people-catalog__count")).toBeVisible();
        await expect(
            page.locator(
                viewport.width < 700
                    ? ".people-catalog__cards article"
                    : ".people-catalog__table tbody tr",
            ),
        ).toHaveCount(1);
        await captureState(page, testInfo, `${viewport.name}-people-catalog`);

        await page.getByRole("button", { name: "Filtros" }).click();
        const filters =
            viewport.width < 700
                ? page.getByRole("dialog", { name: "Filtros" })
                : page.locator(".people-catalog__filter-panel");
        await expect(filters).toBeVisible();
        await captureState(page, testInfo, `${viewport.name}-people-filters`);
        await filters.getByRole("button", { name: "Fechar filtros" }).click();
        await expect(filters).toBeHidden();

        await page.getByRole("button", { name: "Nova Pessoa" }).click();
        const form = page.getByRole("region", { name: "Nova Pessoa" });
        await expect(form).toBeVisible();
        await expect(
            form.getByRole("textbox", { name: "Nome", exact: true }),
        ).toBeVisible();
        await captureState(page, testInfo, `${viewport.name}-person-form`);
    });
}
