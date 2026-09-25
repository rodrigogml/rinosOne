import { expect, test, type Page, type TestInfo } from '@playwright/test';

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
        await route.fulfill({ contentType: 'application/json', status: 202, body: JSON.stringify({ message: 'accepted', challengeId: 'challenge-passwordless' }) });
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
        expect(route.request().postDataJSON()).toEqual({ email: 'person@example.test', rememberMe: false });
        await route.fulfill({ contentType: 'application/json', status: 202, body: JSON.stringify({ message: 'accepted', challengeId: 'challenge-registration' }) });
    });
    await page.route('**/api/v1/auth/email-verifications', async (route) => {
        expect(route.request().postDataJSON()).toEqual({ challengeId: 'challenge-registration', code: '654321', displayName: 'Pessoa' });
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
        expect(route.request().postDataJSON()).toEqual({ challengeId: 'challenge-3', token: 'secret-token' });
        authenticated = true;
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user }) });
    });

    await page.goto('/access/passwordless?challengeId=challenge-3&token=secret-token');
    await expect(page).toHaveURL(/\/access\/passwordless$/);
    await expect(page.getByRole('main', { name: 'Área de trabalho', exact: true })).toBeVisible();
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
        { id: '01J00000000000000000000000', displayName: 'Ateliê Norte', state: 'ACTIVE', selectable: true, role: 'OWNER' },
        { id: '01J00000000000000000000001', displayName: 'Ateliê Sul', state: 'ACTIVE', selectable: true, role: 'OWNER' },
    ];

    await context.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user }) });
    });
    await context.route('**/api/v1/tenants', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ tenants }) });
    });
    await context.route('**/api/v1/tenants/*/contexts', async (route) => {
        if (route.request().method() === 'DELETE') { await route.fulfill({ status: 204 }); return; }
        const tenant = tenants.find((candidate) => route.request().url().includes(candidate.id));
        await route.fulfill(tenant
            ? { contentType: 'application/json', body: JSON.stringify({ context: { tenant: { id: tenant.id, displayName: tenant.displayName }, membership: { id: `membership-${tenant.id}`, role: 'OWNER' }, availableModules: [] } }) }
            : { contentType: 'application/json', status: 404, body: JSON.stringify({ error: { code: 'TENANT_NOT_AVAILABLE', message: 'Unavailable' } }) });
    });

    const secondTab = await context.newPage();
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

test('anchors hover mega menus and demonstrates window, application and notification layers', async ({ page }) => {
    await mockAuthenticatedSession(page);
    await page.setViewportSize({ width: 1440, height: 520 });
    await page.goto('/');

    const windowArea = page.locator('.workspace-window-area');
    const lastCategory = page.locator('.workspace-navigation-rail__category').last();
    await lastCategory.hover();
    await expect(page.locator('#workspace-mega-menu')).toBeVisible();

    const areaBounds = await windowArea.boundingBox();
    const menuBounds = await page.locator('#workspace-mega-menu').boundingBox();
    expect(menuBounds!.height).toBeLessThan(areaBounds!.height);
    expect(menuBounds!.y).toBeGreaterThanOrEqual(areaBounds!.y);
    expect(menuBounds!.y + menuBounds!.height).toBeLessThanOrEqual(areaBounds!.y + areaBounds!.height);
    expect(await page.locator('#workspace-mega-menu').evaluate((element) => element.scrollHeight <= element.clientHeight)).toBe(true);

    await page.locator('.workspace-navigation-rail__category').nth(1).hover();
    await page.getByRole('button', { name: 'Fluxo de caixa' }).click();
    await expect(page.getByRole('tab', { name: 'Fluxo de caixa' })).toBeVisible();
    await expect(page.getByText('Ambiente de demonstração')).toBeVisible();
    await expect(page.locator('.workspace-stage__close')).toBeVisible();
    await expect(page.locator('.workspace-taskbar__title')).toHaveCount(0);
    await expect(page.locator('.workspace-taskbar__active-pill')).toHaveCount(1);
    await page.getByRole('tab', { name: 'Fluxo de caixa' }).hover();
    expect(await page.getByRole('tab', { name: 'Fluxo de caixa' }).evaluate((element) => getComputedStyle(element).transform)).not.toBe('none');

    const topBar = page.locator('.application-top-bar');
    expect(await topBar.evaluate((element) => getComputedStyle(element).backgroundColor)).toBe('rgb(0, 0, 0)');
    const tenantAvatar = page.getByRole('button', { name: 'Selecionar organização' });
    const personalAvatar = page.getByRole('button', { name: 'Menu pessoal de Pessoa' });
    const tenantBox = await tenantAvatar.boundingBox();
    const personalBox = await personalAvatar.boundingBox();
    expect(personalBox!.x - (tenantBox!.x + tenantBox!.width)).toBeGreaterThanOrEqual(8);

    await page.getByRole('button', { name: 'Diálogo da aplicação' }).click();
    const applicationDialog = page.getByRole('dialog', { name: 'Informação' });
    await expect(applicationDialog).toBeVisible();
    await expect(page.getByRole('button', { name: 'Menu pessoal de Pessoa' })).toBeVisible();
    await applicationDialog.getByRole('button', { name: 'Fechar' }).click();

    await page.getByRole('button', { name: 'Diálogo desta janela' }).click();
    const windowDialog = page.locator('.workspace-stage--active .ui-dialog-backdrop--contained');
    await expect(windowDialog).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Fluxo de caixa' })).toBeVisible();
    await windowDialog.getByRole('button', { name: 'Abrir diálogo acima' }).click();
    await expect(page.getByRole('dialog', { name: 'Detalhe do diálogo' })).toBeVisible();
    await page.getByRole('dialog', { name: 'Detalhe do diálogo' }).getByRole('button', { name: 'Fechar' }).click();
    await expect(page.getByRole('dialog', { name: 'Diálogo desta janela' })).toBeVisible();
    await windowDialog.getByRole('button', { name: 'Fechar' }).click();

    await page.getByRole('button', { name: 'Diálogo desta janela' }).click();
    await page.locator('.workspace-navigation-rail__category').nth(2).hover();
    await page.getByRole('button', { name: 'Contatos' }).click();
    await expect(page.getByRole('tab', { name: 'Contatos' })).toBeVisible();
    await expect(windowDialog).toBeHidden();
    await page.getByRole('tab', { name: 'Fluxo de caixa' }).click();
    await expect(windowDialog).toBeVisible();
    await windowDialog.getByRole('button', { name: 'Fechar' }).click();

    await page.getByRole('button', { name: 'Exibir notificação' }).click();
    await expect(page.getByText('Notificação de demonstração exibida com sucesso.')).toBeVisible();

    await page.locator('.workspace-stage__close').click();
    await expect(page.getByRole('tab', { name: 'Fluxo de caixa' })).toHaveCount(0);

    const taskbar = page.locator('.workspace-taskbar');
    expect(await taskbar.evaluate((element) => getComputedStyle(element).borderTopWidth)).toBe('0px');
    expect(await taskbar.evaluate((element) => getComputedStyle(element).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
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
        await expect(personalMenu.getByRole('button', { name: 'Configurações do usuário' })).toBeDisabled();

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
