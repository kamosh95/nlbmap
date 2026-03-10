const CACHE_NAME = 'nlb-map-v2';
const ASSETS = [
    'assets/css/style.css',
    'assets/img/Logo.png'
];

// Install Service Worker
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS);
        })
    );
});

// Activate Service Worker and clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Assets
self.addEventListener('fetch', event => {
    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(response => {
            if (response) {
                return response;
            }

            // Try fetching from the network, catch offline errors
            return fetch(event.request).catch(() => {
                // If it's a page navigation, we could return a custom offline page here
                // For now, we return a fallback response so we don't crash
                console.log("Network request failed and no cache available for:", event.request.url);
                return new Response("Offline mode: Resource not available offline.", {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: new Headers({ 'Content-Type': 'text/plain' })
                });
            });
        })
    );
});
