#!/usr/bin/env node
/**
 * Render the app icons from the Logo component.
 *
 * The gear outline is not duplicated here — it is read out of Logo.tsx, so the
 * icon on a phone's home screen cannot drift away from the mark in the sidebar.
 * Change the component and re-run this; there is no second copy to remember.
 *
 *   node scripts/generate-icons.mjs
 *
 * Requires `sharp`, which is already present in apps/customer-web.
 */

import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const CUSTOMER = join(ROOT, 'apps/customer-web');
const OUT = join(CUSTOMER, 'public/icons');

// sharp lives in the customer app, not at the repo root.
const require = createRequire(join(CUSTOMER, 'package.json'));
const sharp = require('sharp');

/* ---- Read the mark out of the component ------------------------------- */

const src = readFileSync(join(CUSTOMER, 'components/Logo.tsx'), 'utf8');

/** Pull `const NAME = 'a' + 'b' + …;` and join the pieces. */
function constant(name) {
  const m = src.match(new RegExp(`const ${name}\\s*=\\s*([\\s\\S]*?);\\n`));
  if (!m) throw new Error(`Logo.tsx: could not find ${name}`);
  const parts = [...m[1].matchAll(/'((?:[^'\\]|\\.)*)'/g)].map((p) => p[1]);
  if (!parts.length) throw new Error(`Logo.tsx: ${name} held no string literal`);
  return parts.join('');
}

const GEAR = constant('GEAR');
const HOLE = constant('HOLE');

const hub = src.match(/<circle cx="24" cy="24" r="([\d.]+)" fill="(#[0-9a-fA-F]{3,8})"/);
if (!hub) throw new Error('Logo.tsx: could not find the hub circle');
const [, HUB_R, HUB_FILL] = hub;

/* ---- Compose ----------------------------------------------------------- */

/** Manifest theme_color, so the icon and the OS chrome agree. */
const NAVY = '#0a2540';
const MARK = '#ffffff';

/**
 * The component draws on a 0 0 48 48 grid with the gear centred at (24,24) and
 * its teeth reaching r=22 — 92% of the box. That is too tight for an app icon,
 * so each target widens the viewBox to inset the mark rather than scaling it,
 * which would change the stroke-to-gap ratio.
 */
function svg({ pad }) {
  const min = -pad;
  const size = 48 + pad * 2;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${min} ${min} ${size} ${size}">
  <rect x="${min}" y="${min}" width="${size}" height="${size}" fill="${NAVY}"/>
  <path d="${GEAR} ${HOLE}" fill="${MARK}" fill-rule="evenodd"/>
  <circle cx="24" cy="24" r="${HUB_R}" fill="${HUB_FILL}"/>
</svg>`;
}

/**
 * The customer app is installable, so it needs the full manifest set. The
 * portal is not a PWA — it only needs a tab icon, which is why it gets the
 * vector plus one raster fallback rather than the maskable variants.
 */
const APPS = [
  {
    out: OUT,
    targets: [
      // purpose "any" — shown roughly as drawn, so a modest inset reads best.
      { file: 'icon-192.png', size: 192, pad: 4 },
      { file: 'icon-512.png', size: 512, pad: 4 },

      // purpose "maskable" — the platform may crop to a circle inscribed in
      // the icon. Everything must sit inside a circle of radius 40% of the
      // icon, so the mark's r=22 needs a canvas of at least 55 units; 60
      // leaves margin.
      { file: 'icon-maskable-512.png', size: 512, pad: 6 },

      // iOS applies its own rounded-rect mask and does not honour
      // transparency, hence the opaque navy field.
      { file: 'apple-touch-icon.png', size: 180, pad: 4 },
    ],
  },
  {
    out: join(ROOT, 'apps/service-admin/public/icons'),
    targets: [
      { file: 'icon-192.png', size: 192, pad: 4 },
      { file: 'apple-touch-icon.png', size: 180, pad: 4 },
    ],
  },
];

let written = 0;
for (const { out, targets } of APPS) {
  mkdirSync(out, { recursive: true });
  console.log(`\n${out.replace(`${ROOT}/`, '')}`);

  // Keep the vector alongside the PNGs: it doubles as a modern favicon and
  // makes the next regeneration inspectable.
  writeFileSync(join(out, 'icon.svg'), `${svg({ pad: 4 })}\n`);
  console.log('  icon.svg');
  written += 1;

  for (const { file, size, pad } of targets) {
    await sharp(Buffer.from(svg({ pad })), { density: 384 })
      .resize(size, size, { fit: 'fill' })
      .png({ compressionLevel: 9 })
      .toFile(join(out, file));
    console.log(`  ${file}  ${size}x${size}`);
    written += 1;
  }
}

console.log(`\nWrote ${written} files.`);
