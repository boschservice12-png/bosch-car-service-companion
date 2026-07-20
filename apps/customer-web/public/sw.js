/*
 * Service worker MINIMAL — un singur scop: când nu există internet, navigarea
 * să arate pagina /offline.html în loc de ecranul alb de eroare al browserului.
 *
 * INTENȚIONAT nu se cache-ază NIMIC altceva: niciun răspuns de API, nicio dată
 * de client/vehicul/scadență, niciun document. Datele apar doar cu conexiune.
 */
// ATENȚIE: dacă modificați offline.html sau iconițele cache-uite, măriți
// versiunea de aici — browserul reinstalează SW-ul doar când sw.js se schimbă,
// altfel copia veche din cache ar rămâne servită la nesfârșit.
const CACHE = 'bcsc-offline-v2';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) =>
      // „reload": ocolește cache-ul HTTP al browserului — instalarea ia mereu
      // versiunea proaspătă de pe server, nu o copie veche.
      cache.addAll([new Request(OFFLINE_URL, { cache: 'reload' }), new Request('/icons/icon-192.png', { cache: 'reload' })]),
    ),
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  // Doar navigări de pagină; cererile de API/imagini nu trec niciodată prin cache.
  if (event.request.mode !== 'navigate') return;
  // Descărcările (export GDPR, documente) sunt tot „navigări" — fără excepția
  // asta, offline, fișierul salvat ar fi pagina offline.html în loc de eroare.
  if (new URL(event.request.url).pathname.startsWith('/api/')) return;
  event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
