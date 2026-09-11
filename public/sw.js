// Versión inyectada por el servidor al momento de servir — cambia en cada deploy
const CACHE_NAME = '__CACHE_VERSION__';
const OFFLINE_URL = '/offline';

// Páginas HTML a pre-cachear en la instalación
const HTML_ROUTES = [
    '/',
    '/preguntas',
    '/historial',
    '/tutor',
    '/agenda',
    '/cursos',
    '/offline',
];

// Assets estáticos propios a pre-cachear
const STATIC_ASSETS = [
    '/resources/favicon.ico',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

// ─── Instalación ────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll([...HTML_ROUTES, ...STATIC_ASSETS]))
            .catch(err => console.warn('SW install warning:', err))
    );
    // Activar inmediatamente sin esperar que cierren las pestañas anteriores
    self.skipWaiting();
});

// ─── Activación — limpiar cachés de versiones anteriores ────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => {
                    console.log('SW: eliminando caché obsoleta:', k);
                    return caches.delete(k);
                })
            ))
            .then(() => self.clients.claim())
    );
});

// ─── Fetch — estrategia híbrida ──────────────────────────────────────────────
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    const isStaticAsset = /\/(css|js|images|icons|fonts)\//.test(url.pathname)
        || url.hostname.includes('cdnjs.cloudflare.com')
        || url.hostname.includes('cdn.jsdelivr.net')
        || url.hostname.includes('fonts.googleapis.com')
        || url.hostname.includes('fonts.gstatic.com');

    const isHTML = event.request.headers.get('accept')?.includes('text/html');

    if (isStaticAsset) {
        // Cache First: sirve desde caché, actualiza en background
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
    } else if (isHTML) {
        // Network First: intenta red, cae a caché, cae a /offline
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    if (response.status === 200) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(event.request)
                        .then(cached => cached || caches.match(OFFLINE_URL))
                )
        );
    }
});

// ─── Push notifications (VAPID — cuando se implementen) ─────────────────────
self.addEventListener('push', event => {
    if (!event.data) return;
    const data = event.data.json();
    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-72x72.png',
            tag: data.tag || 'push-default',
            data: { url: data.url || '/' },
            vibrate: [100, 50, 100],
        })
    );
});

// ─── Notificaciones locales (sin VAPID) — disparadas desde la página ─────────
// La página envía: { type: 'SHOW_NOTIFICATION', title, body, url, tag }
self.addEventListener('message', event => {
    if (event.data?.type !== 'SHOW_NOTIFICATION') return;
    const { title, body, url, tag } = event.data;
    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-72x72.png',
            tag: tag || 'agenda-reminder',
            data: { url: url || '/agenda' },
            vibrate: [200, 100, 200],
            requireInteraction: false,
        })
    );
});

// ─── Clic en notificación — abrir o enfocar la pestaña correspondiente ───────
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/agenda';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            for (const client of clientList) {
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(targetUrl);
        })
    );
});

// ─── Background Sync — reintentar guardado de conversaciones fallidas ─────────
self.addEventListener('sync', event => {
    if (event.tag === 'save-conversation') {
        event.waitUntil(syncPendingConversations());
    }
});

async function syncPendingConversations() {
    let db;
    try {
        db = await openSwDB();
    } catch (e) {
        console.warn('SW: no se pudo abrir IndexedDB:', e);
        return;
    }

    const tx = db.transaction('pending_conversations', 'readonly');
    const items = await getAllFromStore(tx.objectStore('pending_conversations'));

    for (const item of items) {
        try {
            const headers = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': item.csrf,
            };

            let response;
            if (item.conversationId) {
                response = await fetch(`/conversaciones/${item.conversationId}`, {
                    method: 'PUT',
                    headers,
                    body: JSON.stringify({ content: item.content }),
                });
            } else {
                response = await fetch('/conversaciones', {
                    method: 'POST',
                    headers,
                    body: JSON.stringify({ title: item.title, type: 'chat', content: item.content }),
                });
            }

            if (response.ok) {
                const delTx = db.transaction('pending_conversations', 'readwrite');
                delTx.objectStore('pending_conversations').delete(item.id);
                console.log('SW: conversación sincronizada, id local:', item.id);
            }
        } catch (e) {
            console.warn('SW: error al sincronizar conversación:', e);
        }
    }
}

// ─── Helpers de IndexedDB ─────────────────────────────────────────────────────
function openSwDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('asistente-sw', 1);
        req.onupgradeneeded = e => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('pending_conversations')) {
                db.createObjectStore('pending_conversations', { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror = e => reject(e.target.error);
    });
}

function getAllFromStore(store) {
    return new Promise((resolve, reject) => {
        const req = store.getAll();
        req.onsuccess = e => resolve(e.target.result);
        req.onerror = e => reject(e.target.error);
    });
}
