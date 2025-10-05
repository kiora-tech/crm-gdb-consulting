// Service Worker for CRM-GDB Offline Mode - PWA Enhanced
// Version 2.0 - Phase 3 PWA Implementation

const VERSION = '2.0.0';
const CACHE_NAME = 'crm-gdb-v2';
const STATIC_CACHE = 'crm-gdb-static-v2';
const DYNAMIC_CACHE = 'crm-gdb-dynamic-v2';
const API_CACHE = 'crm-gdb-api-v2';
const IMAGE_CACHE = 'crm-gdb-images-v2';
const FONT_CACHE = 'crm-gdb-fonts-v2';
const OFFLINE_PAGE_CACHE = 'crm-gdb-offline-v2';

// Cache configuration
const CACHE_CONFIG = {
    static: {
        maxAge: 30 * 24 * 60 * 60 * 1000, // 30 days
        maxEntries: 100
    },
    dynamic: {
        maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
        maxEntries: 50
    },
    api: {
        maxAge: 24 * 60 * 60 * 1000, // 1 day
        maxEntries: 100
    },
    images: {
        maxAge: 30 * 24 * 60 * 60 * 1000, // 30 days
        maxEntries: 200
    },
    fonts: {
        maxAge: 365 * 24 * 60 * 60 * 1000, // 1 year
        maxEntries: 20
    }
};

// Resources to cache immediately
const STATIC_RESOURCES = [
    '/',
    '/offline.html',
    '/manifest.json',
    '/assets/styles/app.css',
    '/assets/app.js',
    '/assets/bootstrap.js',
    '/icons/pwa-192x192.png',
    '/icons/pwa-512x512.png',
    '/icons/maskable-icon-512x512.png',
    // These will be resolved dynamically based on actual asset paths
];

// API endpoints patterns to cache
const API_PATTERNS = [
    /^\/api\/customers/,
    /^\/api\/energies/,
    /^\/api\/contacts/,
    /^\/api\/comments/,
    /^\/api\/sync/
];

// Network-first patterns (always try network first, fallback to cache)
const NETWORK_FIRST_PATTERNS = [
    /^\/api\/sync/,
    /^\/login/,
    /^\/logout/
];

// Cache-first patterns (serve from cache if available, update in background)
const CACHE_FIRST_PATTERNS = [
    /\.css$/,
    /\.js$/,
    /\.woff2?$/,
    /\.ttf$/,
    /\.png$/,
    /\.jpg$/,
    /\.jpeg$/,
    /\.webp$/,
    /\.svg$/,
    /\.ico$/,
    /\.gif$/
];

// Image patterns for special handling
const IMAGE_PATTERNS = [
    /\.png$/,
    /\.jpg$/,
    /\.jpeg$/,
    /\.webp$/,
    /\.gif$/,
    /\.svg$/,
    /\.ico$/
];

// Font patterns for special handling
const FONT_PATTERNS = [
    /\.woff2?$/,
    /\.ttf$/,
    /\.otf$/,
    /\.eot$/
];

self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Service Worker: Caching static resources');
                return cache.addAll(STATIC_RESOURCES);
            })
            .then(() => {
                console.log('Service Worker: Installation complete');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Installation failed', error);
            })
    );
});

self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    // Clean up old caches
                    const currentCaches = [
                        STATIC_CACHE,
                        DYNAMIC_CACHE,
                        API_CACHE,
                        IMAGE_CACHE,
                        FONT_CACHE,
                        OFFLINE_PAGE_CACHE
                    ];
                    
                    if (!currentCaches.includes(cacheName)) {
                        console.log('Service Worker: Deleting old cache', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('Service Worker: Activation complete');
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip chrome-extension and other non-HTTP requests
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return handleNonGetRequest(event);
    }
    
    // Handle different request types
    if (isApiRequest(url.pathname)) {
        event.respondWith(handleApiRequest(request));
    } else if (isStaticResource(url.pathname)) {
        event.respondWith(handleStaticResource(request));
    } else {
        event.respondWith(handleNavigationRequest(request));
    }
});

function handleNonGetRequest(event) {
    const { request } = event;
    const url = new URL(request.url);
    
    // For offline API requests, store them for later sync
    if (isApiRequest(url.pathname)) {
        event.respondWith(handleOfflineApiRequest(request));
    }
}

async function handleOfflineApiRequest(request) {
    try {
        // Try network first
        const response = await fetch(request);
        return response;
    } catch (error) {
        // If offline, store the request for later sync
        if (request.method === 'POST' || request.method === 'PUT' || request.method === 'DELETE') {
            await storeOfflineRequest(request);
            
            // Return a response indicating the request was queued
            return new Response(JSON.stringify({
                success: true,
                queued: true,
                message: 'Request queued for sync when online'
            }), {
                status: 202,
                headers: {
                    'Content-Type': 'application/json'
                }
            });
        }
        
        throw error;
    }
}

async function storeOfflineRequest(request) {
    const requestData = {
        url: request.url,
        method: request.method,
        headers: [...request.headers.entries()],
        body: await request.text(),
        timestamp: Date.now()
    };
    
    // Store in IndexedDB for sync queue
    const db = await openIndexedDB();
    const transaction = db.transaction(['syncQueue'], 'readwrite');
    const store = transaction.objectStore('syncQueue');
    await store.add(requestData);
}

function isApiRequest(pathname) {
    return API_PATTERNS.some(pattern => pattern.test(pathname));
}

function isStaticResource(pathname) {
    return CACHE_FIRST_PATTERNS.some(pattern => pattern.test(pathname));
}

function isImageResource(pathname) {
    return IMAGE_PATTERNS.some(pattern => pattern.test(pathname));
}

function isFontResource(pathname) {
    return FONT_PATTERNS.some(pattern => pattern.test(pathname));
}

function isNetworkFirst(pathname) {
    return NETWORK_FIRST_PATTERNS.some(pattern => pattern.test(pathname));
}

async function handleApiRequest(request) {
    const url = new URL(request.url);
    
    if (isNetworkFirst(url.pathname)) {
        return handleNetworkFirstWithExpiry(request, API_CACHE, CACHE_CONFIG.api);
    }
    
    // For API requests, try cache first for GET, network first for others
    return handleCacheFirstWithExpiry(request, API_CACHE, CACHE_CONFIG.api);
}

async function handleStaticResource(request) {
    const url = new URL(request.url);
    
    // Route to appropriate cache based on resource type
    if (isImageResource(url.pathname)) {
        return handleCacheFirstWithExpiry(request, IMAGE_CACHE, CACHE_CONFIG.images);
    } else if (isFontResource(url.pathname)) {
        return handleCacheFirstWithExpiry(request, FONT_CACHE, CACHE_CONFIG.fonts);
    } else {
        return handleCacheFirstWithExpiry(request, STATIC_CACHE, CACHE_CONFIG.static);
    }
}

async function handleNavigationRequest(request) {
    return handleNetworkFirstWithExpiry(request, DYNAMIC_CACHE, CACHE_CONFIG.dynamic);
}

async function handleNetworkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            // Cache successful responses
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.log('Service Worker: Network failed, trying cache', request.url);
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // If it's a navigation request and we have no cache, return offline page
        if (request.mode === 'navigate') {
            const offlinePage = await caches.match('/offline.html');
            if (offlinePage) {
                return offlinePage;
            }
            return caches.match('/') || new Response(
                '<!DOCTYPE html><html><head><title>Offline - CRM-GDB</title><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="font-family:system-ui,sans-serif;text-align:center;padding:2rem;background:#f8f9fa"><h1 style="color:#0d6efd">You\'re offline</h1><p>This page isn\'t available offline. Please check your connection.</p><button onclick="window.location.reload()" style="background:#0d6efd;color:white;border:none;padding:0.5rem 1rem;border-radius:0.25rem;cursor:pointer">Try Again</button></body></html>', 
                { 
                    status: 503, 
                    headers: { 'Content-Type': 'text/html' }
                }
            );
        }
        
        throw error;
    }
}

async function handleCacheFirst(request, cacheName) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        // Update cache in background
        fetch(request).then(response => {
            if (response.ok) {
                const cache = caches.open(cacheName);
                cache.then(c => c.put(request, response));
            }
        }).catch(() => {
            // Ignore network errors in background update
        });
        
        return cachedResponse;
    }
    
    // If not in cache, fetch from network and cache
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        
        return response;
    } catch (error) {
        console.error('Service Worker: Failed to fetch', request.url, error);
        throw error;
    }
}

// Enhanced cache-first with expiry and size management
async function handleCacheFirstWithExpiry(request, cacheName, config) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        // Check if cached response is still valid
        const cachedDate = cachedResponse.headers.get('sw-cache-date');
        if (cachedDate) {
            const cacheAge = Date.now() - parseInt(cachedDate, 10);
            if (cacheAge > config.maxAge) {
                console.log('Service Worker: Cache expired for', request.url);
                // Remove expired entry and fetch fresh
                const cache = await caches.open(cacheName);
                cache.delete(request);
            } else {
                // Valid cached response - update in background
                updateCacheInBackground(request, cacheName);
                return cachedResponse;
            }
        } else {
            // Legacy cache entry without date - update in background
            updateCacheInBackground(request, cacheName);
            return cachedResponse;
        }
    }
    
    // Fetch from network
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            await putInCacheWithManagement(request, response.clone(), cacheName, config);
        }
        
        return response;
    } catch (error) {
        // If network fails and we have expired cache, return it anyway
        if (cachedResponse) {
            console.log('Service Worker: Network failed, using expired cache for', request.url);
            return cachedResponse;
        }
        
        console.error('Service Worker: Failed to fetch', request.url, error);
        throw error;
    }
}

// Enhanced network-first with expiry
async function handleNetworkFirstWithExpiry(request, cacheName, config) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            await putInCacheWithManagement(request, response.clone(), cacheName, config);
        }
        
        return response;
    } catch (error) {
        console.log('Service Worker: Network failed, trying cache for', request.url);
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Check expiry but be more lenient for network-first
            const cachedDate = cachedResponse.headers.get('sw-cache-date');
            if (cachedDate) {
                const cacheAge = Date.now() - parseInt(cachedDate, 10);
                // Allow using slightly expired cache for network-first (2x the normal age)
                if (cacheAge > (config.maxAge * 2)) {
                    console.log('Service Worker: Cache too old, rejecting for', request.url);
                    throw error;
                }
            }
            return cachedResponse;
        }
        
        // If it's a navigation request and we have no cache, return offline page
        if (request.mode === 'navigate') {
            const offlinePage = await caches.match('/offline.html');
            if (offlinePage) {
                return offlinePage;
            }
            return caches.match('/') || new Response(
                '<!DOCTYPE html><html><head><title>Offline - CRM-GDB</title><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="font-family:system-ui,sans-serif;text-align:center;padding:2rem;background:#f8f9fa"><h1 style="color:#0d6efd">You are offline</h1><p>This page is not available offline. Please check your connection.</p><button onclick="window.location.reload()" style="background:#0d6efd;color:white;border:none;padding:0.5rem 1rem;border-radius:0.25rem;cursor:pointer">Try Again</button></body></html>', 
                { 
                    status: 503, 
                    headers: { 'Content-Type': 'text/html' }
                }
            );
        }
        
        throw error;
    }
}

// Helper functions for cache management
async function putInCacheWithManagement(request, response, cacheName, config) {
    const cache = await caches.open(cacheName);
    
    // Add timestamp header to response
    const headers = new Headers(response.headers);
    headers.set('sw-cache-date', Date.now().toString());
    
    const responseWithDate = new Response(response.body, {
        status: response.status,
        statusText: response.statusText,
        headers: headers
    });
    
    // Put in cache
    await cache.put(request, responseWithDate);
    
    // Manage cache size
    await manageCacheSize(cacheName, config);
}

async function manageCacheSize(cacheName, config) {
    if (!config.maxEntries) return;
    
    try {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        
        if (keys.length > config.maxEntries) {
            // Sort by cache date (oldest first)
            const keysWithDates = await Promise.all(
                keys.map(async (key) => {
                    const response = await cache.match(key);
                    const cacheDate = response ? response.headers.get('sw-cache-date') : null;
                    return {
                        key,
                        date: cacheDate ? parseInt(cacheDate, 10) : 0
                    };
                })
            );
            
            keysWithDates.sort((a, b) => a.date - b.date);
            
            // Delete oldest entries
            const toDelete = keysWithDates.slice(0, keys.length - config.maxEntries);
            await Promise.all(
                toDelete.map(({ key }) => cache.delete(key))
            );
            
            console.log(`Service Worker: Cleaned ${toDelete.length} old entries from ${cacheName}`);
        }
    } catch (error) {
        console.error('Service Worker: Cache size management failed:', error);
    }
}

async function updateCacheInBackground(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            
            // Add timestamp
            const headers = new Headers(response.headers);
            headers.set('sw-cache-date', Date.now().toString());
            
            const responseWithDate = new Response(response.body, {
                status: response.status,
                statusText: response.statusText,
                headers: headers
            });
            
            await cache.put(request, responseWithDate);
        }
    } catch (error) {
        // Silently fail background updates
        console.log('Service Worker: Background update failed for', request.url);
    }
}

// IndexedDB helper functions
function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('CRM_GDB_Offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            // Create sync queue store
            if (!db.objectStoreNames.contains('syncQueue')) {
                const syncStore = db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
                syncStore.createIndex('timestamp', 'timestamp');
            }
        };
    });
}

// Listen for messages from the main thread - Enhanced PWA messaging
self.addEventListener('message', event => {
    const { data } = event;
    
    if (!data) return;
    
    switch(data.type) {
        case 'SKIP_WAITING':
            console.log('Service Worker: Skipping waiting...');
            self.skipWaiting();
            break;
            
        case 'GET_VERSION':
            event.ports[0].postMessage({ 
                version: VERSION,
                cacheVersion: CACHE_NAME,
                timestamp: Date.now()
            });
            break;
            
        case 'CLEAR_CACHE':
            console.log('Service Worker: Clearing all caches...');
            event.waitUntil(
                clearAllCaches().then(() => {
                    event.ports[0].postMessage({ success: true });
                })
            );
            break;
            
        case 'FORCE_SYNC':
            console.log('Service Worker: Forcing sync...');
            event.waitUntil(syncOfflineRequests());
            break;
            
        case 'GET_CACHE_SIZE':
            event.waitUntil(
                getCacheSize().then(size => {
                    event.ports[0].postMessage({ cacheSize: size });
                })
            );
            break;
            
        case 'GET_SYNC_QUEUE':
            event.waitUntil(
                getSyncQueueInfo().then(info => {
                    event.ports[0].postMessage({ syncQueue: info });
                })
            );
            break;
    }
});

async function clearAllCaches() {
    try {
        const cacheNames = await caches.keys();
        console.log('Service Worker: Clearing caches:', cacheNames);
        
        const results = await Promise.all(
            cacheNames.map(cacheName => caches.delete(cacheName))
        );
        
        console.log('Service Worker: All caches cleared');
        return results;
    } catch (error) {
        console.error('Service Worker: Error clearing caches:', error);
        throw error;
    }
}

// PWA utility functions
async function getCacheSize() {
    try {
        const cacheNames = await caches.keys();
        let totalSize = 0;
        
        for (const cacheName of cacheNames) {
            const cache = await caches.open(cacheName);
            const keys = await cache.keys();
            
            for (const request of keys) {
                const response = await cache.match(request);
                if (response && response.headers.get('content-length')) {
                    totalSize += parseInt(response.headers.get('content-length'), 10);
                }
            }
        }
        
        return {
            totalSize,
            cacheCount: cacheNames.length,
            humanReadable: formatBytes(totalSize)
        };
    } catch (error) {
        console.error('Service Worker: Error calculating cache size:', error);
        return { totalSize: 0, cacheCount: 0, humanReadable: '0 B' };
    }
}

async function getSyncQueueInfo() {
    try {
        const db = await openIndexedDB();
        const transaction = db.transaction(['syncQueue'], 'readonly');
        const store = transaction.objectStore('syncQueue');
        const requests = await store.getAll();
        
        return {
            queueLength: requests.length,
            oldestRequest: requests.length > 0 ? new Date(Math.min(...requests.map(r => r.timestamp))) : null,
            newestRequest: requests.length > 0 ? new Date(Math.max(...requests.map(r => r.timestamp))) : null
        };
    } catch (error) {
        console.error('Service Worker: Error getting sync queue info:', error);
        return { queueLength: 0, oldestRequest: null, newestRequest: null };
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Push notification handling for PWA
self.addEventListener('push', event => {
    console.log('Service Worker: Push notification received');
    
    let notificationData = {
        title: 'CRM-GDB Notification',
        body: 'You have a new update',
        icon: '/icons/pwa-192x192.png',
        badge: '/icons/pwa-64x64.png',
        tag: 'crm-notification',
        requireInteraction: false,
        actions: [
            {
                action: 'view',
                title: 'View',
                icon: '/icons/pwa-64x64.png'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ],
        data: {
            url: '/',
            timestamp: Date.now()
        }
    };
    
    if (event.data) {
        try {
            const pushData = event.data.json();
            notificationData = { ...notificationData, ...pushData };
        } catch (error) {
            console.error('Service Worker: Invalid push data:', error);
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, notificationData)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('Service Worker: Notification clicked');
    
    event.notification.close();
    
    const action = event.action;
    const data = event.notification.data || {};
    
    if (action === 'dismiss') {
        return;
    }
    
    // Default action or 'view' action
    const urlToOpen = data.url || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Check if there's already a window open
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(urlToOpen);
                        return client.focus();
                    }
                }
                
                // Open new window if none exists
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Handle notification close
self.addEventListener('notificationclose', event => {
    console.log('Service Worker: Notification closed');
    
    // Track notification dismissal if needed
    const data = event.notification.data || {};
    if (data.trackDismissal) {
        // Could send analytics event here
    }
});

// Background sync event - Enhanced PWA sync
self.addEventListener('sync', event => {
    console.log('Service Worker: Background sync event:', event.tag);
    
    switch(event.tag) {
        case 'background-sync':
            event.waitUntil(syncOfflineRequests());
            break;
        case 'customer-sync':
            event.waitUntil(syncCustomerData());
            break;
        case 'energy-sync':
            event.waitUntil(syncEnergyData());
            break;
        case 'form-sync':
            event.waitUntil(syncFormData());
            break;
        default:
            event.waitUntil(syncOfflineRequests());
    }
});

async function syncOfflineRequests() {
    console.log('Service Worker: Starting background sync...');
    let syncedCount = 0;
    let failedCount = 0;
    
    try {
        const db = await openIndexedDB();
        const transaction = db.transaction(['syncQueue'], 'readonly');
        const store = transaction.objectStore('syncQueue');
        const requests = await store.getAll();
        
        console.log(`Service Worker: Found ${requests.length} requests to sync`);
        
        for (const requestData of requests) {
            try {
                const headers = new Headers();
                if (requestData.headers) {
                    requestData.headers.forEach(([key, value]) => {
                        headers.append(key, value);
                    });
                }
                
                const response = await fetch(requestData.url, {
                    method: requestData.method,
                    headers: headers,
                    body: requestData.body
                });
                
                if (response.ok) {
                    // Remove from sync queue
                    const deleteTransaction = db.transaction(['syncQueue'], 'readwrite');
                    const deleteStore = deleteTransaction.objectStore('syncQueue');
                    await deleteStore.delete(requestData.id);
                    syncedCount++;
                    
                    console.log('Service Worker: Successfully synced request:', requestData.url);
                } else {
                    failedCount++;
                    console.error('Service Worker: Sync failed with status:', response.status, requestData.url);
                }
            } catch (error) {
                failedCount++;
                console.error('Service Worker: Failed to sync request:', requestData.url, error);
                
                // If request is too old (24 hours), remove it
                if (Date.now() - requestData.timestamp > 24 * 60 * 60 * 1000) {
                    const deleteTransaction = db.transaction(['syncQueue'], 'readwrite');
                    const deleteStore = deleteTransaction.objectStore('syncQueue');
                    await deleteStore.delete(requestData.id);
                    console.log('Service Worker: Removed expired sync request');
                }
            }
        }
        
        // Notify clients about sync results
        await notifyClients({
            type: 'SYNC_COMPLETE',
            synced: syncedCount,
            failed: failedCount
        });
        
        console.log(`Service Worker: Sync complete - ${syncedCount} synced, ${failedCount} failed`);
    } catch (error) {
        console.error('Service Worker: Background sync failed:', error);
        await notifyClients({
            type: 'SYNC_ERROR',
            error: error.message
        });
    }
}

// Specialized sync functions
async function syncCustomerData() {
    console.log('Service Worker: Syncing customer data...');
    await syncOfflineRequests(); // For now, use general sync
}

async function syncEnergyData() {
    console.log('Service Worker: Syncing energy data...');
    await syncOfflineRequests(); // For now, use general sync
}

async function syncFormData() {
    console.log('Service Worker: Syncing form data...');
    await syncOfflineRequests(); // For now, use general sync
}

// Helper function to notify all clients
async function notifyClients(message) {
    const clients = await self.clients.matchAll({ includeUncontrolled: true });
    clients.forEach(client => {
        client.postMessage(message);
    });
}