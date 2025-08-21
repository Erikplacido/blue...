/**
 * Service Worker para Blue Services PWA
 * Funcionalidades: Cache, Offline, Push Notifications, Background Sync
 */

const CACHE_NAME = 'blue-services-v1.0.0';
const RUNTIME_CACHE = 'blue-services-runtime';
const NOTIFICATION_CACHE = 'blue-services-notifications';

// URLs para cache estático
const STATIC_CACHE_URLS = [
    '/',
    '/index.html',
    '/booking2.php',
    '/customer/dashboard.php',
    '/liquid-glass-components.css',
    '/assets/css/blue7.css',
    '/assets/js/booking5.js',
    '/assets/js/calendar.js',
    '/assets/js/pricing-calculator.js',
    '/manifest.json',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// URLs que devem ser sempre buscadas da rede
const NETWORK_FIRST_URLS = [
    '/api/',
    '/customer/subscription-management.php',
    '/professional/'
];

// URLs para cache offline
const OFFLINE_URLS = [
    '/offline.html'
];

/**
 * Event: Install
 * Executado quando o SW é instalado
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Cache estático
            caches.open(CACHE_NAME).then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_CACHE_URLS);
            }),
            
            // Cache offline
            caches.open(OFFLINE_URLS).then((cache) => {
                console.log('[SW] Caching offline pages');
                return cache.addAll(OFFLINE_URLS);
            })
        ]).then(() => {
            console.log('[SW] Installation complete');
            // Força a ativação imediata
            return self.skipWaiting();
        })
    );
});

/**
 * Event: Activate
 * Executado quando o SW é ativado
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Limpa caches antigos
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME && 
                            cacheName !== RUNTIME_CACHE &&
                            cacheName !== NOTIFICATION_CACHE) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            
            // Toma controle de todas as abas
            self.clients.claim()
        ]).then(() => {
            console.log('[SW] Activation complete');
        })
    );
});

/**
 * Event: Fetch
 * Intercepta todas as requisições de rede
 */
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Ignora requisições não-HTTP
    if (!request.url.startsWith('http')) {
        return;
    }
    
    // Estratégia baseada no tipo de URL
    if (isAPIRequest(url.pathname)) {
        // APIs: Network First
        event.respondWith(networkFirstStrategy(request));
    } else if (isStaticAsset(url.pathname)) {
        // Assets estáticos: Cache First
        event.respondWith(cacheFirstStrategy(request));
    } else {
        // Páginas: Stale While Revalidate
        event.respondWith(staleWhileRevalidateStrategy(request));
    }
});

/**
 * Event: Push
 * Recebe push notifications
 */
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received');
    
    let data = {};
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = {
                title: 'Blue Services',
                body: event.data.text(),
                icon: '/assets/icons/icon-192x192.png',
                badge: '/assets/icons/badge-72x72.png'
            };
        }
    }
    
    const options = {
        title: data.title || 'Blue Services',
        body: data.body || 'Você tem uma nova notificação',
        icon: data.icon || '/assets/icons/icon-192x192.png',
        badge: data.badge || '/assets/icons/badge-72x72.png',
        tag: data.tag || 'general',
        data: data.data || {},
        actions: data.actions || [
            {
                action: 'view',
                title: 'Ver Detalhes',
                icon: '/assets/icons/action-view.png'
            },
            {
                action: 'dismiss',
                title: 'Dispensar',
                icon: '/assets/icons/action-dismiss.png'
            }
        ],
        requireInteraction: data.requireInteraction || false,
        silent: data.silent || false,
        vibrate: data.vibrate || [200, 100, 200],
        timestamp: Date.now()
    };
    
    event.waitUntil(
        self.registration.showNotification(options.title, options)
    );
});

/**
 * Event: Notification Click
 * Manipula cliques em notificações
 */
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.notification);
    
    event.notification.close();
    
    const action = event.action;
    const data = event.notification.data;
    
    if (action === 'dismiss') {
        return;
    }
    
    let url = '/';
    
    if (action === 'view' || !action) {
        if (data && data.url) {
            url = data.url;
        } else if (data && data.type) {
            switch (data.type) {
                case 'booking_confirmed':
                    url = '/customer/dashboard.php';
                    break;
                case 'professional_assigned':
                    url = '/tracking.php';
                    break;
                case 'service_completed':
                    url = '/customer/dashboard.php';
                    break;
                default:
                    url = '/';
            }
        }
    }
    
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then((clientList) => {
            // Verifica se já existe uma aba aberta com a URL
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            
            // Abre nova aba se não encontrou
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

/**
 * Event: Background Sync
 * Executa tarefas em background quando a conexão é restaurada
 */
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'sync-bookings') {
        event.waitUntil(syncPendingBookings());
    } else if (event.tag === 'sync-ratings') {
        event.waitUntil(syncPendingRatings());
    } else if (event.tag === 'sync-location') {
        event.waitUntil(syncLocationUpdates());
    }
});

/**
 * Event: Background Fetch
 * Gerencia downloads em background
 */
self.addEventListener('backgroundfetch', (event) => {
    console.log('[SW] Background fetch:', event.tag);
    
    if (event.tag === 'service-photos') {
        event.waitUntil(handleServicePhotoDownload(event));
    }
});

/**
 * Estratégias de Cache
 */

// Network First: Tenta rede primeiro, fallback para cache
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', error);
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Retorna página offline para navegação
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

// Cache First: Verifica cache primeiro, fallback para rede
async function cacheFirstStrategy(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(RUNTIME_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Cache and network failed:', error);
        throw error;
    }
}

// Stale While Revalidate: Retorna cache e atualiza em background
async function staleWhileRevalidateStrategy(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then((networkResponse) => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch((error) => {
        console.log('[SW] Network failed for SWR:', error);
        return cachedResponse;
    });
    
    return cachedResponse || fetchPromise;
}

/**
 * Funções auxiliares
 */

function isAPIRequest(pathname) {
    return pathname.startsWith('/api/') || 
           pathname.includes('.php') && pathname.includes('api');
}

function isStaticAsset(pathname) {
    return pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|ttf|ico)$/);
}

// Sincronização de bookings pendentes
async function syncPendingBookings() {
    try {
        const pendingBookings = await getStoredData('pendingBookings');
        
        for (const booking of pendingBookings) {
            try {
                const response = await fetch('/api/booking/create.php', {
                    method: 'POST',
                    body: JSON.stringify(booking),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    await removeStoredData('pendingBookings', booking.id);
                    
                    // Notifica sucesso
                    self.registration.showNotification('Agendamento Sincronizado', {
                        body: 'Seu agendamento foi processado com sucesso',
                        icon: '/assets/icons/icon-192x192.png',
                        tag: 'sync-success'
                    });
                }
            } catch (error) {
                console.error('[SW] Failed to sync booking:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Sync bookings failed:', error);
    }
}

// Sincronização de avaliações pendentes
async function syncPendingRatings() {
    try {
        const pendingRatings = await getStoredData('pendingRatings');
        
        for (const rating of pendingRatings) {
            try {
                const response = await fetch('/api/ratings/submit.php', {
                    method: 'POST',
                    body: JSON.stringify(rating),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    await removeStoredData('pendingRatings', rating.id);
                }
            } catch (error) {
                console.error('[SW] Failed to sync rating:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Sync ratings failed:', error);
    }
}

// Sincronização de atualizações de localização
async function syncLocationUpdates() {
    try {
        const pendingLocations = await getStoredData('pendingLocations');
        
        for (const location of pendingLocations) {
            try {
                const response = await fetch('/api/professional/location.php', {
                    method: 'POST',
                    body: JSON.stringify(location),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                if (response.ok) {
                    await removeStoredData('pendingLocations', location.id);
                }
            } catch (error) {
                console.error('[SW] Failed to sync location:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Sync locations failed:', error);
    }
}

// Gerencia download de fotos de serviços
async function handleServicePhotoDownload(event) {
    const downloadUrl = event.downloadUrl;
    const serviceId = event.tag.split('-')[1];
    
    try {
        const response = await fetch(downloadUrl);
        const blob = await response.blob();
        
        // Armazena a foto no cache
        const cache = await caches.open('service-photos');
        await cache.put(`/service-photos/${serviceId}`, new Response(blob));
        
        // Notifica que o download foi concluído
        self.registration.showNotification('Fotos Disponíveis', {
            body: 'As fotos do seu serviço foram baixadas',
            icon: '/assets/icons/icon-192x192.png',
            tag: 'photos-ready',
            data: { serviceId: serviceId }
        });
        
    } catch (error) {
        console.error('[SW] Photo download failed:', error);
    }
}

// Funções de armazenamento local
async function getStoredData(key) {
    return new Promise((resolve) => {
        const request = indexedDB.open('BlueServicesDB', 1);
        
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['storage'], 'readonly');
            const store = transaction.objectStore('storage');
            const getRequest = store.get(key);
            
            getRequest.onsuccess = () => {
                resolve(getRequest.result?.data || []);
            };
            
            getRequest.onerror = () => {
                resolve([]);
            };
        };
        
        request.onerror = () => {
            resolve([]);
        };
    });
}

async function removeStoredData(key, id) {
    return new Promise((resolve) => {
        const request = indexedDB.open('BlueServicesDB', 1);
        
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['storage'], 'readwrite');
            const store = transaction.objectStore('storage');
            
            const getRequest = store.get(key);
            getRequest.onsuccess = () => {
                const data = getRequest.result?.data || [];
                const updatedData = data.filter(item => item.id !== id);
                
                store.put({ key: key, data: updatedData });
                resolve();
            };
        };
        
        request.onerror = () => {
            resolve();
        };
    });
}

console.log('[SW] Service Worker loaded successfully');
