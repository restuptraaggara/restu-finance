const CACHE_NAME = 'restu-finance-v1.6.0';

const STATIC_ASSETS = [
  './',
  './index.html',
  './style.css?v=16',
  './script.js?v=16',
  './js/utils.js?v=16',
  './js/api.js?v=16',
  './js/audio.js?v=16',
  './js/theme.js?v=16',
  './js/wallets.js?v=16',
  './js/budgets.js?v=16',
  './js/goals.js?v=16',
  './js/recurring.js?v=16',
  './js/transactions.js?v=16',
  './js/app.js?v=16',
  './qrcode.min.js',
  './manifest.webmanifest',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './icons/icon.svg',
  './icons/icon-light.svg',
  './icons/icon-dark.svg',
  './icons/icon-pixel.svg',
];

// Install: Cache App Shell & Static Assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(STATIC_ASSETS).catch((err) => {
          console.warn('[SW] Cache addAll warning:', err);
        });
      })
      .then(() => self.skipWaiting())
  );
});

// Activate: Clean up old cache versions
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch: Pure Network for API; Cache-first / Network-fallback for Static Shell
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // 1. SEMUA ENDPOINT API & NON-GET REQUEST HARUS SELALU KE NETWORK (TIDAK PERNAH DI-CACHE)
  if (url.pathname.startsWith('/api/') || req.method !== 'GET') {
    event.respondWith(
      fetch(req).catch(() => {
        // Fallback JSON jika server offline / unreachable
        return new Response(
          JSON.stringify({
            success: false,
            message: 'Koneksi ke server tidak tersedia. Pastikan server aktif dan perangkat terhubung ke Wi-Fi/LAN.',
            offline: true,
          }),
          {
            status: 503,
            headers: { 'Content-Type': 'application/json' },
          }
        );
      })
    );
    return;
  }

  // 2. Navigation Request (HTML page): Network first with Cache fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const copy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return networkRes;
        })
        .catch(() => {
          return caches.match(req).then((cached) => cached || caches.match('./index.html'));
        })
    );
    return;
  }

  // 3. Static Assets (CSS, JS, Fonts, Images): Cache First with Network Fallback & Refresh
  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) {
        // Background refresh for fresh assets
        fetch(req)
          .then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              caches.open(CACHE_NAME).then((cache) => cache.put(req, networkRes));
            }
          })
          .catch(() => {});
        return cached;
      }

      return fetch(req)
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const copy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return networkRes;
        })
        .catch((err) => {
          console.warn('[SW] Fetch failed for:', req.url, err);
        });
    })
  );
});
