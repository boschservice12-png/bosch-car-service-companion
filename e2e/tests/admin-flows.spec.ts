import { test, expect, type Page } from '@playwright/test';

/**
 * P1-08 — fluxuri de admin în browser: căutarea din panoul principal
 * (nume / număr / VIN, combinate cu ȘI) și navigarea în module.
 * Necesită stiva pornită cu datele demo (vezi README.md).
 */

const ADMIN_URL = process.env.ADMIN_URL ?? 'http://localhost:3001';
const ADMIN = { email: 'admin@bcsc.ro', password: 'Demo1234!' };

async function loginAdmin(page: Page) {
  await page.goto(`${ADMIN_URL}/login`);
  await page.getByLabel('Email').fill(ADMIN.email);
  await page.getByLabel(/Parol/).fill(ADMIN.password);
  await page.getByRole('button', { name: /Intr/i }).click();
  await expect(page.getByRole('heading', { name: 'Vehicule' })).toBeVisible();
}

test('căutarea pe 3 câmpuri filtrează vehiculele (normalizat, fără spații)', async ({ page }) => {
  await loginAdmin(page);
  await expect(page.getByText('MS01POP')).toBeVisible();
  await expect(page.getByText('MS02POP')).toBeVisible();

  // Numărul se caută normalizat: „ms 02" găsește MS02POP.
  await page.getByLabel('Nr. înmatriculare').fill('ms 02');
  await expect(page.getByText('MS02POP')).toBeVisible();
  await expect(page.getByText('MS01POP')).toBeHidden();

  // Criteriile se combină cu ȘI: un VIN străin nu mai lasă niciun rezultat.
  await page.getByLabel('VIN').fill('ZZZ-inexistent');
  await expect(page.getByText('MS02POP')).toBeHidden();

  await page.getByLabel('VIN').fill('');
  await page.getByLabel('Nr. înmatriculare').fill('');
  await expect(page.getByText('MS01POP')).toBeVisible();
});

test('modulele din bara de sus se deschid (taxe, securitate)', async ({ page }) => {
  await loginAdmin(page);

  await page.getByRole('link', { name: /Taxe/ }).click();
  await expect(page.getByRole('heading', { name: /Taxe/ })).toBeVisible();

  await page.goto(`${ADMIN_URL}/securitate`);
  await expect(page.getByRole('heading', { name: 'Securitatea contului' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Activează 2FA' })).toBeVisible();
});
