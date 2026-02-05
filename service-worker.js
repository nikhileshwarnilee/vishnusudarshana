const CACHE_VERSION = 'vishnusudarshana-pwa-v3';
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

/**
 * Firebase Cloud Messaging - Handle background messages
 */
self.addEventListener('push', (event) => {
  if (!event.data) {
    console.log('Push notification received with no data');
    return;
  }

  let notificationData = {};
  
  try {
    // Try to parse JSON data
    notificationData = event.data.json();
  } catch (e) {
    // If JSON parsing fails, treat as plain text
    notificationData = {
      title: 'Notification',
      body: event.data.text()
    };
  }

  const notificationTitle = notificationData.notification?.title || notificationData.title || 'Vishnu Sudarshana';
  const notificationOptions = {
    body: notificationData.notification?.body || notificationData.body || '',
    icon: notificationData.notification?.icon || '/assets/images/logo/icon-iconpwa192.png',
    badge: '/assets/images/logo/icon-iconpwa192.png',
    tag: 'vishnusudarshana-notification',
    requireInteraction: notificationData.notification?.requireInteraction || false,
    data: {
      ...notificationData.data,
      timestamp: new Date().getTime(),
      url: notificationData.notification?.clickAction || notificationData.data?.link || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(notificationTitle, notificationOptions)
  );
});

/**
 * Handle notification click
 */
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const urlToOpen = event.notification.data.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Check if the app is already open in a window/tab
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      // If not open, open a new window/tab
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

/**
 * Handle notification close
 */
self.addEventListener('notificationclose', (event) => {
  console.log('Notification closed:', event.notification.tag);
});
