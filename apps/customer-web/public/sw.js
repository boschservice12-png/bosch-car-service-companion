/*
 * Service worker MINIMAL — un singur scop: când nu există internet, navigarea
 * să arate pagina /offline.html în loc de ecranul alb de eroare al browserului.
 *
 * INTENȚIONAT nu se cache-ază NIMIC altceva: niciun răspuns de API, nicio dată
 * de client/vehicul/scadență, niciun document. Datele apar doar cu conexiune.
 */
const CACHE = 'bcsc-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])),
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
  event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
