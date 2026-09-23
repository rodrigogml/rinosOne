import { expect, test } from '@playwright/test';

test('renders the application bootstrap', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Acesse sua conta' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Criar conta' })).toBeVisible();
});

test('supports keyboard navigation between access modes', async ({ page }) => {
    await page.goto('/');
    const registrationTab = page.getByRole('tab', { name: 'Criar conta' });

    await registrationTab.focus();
    await page.keyboard.press('ArrowRight');

    await expect(page.getByRole('tab', { name: 'Entrar', selected: true })).toBeFocused();
    await expect(page.getByLabel('Senha')).toBeVisible();
});

test('continues passwordless access from an e-mail link in a new tab', async ({ page }) => {
    let sessionRequests = 0;
    await page.route('**/api/v1/auth/passwordless-sessions/link-confirmations', async (route) => {
        await route.fulfill({ contentType: 'application/json', status: 201, body: JSON.stringify({ user: { id: 'user-1' } }) });
    });
    await page.route('**/api/v1/auth/session', async (route) => {
        sessionRequests += 1;
        await route.fulfill(sessionRequests === 1
            ? { contentType: 'application/json', status: 401, body: JSON.stringify({ code: 'UNAUTHENTICATED' }) }
            : { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: false } }) });
    });

    await page.goto('/access/passwordless?challengeId=challenge-3&token=secret-token');

    await expect(page).toHaveURL(/\/access\/passwordless$/);
    await expect(page.getByRole('heading', { name: 'Segurança de acesso' })).toBeVisible();
    await expect(page.getByText('Você permanecerá conectado neste navegador.')).toBeVisible();
});

test('restores the security screen from a persistent browser session', async ({ page }) => {
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: false } }) });
    });

    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Segurança de acesso' })).toBeVisible();
    await expect(page.getByText('Você permanecerá conectado neste navegador.')).toBeVisible();
});

test('confirms before invalidating other sessions', async ({ page }) => {
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: true, user: { displayName: 'Pessoa', passwordDefined: true } }) });
    });
    await page.route('**/api/v1/auth/other-sessions', async (route) => {
        await route.fulfill({ status: 204 });
    });

    await page.goto('/');
    await page.getByRole('button', { name: 'Invalidar outras sessões' }).click();
    await expect(page.getByRole('alertdialog')).toBeVisible();
    await page.getByRole('button', { name: 'Invalidar sessões' }).click();

    await expect(page.getByText('Outras sessões foram invalidadas.')).toBeVisible();
});

test('ends the current session from the security screen', async ({ page }) => {
    await page.route('**/api/v1/auth/session', async (route) => {
        await route.fulfill(route.request().method() === 'DELETE'
            ? { status: 204 }
            : { contentType: 'application/json', body: JSON.stringify({ persistentAuthentication: false, user: { displayName: 'Pessoa', passwordDefined: true } }) });
    });

    await page.goto('/');
    await page.getByRole('button', { name: 'Encerrar esta sessão' }).click();

    await expect(page.getByRole('heading', { name: 'Acesse sua conta' })).toBeVisible();
});

for (const viewport of [
    { name: 'telefone', width: 375, height: 667 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1440, height: 900 },
]) {
    test(`keeps the access controls usable on ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto('/');

        await expect(page.getByRole('heading', { name: 'Acesse sua conta' })).toBeVisible();
        await expect(page.getByRole('tablist', { name: 'Modo de acesso' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Criar conta' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        await expect(page.getByRole('button', { name: 'Criar conta' })).toHaveCSS('min-height', '44px');
    });
}
