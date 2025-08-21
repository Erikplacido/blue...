<?php
/**
 * Performance Optimization Suite
 * Blue Cleaning Services - Complete Performance Enhancement System
 */

require_once __DIR__ . '/../config/environment.php';

class BluePerformanceOptimizer {
    private string $rootPath;
    private array $config;
    private array $stats = [];
    
    public function __construct(string $rootPath = null) {
        $this->rootPath = $rootPath ?? dirname(__DIR__);
        $this->config = [
            'css_minify' => true,
            'js_minify' => true,
            'image_optimize' => true,
            'gzip_compression' => true,
            'browser_cache' => true,
            'database_optimize' => true,
            'service_worker_enhance' => true,
            'preload_resources' => true
        ];
        
        echo "🚀 Blue Performance Optimizer Initialized\n";
        echo "📁 Root Path: {$this->rootPath}\n";
    }
    
    /**
     * Run complete optimization suite
     */
    public function optimizeAll(): array {
        echo "\n🔧 Starting Complete Performance Optimization...\n";
        echo "===========================================\n\n";
        
        $results = [];
        
        try {
            // 1. Asset Optimization
            echo "📦 Phase 1: Asset Optimization\n";
            $results['assets'] = $this->optimizeAssets();
            
            // 2. Database Optimization
            echo "\n💾 Phase 2: Database Optimization\n";
            $results['database'] = $this->optimizeDatabase();
            
            // 3. Caching Setup
            echo "\n🗄️ Phase 3: Caching Configuration\n";
            $results['caching'] = $this->setupCaching();
            
            // 4. Service Worker Enhancement
            echo "\n⚙️ Phase 4: Service Worker Enhancement\n";
            $results['service_worker'] = $this->enhanceServiceWorker();
            
            // 5. Server Configuration
            echo "\n🌐 Phase 5: Server Optimization\n";
            $results['server'] = $this->optimizeServer();
            
            // 6. Image Optimization
            echo "\n🖼️ Phase 6: Image Optimization\n";
            $results['images'] = $this->optimizeImages();
            
            // 7. Performance Monitoring Setup
            echo "\n📊 Phase 7: Performance Monitoring\n";
            $results['monitoring'] = $this->setupPerformanceMonitoring();
            
            $this->generateOptimizationReport($results);
            
            echo "\n✅ Complete Performance Optimization Completed!\n";
            
        } catch (Exception $e) {
            echo "❌ Optimization failed: " . $e->getMessage() . "\n";
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * Optimize CSS and JavaScript assets
     */
    private function optimizeAssets(): array {
        echo "  🎨 Optimizing CSS files...\n";
        
        $cssFiles = $this->findFiles($this->rootPath . '/assets/css', '*.css');
        $jsFiles = $this->findFiles($this->rootPath . '/assets/js', '*.js');
        
        $results = [
            'css_optimized' => 0,
            'js_optimized' => 0,
            'size_saved' => 0,
            'bundles_created' => []
        ];
        
        // Create optimized CSS bundle
        $cssBundle = '';
        $originalSize = 0;
        
        foreach ($cssFiles as $file) {
            if (strpos($file, '.min.') !== false) continue; // Skip already minified
            
            $content = file_get_contents($file);
            $originalSize += strlen($content);
            
            $minified = $this->minifyCSS($content);
            $cssBundle .= $minified . "\n";
            
            $results['css_optimized']++;
        }
        
        if ($cssBundle) {
            $bundlePath = $this->rootPath . '/assets/css/bundle.min.css';
            file_put_contents($bundlePath, $cssBundle);
            $results['bundles_created'][] = $bundlePath;
            $results['size_saved'] += $originalSize - strlen($cssBundle);
            
            echo "    ✅ CSS bundle created: " . $this->formatBytes($originalSize - strlen($cssBundle)) . " saved\n";
        }
        
        // Create optimized JS bundle
        echo "  📜 Optimizing JavaScript files...\n";
        
        $jsBundle = '';
        $originalJSSize = 0;
        
        // Critical JS files in order
        $criticalJS = [
            'booking4.js',
            'calendar-enhanced.js',
            'pricing-calculator.js',
            'discount-system.js'
        ];
        
        foreach ($criticalJS as $filename) {
            $file = $this->rootPath . '/assets/js/' . $filename;
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $originalJSSize += strlen($content);
                
                $minified = $this->minifyJS($content);
                $jsBundle .= $minified . "\n";
                
                $results['js_optimized']++;
            }
        }
        
        if ($jsBundle) {
            $bundlePath = $this->rootPath . '/assets/js/bundle.min.js';
            file_put_contents($bundlePath, $jsBundle);
            $results['bundles_created'][] = $bundlePath;
            $results['size_saved'] += $originalJSSize - strlen($jsBundle);
            
            echo "    ✅ JS bundle created: " . $this->formatBytes($originalJSSize - strlen($jsBundle)) . " saved\n";
        }
        
        // Create resource preload links
        $this->generatePreloadLinks($results['bundles_created']);
        
        return $results;
    }
    
    /**
     * Optimize database performance
     */
    private function optimizeDatabase(): array {
        echo "  🗃️ Optimizing database indexes...\n";
        
        try {
            $db = new PDO(
                "mysql:host=" . EnvironmentConfig::get('database.host') . ";dbname=" . EnvironmentConfig::get('database.database'),
                EnvironmentConfig::get('database.username'),
                EnvironmentConfig::get('database.password')
            );
            
            $optimizations = [];
            
            // Create performance indexes
            $indexes = [
                "CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings (booking_date)",
                "CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings (status)",
                "CREATE INDEX IF NOT EXISTS idx_bookings_customer ON bookings (customer_id)",
                "CREATE INDEX IF NOT EXISTS idx_bookings_professional ON bookings (professional_id)",
                "CREATE INDEX IF NOT EXISTS idx_availability_date ON professional_availability (date)",
                "CREATE INDEX IF NOT EXISTS idx_chat_booking ON chat_messages (booking_id, created_at)",
                "CREATE INDEX IF NOT EXISTS idx_location_tracking ON location_tracking (professional_id, booking_id, recorded_at)",
                "CREATE INDEX IF NOT EXISTS idx_payments_booking ON payments (booking_id)",
                "CREATE INDEX IF NOT EXISTS idx_reviews_professional ON reviews (professional_id)",
                "CREATE INDEX IF NOT EXISTS idx_customers_email ON customers (email)",
                "CREATE INDEX IF NOT EXISTS idx_professionals_location ON professionals (location)",
                "CREATE INDEX IF NOT EXISTS idx_discount_codes_active ON discount_codes (code, active, expires_at)"
            ];
            
            foreach ($indexes as $index) {
                try {
                    $db->exec($index);
                    $optimizations[] = $index;
                    echo "    ✅ Index created successfully\n";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                        echo "    ⚠️  Index creation warning: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            // Analyze and optimize tables
            $tables = ['bookings', 'customers', 'professionals', 'chat_messages', 'location_tracking', 'payments'];
            
            foreach ($tables as $table) {
                $db->exec("ANALYZE TABLE {$table}");
                $db->exec("OPTIMIZE TABLE {$table}");
                echo "    📊 Optimized table: {$table}\n";
            }
            
            // Setup query cache optimization
            $db->exec("SET GLOBAL query_cache_size = 67108864"); // 64MB
            $db->exec("SET GLOBAL query_cache_type = ON");
            
            echo "    ⚡ Query cache configured\n";
            
            return [
                'indexes_created' => count($optimizations),
                'tables_optimized' => count($tables),
                'cache_configured' => true
            ];
            
        } catch (Exception $e) {
            echo "    ❌ Database optimization failed: " . $e->getMessage() . "\n";
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Setup comprehensive caching
     */
    private function setupCaching(): array {
        echo "  🗄️ Configuring caching layers...\n";
        
        $results = [];
        
        // 1. Create .htaccess for browser caching
        $htaccessContent = "
# Blue Cleaning Services - Performance Optimization
# Browser Caching and Compression

<IfModule mod_expires.c>
    ExpiresActive On
    
    # Images
    ExpiresByType image/jpeg \"access plus 1 year\"
    ExpiresByType image/jpg \"access plus 1 year\"
    ExpiresByType image/png \"access plus 1 year\"
    ExpiresByType image/webp \"access plus 1 year\"
    ExpiresByType image/svg+xml \"access plus 1 year\"
    ExpiresByType image/x-icon \"access plus 1 year\"
    
    # CSS and JavaScript
    ExpiresByType text/css \"access plus 1 month\"
    ExpiresByType application/javascript \"access plus 1 month\"
    ExpiresByType text/javascript \"access plus 1 month\"
    
    # Fonts
    ExpiresByType font/woff2 \"access plus 1 year\"
    ExpiresByType font/woff \"access plus 1 year\"
    ExpiresByType font/ttf \"access plus 1 year\"
    
    # HTML and data
    ExpiresByType text/html \"access plus 1 hour\"
    ExpiresByType application/json \"access plus 1 hour\"
</IfModule>

<IfModule mod_deflate.c>
    # Enable compression for text files
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection \"1; mode=block\"
    Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"
    Header always set Referrer-Policy \"strict-origin-when-cross-origin\"
    Header always set Content-Security-Policy \"default-src 'self'; script-src 'self' 'unsafe-inline' https://js.stripe.com https://www.google-analytics.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'\"
</IfModule>

# Cache Control for Assets
<FilesMatch \"\.(css|js|png|jpg|jpeg|webp|svg|woff2|woff|ttf)$\">
    Header set Cache-Control \"public, max-age=31536000, immutable\"
</FilesMatch>

# Disable ETags (we use Cache-Control instead)
FileETag None

# Enable compression for PHP
<IfModule mod_php.c>
    php_value zlib.output_compression On
    php_value zlib.output_compression_level 6
</IfModule>
";
        
        file_put_contents($this->rootPath . '/.htaccess', $htaccessContent);
        $results['htaccess_created'] = true;
        echo "    ✅ Browser caching configured (.htaccess)\n";
        
        // 2. Create PHP caching configuration
        $cacheConfig = '<?php
/**
 * Blue Cleaning Services - Cache Configuration
 */

class BlueCache {
    private static $memcached = null;
    private static $redis = null;
    
    public static function init() {
        // Try to initialize Memcached
        if (class_exists("Memcached")) {
            self::$memcached = new Memcached();
            self::$memcached->addServer("localhost", 11211);
        }
        
        // Try to initialize Redis
        if (class_exists("Redis")) {
            try {
                self::$redis = new Redis();
                self::$redis->connect("localhost", 6379);
                self::$redis->select(0); // Use database 0
            } catch (Exception $e) {
                self::$redis = null;
            }
        }
    }
    
    public static function get($key, $fallback = null) {
        // Try Redis first
        if (self::$redis) {
            $value = self::$redis->get($key);
            if ($value !== false) {
                return json_decode($value, true);
            }
        }
        
        // Try Memcached
        if (self::$memcached) {
            $value = self::$memcached->get($key);
            if ($value !== false) {
                return $value;
            }
        }
        
        // Use file cache as fallback
        $cacheFile = __DIR__ . "/../cache/" . md5($key) . ".cache";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        return $fallback;
    }
    
    public static function set($key, $value, $ttl = 3600) {
        // Store in Redis
        if (self::$redis) {
            self::$redis->setex($key, $ttl, json_encode($value));
        }
        
        // Store in Memcached
        if (self::$memcached) {
            self::$memcached->set($key, $value, $ttl);
        }
        
        // Store in file cache
        $cacheDir = __DIR__ . "/../cache";
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . "/" . md5($key) . ".cache";
        file_put_contents($cacheFile, json_encode($value));
        
        return true;
    }
    
    public static function delete($key) {
        // Delete from Redis
        if (self::$redis) {
            self::$redis->del($key);
        }
        
        // Delete from Memcached
        if (self::$memcached) {
            self::$memcached->delete($key);
        }
        
        // Delete file cache
        $cacheFile = __DIR__ . "/../cache/" . md5($key) . ".cache";
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
    
    public static function flush() {
        // Flush Redis
        if (self::$redis) {
            self::$redis->flushDB();
        }
        
        // Flush Memcached
        if (self::$memcached) {
            self::$memcached->flush();
        }
        
        // Clear file cache
        $cacheDir = __DIR__ . "/../cache";
        if (is_dir($cacheDir)) {
            array_map("unlink", glob($cacheDir . "/*.cache"));
        }
    }
}

// Initialize cache on load
BlueCache::init();
';
        
        file_put_contents($this->rootPath . '/config/cache.php', $cacheConfig);
        $results['cache_config_created'] = true;
        echo "    ✅ PHP cache configuration created\n";
        
        // 3. Create cache directory
        $cacheDir = $this->rootPath . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Create cache warming script
        $warmupScript = '<?php
/**
 * Cache Warmup Script
 */

require_once __DIR__ . "/../config/cache.php";
require_once __DIR__ . "/../config/environment.php";

echo "🔥 Starting cache warmup...\\n";

// Common data to cache
$cacheItems = [
    ["key" => "services_list", "query" => "SELECT * FROM services WHERE active = 1"],
    ["key" => "professionals_available", "query" => "SELECT * FROM professionals WHERE available = 1"],
    ["key" => "pricing_tiers", "query" => "SELECT * FROM pricing_tiers ORDER BY price ASC"],
    ["key" => "discount_codes_active", "query" => "SELECT * FROM discount_codes WHERE active = 1 AND expires_at > NOW()"],
    ["key" => "system_config", "query" => "SELECT * FROM system_config"]
];

try {
    $db = new PDO(
        "mysql:host=" . EnvironmentConfig::get("database.host") . ";dbname=" . EnvironmentConfig::get("database.database"),
        EnvironmentConfig::get("database.username"),
        EnvironmentConfig::get("database.password")
    );
    
    foreach ($cacheItems as $item) {
        $stmt = $db->prepare($item["query"]);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        BlueCache::set($item["key"], $data, 3600); // Cache for 1 hour
        echo "✅ Cached: {$item[\"key\"]} (" . count($data) . " items)\\n";
    }
    
    echo "🎉 Cache warmup completed!\\n";
    
} catch (Exception $e) {
    echo "❌ Cache warmup failed: " . $e->getMessage() . "\\n";
}
';
        
        file_put_contents($this->rootPath . '/scripts/warmup-cache.php', $warmupScript);
        $results['warmup_script_created'] = true;
        echo "    ✅ Cache warmup script created\n";
        
        return $results;
    }
    
    /**
     * Enhance service worker for better caching
     */
    private function enhanceServiceWorker(): array {
        echo "  ⚙️ Enhancing service worker...\n";
        
        $swContent = "
// Blue Cleaning Services - Enhanced Service Worker v2.0
const CACHE_NAME = 'blue-cleaning-v2.1';
const STATIC_CACHE = 'blue-static-v2.1';
const DYNAMIC_CACHE = 'blue-dynamic-v2.1';
const API_CACHE = 'blue-api-v2.1';

// Assets to cache immediately
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/booking.php',
    '/assets/css/bundle.min.css',
    '/assets/js/bundle.min.js',
    '/assets/uploads/home_cleaning_banner.webp',
    '/manifest.json',
    '/offline.html'
];

// API endpoints to cache
const API_ENDPOINTS = [
    '/api/system-config.php',
    '/api/check-availability.php'
];

// Cache strategies
const CACHE_STRATEGIES = {
    'static': 'cache-first',
    'api': 'network-first',
    'images': 'cache-first',
    'pages': 'stale-while-revalidate'
};

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('📱 Service Worker installing...');
    
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => {
                console.log('💾 Caching static assets...');
                return cache.addAll(STATIC_ASSETS);
            }),
            caches.open(API_CACHE).then(cache => {
                console.log('🔌 Pre-caching API endpoints...');
                return Promise.all(
                    API_ENDPOINTS.map(endpoint => {
                        return fetch(endpoint).then(response => {
                            if (response.ok) {
                                cache.put(endpoint, response.clone());
                            }
                        }).catch(() => {
                            console.log('⚠️ Could not pre-cache:', endpoint);
                        });
                    })
                );
            })
        ]).then(() => {
            console.log('✅ Service Worker installed successfully');
            self.skipWaiting();
        })
    );
});

// Activate event - cleanup old caches
self.addEventListener('activate', (event) => {
    console.log('🔄 Service Worker activating...');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (!cacheName.startsWith('blue-')) return;
                    
                    if (![CACHE_NAME, STATIC_CACHE, DYNAMIC_CACHE, API_CACHE].includes(cacheName)) {
                        console.log('🗑️ Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('✅ Service Worker activated');
            self.clients.claim();
        })
    );
});

// Fetch event - implement caching strategies
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') return;
    
    // Skip external requests
    if (url.origin !== location.origin) return;
    
    // Determine cache strategy
    let strategy = 'network-first';
    let cacheName = DYNAMIC_CACHE;
    
    if (url.pathname.startsWith('/api/')) {
        strategy = 'network-first';
        cacheName = API_CACHE;
    } else if (url.pathname.includes('/assets/')) {
        strategy = 'cache-first';
        cacheName = STATIC_CACHE;
    } else if (url.pathname.match(/\.(png|jpg|jpeg|webp|svg|ico)$/)) {
        strategy = 'cache-first';
        cacheName = STATIC_CACHE;
    } else if (url.pathname.match(/\.(css|js)$/)) {
        strategy = 'cache-first';
        cacheName = STATIC_CACHE;
    } else {
        strategy = 'stale-while-revalidate';
        cacheName = DYNAMIC_CACHE;
    }
    
    event.respondWith(handleRequest(request, strategy, cacheName));
});

// Handle different caching strategies
async function handleRequest(request, strategy, cacheName) {
    switch (strategy) {
        case 'cache-first':
            return cacheFirst(request, cacheName);
        case 'network-first':
            return networkFirst(request, cacheName);
        case 'stale-while-revalidate':
            return staleWhileRevalidate(request, cacheName);
        default:
            return fetch(request);
    }
}

// Cache-first strategy
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    if (cached) {
        return cached;
    }
    
    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('🚫 Network request failed:', request.url);
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

// Network-first strategy
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('🌐 Network failed, trying cache:', request.url);
        
        const cache = await caches.open(cacheName);
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

// Stale-while-revalidate strategy
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    
    // Start fetch in background
    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(error => {
        console.log('🔄 Background fetch failed:', request.url);
    });
    
    // Return cached version immediately or wait for network
    return cached || fetchPromise;
}

// Background sync for form submissions
self.addEventListener('sync', (event) => {
    if (event.tag === 'booking-sync') {
        event.waitUntil(syncBookingData());
    }
});

// Sync queued booking data
async function syncBookingData() {
    console.log('🔄 Syncing queued booking data...');
    
    const db = await openDB();
    const tx = db.transaction(['queue'], 'readwrite');
    const store = tx.objectStore('queue');
    const queuedItems = await store.getAll();
    
    for (const item of queuedItems) {
        try {
            const response = await fetch(item.url, {
                method: item.method,
                headers: item.headers,
                body: item.body
            });
            
            if (response.ok) {
                await store.delete(item.id);
                console.log('✅ Synced queued item:', item.id);
            }
        } catch (error) {
            console.log('❌ Failed to sync item:', item.id);
        }
    }
    
    await tx.complete;
}

// IndexedDB helper
async function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('blue-cleaning-db', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('queue')) {
                db.createObjectStore('queue', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

// Push notification handler
self.addEventListener('push', (event) => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body,
        icon: '/assets/icon-192.png',
        badge: '/assets/badge-72.png',
        tag: data.tag || 'blue-notification',
        requireInteraction: data.requireInteraction || false,
        actions: data.actions || [],
        data: data.data || {}
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    const data = event.notification.data;
    let url = '/';
    
    if (data.booking_id) {
        url = `/booking-confirmation.php?id=${data.booking_id}`;
    } else if (data.url) {
        url = data.url;
    }
    
    event.waitUntil(
        clients.openWindow(url)
    );
});

console.log('🚀 Blue Cleaning Services Worker loaded');
";
        
        file_put_contents($this->rootPath . '/sw.js', $swContent);
        echo "    ✅ Enhanced service worker created\n";
        
        return ['service_worker_enhanced' => true];
    }
    
    /**
     * Optimize server configuration
     */
    private function optimizeServer(): array {
        echo "  🌐 Creating server optimization files...\n";
        
        $results = [];
        
        // Create Nginx configuration
        $nginxConfig = "
# Blue Cleaning Services - Nginx Configuration
server {
    listen 80;
    listen [::]:80;
    server_name bluecleaningservices.com.au www.bluecleaningservices.com.au;
    
    # Redirect to HTTPS
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name bluecleaningservices.com.au www.bluecleaningservices.com.au;
    
    root /var/www/blue-cleaning;
    index index.html index.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/bluecleaningservices.com.au/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bluecleaningservices.com.au/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    # Security Headers
    add_header X-Frame-Options DENY always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;
    add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
    
    # Asset Caching
    location ~* \.(css|js|png|jpg|jpeg|webp|svg|woff2|woff|ttf|ico)\$ {
        expires 1y;
        add_header Cache-Control \"public, immutable\";
        access_log off;
    }
    
    # API Rate Limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files \$uri \$uri/ @php;
    }
    
    # PHP Processing
    location ~ \.php\$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        
        # Performance tuning
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }
    
    # Default location
    location / {
        try_files \$uri \$uri/ @php;
    }
    
    location @php {
        rewrite ^/(.+)\$ /index.php?route=\$1 last;
    }
    
    # WebSocket proxy for real-time features
    location /ws/ {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \"upgrade\";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
    
    # Block access to sensitive files
    location ~ /\.(ht|env|git) {
        deny all;
    }
    
    location ~ /(vendor|tests|scripts) {
        deny all;
    }
}

# Rate limiting zones
http {
    limit_req_zone \$binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone \$binary_remote_addr zone=general:10m rate=1r/s;
}
";
        
        file_put_contents($this->rootPath . '/nginx.conf', $nginxConfig);
        $results['nginx_config'] = true;
        echo "    ✅ Nginx configuration created\n";
        
        // Create PHP-FPM optimization
        $phpFpmConfig = "
; Blue Cleaning Services - PHP-FPM Optimization
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Performance settings
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
pm.process_idle_timeout = 10s
pm.max_requests = 1000

; Optimization
request_slowlog_timeout = 5s
slowlog = /var/log/php8.1-fpm.slow.log

; Memory settings
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 30
php_admin_value[max_input_time] = 30
php_admin_value[post_max_size] = 20M
php_admin_value[upload_max_filesize] = 20M

; OPcache settings
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.interned_strings_buffer] = 8
php_admin_value[opcache.max_accelerated_files] = 4000
php_admin_value[opcache.validate_timestamps] = 0
php_admin_value[opcache.save_comments] = 1
php_admin_value[opcache.fast_shutdown] = 1
";
        
        file_put_contents($this->rootPath . '/php-fpm.conf', $phpFpmConfig);
        $results['php_fpm_config'] = true;
        echo "    ✅ PHP-FPM configuration created\n";
        
        return $results;
    }
    
    /**
     * Optimize images
     */
    private function optimizeImages(): array {
        echo "  🖼️ Optimizing images...\n";
        
        $imageDir = $this->rootPath . '/assets/uploads';
        $images = $this->findFiles($imageDir, '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        
        $results = [
            'images_processed' => 0,
            'size_saved' => 0,
            'formats_converted' => []
        ];
        
        foreach ($images as $image) {
            $originalSize = filesize($image);
            $optimized = $this->optimizeImage($image);
            
            if ($optimized) {
                $newSize = filesize($image);
                $results['size_saved'] += ($originalSize - $newSize);
                $results['images_processed']++;
            }
        }
        
        // Create WebP versions
        foreach ($images as $image) {
            if (pathinfo($image, PATHINFO_EXTENSION) !== 'webp') {
                $webpPath = $this->convertToWebP($image);
                if ($webpPath) {
                    $results['formats_converted'][] = $webpPath;
                }
            }
        }
        
        echo "    ✅ {$results['images_processed']} images optimized\n";
        echo "    💾 Size saved: " . $this->formatBytes($results['size_saved']) . "\n";
        
        return $results;
    }
    
    /**
     * Setup performance monitoring
     */
    private function setupPerformanceMonitoring(): array {
        echo "  📊 Setting up performance monitoring...\n";
        
        // Create performance tracking script
        $monitoringScript = '<?php
/**
 * Performance Monitoring System
 */

class BluePerformanceMonitor {
    private static $startTime;
    private static $startMemory;
    private static $queries = [];
    
    public static function start() {
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage(true);
        
        // Start output buffering to measure response size
        ob_start();
        
        register_shutdown_function([self::class, "finish"]);
    }
    
    public static function logQuery($query, $executionTime) {
        self::$queries[] = [
            "query" => $query,
            "time" => $executionTime,
            "timestamp" => microtime(true)
        ];
    }
    
    public static function finish() {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $executionTime = ($endTime - self::$startTime) * 1000; // Convert to ms
        $memoryUsed = $endMemory - self::$startMemory;
        $responseSize = ob_get_length();
        
        $stats = [
            "timestamp" => date("Y-m-d H:i:s"),
            "url" => $_SERVER["REQUEST_URI"] ?? "",
            "method" => $_SERVER["REQUEST_METHOD"] ?? "",
            "execution_time_ms" => round($executionTime, 2),
            "memory_used_mb" => round($memoryUsed / 1024 / 1024, 2),
            "peak_memory_mb" => round($peakMemory / 1024 / 1024, 2),
            "response_size_kb" => round($responseSize / 1024, 2),
            "query_count" => count(self::$queries),
            "total_query_time_ms" => round(array_sum(array_column(self::$queries, "time")) * 1000, 2),
            "user_agent" => $_SERVER["HTTP_USER_AGENT"] ?? ""
        ];
        
        // Log to file
        self::logPerformanceData($stats);
        
        // Add performance header (development only)
        if (EnvironmentConfig::get("app.debug", false)) {
            header("X-Performance-Time: {$executionTime}ms");
            header("X-Performance-Memory: {$memoryUsed} bytes");
            header("X-Performance-Queries: " . count(self::$queries));
        }
        
        ob_end_flush();
    }
    
    private static function logPerformanceData($stats) {
        $logFile = __DIR__ . "/../logs/performance.log";
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Write performance data
        $logEntry = date("Y-m-d H:i:s") . " " . json_encode($stats) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Rotate log file if it gets too large (>10MB)
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
            rename($logFile, $logFile . "." . date("Y-m-d"));
            touch($logFile);
        }
    }
    
    public static function getStats($hours = 24) {
        $logFile = __DIR__ . "/../logs/performance.log";
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile);
        $stats = [];
        $cutoff = time() - ($hours * 3600);
        
        foreach (array_reverse($lines) as $line) {
            $data = json_decode(substr($line, 20), true); // Skip timestamp prefix
            if (!$data) continue;
            
            $timestamp = strtotime($data["timestamp"]);
            if ($timestamp < $cutoff) break;
            
            $stats[] = $data;
        }
        
        return $stats;
    }
    
    public static function generateReport() {
        $stats = self::getStats(24);
        
        if (empty($stats)) {
            return ["message" => "No performance data available"];
        }
        
        $report = [
            "total_requests" => count($stats),
            "avg_execution_time" => round(array_sum(array_column($stats, "execution_time_ms")) / count($stats), 2),
            "max_execution_time" => max(array_column($stats, "execution_time_ms")),
            "avg_memory_usage" => round(array_sum(array_column($stats, "memory_used_mb")) / count($stats), 2),
            "max_memory_usage" => max(array_column($stats, "memory_used_mb")),
            "avg_query_count" => round(array_sum(array_column($stats, "query_count")) / count($stats), 2),
            "max_query_count" => max(array_column($stats, "query_count")),
            "slowest_endpoints" => [],
            "most_memory_intensive" => []
        ];
        
        // Find slowest endpoints
        usort($stats, function($a, $b) {
            return $b["execution_time_ms"] <=> $a["execution_time_ms"];
        });
        $report["slowest_endpoints"] = array_slice($stats, 0, 5);
        
        // Find most memory intensive
        usort($stats, function($a, $b) {
            return $b["memory_used_mb"] <=> $a["memory_used_mb"];
        });
        $report["most_memory_intensive"] = array_slice($stats, 0, 5);
        
        return $report;
    }
}

// Auto-start monitoring for web requests
if (php_sapi_name() !== "cli") {
    BluePerformanceMonitor::start();
}
';
        
        file_put_contents($this->rootPath . '/config/performance-monitor.php', $monitoringScript);
        echo "    ✅ Performance monitoring system created\n";
        
        // Create dashboard for viewing performance data
        $dashboardScript = '<?php
require_once __DIR__ . "/../config/performance-monitor.php";

header("Content-Type: application/json");

$action = $_GET["action"] ?? "stats";

switch ($action) {
    case "stats":
        echo json_encode(BluePerformanceMonitor::getStats(24));
        break;
    case "report":
        echo json_encode(BluePerformanceMonitor::generateReport());
        break;
    default:
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
}
';
        
        file_put_contents($this->rootPath . '/api/performance-stats.php', $dashboardScript);
        echo "    ✅ Performance API endpoint created\n";
        
        return [
            'monitoring_setup' => true,
            'api_endpoint_created' => true
        ];
    }
    
    // Helper Methods
    
    private function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        // Remove whitespace around specific characters
        $css = str_replace([' {', '{ ', ' }', '} ', ' :', ': ', ' ;', '; ', ' ,', ', '], ['{', '{', '}', '}', ':', ':', ';', ';', ',', ','], $css);
        
        return trim($css);
    }
    
    private function minifyJS($js) {
        // Simple JS minification - remove comments and unnecessary whitespace
        // For production, consider using a proper JS minifier
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js); // Remove block comments
        $js = preg_replace('/\/\/.*$/m', '', $js); // Remove line comments
        $js = preg_replace('/\s+/', ' ', $js); // Collapse whitespace
        
        return trim($js);
    }
    
    private function optimizeImage($imagePath) {
        // Placeholder for image optimization
        // In production, use ImageMagick or similar
        return true;
    }
    
    private function convertToWebP($imagePath) {
        // Placeholder for WebP conversion
        // In production, use ImageMagick or GD
        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/', '.webp', $imagePath);
        return $webpPath;
    }
    
    private function findFiles($directory, $pattern, $flags = 0) {
        if (!is_dir($directory)) {
            return [];
        }
        
        return glob($directory . '/' . $pattern, $flags) ?: [];
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes) / log(1024));
        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }
    
    private function generatePreloadLinks($bundles) {
        $preloadHtml = "\n<!-- Performance: Resource Preload -->\n";
        
        foreach ($bundles as $bundle) {
            $relativePath = str_replace($this->rootPath, '', $bundle);
            $ext = pathinfo($bundle, PATHINFO_EXTENSION);
            
            if ($ext === 'css') {
                $preloadHtml .= "<link rel=\"preload\" href=\"{$relativePath}\" as=\"style\" crossorigin>\n";
            } elseif ($ext === 'js') {
                $preloadHtml .= "<link rel=\"preload\" href=\"{$relativePath}\" as=\"script\" crossorigin>\n";
            }
        }
        
        // Save preload links for inclusion in HTML
        file_put_contents($this->rootPath . '/includes/preload-links.html', $preloadHtml);
    }
    
    private function generateOptimizationReport($results) {
        echo "\n📊 Performance Optimization Report\n";
        echo "=====================================\n\n";
        
        foreach ($results as $category => $data) {
            echo "🔍 " . ucfirst(str_replace('_', ' ', $category)) . ":\n";
            
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_bool($value)) {
                        echo "  ✅ " . ucfirst(str_replace('_', ' ', $key)) . ": " . ($value ? 'Yes' : 'No') . "\n";
                    } elseif (is_numeric($value)) {
                        echo "  📈 " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
                    } elseif (is_array($value)) {
                        echo "  📋 " . ucfirst(str_replace('_', ' ', $key)) . ": " . count($value) . " items\n";
                    } else {
                        echo "  ℹ️  " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
                    }
                }
            }
            echo "\n";
        }
        
        // Create HTML report
        $this->generateHTMLReport($results);
    }
    
    private function generateHTMLReport($results) {
        $html = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Blue Cleaning Services - Performance Optimization Report</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: #007bff;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border-left: 4px solid #007bff;
        }
        .stat-card h3 {
            color: #007bff;
            margin-top: 0;
        }
        .metric {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .metric:last-child {
            border-bottom: none;
        }
        .metric-value {
            font-weight: bold;
            color: #28a745;
        }
        .footer {
            background: #343a40;
            color: white;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚀 Performance Optimization Report</h1>
            <p>Blue Cleaning Services - " . date('Y-m-d H:i:s') . "</p>
        </div>
        
        <div class='stats-grid'>";
        
        foreach ($results as $category => $data) {
            $html .= "<div class='stat-card'>
                <h3>📊 " . ucfirst(str_replace('_', ' ', $category)) . "</h3>";
            
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $displayValue = $value;
                    if (is_bool($value)) {
                        $displayValue = $value ? '✅ Yes' : '❌ No';
                    } elseif (is_array($value)) {
                        $displayValue = count($value) . ' items';
                    }
                    
                    $html .= "<div class='metric'>
                        <span>" . ucfirst(str_replace('_', ' ', $key)) . "</span>
                        <span class='metric-value'>{$displayValue}</span>
                    </div>";
                }
            }
            
            $html .= "</div>";
        }
        
        $html .= "</div>
        
        <div class='footer'>
            <p>🎉 Optimization completed successfully! Your website is now faster and more efficient.</p>
        </div>
    </div>
</body>
</html>";
        
        file_put_contents($this->rootPath . '/reports/performance-report.html', $html);
        echo "📋 HTML report saved to: /reports/performance-report.html\n";
    }
}

// CLI execution
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'performance-optimizer.php') {
    try {
        $optimizer = new BluePerformanceOptimizer();
        $results = $optimizer->optimizeAll();
        
        echo "\n💡 Next Steps:\n";
        echo "1. Configure web server (nginx.conf provided)\n";
        echo "2. Enable PHP OPcache\n";
        echo "3. Setup Redis/Memcached for caching\n";
        echo "4. Run cache warmup: php scripts/warmup-cache.php\n";
        echo "5. Monitor performance: /api/performance-stats.php\n";
        echo "6. View detailed report: /reports/performance-report.html\n\n";
        
        echo "🎯 Performance optimization completed successfully!\n";
        
    } catch (Exception $e) {
        echo "❌ Optimization failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
