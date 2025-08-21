/**
 * Enhanced PWA Service Worker v4.0
 * Blue Cleaning Services - Advanced Features & Real-time Support
 */

const CACHE_VERSION = 'blue-v4.0';
const STATIC_CACHE = `blue-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `blue-dynamic-${CACHE_VERSION}`;
const API_CACHE = `blue-api-${CACHE_VERSION}`;
const IMAGE_CACHE = `blue-images-${CACHE_VERSION}`;
const MEDIA_CACHE = `blue-media-${CACHE_VERSION}`;

// Enhanced caching strategies
const CACHE_STRATEGIES = {
    'static': 'cache-first',
    'api': 'network-first-with-fallback',
    'images': 'cache-first-with-update',
    'pages': 'stale-while-revalidate',
    'media': 'cache-first-with-update',
    'user-content': 'network-only'
};

// Background sync tags for offline operations
const SYNC_TAGS = {
    BOOKING_SUBMIT: 'booking-sync',
    CONTACT_FORM: 'contact-sync',
    PROFILE_UPDATE: 'profile-sync',
    REVIEW_SUBMIT: 'review-sync',
    CHAT_MESSAGE: 'chat-sync',
    MEDIA_UPLOAD: 'media-sync',
    GPS_UPDATE: 'gps-sync'
};

// URLs to cache on install
const STATIC_ASSETS = [
  '/',
  '/offline.html',
  '/booking.php',
  '/help.php',
  '/customer/dashboard.php',
  '/assets/css/blue.css',
  '/liquid-glass-components.css',
  '/assets/js/booking5.js',
  '/assets/js/calendar-enhanced.js',
  '/assets/js/chat-widget.js',
  '/manifest.json'
];

// API endpoints to cache
const API_ENDPOINTS = [
  '/api/check-availability.php',
  '/api/system-config.php',
  '/api/validate-discount.php'
];

// Strategy configurations
const STRATEGIES = {
  CACHE_FIRST: 'cache-first',
  NETWORK_FIRST: 'network-first',
  STALE_WHILE_REVALIDATE: 'stale-while-revalidate',
  NETWORK_ONLY: 'network-only',
  CACHE_ONLY: 'cache-only'
};

// Route strategies
const ROUTE_STRATEGIES = [
  { pattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/i, strategy: STRATEGIES.CACHE_FIRST, cache: IMAGES_CACHE },
  { pattern: /\.(?:js|css)$/i, strategy: STRATEGIES.STALE_WHILE_REVALIDATE, cache: STATIC_CACHE },
  { pattern: /^\/api\//, strategy: STRATEGIES.NETWORK_FIRST, cache: API_CACHE },
  { pattern: /\.(?:html|php)$/i, strategy: STRATEGIES.NETWORK_FIRST, cache: CACHE_NAME }
];

// Push notification settings with enhanced options
const NOTIFICATION_CONFIG = {
  badge: '/assets/icons/badge-72x72.png',
  icon: '/assets/icons/icon-192x192.png',
  vibrate: [200, 100, 200],
  requireInteraction: true,
  silent: false
};

self.addEventListener('install', event => {
  console.log('Service Worker installing...');
  event.waitUntil(
    Promise.all([
      // Cache static assets
      caches.open(STATIC_CACHE).then(cache => {
        console.log('Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      }),
      
      // Cache API endpoints
      caches.open(API_CACHE).then(cache => {
        console.log('Pre-caching API endpoints');
        return Promise.allSettled(
          API_ENDPOINTS.map(url => 
            fetch(url).then(response => {
              if (response.ok) {
                return cache.put(url, response.clone());
              }
            }).catch(() => {
              // Ignore pre-cache failures for API endpoints
            })
          )
        );
      }),
      
      // Skip waiting to activate immediately
      self.skipWaiting()
    ])
  );
});

self.addEventListener('activate', event => {
  console.log('Service Worker activating...');
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME && 
                cacheName !== API_CACHE && 
                cacheName !== IMAGES_CACHE &&
                cacheName !== STATIC_CACHE) {
              console.log('Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),
      
      // Take control of all pages immediately
      self.clients.claim()
    ])
  );
});

// Enhanced fetch event handler with multiple strategies
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  
  // Skip non-GET requests for caching
  if (request.method !== 'GET') {
    // Handle POST requests for offline functionality
    if (request.url.includes('/api/booking/create.php')) {
      event.respondWith(handleBookingRequest(request));
    }
    return;
  }
  
  // Find matching strategy
  const matchedStrategy = ROUTE_STRATEGIES.find(route => 
    route.pattern.test(url.pathname + url.search)
  );
  
  if (matchedStrategy) {
    event.respondWith(
      executeStrategy(request, matchedStrategy.strategy, matchedStrategy.cache)
    );
  } else {
    // Default strategy
    event.respondWith(
      executeStrategy(request, STRATEGIES.NETWORK_FIRST, CACHE_NAME)
    );
  }
});

// Strategy implementations
async function executeStrategy(request, strategy, cacheName) {
  const cache = await caches.open(cacheName);
  
  switch (strategy) {
    case STRATEGIES.CACHE_FIRST:
      return cacheFirst(request, cache);
    
    case STRATEGIES.NETWORK_FIRST:
      return networkFirst(request, cache);
    
    case STRATEGIES.STALE_WHILE_REVALIDATE:
      return staleWhileRevalidate(request, cache);
    
    case STRATEGIES.NETWORK_ONLY:
      return fetch(request);
    
    case STRATEGIES.CACHE_ONLY:
      return cache.match(request);
    
    default:
      return networkFirst(request, cache);
  }
}

async function cacheFirst(request, cache) {
  const cachedResponse = await cache.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    return getOfflineResponse(request);
  }
}

async function networkFirst(request, cache) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    return getOfflineResponse(request);
  }
}

async function staleWhileRevalidate(request, cache) {
  const cachedResponse = await cache.match(request);
  
  // Always fetch from network in background
  const networkPromise = fetch(request).then(response => {
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  }).catch(() => {
    // Network failed, ignore
  });
  
  // Return cached version immediately if available
  if (cachedResponse) {
    return cachedResponse;
  }
  
  // Wait for network if no cache
  try {
    return await networkPromise;
  } catch (error) {
    return getOfflineResponse(request);
  }
}

// Handle offline responses
function getOfflineResponse(request) {
  const url = new URL(request.url);
  
  // Return cached offline page for HTML requests
  if (request.headers.get('accept').includes('text/html')) {
    return caches.match(OFFLINE_URL);
  }
  
  // Return offline JSON for API requests
  if (url.pathname.startsWith('/api/')) {
    return new Response(
      JSON.stringify({
        error: true,
        offline: true,
        message: 'This request is not available offline. Please try again when connected.'
      }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
  
  // Return generic offline response
  return new Response('Offline', {
    status: 503,
    statusText: 'Service Unavailable'
  });
}

// Enhanced booking request handler with offline support
async function handleBookingRequest(request) {
  try {
    const response = await fetch(request.clone());
    return response;
  } catch (error) {
    // Store booking request for background sync
    const formData = await request.formData();
    const bookingData = {
      timestamp: Date.now(),
      data: Object.fromEntries(formData),
      url: request.url,
      method: request.method
    };
    
    // Store in IndexedDB for background sync
    await storeForBackgroundSync(SYNC_TAGS.BOOKING_REQUEST, bookingData);
    
    // Register background sync
    if (self.registration.sync) {
      await self.registration.sync.register(SYNC_TAGS.BOOKING_REQUEST);
    }
    
    return new Response(
      JSON.stringify({
        success: false,
        offline: true,
        message: 'Your booking request has been saved and will be submitted when you\'re back online.',
        bookingId: `offline-${Date.now()}`
      }),
      {
        status: 202,
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

// Background sync event
self.addEventListener('sync', event => {
  console.log('Background sync triggered:', event.tag);
  
  switch (event.tag) {
    case SYNC_TAGS.BOOKING_REQUEST:
      event.waitUntil(syncBookingRequests());
      break;
    
    case SYNC_TAGS.CONTACT_FORM:
      event.waitUntil(syncContactForms());
      break;
    
    case SYNC_TAGS.RATING_SUBMISSION:
      event.waitUntil(syncRatings());
      break;
  }
});

// Push notification event
self.addEventListener('push', event => {
  console.log('Push notification received:', event);
  
  if (!event.data) {
    return;
  }
  
  const data = event.data.json();
  const options = {
    ...NOTIFICATION_CONFIG,
    body: data.body,
    tag: data.tag || 'blue-notification',
    data: data.data || {},
    actions: data.actions || [
      {
        action: 'view',
        title: 'View Details',
        icon: '/assets/icons/action-view.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/assets/icons/action-dismiss.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'Blue Cleaning Services', options)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event);
  
  event.notification.close();
  
  const action = event.action;
  const data = event.notification.data || {};
  
  if (action === 'dismiss') {
    return;
  }
  
  let url = '/';
  
  if (action === 'view' && data.url) {
    url = data.url;
  } else if (data.defaultUrl) {
    url = data.defaultUrl;
  }
  
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      // Try to focus existing window
      for (const client of clientList) {
        if (client.url.includes(self.location.origin)) {
          client.navigate(url);
          return client.focus();
        }
      }
      
      // Open new window
      return clients.openWindow(url);
    })
  );
});

// Background sync functions
async function syncBookingRequests() {
  const requests = await getStoredRequests(SYNC_TAGS.BOOKING_REQUEST);
  
  for (const request of requests) {
    try {
      const response = await fetch(request.url, {
        method: request.method,
        body: JSON.stringify(request.data),
        headers: {
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        await removeStoredRequest(SYNC_TAGS.BOOKING_REQUEST, request.timestamp);
        console.log('Booking request synced successfully');
        
        // Show success notification
        self.registration.showNotification('Booking Confirmed', {
          ...NOTIFICATION_CONFIG,
          body: 'Your booking request has been successfully submitted!',
          tag: 'booking-success'
        });
      }
    } catch (error) {
      console.error('Failed to sync booking request:', error);
    }
  }
}

async function syncContactForms() {
  // Similar implementation for contact forms
  console.log('Syncing contact forms...');
}

async function syncRatings() {
  // Similar implementation for ratings
  console.log('Syncing ratings...');
}

// IndexedDB helpers for background sync
async function storeForBackgroundSync(tag, data) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('BlueCleaningSyncStore', 1);
    
    request.onerror = () => reject(request.error);
    
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction([tag], 'readwrite');
      const store = transaction.objectStore(tag);
      
      store.add(data);
      transaction.oncomplete = () => resolve();
      transaction.onerror = () => reject(transaction.error);
    };
    
    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(tag)) {
        const store = db.createObjectStore(tag, { keyPath: 'timestamp' });
        store.createIndex('timestamp', 'timestamp', { unique: false });
      }
    };
  });
}

async function getStoredRequests(tag) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('BlueCleaningSyncStore', 1);
    
    request.onerror = () => reject(request.error);
    
    request.onsuccess = () => {
      const db = request.result;
      
      if (!db.objectStoreNames.contains(tag)) {
        resolve([]);
        return;
      }
      
      const transaction = db.transaction([tag], 'readonly');
      const store = transaction.objectStore(tag);
      const getAllRequest = store.getAll();
      
      getAllRequest.onsuccess = () => resolve(getAllRequest.result);
      getAllRequest.onerror = () => reject(getAllRequest.error);
    };
  });
}

async function removeStoredRequest(tag, timestamp) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('BlueCleaningSyncStore', 1);
    
    request.onerror = () => reject(request.error);
    
    request.onsuccess = () => {
      const db = request.result;
      const transaction = db.transaction([tag], 'readwrite');
      const store = transaction.objectStore(tag);
      
      store.delete(timestamp);
      transaction.oncomplete = () => resolve();
      transaction.onerror = () => reject(transaction.error);
    };
  });
}

// Periodic background sync (if supported)
self.addEventListener('periodicsync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(performPeriodicSync());
  }
});

async function performPeriodicSync() {
  console.log('Performing periodic background sync...');
  
  // Update cache with fresh data
  try {
    const cache = await caches.open(API_CACHE);
    const systemConfig = await fetch('/api/system-config.php');
    
    if (systemConfig.ok) {
      await cache.put('/api/system-config.php', systemConfig.clone());
    }
  } catch (error) {
    console.log('Periodic sync failed:', error);
  }
}

// Update available notification
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

console.log('Blue Cleaning Services Service Worker v2.0 loaded');
