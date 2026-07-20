import { test, expect, type Page } from '@playwright/test';

/**
 * P1-08 — fluxuri de client în browser, cap-coadă:
 *  - înregistrare liberă (email + parolă) → vehicul propriu → taxă → plată
 *    parțială (declarativ, fără fișiere);
 *  - comutatorul de limbă RO → HU (persistat în localStorage);
 *  - mesaj client → răspuns admin → clientul vede răspunsul (două sesiuni).
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
  // Așteptăm încheierea autentificării (redirect din /login) — altfel pasul
  // următor pornește fără cookie de sesiune.
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
}

test('înregistrare nouă → vehicul → taxă → plată parțială → limbă HU', async ({ page }) => {
  const email = `e2e-${Date.now()}@example.test`;
  const vin = 'WBA3A5C50EF' + String(Math.floor(100000 + Math.random() * 900000));

  // Înregistrare liberă (cont nou — fără număr de înmatriculare).
  await page.goto(`${CLIENT_URL}/register`);
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Parolă', { exact: true }).fill('Parola1234');
  await page.getByRole('checkbox').check();
  await page.getByRole('button', { name: /Creează cont/ }).click();
  await expect(page).toHaveURL(`${CLIENT_URL}/`);

  // Vehicul propriu.
  await page.goto(`${CLIENT_URL}/vehicule/nou`);
  await page.getByLabel(/Serie de șasiu/).fill(vin);
  await page.getByLabel('Număr de înmatriculare').fill('MS 99 E2E');
  await page.getByRole('button', { name: /Salvează|Adaugă/ }).click();
  await expect(page).toHaveURL(`${CLIENT_URL}/vehicule`);
  await expect(page.getByText('MS 99 E2E')).toBeVisible();

  // Taxă nouă + plată parțială declarativă (fără niciun fișier).
  await page.goto(`${CLIENT_URL}/taxe/nou`);
  await page.getByLabel('Sumă (RON)').fill('300');
  await page.getByRole('button', { name: /Salvează|Adaugă/ }).click();
  await expect(page).toHaveURL(/\/taxe\/[0-9a-f-]+$/);
  await page.getByLabel(/Sumă plătită/).fill('100');
  await page.getByRole('button', { name: /plat/i }).click();
  await expect(page.getByText(/parțial/i).first()).toBeVisible();

  // Comutatorul de limbă: HU se aplică imediat și persistă la reîncărcare.
  await page.getByRole('button', { name: 'HU', exact: true }).click();
  await expect(page.getByText('Részben befizetve').first()).toBeVisible();
  await page.reload();
  await expect(page.getByText('Részben befizetve').first()).toBeVisible();
});

test('mesaj client → răspuns admin → clientul vede răspunsul', async ({ browser }) => {
  const subject = `E2E întrebare ${Date.now()}`;

  // CLIENT: pornește conversația.
  const clientCtx = await browser.newContext();
  const clientPage = await clientCtx.newPage();
  await login(clientPage, CLIENT_URL, CLIENT.email, CLIENT.password);
  await clientPage.goto(`${CLIENT_URL}/mesaje/nou`);
  await clientPage.getByLabel('Subiect').fill(subject);
  await clientPage.getByLabel('Mesaj').fill('Când e liber un interval pentru revizie?');
  await clientPage.getByRole('button', { name: 'Trimite' }).click();
  await expect(clientPage.getByText(subject)).toBeVisible();

  // ADMIN: vede conversația și răspunde.
  const adminCtx = await browser.newContext();
  const adminPage = await adminCtx.newPage();
  await login(adminPage, ADMIN_URL, ADMIN.email, ADMIN.password);
  await adminPage.goto(`${ADMIN_URL}/mesaje`);
  await adminPage.getByText(subject).click();
  await adminPage.getByRole('textbox').last().fill('Marți la 10:00 avem loc.');
  await adminPage.getByRole('button', { name: /Trimite|Răspunde/ }).click();
  await expect(adminPage.getByText('Marți la 10:00 avem loc.')).toBeVisible();

  // CLIENT: vede răspunsul service-ului.
  await clientPage.reload();
  await clientPage.getByText(subject).click();
  await expect(clientPage.getByText('Marți la 10:00 avem loc.')).toBeVisible();

  await clientCtx.close();
  await adminCtx.close();
});
