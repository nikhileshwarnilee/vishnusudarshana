const APP_SCOPE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const APP_BASE_PATH = APP_SCOPE_PATH === '/' ? '' : APP_SCOPE_PATH;

function appPath(path) {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${APP_BASE_PATH}${normalizedPath}`;
}

const CACHE_VERSION = 'vishnusudarshana-pwa-v4';
const PRECACHE_ASSETS = [
  appPath('/assets/images/logo/icon-iconpwa192.png'),
  appPath('/assets/images/logo/icon-iconpwa512.png')
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

