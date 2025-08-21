<?php
/**
 * API de Otimização e Performance - Blue Project V2
 * Endpoint para gerenciamento de cache, otimização e performance
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include required configurations
require_once __DIR__ . '/../config/cache-system.php';
require_once __DIR__ . '/../utils/security-helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Controlador da API de Performance
 */
class PerformanceAPI {
    
    /**
     * Processar requisições da API
     */
    public static function handleRequest() {
        try {
            // Secure input validation
            $action = SecurityHelpers::getGetInput('action', 'string', 
                SecurityHelpers::getPostInput('action', 'string', 'status', 50),
                50,
                ['status', 'minify_css', 'minify_js', 'combine_css', 'combine_js', 'analyze_page']
            );
            
            // Rate limiting
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!SecurityHelpers::checkRateLimit("performance_api_{$clientIP}", 50, 300)) {
                http_response_code(429);
                echo json_encode(['error' => 'Too many requests']);
                exit;
            }
            
            switch ($action) {
                case 'status':
                    return self::getSystemStatus();
                    
                case 'init_cache':
                    return CacheManager::init();
                    
                case 'cache_stats':
                    return [
                        'success' => true,
                        'stats' => CacheManager::getStats(),
                        'health' => CacheUtils::getCacheHealth()
                    ];
                    
                case 'flush_cache':
                    return CacheManager::flush();
                    
                case 'clean_cache':
                    return CacheManager::cleanExpiredCache();
                    
                case 'optimize_assets':
                    return AssetOptimizer::optimizeAll();
                    
                case 'minify_css':
                    $filename = SecurityHelpers::getPostInput('filename', 'string', '', 255);
                    if (empty($filename) || !preg_match('/^[a-zA-Z0-9._-]+\.css$/', $filename)) {
                        http_response_code(400);
                        return ['success' => false, 'error' => 'Valid CSS filename is required'];
                    }
                    return AssetOptimizer::minifyCSS($filename);
                    
                case 'minify_js':
                    $filename = SecurityHelpers::getPostInput('filename', 'string', '', 255);
                    if (empty($filename) || !preg_match('/^[a-zA-Z0-9._-]+\.js$/', $filename)) {
                        http_response_code(400);
                        return ['success' => false, 'error' => 'Valid JS filename is required'];
                    }
                    return AssetOptimizer::minifyJS($filename);
                    
                case 'combine_css':
                    $files = SecurityHelpers::getPostInput('files', 'array', []);
                    $output = SecurityHelpers::getPostInput('output', 'string', 'combined.min.css', 255);
                    
                    if (empty($files) || !is_array($files)) {
                        http_response_code(400);
                        return ['success' => false, 'error' => 'Files array is required'];
                    }
                    
                    // Validate filenames
                    foreach ($files as $file) {
                        if (!preg_match('/^[a-zA-Z0-9._-]+\.css$/', $file)) {
                            http_response_code(400);
                            return ['success' => false, 'error' => 'Invalid CSS filename: ' . htmlspecialchars($file)];
                        }
                    }
                    
                    return AssetOptimizer::combineCSSFiles($files, $output);
                    
                case 'combine_js':
                    $files = SecurityHelpers::getPostInput('files', 'array', []);
                    $output = SecurityHelpers::getPostInput('output', 'string', 'combined.min.js', 255);
                    
                    if (empty($files) || !is_array($files)) {
                        http_response_code(400);
                        return ['success' => false, 'error' => 'Files array is required'];
                    }
                    
                    // Validate filenames
                    foreach ($files as $file) {
                        if (!preg_match('/^[a-zA-Z0-9._-]+\.js$/', $file)) {
                            http_response_code(400);
                            return ['success' => false, 'error' => 'Invalid JS filename: ' . htmlspecialchars($file)];
                        }
                    }
                    
                    return AssetOptimizer::combineJSFiles($files, $output);
                    
                case 'generate_manifest':
                    return AssetOptimizer::generateManifest();
                    
                case 'generate_lazy_loading':
                    return PerformanceOptimizer::generateLazyLoadScript();
                    
                case 'generate_performance_css':
                    return PerformanceOptimizer::generatePerformanceCSS();
                    
                case 'analyze_performance':
                case 'analyze_page':
                    $url = SecurityHelpers::getGetInput('url', 'url',
                        SecurityHelpers::getPostInput('url', 'url', 'http://localhost', 2000),
                        2000
                    );
                    
                    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
                        http_response_code(400);
                        return ['success' => false, 'error' => 'Valid URL is required'];
                    }
                    
                    return PerformanceOptimizer::analyzePagePerformance($url);
                    
                case 'get_recommendations':
                    return self::getOptimizationRecommendations();
                    
                case 'preload_critical':
                    return self::generateCriticalResourcePreload();
                    
                case 'setup_cdn':
                    return self::generateCDNConfiguration();
                    
                default:
                    throw new Exception('Invalid action');
            }
            
        } catch (Exception $e) {
            error_log("Performance API error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'system_error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obter status geral do sistema
     */
    private static function getSystemStatus() {
        $cacheStats = CacheManager::getStats();
        $cacheHealth = CacheUtils::getCacheHealth();
        
        // Verificar espaço em disco
        $diskSpace = disk_free_space('.');
        $totalSpace = disk_total_space('.');
        $usedSpace = $totalSpace - $diskSpace;
        $diskUsage = round(($usedSpace / $totalSpace) * 100, 2);
        
        // Verificar assets minificados
        $minifiedDir = '../assets/minified/';
        $minifiedFiles = glob($minifiedDir . '*');
        
        return [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'cache' => [
                'status' => $cacheHealth['status'],
                'total_files' => $cacheStats['total_files'],
                'total_size' => $cacheStats['total_size_formatted'],
                'expired_files' => $cacheStats['expired_files']
            ],
            'disk_usage' => [
                'free_space' => CacheUtils::formatBytes($diskSpace),
                'used_space' => CacheUtils::formatBytes($usedSpace),
                'usage_percentage' => $diskUsage
            ],
            'assets' => [
                'minified_files' => count($minifiedFiles),
                'minified_dir_size' => CacheUtils::formatBytes(array_sum(array_map('filesize', $minifiedFiles)))
            ],
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_enabled' => extension_loaded('opcache') && opcache_get_status()['opcache_enabled'] ?? false
            ],
            'recommendations' => self::getSystemRecommendations($cacheStats, $diskUsage)
        ];
    }
    
    /**
     * Gerar recomendações de otimização
     */
    private static function getOptimizationRecommendations() {
        $recommendations = [
            'immediate' => [],
            'short_term' => [],
            'long_term' => []
        ];
        
        // Verificar cache
        $cacheStats = CacheManager::getStats();
        if ($cacheStats['expired_files'] > 0) {
            $recommendations['immediate'][] = [
                'category' => 'Cache',
                'priority' => 'high',
                'title' => 'Clean Expired Cache',
                'description' => "Remove {$cacheStats['expired_files']} expired cache files",
                'action' => 'clean_cache',
                'estimated_improvement' => 'Faster response times'
            ];
        }
        
        // Verificar assets não minificados
        $cssFiles = glob('../assets/css/*.css');
        $jsFiles = glob('../assets/js/*.js');
        $unminifiedCSS = array_filter($cssFiles, fn($f) => !strpos($f, '.min.css'));
        $unminifiedJS = array_filter($jsFiles, fn($f) => !strpos($f, '.min.js'));
        
        if (count($unminifiedCSS) > 0 || count($unminifiedJS) > 0) {
            $recommendations['short_term'][] = [
                'category' => 'Assets',
                'priority' => 'medium',
                'title' => 'Minify Assets',
                'description' => 'Minify ' . count($unminifiedCSS) . ' CSS and ' . count($unminifiedJS) . ' JS files',
                'action' => 'optimize_assets',
                'estimated_improvement' => '20-40% reduction in file sizes'
            ];
        }
        
        // Recomendações de longo prazo
        $recommendations['long_term'][] = [
            'category' => 'Infrastructure',
            'priority' => 'medium',
            'title' => 'Implement CDN',
            'description' => 'Use Content Delivery Network for static assets',
            'action' => 'setup_cdn',
            'estimated_improvement' => 'Faster global load times'
        ];
        
        $recommendations['long_term'][] = [
            'category' => 'Images',
            'priority' => 'low',
            'title' => 'Image Optimization',
            'description' => 'Convert images to WebP format and implement lazy loading',
            'action' => 'generate_lazy_loading',
            'estimated_improvement' => 'Reduced bandwidth usage'
        ];
        
        return [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'recommendations' => $recommendations,
            'summary' => [
                'immediate_actions' => count($recommendations['immediate']),
                'short_term_actions' => count($recommendations['short_term']),
                'long_term_actions' => count($recommendations['long_term'])
            ]
        ];
    }
    
    /**
     * Gerar configuração de preload para recursos críticos
     */
    private static function generateCriticalResourcePreload() {
        $criticalResources = [
            'css' => [
                '/assets/minified/main.min.css',
                '/assets/minified/performance.min.css'
            ],
            'js' => [
                '/assets/minified/main.min.js',
                '/assets/minified/lazy-loading.min.js'
            ],
            'fonts' => [
                '/assets/fonts/Roboto-Regular.woff2',
                '/assets/fonts/Roboto-Bold.woff2'
            ],
            'images' => [
                '/assets/uploads/home_cleaning_banner.webp',
                '/assets/logo/blue-cleaning-logo.svg'
            ]
        ];
        
        $preloadHTML = "<!-- Critical Resource Preloading -->\n";
        
        foreach ($criticalResources['css'] as $css) {
            $preloadHTML .= "<link rel=\"preload\" href=\"{$css}\" as=\"style\" onload=\"this.onload=null;this.rel='stylesheet'\">\n";
        }
        
        foreach ($criticalResources['js'] as $js) {
            $preloadHTML .= "<link rel=\"preload\" href=\"{$js}\" as=\"script\">\n";
        }
        
        foreach ($criticalResources['fonts'] as $font) {
            $preloadHTML .= "<link rel=\"preload\" href=\"{$font}\" as=\"font\" type=\"font/woff2\" crossorigin>\n";
        }
        
        foreach ($criticalResources['images'] as $image) {
            $preloadHTML .= "<link rel=\"preload\" href=\"{$image}\" as=\"image\">\n";
        }
        
        // Gerar Service Worker para cache
        $serviceWorker = self::generateServiceWorker($criticalResources);
        
        return [
            'success' => true,
            'preload_html' => $preloadHTML,
            'service_worker' => $serviceWorker,
            'critical_resources' => $criticalResources,
            'usage' => 'Add the preload HTML to the <head> section of your pages'
        ];
    }
    
    /**
     * Gerar Service Worker para cache offline
     */
    private static function generateServiceWorker($resources) {
        $sw = "
// Blue Project - Service Worker
const CACHE_NAME = 'blue-cleaning-v2';
const urlsToCache = [
    '/',
    '/booking2.php',
    '/customer/dashboard.php'";
        
        foreach (array_merge($resources['css'], $resources['js'], $resources['images']) as $resource) {
            $sw .= ",\n    '{$resource}'";
        }
        
        $sw .= "
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Cache hit - return response
                if (response) {
                    return response;
                }
                return fetch(event.request);
            }
        )
    );
});

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
        })
    );
});
";
        
        $swPath = '../sw.js';
        file_put_contents($swPath, $sw);
        
        return [
            'path' => $swPath,
            'size' => strlen($sw),
            'registration' => "
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}
</script>"
        ];
    }
    
    /**
     * Gerar configuração de CDN
     */
    private static function generateCDNConfiguration() {
        return [
            'success' => true,
            'recommended_cdn' => 'Cloudflare',
            'configuration' => [
                'static_assets' => [
                    'pattern' => '/assets/*',
                    'cache_ttl' => 86400, // 24 hours
                    'compression' => true,
                    'minification' => true
                ],
                'api_responses' => [
                    'pattern' => '/api/*',
                    'cache_ttl' => 300, // 5 minutes
                    'bypass_on_cookie' => true
                ],
                'html_pages' => [
                    'pattern' => '/*.php',
                    'cache_ttl' => 1800, // 30 minutes
                    'bypass_on_cookie' => true
                ]
            ],
            'performance_rules' => [
                [
                    'name' => 'Auto Minify',
                    'description' => 'Automatically minify CSS, JS, and HTML',
                    'recommended' => true
                ],
                [
                    'name' => 'Brotli Compression',
                    'description' => 'Use Brotli compression for better compression ratios',
                    'recommended' => true
                ],
                [
                    'name' => 'HTTP/2 Server Push',
                    'description' => 'Push critical resources before they are requested',
                    'recommended' => false
                ],
                [
                    'name' => 'Image Optimization',
                    'description' => 'Automatically optimize and convert images',
                    'recommended' => true
                ]
            ],
            'setup_steps' => [
                '1. Sign up for Cloudflare account',
                '2. Add your domain to Cloudflare',
                '3. Update DNS settings to use Cloudflare nameservers',
                '4. Configure caching rules in Cloudflare dashboard',
                '5. Enable recommended performance settings',
                '6. Test and monitor performance improvements'
            ]
        ];
    }
    
    /**
     * Gerar recomendações específicas do sistema
     */
    private static function getSystemRecommendations($cacheStats, $diskUsage) {
        $recommendations = [];
        
        if ($diskUsage > 80) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'High disk usage detected. Consider cleaning up old files.',
                'action' => 'cleanup_files'
            ];
        }
        
        if ($cacheStats['expired_files'] > ($cacheStats['total_files'] * 0.2)) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Many cache files have expired. Run cleanup to improve performance.',
                'action' => 'clean_cache'
            ];
        }
        
        if (!extension_loaded('opcache')) {
            $recommendations[] = [
                'type' => 'suggestion',
                'message' => 'Enable OPcache for better PHP performance.',
                'action' => 'enable_opcache'
            ];
        }
        
        return $recommendations;
    }
}

// Processar requisição
try {
    $result = PerformanceAPI::handleRequest();
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("Performance API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'system_error',
        'message' => 'Unable to process performance request'
    ]);
}

?>
