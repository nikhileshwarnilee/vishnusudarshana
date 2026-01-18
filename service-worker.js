const CACHE_VERSION = 'vishnusudarshana-pwa-v2';
const PRECACHE_ASSETS = [
  '/assets/images/logo/icon-iconpwa192.png',
  '/assets/images/logo/icon-iconpwa512.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(PRECACHE_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((key) => {
        if (key !== CACHE_VERSION) {
          return caches.delete(key);
        }
      }))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // For PHP/HTML and API requests, always go to network to avoid stale pages
  const isDynamic = url.pathname.endsWith('.php') || request.mode === 'navigate';
  if (isDynamic) {
    event.respondWith(fetch(request).catch(() => caches.match(request)));
    return;
  }

  // For static assets, try cache first then network
  event.respondWith(
    caches.match(request).then((cached) => cached || fetch(request))
  );
});
