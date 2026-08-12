// Hand-written, no Workbox — per Docs/adr/0002-offline-sync-strategy.md §10.
//
// The app shell (/rep, its manifest, its icon) is precached on install. The
// hashed JS/CSS bundle Vite produces is not — its filename isn't known at
// write time — so it is cached the first time it's actually fetched, which
// happens the moment a rep opens /rep while online, before they can go
// offline at all.
const CACHE_VERSION = 'dukaflow-rep-v1';
const APP_SHELL = ['/rep', '/manifest.json', '/rep-icon.svg', '/offline.html'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION).then((cache) => cache.addAll(APP_SHELL)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return; // Sync pushes and cross-origin requests pass straight through.
    }

    // Sync data lives in IndexedDB, not the HTTP cache — a cached pull
    // response would be silently stale the moment anything changes.
    if (url.pathname.startsWith('/api/sync/')) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(networkFirst(event.request));
        return;
    }

    event.respondWith(cacheFirst(event.request));
});

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        const cache = await caches.open(CACHE_VERSION);
        cache.put(request, response.clone());
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        return cached ?? caches.match('/offline.html');
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    const cache = await caches.open(CACHE_VERSION);
    cache.put(request, response.clone());
    return response;
}
