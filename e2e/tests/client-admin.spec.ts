import { test, expect, type Page } from '@playwright/test';

/**
 * Fluxul demonstrabil din două sesiuni separate (CLIENT + ADMIN), împotriva
 * datelor demo (`app:demo:seed`). Necesită stiva pornită — vezi README.md.
 */

const CLIENT_URL = process.env.CLIENT_URL ?? 'http://localhost:3000';
const ADMIN_URL = process.env.ADMIN_URL ?? 'http://localhost:3001';

const CLIENT = { email: 'client@bcsc.ro', password: 'Demo1234!' };
const ADMIN = { email: 'admin@bcsc.ro', password: 'Demo1234!' };

async function login(page: Page, base: string, email: string, password: string) {
  await page.goto(`${base}/login`);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel(/Parol/).fill(password);
  await page.getByRole('button', { name: /Intr|conect/i }).click();
}

test.describe('CLIENT', () => {
  test('vede vehiculele, scadențele, istoricul și oferta', async ({ page }) => {
    await login(page, CLIENT_URL, CLIENT.email, CLIENT.password);

    // Vehicule
    await page.goto(`${CLIENT_URL}/vehicule`);
    await expect(page.getByText('MS01POP')).toBeVisible();

    // Scadențe pe primul vehicul
    await page.getByText('MS01POP').click();
    await expect(page.getByRole('heading', { name: 'Scadențe' })).toBeVisible();

    // Istoric service (înregistrare publicată)
    await page.getByRole('link', { name: /Istoric service/i }).click();
    await expect(page.getByRole('heading', { name: 'Istoric service' })).toBeVisible();

    // Comunicare: cererea de ofertă demo, în stare QUOTED
    await page.goto(`${CLIENT_URL}/mesaje`);
    await expect(page.getByText('Zgomot la frânare')).toBeVisible();
  });
});

test.describe('ADMIN', () => {
  test('vede vehiculele clienților și conversațiile', async ({ page }) => {
    await login(page, ADMIN_URL, ADMIN.email, ADMIN.password);

    await expect(page.getByText('MS01POP')).toBeVisible();

    await page.getByRole('link', { name: /Mesaje/i }).click();
    await expect(page.getByText('Zgomot la frânare')).toBeVisible();
  });
});
