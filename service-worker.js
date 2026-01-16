self.addEventListener('install', function(e) {
  e.waitUntil(
    caches.open('vishnusudarshana-pwa').then(function(cache) {
      return cache.addAll([
        '/',
        '/index.php',
        '/services.php',
        '/blogs.php',
        '/assets/css/',
        '/assets/images/logo/icon-iconpwa192.png',
        '/assets/images/logo/icon-iconpwa512.png'
      ]);
    })
  );
});

self.addEventListener('fetch', function(e) {
  e.respondWith(
    caches.match(e.request).then(function(response) {
      return response || fetch(e.request);
    })
  );
});
