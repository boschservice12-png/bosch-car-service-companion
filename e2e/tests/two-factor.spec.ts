import { test, expect, type Page } from '@playwright/test';
import { createHmac } from 'node:crypto';

/**
 * P1-08 / P0-06 — 2FA cap-coadă, prin interfața reală:
 * înrolare (parolă → secret → cod de confirmare → coduri de rezervă) →
 * re-login cu provocare OTP (cod greșit respins, cod corect acceptat) →
 * dezactivare (curăță starea pentru celelalte rulări).
 *
 * Codul TOTP se calculează în test (RFC 6238, HMAC-SHA1, 6 cifre, pas 30s)
 * din secretul afișat la înrolare — exact ce ar face aplicația de telefon.
 */

const ADMIN_URL = process.env.ADMIN_URL ?? 'http://localhost:3001';
const ADMIN = { email: 'admin@bcsc.ro', password: 'Demo1234!' };

function base32Decode(secret: string): Buffer {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = 0;
  let value = 0;
  const bytes: number[] = [];
  for (const ch of secret.replace(/=+$/, '').toUpperCase()) {
    const idx = alphabet.indexOf(ch);
    if (idx < 0) continue;
    value = (value << 5) | idx;
    bits += 5;
    if (bits >= 8) {
      bits -= 8;
      bytes.push((value >> bits) & 0xff);
    }
  }
  return Buffer.from(bytes);
}

function totp(secret: string, atMs = Date.now()): string {
  const counter = Math.floor(atMs / 1000 / 30);
  const buf = Buffer.alloc(8);
  buf.writeBigUInt64BE(BigInt(counter));
  const hash = createHmac('sha1', base32Decode(secret)).update(buf).digest();
  const offset = hash[hash.length - 1] & 0x0f;
  const code = ((hash.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).toString();
  return code.padStart(6, '0');
}

async function loginAdmin(page: Page) {
  await page.goto(`${ADMIN_URL}/login`);
  await page.getByLabel('Email').fill(ADMIN.email);
  await page.getByLabel(/Parol/).fill(ADMIN.password);
  await page.getByRole('button', { name: /Intr/i }).click();
}

test('înrolare 2FA → provocare la login → dezactivare', async ({ page }) => {
  test.setTimeout(90_000);

  // 1) Înrolare: parolă → secret → confirmare cu primul cod.
  await loginAdmin(page);
  await expect(page.getByRole('heading', { name: 'Vehicule' })).toBeVisible();
  await page.goto(`${ADMIN_URL}/securitate`);
  await page.getByLabel('Confirmați parola contului').fill(ADMIN.password);
  await page.getByRole('button', { name: 'Activează 2FA' }).click();

  const secret = (await page.locator('code').first().innerText()).trim();
  expect(secret).toMatch(/^[A-Z2-7]{32}$/);

  await page.getByLabel('Cod din aplicație (6 cifre)').fill(totp(secret));
  await page.getByRole('button', { name: 'Confirmă și activează' }).click();

  // Codurile de rezervă apar O SINGURĂ DATĂ (8 bucăți, format XXXX-XXXX).
  await expect(page.getByText('2FA activat')).toBeVisible();
  const recovery = await page.locator('ul li').allInnerTexts();
  expect(recovery.filter((c) => /^[A-Z2-9]{4}-[A-Z2-9]{4}$/.test(c.trim()))).toHaveLength(8);
  await page.getByRole('button', { name: 'Am salvat codurile de rezervă' }).click();
  await expect(page.getByText(/2FA este ACTIV/)).toBeVisible();

  // 2) Re-login: al doilea pas e obligatoriu; codul greșit e respins.
  await page.goto(`${ADMIN_URL}/`);
  await page.getByRole('button', { name: 'Ieșire' }).click();
  await loginAdmin(page);
  await expect(page.getByRole('heading', { name: 'Verificare în doi pași' })).toBeVisible();

  await page.getByLabel('Cod din aplicație (6 cifre)').fill('000000');
  await page.getByRole('button', { name: 'Verifică' }).click();
  await expect(page.getByRole('alert')).toBeVisible();

  await page.getByLabel('Cod din aplicație (6 cifre)').fill(totp(secret));
  await page.getByRole('button', { name: 'Verifică' }).click();
  await expect(page.getByRole('heading', { name: 'Vehicule' })).toBeVisible();

  // 3) Dezactivare (lasă contul demo curat pentru celelalte teste/rulări).
  await page.goto(`${ADMIN_URL}/securitate`);
  await page.getByLabel('Cod din aplicație (6 cifre)').fill(totp(secret));
  await page.getByRole('button', { name: 'Dezactivează 2FA' }).click();
  await expect(page.getByRole('button', { name: 'Activează 2FA' })).toBeVisible();
});
