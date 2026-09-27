import { expect, test, type Page, type Route, type TestInfo } from '@playwright/test';

const user = { id: 'user-1', displayName: 'Pessoa', passwordDefined: false };

async function mockUnauthenticatedSession(page: Page): Promise<void> {
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill({ contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) });
    });
}

async function mockAuthenticatedSession(page: Page, persistentAuthentication = true): Promise<void> {
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ persistentAuthentication, user }) });
    });
}

async function captureState(page: Page, testInfo: TestInfo, name: string): Promise<void> {
    await page.screenshot({ path: testInfo.outputPath(`${name}.png`), fullPage: true });
}

/**
 * Allows browser-level UI tests to keep exercising the production bundle when
 * a local PHP server cannot bootstrap its dynamic document.
 */
async function mockProductionDocument(page: Page): Promise<void> {
    const manifestResponse = await page.request.get('/build/manifest.json');
    const manifest = await manifestResponse.json() as Record<string, { file: string; css?: string[] }>;
    const entry = manifest['resources/js/app.ts'];
    if (!entry) throw new Error('Production entry was not found in the Vite manifest.');

    const styleFiles = [...new Set(Object.values(manifest).flatMap((asset) => [asset.file.endsWith('.css') ? asset.file : null, ...(asset.css ?? [])]).filter((file): file is string => file !== null))];
    const styles = styleFiles.map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('');
    const fulfillDocument = (route: Route) => route.fulfill({
        contentType: 'text/html',
        body: `<!doctype html><html lang="pt-BR"><head><meta name="viewport" content="width=device-width, initial-scale=1">${styles}</head><body><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`,
    });
    await page.route('**/', fulfillDocument);
    await page.route('**/access/**', fulfillDocument);
}

test.beforeEach(async ({ page }) => {
    await mockProductionDocument(page);
});

test('starts passwordless access and completes its documented API contract', async ({ page }) => {
    let authenticated = false;
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill(authenticated
            ? { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user }) }
            : { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) });
    });
    await page.route('**/api/v1/auth/passwordless-sessions', async (route) => {
        expect(route.request().method()).toBe('POST');
        expect(route.request().postDataJSON()).toEqual({ email: 'person@example.test', rememberMe: true });
        await route.fulfill({ contentType: 'application/json', status: 202, body: JSON.stringify({ message: 'accepted', challengeId: 'challenge-passwordless', resendAvailableInSeconds: 180 }) });
    });
    await page.route('**/api/v1/auth/passwordless-sessions/confirmations', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ challengeId: 'challenge-passwordless', code: '123456' });
        authenticated = true;
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user }) });
    });

    await page.goto('/');
    await page.getByLabel('E-mail').fill('person@example.test');
    await page.getByLabel('Manter-me conectado').check();
    await expect(page.getByRole('button', { name: 'Entrar sem senha' })).toBeVisible();
    await page.getByRole('button', { name: 'Entrar sem senha' }).click();

    await expect(page.getByRole('heading', { name: 'Confirme seu e-mail' })).toBeVisible();
    await expect(page.getByText('Se existir uma conta para person@example.test, enviaremos um e-mail de acesso sem senha.')).toBeVisible();
    await expect(page.getByRole('button', { name: /Reenviar em 3:00/ })).toBeDisabled();
    await page.getByLabel('Código de confirmação').fill('123456');
    await page.getByRole('button', { name: 'Concluir acesso' }).click();
    await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
});

test('starts password access with its documented API contract', async ({ page }) => {
    let authenticated = false;
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill(authenticated
            ? { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: false, user }) }
            : { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) });
    });
    await page.route('**/api/v1/auth/password-sessions', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ email: 'person@example.test', password: 'Secret#1', rememberMe: false });
        authenticated = true;
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user }) });
    });

    await page.goto('/');
    await page.getByLabel('E-mail').fill('person@example.test');
    await page.getByLabel('Senha').fill('Secret#1');
    await expect(page.getByRole('button', { name: 'Entrar', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
});

test('creates an account, confirms the e-mail code and enters the authenticated area', async ({ page }) => {
    let authenticated = false;
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill(authenticated
            ? { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: false, user }) }
            : { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) });
    });
    await page.route('**/api/v1/auth/registrations', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ email: 'person@example.test', displayName: 'Pessoa', rememberMe: false });
        await route.fulfill({ contentType: 'application/json', status: 202, body: JSON.stringify({ message: 'accepted', challengeId: 'challenge-registration', resendAvailableInSeconds: 180 }) });
    });
    await page.route('**/api/v1/auth/email-verifications', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ challengeId: 'challenge-registration', code: '654321' });
        authenticated = true;
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user }) });
    });

    await page.goto('/');
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await expect(page).toHaveURL(/\/access\/register$/);
    await page.getByLabel('Nome').fill('Pessoa');
    await page.getByLabel('E-mail').fill('person@example.test');
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await page.getByLabel('Código de confirmação').fill('654321');
    await page.getByRole('button', { name: 'Concluir acesso' }).click();
    await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
});

test('continues passwordless access from an e-mail link in a new tab', async ({ page }) => {
    let authenticated = false;
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill(authenticated
            ? { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user }) }
            : { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) });
    });
    await page.route('**/api/v1/auth/passwordless-sessions/link-confirmations', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ challengeId: 3, token: 'secret-token' });
        authenticated = true;
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user }) });
    });

    await page.goto('/access/passwordless?challengeId=3&token=secret-token');
    await expect(page).toHaveURL(/\/access\/passwordless$/);
    await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
});

test('confirms a registration link directly without requesting name or code again', async ({ page }) => {
    let authenticated = false;
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill(authenticated
            ? { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: false, user }) }
            : { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) });
    });
    await page.route('**/api/v1/auth/email-verifications/link-confirmations', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ challengeId: 4, token: 'secret-token' });
        authenticated = true;
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user }) });
    });

    await page.goto('/access/email-verification?challengeId=4&token=secret-token');
    await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
    await expect(page.getByLabel('Código de confirmação')).toHaveCount(0);
    await expect(page.getByLabel('Nome')).toHaveCount(0);
});

test('preserves safe form state, route and session while changing presentation and language', async ({ page }) => {
    await mockUnauthenticatedSession(page);
    await page.goto('/');
    await page.getByRole('button', { name: 'Criar conta' }).click();
    await page.getByLabel('Nome').fill('Pessoa');
    await page.getByLabel('E-mail').fill('person@example.test');

    await page.getByRole('button', { name: 'Preferências visuais' }).click();
    await page.locator('#theme-choice-dark').click();
    await page.locator('#font-scale-choice-comfortable').click();
    await page.locator('#spacing-scale-choice-compact').click();
    await page.locator('#component-scale-choice-comfortable').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('html')).toHaveAttribute('data-font-scale', 'comfortable');
    await expect(page.locator('html')).toHaveAttribute('data-spacing-scale', 'compact');
    await expect(page.locator('html')).toHaveAttribute('data-component-scale', 'comfortable');

    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Idioma atual: Português (Brasil)' }).click();
    await page.getByRole('option', { name: 'English' }).click();
    await expect(page.getByRole('heading', { name: 'Create account' })).toBeVisible();
    await expect(page).toHaveURL(/\/access\/register$/);
    await expect(page.getByLabel('Name')).toHaveValue('Pessoa');
    await expect(page.getByLabel('Email')).toHaveValue('person@example.test');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect.poll(async () => page.evaluate(() => localStorage.getItem('rinos-one.visual-preferences.v1'))).not.toContain('person@example.test');
});

test('keeps the authenticated shell controls keyboard accessible', async ({ page }) => {
    await mockAuthenticatedSession(page);
    await page.goto('/');

    await page.getByRole('button', { name: 'Menu pessoal de Pessoa' }).click();
    const preferences = page.getByRole('dialog', { name: 'Menu pessoal' }).getByRole('button', { name: 'Preferências visuais' });
    await preferences.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog', { name: 'Preferências visuais' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(preferences).toBeFocused();
});

test('keeps tenant context isolated by tab and clears it after a page reload', async ({ page, context }) => {
    const tenants = [
        { id: 1, displayName: 'Ateliê Norte', state: 'ACTIVE', selectable: true, canManageAvailability: true },
        { id: 2, displayName: 'Ateliê Sul', state: 'ACTIVE', selectable: true, canManageAvailability: true },
    ];

    await context.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user }) });
    });
    await context.route('**/api/v1/tenants', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ tenants }) });
    });
    await context.route('**/api/v1/tenants/*/contexts', async (route) => {
        if (route.request().method() === 'DELETE') { await route.fulfill({ status: 204 }); return; }
        const tenantId = Number(new URL(route.request().url()).pathname.match(/\/tenants\/(\d+)\/contexts$/)?.[1]);
        const tenant = tenants.find((candidate) => candidate.id === tenantId);
        await route.fulfill(tenant
            ? { contentType: 'application/json', body: JSON.stringify({ context: { tenant: { id: tenant.id, displayName: tenant.displayName }, membership: { id: tenant.id }, capabilities: { canManageAvailability: tenant.canManageAvailability }, availableModules: [] } }) }
            : { contentType: 'application/json', status: 404, body: JSON.stringify({ error: { code: 'TENANT_NOT_AVAILABLE', message: 'Unavailable' } }) });
    });

    const secondTab = await context.newPage();
    await mockProductionDocument(secondTab);
    await page.goto('/');
    await secondTab.goto('/');

    await page.getByRole('button', { name: 'Selecionar organização' }).click();
    await page.getByRole('button', { name: 'Ateliê Norte', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Organização atual: Ateliê Norte' })).toBeVisible();

    await secondTab.getByRole('button', { name: 'Selecionar organização' }).click();
    await secondTab.getByRole('button', { name: 'Ateliê Sul', exact: true }).click();
    await expect(secondTab.getByRole('button', { name: 'Organização atual: Ateliê Sul' })).toBeVisible();

    await page.getByRole('button', { name: 'Organização atual: Ateliê Norte' }).click();
    await page.getByRole('button', { name: 'Usar somente meu espaço' }).click();
    await expect(page.getByRole('button', { name: 'Selecionar organização' })).toBeVisible();
    await expect(secondTab.getByRole('button', { name: 'Organização atual: Ateliê Sul' })).toBeVisible();

    await secondTab.reload();
    await expect(secondTab.getByRole('button', { name: 'Selecionar organização' })).toBeVisible();
    await secondTab.close();
});

test('revalidates a revoked tenant capability before the next browser operation', async ({ page }) => {
    let grantActive = true;
    const tenant = { id: 1, displayName: 'Ateliê Norte', state: 'ACTIVE', selectable: true };

    await mockAuthenticatedSession(page);
    await page.route('**/api/v1/tenants', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ tenants: [{ ...tenant, canManageAvailability: grantActive }] }) });
    });
    await page.route('**/api/v1/tenants/1/contexts', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ context: { tenant: { id: tenant.id, displayName: tenant.displayName }, membership: { id: 1 }, capabilities: { canManageAvailability: grantActive }, availableModules: [] } }) });
    });
    await page.route('**/api/v1/tenants/1/availability', async (route) => {
        expect(route.request().method()).toBe('POST');
        expect(route.request().postDataJSON()).toEqual({ state: 'INACTIVE' });
        await route.fulfill(grantActive
            ? { contentType: 'application/json', body: JSON.stringify({ tenant: { ...tenant, state: 'INACTIVE', selectable: false, canManageAvailability: true } }) }
            : { contentType: 'application/json', status: 403, body: JSON.stringify({ error: { code: 'TENANT_ADMINISTRATOR_REQUIRED', message: 'Unavailable' } }) });
    });

    await page.goto('/');
    await page.getByRole('button', { name: 'Selecionar organização' }).click();
    await page.getByRole('button', { name: tenant.displayName, exact: true }).click();
    await expect(page.getByRole('button', { name: 'Organização atual: Ateliê Norte' })).toBeVisible();

    grantActive = false;
    const result = await page.evaluate(async () => {
        const response = await fetch('/api/v1/tenants/1/availability', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ state: 'INACTIVE' }),
        });

        return { status: response.status, body: await response.json() };
    });

    expect(result).toEqual({ status: 403, body: { error: { code: 'TENANT_ADMINISTRATOR_REQUIRED', message: 'Unavailable' } } });
});

test('opens user settings as a single personal workspace surface', async ({ page }) => {
    await mockAuthenticatedSession(page);
    let otherSessionsRevoked = 0;
    let savedPassword = '';
    await page.route('**/api/v1/auth/other-sessions', async (route) => {
        expect(route.request().method()).toBe('DELETE');
        otherSessionsRevoked += 1;
        await route.fulfill({ status: 204 });
    });
    await page.route('**/api/v1/auth/password', async (route) => {
        if (route.request().method() === 'DELETE') {
            await route.fulfill({ status: 204 });
            return;
        }
        expect(route.request().method()).toBe('PUT');
        savedPassword = route.request().postDataJSON().password;
        await route.fulfill({ status: 204 });
    });
    await page.goto('/');

    await page.getByRole('button', { name: 'Menu pessoal de Pessoa' }).click();
    await page.getByRole('button', { name: 'Configurações do usuário', exact: true }).click();
    await expect(page.getByRole('tab', { name: 'Configurações do usuário' })).toBeVisible();
    await page.getByRole('button', { name: 'Tema e aparência', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Tema e aparência' })).toBeVisible();
    await page.getByRole('button', { name: 'Ametista Técnica' }).click();
    await page.getByRole('button', { name: 'Claro' }).click();
    await page.locator('.workspace-settings__panel').nth(2).getByRole('button', { name: 'Confortável' }).click();
    await page.locator('.workspace-settings__panel').nth(3).getByRole('button', { name: 'Compacta' }).click();
    await page.locator('.workspace-settings__panel').nth(4).getByRole('button', { name: 'Compacta' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-palette', 'amethyst-technical');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await expect(page.locator('html')).toHaveAttribute('data-font-scale', 'comfortable');
    await expect(page.locator('html')).toHaveAttribute('data-spacing-scale', 'compact');
    await expect(page.locator('html')).toHaveAttribute('data-component-scale', 'compact');
    expect(await page.evaluate(() => JSON.parse(localStorage.getItem('rinos-one.visual-preferences.v1') ?? '{}'))).toMatchObject({ palette: 'amethyst-technical', theme: 'light', fontScale: 'comfortable', spacingScale: 'compact', componentScale: 'compact' });
    await page.getByRole('button', { name: 'Escuro' }).click();
    const darkSurface = await page.locator('html').evaluate((element) => getComputedStyle(element).getPropertyValue('--color-surface'));
    await page.getByRole('button', { name: 'Esmeralda Sóbria' }).click();
    await expect.poll(() => page.locator('html').evaluate((element) => getComputedStyle(element).getPropertyValue('--color-surface'))).toBe(darkSurface);
    await page.getByRole('button', { name: 'Sessões' }).click();
    await expect(page.getByRole('heading', { name: 'Sessões ativas' })).toBeVisible();
    await page.getByRole('button', { name: 'Encerrar demais sessões' }).click();
    await page.getByRole('dialog', { name: 'Encerrar demais sessões' }).getByRole('button', { name: 'Encerrar sessões' }).click();
    await expect.poll(() => otherSessionsRevoked).toBe(1);
    await page.getByRole('button', { name: 'Segurança' }).click();
    await expect(page.getByRole('heading', { name: 'Segurança da conta' })).toBeVisible();
    await page.getByRole('button', { name: 'Definir senha' }).click();
    const passwordDialog = page.getByRole('dialog', { name: 'Definir senha' });
    await passwordDialog.getByLabel('Nova senha', { exact: true }).fill('Nova#Senha1');
    await passwordDialog.getByLabel('Confirme a nova senha').fill('Nova#Senha1');
    await passwordDialog.getByRole('button', { name: 'Salvar senha' }).click();
    await expect.poll(() => savedPassword).toBe('Nova#Senha1');
    await expect(page.getByRole('button', { name: 'Alterar senha' })).toBeVisible();
    await page.getByRole('button', { name: 'Remover senha' }).click();
    const removePasswordDialog = page.getByRole('dialog', { name: 'Remover senha' });
    await expect(removePasswordDialog.getByText('Sem senha, o acesso à sua conta deverá ser feito por outro método de autenticação válido')).toBeVisible();
    await removePasswordDialog.getByRole('button', { name: 'Remover senha' }).click();
    await expect(page.getByRole('button', { name: 'Definir senha' })).toBeVisible();

    await page.getByRole('button', { name: 'Menu pessoal de Pessoa' }).click();
    await page.getByRole('button', { name: 'Configurações do usuário', exact: true }).click();
    await expect(page.getByRole('tab', { name: 'Configurações do usuário' })).toHaveCount(1);
});

for (const viewport of [
    { name: 'telefone', width: 375, height: 667 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1440, height: 900 },
]) {
    test(`renders the access journey without horizontal overflow on ${viewport.name} in extreme presentations`, async ({ page }, testInfo) => {
        await mockUnauthenticatedSession(page);
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto('/');
        if (viewport.width < 640) {
            const accessFrame = page.locator('.access-frame');
            const viewportHeight = await page.evaluate(() => window.innerHeight);
            const bounds = await accessFrame.boundingBox();
            expect(bounds!.y + (bounds!.height / 2)).toBeGreaterThan(viewportHeight * 0.4);
            expect(bounds!.y + (bounds!.height / 2)).toBeLessThan(viewportHeight * 0.6);
        }
        await page.getByRole('button', { name: 'Preferências visuais' }).click();
        await page.locator('#theme-choice-dark').click();
        await page.locator('#font-scale-choice-comfortable').click();
        await page.locator('#spacing-scale-choice-comfortable').click();
        await page.locator('#component-scale-choice-comfortable').click();
        await captureState(page, testInfo, `${viewport.name}-dark-comfortable`);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(await page.getByRole('button', { name: 'Entrar sem senha' }).evaluate((element) => Number.parseFloat(getComputedStyle(element).minHeight))).toBeGreaterThanOrEqual(44);

        await page.locator('#theme-choice-light').click();
        await page.locator('#font-scale-choice-compact').press('Enter');
        await page.locator('#spacing-scale-choice-compact').press('Enter');
        await page.locator('#component-scale-choice-compact').press('Enter');
        await captureState(page, testInfo, `${viewport.name}-light-compact`);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });

    test(`keeps the authenticated shell usable on ${viewport.name}`, async ({ page }, testInfo) => {
        let signedOut = false;
        await page.route('**/api/v1/auth/session', async (route) => {
            if (route.request().method() === 'DELETE') {
                signedOut = true;
                await route.fulfill({ status: 204 });
                return;
            }
            await route.fulfill(signedOut
                ? { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) }
                : { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user }) });
        });
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto('/');

        await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Menu pessoal de Pessoa' })).toBeVisible();
        expect(await page.evaluate(() => document.scrollingElement!.scrollHeight <= window.innerHeight)).toBe(true);

        if (viewport.width < 640) {
            const navigationOpener = page.getByRole('button', { name: 'Abrir navegação' });
            await expect(navigationOpener).toBeVisible();
            await navigationOpener.focus();
            await page.keyboard.press('Enter');
            const drawer = page.getByRole('dialog', { name: 'Navegação' });
            await expect(drawer).toBeVisible();
            await expect(drawer.getByRole('link')).toHaveCount(0);
            await page.keyboard.press('Escape');
            await expect(navigationOpener).toBeFocused();
        } else {
            await expect(page.locator('.application-top-bar__desktop-brand')).toBeVisible();
            await expect(page.getByRole('button', { name: 'Abrir navegação' })).toBeHidden();

            const stage = page.locator('.workspace-stage--empty');
            const stageBefore = await stage.boundingBox();
            await page.locator('.workspace-navigation-rail__category').first().click();
            await expect(page.locator('#workspace-mega-menu')).toBeVisible();
            const stageAfter = await stage.boundingBox();
            const megaMenu = await page.locator('#workspace-mega-menu').boundingBox();
            expect(stageAfter).toEqual(stageBefore);
            expect(megaMenu?.x).toBe(stageBefore?.x);
            expect(megaMenu?.width).toBe(stageBefore?.width);
            await page.keyboard.press('Escape');
        }

        const personalMenuOpener = page.getByRole('button', { name: 'Menu pessoal de Pessoa' });
        await personalMenuOpener.focus();
        await page.keyboard.press('Enter');
        const personalMenu = page.getByRole('dialog', { name: 'Menu pessoal' });
        await expect(personalMenu).toBeVisible();
        await expect(personalMenu.getByRole('button', { name: 'Configurações do usuário' })).toBeEnabled();

        await personalMenu.getByRole('button', { name: 'Preferências visuais' }).click();
        await page.locator('#theme-choice-dark').click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await page.keyboard.press('Escape');
        await personalMenu.getByRole('button', { name: 'Idioma atual: Português (Brasil)' }).click();
        await personalMenu.getByRole('option', { name: 'English' }).click();
        await expect(page.getByRole('main', { name: 'Workspace', exact: true })).toBeVisible();
        await captureState(page, testInfo, `${viewport.name}-authenticated-shell`);

        await page.getByRole('button', { name: 'Sign out' }).click();
        await expect(page.getByRole('heading', { name: 'Access your account' })).toBeVisible();
    });
}

test('opens the authorized maintenance hub, confirms its action and reflows on a telephone', async ({ page }, testInfo) => {
    const routine = {
        routineKey: 'financial-institution-catalog', title: 'Instituições financeiras', description: 'Catálogo oficial do Banco Central.', state: 'READY', scheduleDescription: 'Diariamente', capabilities: { canSynchronize: true },
        lastExecution: { state: 'SUCCEEDED', triggerType: 'SCHEDULED', startedAt: '2026-09-26T10:00:00Z', completedAt: '2026-09-26T10:01:00Z', summary: 'Concluída.', createdCount: 1, updatedCount: 2 }, executionHistory: [], administrativeAudits: [],
    };
    await mockAuthenticatedSession(page);
    await page.route('**/api/v1/platform/maintenance/routines**', async (route) => {
        const request = route.request();
        if (request.method() === 'POST') {
            await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ execution: { summary: 'Atualização aceita.' } }) });
            return;
        }
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify(request.url().endsWith('/routines') ? { routines: [routine] } : { routine }) });
    });
    await mockProductionDocument(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.locator('.workspace-navigation-rail__category').first().click();
    await page.getByRole('button', { name: 'Manutenções' }).click();
    await expect(page.locator('#maintenance-title')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Instituições financeiras' })).toBeVisible();
    await page.getByRole('button', { name: 'Atualizar agora' }).click();
    const dialog = page.getByRole('dialog', { name: 'Atualizar instituições financeiras' });
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Confirmar atualização' }).click();
    await expect(page.getByText('Atualização aceita.')).toBeVisible();

    await page.setViewportSize({ width: 375, height: 667 });
    await expect(page.getByRole('button', { name: 'Voltar às rotinas' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await captureState(page, testInfo, 'maintenance-hub-phone');
    const surfaceContent = page.locator('.workspace-stage__surface-content');
    await surfaceContent.evaluate((element) => { element.scrollTop = element.scrollHeight; });
    await expect(page.getByRole('heading', { name: 'Histórico técnico' })).toBeVisible();
    await captureState(page, testInfo, 'maintenance-hub-phone-history');
});

test('rechecks an authorized folder action and removes it after revocation on desktop and telephone', async ({ page }, testInfo) => {
    let allowed = true;
    await mockAuthenticatedSession(page);
    await page.route('**/api/v1/authorization/personal-workspace/folders', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ folders: [{ id: 7, parentFolderId: null, displayName: 'Compartilhada' }] }) });
    });
    await page.route('**/api/v1/authorization/resource-checks', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ checks: [{ permissionKey: 'personal.folder.read', resource: { type: 'personal.folder', id: 7 } }] });
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ decisions: [{ allowed }] }) });
    });
    await mockProductionDocument(page);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/');
    await page.getByRole('button', { name: 'Documentos' }).click();
    await page.getByRole('button', { name: 'Arquivos e anexos' }).click();
    await expect(page.locator('#workspace-folders-title')).toBeVisible();
    await page.getByRole('button', { name: 'Compartilhada' }).click();
    await expect(page.getByText('Pasta aberta:')).toContainText('Compartilhada');
    await captureState(page, testInfo, 'authorized-folder-desktop');

    allowed = false;
    await page.getByRole('button', { name: 'Compartilhada' }).click();
    await expect(page.getByText('Não há pastas acessíveis.')).toBeVisible();

    await page.setViewportSize({ width: 375, height: 667 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    await captureState(page, testInfo, 'authorized-folder-phone-revoked');
});

test('honours reduced motion and ships the web installation manifest', async ({ page }) => {
    await mockUnauthenticatedSession(page);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'Acesse sua conta' })).toBeVisible();
    expect(await page.getByRole('button', { name: 'Entrar sem senha' }).evaluate((element) => getComputedStyle(element).transitionDuration)).toBe('0s');

    const manifest = await page.request.get('/manifest.webmanifest');
    expect(manifest.ok()).toBe(true);
    await expect(manifest.json()).resolves.toMatchObject({ name: 'Rinos One', start_url: '/', display: 'standalone' });
});
