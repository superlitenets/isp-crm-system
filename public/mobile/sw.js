const CACHE_NAME = 'isp-crm-mobile-v2';
const STATIC_CACHE = 'isp-static-v2';
const API_CACHE = 'isp-api-v1';

const STATIC_ASSETS = [
    '/mobile/',
    '/mobile/index.html',
    '/mobile/css/mobile.css',
    '/mobile/js/app.js',
    '/mobile/manifest.json',
    '/mobile/icons/icon-192.png'
];

const CDN_ASSETS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => cache.addAll(STATIC_ASSETS)),
            caches.open(CACHE_NAME).then(cache => {
                return Promise.allSettled(CDN_ASSETS.map(url => 
                    cache.add(url).catch(err => console.log('CDN cache skip:', url))
                ));
            })
        ])
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(names => 
            Promise.all(names.map(name => {
                if (![CACHE_NAME, STATIC_CACHE, API_CACHE].includes(name)) {
                    return caches.delete(name);
                }
            }))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    
    if (url.pathname.includes('/mobile-api.php')) {
        event.respondWith(networkFirst(event.request));
        return;
    }
    
    if (url.hostname.includes('cdn.jsdelivr.net') || 
        url.pathname.startsWith('/mobile/icons/') ||
        url.pathname.startsWith('/mobile/css/') ||
        url.pathname.startsWith('/mobile/js/')) {
        event.respondWith(cacheFirst(event.request));
        return;
    }
    
    event.respondWith(staleWhileRevalidate(event.request));
});

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (e) {
        return new Response('Offline', { status: 503 });
    }
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(API_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (e) {
        const cached = await caches.match(request);
        if (cached) return cached;
        return new Response(JSON.stringify({ 
            success: false, 
            error: 'You are offline',
            offline: true
        }), {
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

async function staleWhileRevalidate(request) {
    const cached = await caches.match(request);
    
    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            caches.open(CACHE_NAME).then(cache => cache.put(request, response.clone()));
        }
        return response;
    }).catch(() => cached);
    
    return cached || fetchPromise;
}
