// ─── APP HROMAN — Service Worker con auto-actualización ───
// Cambiar APP_VERSION en cada release para forzar actualización del caché
const APP_VERSION = '2.2.0';
const CACHE_NAME  = `hroman-v${APP_VERSION}`;

const STATIC_ASSETS = [
    './',
    './index.html',
    './registros.html',
    './resumen.html',
    './checklist.html',
    './configuracion.html',
    './manifest.json',
    './img/logo.png'
];

// INSTALL: pre-cachea los assets y fuerza activación inmediata
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// ACTIVATE: borra cachés de versiones anteriores
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
            )
        ).then(() => self.clients.claim())
         .then(() => {
            // Notificar a todos los clientes que hay versión nueva
            self.clients.matchAll().then(clients => {
                clients.forEach(client => {
                    client.postMessage({ type: 'SW_UPDATED', version: APP_VERSION });
                });
            });
         })
    );
});

// FETCH: Network-first para HTML, Cache-first para assets estáticos
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Para archivos HTML: siempre intentar red primero (así se actualiza)
    if (event.request.mode === 'navigate' || url.pathname.endsWith('.html')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Guardar copia actualizada en caché
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    return response;
                })
                .catch(() => caches.match(event.request)) // Offline: usar caché
        );
        return;
    }

    // Para API calls: siempre red, nunca cachear
    if (url.pathname.includes('/api/')) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Para assets estáticos (img, css, js): cache-first con actualización en background
    event.respondWith(
        caches.match(event.request).then(cached => {
            const fetchPromise = fetch(event.request).then(response => {
                caches.open(CACHE_NAME).then(cache => cache.put(event.request, response.clone()));
                return response;
            }).catch(() => cached);

            return cached || fetchPromise;
        })
    );
});

// Escuchar mensaje de la app para forzar actualización
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});
