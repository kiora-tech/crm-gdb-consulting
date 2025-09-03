// Service Worker for CRM-GDB Offline Mode
// Version 1.0

const CACHE_NAME = 'crm-gdb-v1';
const STATIC_CACHE = 'crm-gdb-static-v1';
const DYNAMIC_CACHE = 'crm-gdb-dynamic-v1';
const API_CACHE = 'crm-gdb-api-v1';

// Resources to cache immediately
const STATIC_RESOURCES = [
    '/',
    '/assets/styles/app.css',
    '/assets/app.js',
    '/assets/bootstrap.js',
    '/img/logo.svg',
    '/img/logo.ico',
    '/img/default_user.png',
    // Bootstrap and other CSS/JS dependencies will be cached dynamically
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
    /\.png$/,
    /\.jpg$/,
    /\.jpeg$/,
    /\.svg$/,
    /\.ico$/
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
                    if (cacheName !== STATIC_CACHE && 
                        cacheName !== DYNAMIC_CACHE && 
                        cacheName !== API_CACHE) {
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

function isNetworkFirst(pathname) {
    return NETWORK_FIRST_PATTERNS.some(pattern => pattern.test(pathname));
}

async function handleApiRequest(request) {
    const url = new URL(request.url);
    
    if (isNetworkFirst(url.pathname)) {
        return handleNetworkFirst(request, API_CACHE);
    }
    
    // For API requests, try cache first for GET, network first for others
    return handleCacheFirst(request, API_CACHE);
}

async function handleStaticResource(request) {
    return handleCacheFirst(request, STATIC_CACHE);
}

async function handleNavigationRequest(request) {
    return handleNetworkFirst(request, DYNAMIC_CACHE);
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
            return caches.match('/') || new Response('Offline', { status: 503 });
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

// Listen for messages from the main thread
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(clearAllCaches());
        event.ports[0].postMessage({ success: true });
    }
});

async function clearAllCaches() {
    const cacheNames = await caches.keys();
    return Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
    );
}

// Background sync event
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(syncOfflineRequests());
    }
});

async function syncOfflineRequests() {
    try {
        const db = await openIndexedDB();
        const transaction = db.transaction(['syncQueue'], 'readonly');
        const store = transaction.objectStore('syncQueue');
        const requests = await store.getAll();
        
        for (const requestData of requests) {
            try {
                const response = await fetch(requestData.url, {
                    method: requestData.method,
                    headers: requestData.headers,
                    body: requestData.body
                });
                
                if (response.ok) {
                    // Remove from sync queue
                    const deleteTransaction = db.transaction(['syncQueue'], 'readwrite');
                    const deleteStore = deleteTransaction.objectStore('syncQueue');
                    await deleteStore.delete(requestData.id);
                }
            } catch (error) {
                console.error('Service Worker: Failed to sync request', error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Background sync failed', error);
    }
}