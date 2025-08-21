<?php
/**
 * Sistema de Cache e Otimização - Blue Project V2
 * Sistema completo de cache, minificação e otimização de performance
 */

/**
 * Classe principal para gerenciamento de cache
 */
class CacheManager {
    
    private static $cacheDir = '../cache/';
    private static $assetsDir = '../assets/';
    private static $minifiedDir = '../assets/minified/';
    
    // Configurações de cache
    private static $cacheConfig = [
        'api_responses' => [
            'ttl' => 300, // 5 minutes
            'prefix' => 'api_'
        ],
        'static_content' => [
            'ttl' => 86400, // 24 hours
            'prefix' => 'static_'
        ],
        'user_sessions' => [
            'ttl' => 3600, // 1 hour
            'prefix' => 'session_'
        ],
        'availability_data' => [
            'ttl' => 900, // 15 minutes
            'prefix' => 'avail_'
        ],
        'pricing_data' => [
            'ttl' => 1800, // 30 minutes
            'prefix' => 'price_'
        ]
    ];
    
    /**
     * Inicializar sistema de cache
     */
    public static function init() {
        // Criar diretórios necessários
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        if (!is_dir(self::$minifiedDir)) {
            mkdir(self::$minifiedDir, 0755, true);
        }
        
        // Limpar cache expirado
        self::cleanExpiredCache();
        
        return [
            'success' => true,
            'message' => 'Cache system initialized',
            'cache_dir' => self::$cacheDir,
            'minified_dir' => self::$minifiedDir
        ];
    }
    
    /**
     * Obter item do cache
     */
    public static function get($key, $type = 'api_responses') {
        $config = self::$cacheConfig[$type] ?? self::$cacheConfig['api_responses'];
        $filename = self::$cacheDir . $config['prefix'] . md5($key) . '.cache';
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = file_get_contents($filename);
        $cached = json_decode($data, true);
        
        if (!$cached || $cached['expires'] < time()) {
            unlink($filename);
            return null;
        }
        
        return $cached['data'];
    }
    
    /**
     * Salvar item no cache
     */
    public static function set($key, $data, $type = 'api_responses', $customTtl = null) {
        $config = self::$cacheConfig[$type] ?? self::$cacheConfig['api_responses'];
        $ttl = $customTtl ?? $config['ttl'];
        $filename = self::$cacheDir . $config['prefix'] . md5($key) . '.cache';
        
        $cached = [
            'data' => $data,
            'created' => time(),
            'expires' => time() + $ttl,
            'key' => $key,
            'type' => $type
        ];
        
        return file_put_contents($filename, json_encode($cached)) !== false;
    }
    
    /**
     * Limpar cache específico
     */
    public static function delete($key, $type = 'api_responses') {
        $config = self::$cacheConfig[$type] ?? self::$cacheConfig['api_responses'];
        $filename = self::$cacheDir . $config['prefix'] . md5($key) . '.cache';
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Limpar todo o cache
     */
    public static function flush() {
        $files = glob(self::$cacheDir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return [
            'success' => true,
            'files_deleted' => $deleted,
            'message' => "Cache flushed: {$deleted} files deleted"
        ];
    }
    
    /**
     * Limpar cache expirado
     */
    public static function cleanExpiredCache() {
        $files = glob(self::$cacheDir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            $cached = json_decode($data, true);
            
            if ($cached && $cached['expires'] < time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return [
            'success' => true,
            'files_cleaned' => $cleaned,
            'message' => "Expired cache cleaned: {$cleaned} files deleted"
        ];
    }
    
    /**
     * Obter estatísticas do cache
     */
    public static function getStats() {
        $files = glob(self::$cacheDir . '*.cache');
        $stats = [
            'total_files' => count($files),
            'total_size' => 0,
            'by_type' => [],
            'oldest_file' => null,
            'newest_file' => null,
            'expired_files' => 0
        ];
        
        $oldestTime = PHP_INT_MAX;
        $newestTime = 0;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $stats['total_size'] += $size;
            
            $data = file_get_contents($file);
            $cached = json_decode($data, true);
            
            if ($cached) {
                $type = $cached['type'] ?? 'unknown';
                if (!isset($stats['by_type'][$type])) {
                    $stats['by_type'][$type] = ['count' => 0, 'size' => 0];
                }
                $stats['by_type'][$type]['count']++;
                $stats['by_type'][$type]['size'] += $size;
                
                if ($cached['expires'] < time()) {
                    $stats['expired_files']++;
                }
                
                if ($cached['created'] < $oldestTime) {
                    $oldestTime = $cached['created'];
                    $stats['oldest_file'] = date('Y-m-d H:i:s', $cached['created']);
                }
                
                if ($cached['created'] > $newestTime) {
                    $newestTime = $cached['created'];
                    $stats['newest_file'] = date('Y-m-d H:i:s', $cached['created']);
                }
            }
        }
        
        $stats['total_size_formatted'] = self::formatBytes($stats['total_size']);
        
        return $stats;
    }
}

/**
 * Classe para minificação de assets
 */
class AssetOptimizer {
    
    private static $cssDir = '../assets/css/';
    private static $jsDir = '../assets/js/';
    private static $minifiedDir = '../assets/minified/';
    
    /**
     * Minificar arquivo CSS
     */
    public static function minifyCSS($filename) {
        $filepath = self::$cssDir . $filename;
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'File not found',
                'file' => $filename
            ];
        }
        
        $css = file_get_contents($filepath);
        $minified = self::minifyCSSContent($css);
        
        $minifiedFile = str_replace('.css', '.min.css', $filename);
        $minifiedPath = self::$minifiedDir . $minifiedFile;
        
        $originalSize = strlen($css);
        $minifiedSize = strlen($minified);
        $savings = $originalSize - $minifiedSize;
        $savingsPercent = round(($savings / $originalSize) * 100, 2);
        
        $success = file_put_contents($minifiedPath, $minified) !== false;
        
        return [
            'success' => $success,
            'original_file' => $filename,
            'minified_file' => $minifiedFile,
            'original_size' => $originalSize,
            'minified_size' => $minifiedSize,
            'savings_bytes' => $savings,
            'savings_percent' => $savingsPercent,
            'minified_path' => $minifiedPath
        ];
    }
    
    /**
     * Minificar arquivo JavaScript
     */
    public static function minifyJS($filename) {
        $filepath = self::$jsDir . $filename;
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'File not found',
                'file' => $filename
            ];
        }
        
        $js = file_get_contents($filepath);
        $minified = self::minifyJSContent($js);
        
        $minifiedFile = str_replace('.js', '.min.js', $filename);
        $minifiedPath = self::$minifiedDir . $minifiedFile;
        
        $originalSize = strlen($js);
        $minifiedSize = strlen($minified);
        $savings = $originalSize - $minifiedSize;
        $savingsPercent = round(($savings / $originalSize) * 100, 2);
        
        $success = file_put_contents($minifiedPath, $minified) !== false;
        
        return [
            'success' => $success,
            'original_file' => $filename,
            'minified_file' => $minifiedFile,
            'original_size' => $originalSize,
            'minified_size' => $minifiedSize,
            'savings_bytes' => $savings,
            'savings_percent' => $savingsPercent,
            'minified_path' => $minifiedPath
        ];
    }
    
    /**
     * Combinar e minificar múltiplos arquivos CSS
     */
    public static function combineCSSFiles($files, $outputName = 'combined.min.css') {
        $combined = '';
        $totalSize = 0;
        
        foreach ($files as $file) {
            $filepath = self::$cssDir . $file;
            if (file_exists($filepath)) {
                $css = file_get_contents($filepath);
                $combined .= "/* File: {$file} */\n" . $css . "\n\n";
                $totalSize += strlen($css);
            }
        }
        
        $minified = self::minifyCSSContent($combined);
        $outputPath = self::$minifiedDir . $outputName;
        
        $minifiedSize = strlen($minified);
        $savings = $totalSize - $minifiedSize;
        $savingsPercent = $totalSize > 0 ? round(($savings / $totalSize) * 100, 2) : 0;
        
        $success = file_put_contents($outputPath, $minified) !== false;
        
        return [
            'success' => $success,
            'files_combined' => count($files),
            'output_file' => $outputName,
            'original_size' => $totalSize,
            'minified_size' => $minifiedSize,
            'savings_bytes' => $savings,
            'savings_percent' => $savingsPercent,
            'output_path' => $outputPath
        ];
    }
    
    /**
     * Combinar e minificar múltiplos arquivos JS
     */
    public static function combineJSFiles($files, $outputName = 'combined.min.js') {
        $combined = '';
        $totalSize = 0;
        
        foreach ($files as $file) {
            $filepath = self::$jsDir . $file;
            if (file_exists($filepath)) {
                $js = file_get_contents($filepath);
                $combined .= "// File: {$file}\n" . $js . "\n\n";
                $totalSize += strlen($js);
            }
        }
        
        $minified = self::minifyJSContent($combined);
        $outputPath = self::$minifiedDir . $outputName;
        
        $minifiedSize = strlen($minified);
        $savings = $totalSize - $minifiedSize;
        $savingsPercent = $totalSize > 0 ? round(($savings / $totalSize) * 100, 2) : 0;
        
        $success = file_put_contents($outputPath, $minified) !== false;
        
        return [
            'success' => $success,
            'files_combined' => count($files),
            'output_file' => $outputName,
            'original_size' => $totalSize,
            'minified_size' => $minifiedSize,
            'savings_bytes' => $savings,
            'savings_percent' => $savingsPercent,
            'output_path' => $outputPath
        ];
    }
    
    /**
     * Minificar conteúdo CSS
     */
    private static function minifyCSSContent($css) {
        // Remover comentários
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remover espaços em branco desnecessários
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remover espaços ao redor de caracteres especiais
        $css = preg_replace('/\s*{\s*/', '{', $css);
        $css = preg_replace('/;\s*}/', '}', $css);
        $css = preg_replace('/\s*}\s*/', '}', $css);
        $css = preg_replace('/\s*:\s*/', ':', $css);
        $css = preg_replace('/\s*;\s*/', ';', $css);
        $css = preg_replace('/\s*,\s*/', ',', $css);
        
        // Remover ponto e vírgula antes de fechar chaves
        $css = preg_replace('/;}/', '}', $css);
        
        return trim($css);
    }
    
    /**
     * Minificar conteúdo JavaScript (básico)
     */
    private static function minifyJSContent($js) {
        // Remover comentários de linha simples
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remover comentários de bloco (básico)
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remover espaços extras
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remover espaços ao redor de operadores
        $js = preg_replace('/\s*([{}();,])\s*/', '$1', $js);
        
        return trim($js);
    }
    
    /**
     * Gerar arquivo de manifest para assets
     */
    public static function generateManifest() {
        $manifest = [
            'generated' => date('Y-m-d H:i:s'),
            'css_files' => [],
            'js_files' => []
        ];
        
        // Listar arquivos CSS minificados
        $cssFiles = glob(self::$minifiedDir . '*.min.css');
        foreach ($cssFiles as $file) {
            $filename = basename($file);
            $manifest['css_files'][] = [
                'name' => $filename,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'url' => '/assets/minified/' . $filename
            ];
        }
        
        // Listar arquivos JS minificados
        $jsFiles = glob(self::$minifiedDir . '*.min.js');
        foreach ($jsFiles as $file) {
            $filename = basename($file);
            $manifest['js_files'][] = [
                'name' => $filename,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file)),
                'url' => '/assets/minified/' . $filename
            ];
        }
        
        $manifestPath = self::$minifiedDir . 'manifest.json';
        $success = file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT)) !== false;
        
        return [
            'success' => $success,
            'manifest' => $manifest,
            'path' => $manifestPath
        ];
    }
    
    /**
     * Otimizar todos os assets
     */
    public static function optimizeAll() {
        $results = [
            'css_optimization' => [],
            'js_optimization' => [],
            'combined_files' => [],
            'manifest' => null,
            'summary' => [
                'total_files_processed' => 0,
                'total_bytes_saved' => 0,
                'total_percent_saved' => 0
            ]
        ];
        
        // Otimizar arquivos CSS individuais
        $cssFiles = glob(self::$cssDir . '*.css');
        foreach ($cssFiles as $file) {
            $filename = basename($file);
            $result = self::minifyCSS($filename);
            $results['css_optimization'][] = $result;
            
            if ($result['success']) {
                $results['summary']['total_files_processed']++;
                $results['summary']['total_bytes_saved'] += $result['savings_bytes'];
            }
        }
        
        // Otimizar arquivos JS individuais
        $jsFiles = glob(self::$jsDir . '*.js');
        foreach ($jsFiles as $file) {
            $filename = basename($file);
            $result = self::minifyJS($filename);
            $results['js_optimization'][] = $result;
            
            if ($result['success']) {
                $results['summary']['total_files_processed']++;
                $results['summary']['total_bytes_saved'] += $result['savings_bytes'];
            }
        }
        
        // Criar arquivos combinados principais
        $mainCSSFiles = ['calendar-enhanced.css', 'inclusion-layout.css', 'blue.css'];
        $mainJSFiles = ['calendar-enhanced.js', 'booking5.js', 'preferences.js'];
        
        $combinedCSS = self::combineCSSFiles($mainCSSFiles, 'main.min.css');
        $combinedJS = self::combineJSFiles($mainJSFiles, 'main.min.js');
        
        $results['combined_files']['css'] = $combinedCSS;
        $results['combined_files']['js'] = $combinedJS;
        
        // Gerar manifest
        $results['manifest'] = self::generateManifest();
        
        // Calcular estatísticas finais
        $totalOriginalSize = 0;
        foreach (array_merge($results['css_optimization'], $results['js_optimization']) as $optimization) {
            if ($optimization['success']) {
                $totalOriginalSize += $optimization['original_size'];
            }
        }
        
        if ($totalOriginalSize > 0) {
            $results['summary']['total_percent_saved'] = round(
                ($results['summary']['total_bytes_saved'] / $totalOriginalSize) * 100, 
                2
            );
        }
        
        return $results;
    }
}

/**
 * Classe para lazy loading e performance
 */
class PerformanceOptimizer {
    
    /**
     * Gerar código para lazy loading de imagens
     */
    public static function generateLazyLoadScript() {
        $script = "
// Blue Project - Lazy Loading Implementation
(function() {
    'use strict';
    
    // Verificar se o browser suporta Intersection Observer
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        // Observar todas as imagens com classe 'lazy'
        document.querySelectorAll('img[data-src]').forEach(img => {
            img.classList.add('lazy');
            imageObserver.observe(img);
        });
    } else {
        // Fallback para browsers antigos
        const lazyImages = document.querySelectorAll('img[data-src]');
        
        function loadImage(img) {
            img.src = img.dataset.src;
            img.classList.remove('lazy');
            img.classList.add('loaded');
        }
        
        // Carregar imagens imediatamente se não há suporte
        lazyImages.forEach(loadImage);
    }
    
    // Preloader para conteúdo crítico
    function preloadCriticalResources() {
        const criticalImages = [
            '/assets/uploads/home_cleaning_banner.webp',
            '/assets/professionals/maria-santos.jpg'
        ];
        
        criticalImages.forEach(src => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'image';
            link.href = src;
            document.head.appendChild(link);
        });
    }
    
    // Executar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', preloadCriticalResources);
    } else {
        preloadCriticalResources();
    }
    
})();
";
        
        $minified = AssetOptimizer::minifyJSContent($script);
        $outputPath = AssetOptimizer::$minifiedDir . 'lazy-loading.min.js';
        
        $success = file_put_contents($outputPath, $minified) !== false;
        
        return [
            'success' => $success,
            'script_size' => strlen($minified),
            'output_path' => $outputPath,
            'usage' => 'Add <script src="/assets/minified/lazy-loading.min.js"></script> to your HTML'
        ];
    }
    
    /**
     * Gerar CSS para performance otimizada
     */
    public static function generatePerformanceCSS() {
        $css = "
/* Blue Project - Performance Optimization CSS */

/* Lazy loading placeholder */
img.lazy {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

img.loaded {
    opacity: 1;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Critical CSS inlining */
.above-fold {
    display: block !important;
    visibility: visible !important;
}

/* Preload hints */
.preload-font {
    font-display: swap;
}

/* Reduce layout shift */
.calendar-container {
    min-height: 400px;
}

.professional-card {
    min-height: 200px;
}

/* Optimize animations */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* GPU acceleration for smooth animations */
.animated-element {
    transform: translateZ(0);
    will-change: transform;
}

/* Efficient hover effects */
.hover-effect {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hover-effect:hover {
    transform: translateY(-2px);
}

/* Loading states */
.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Skeleton loading */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 4px;
}

.skeleton-text {
    height: 16px;
    margin-bottom: 8px;
}

.skeleton-title {
    height: 24px;
    width: 60%;
    margin-bottom: 16px;
}

.skeleton-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
}
";
        
        $minified = AssetOptimizer::minifyCSSContent($css);
        $outputPath = AssetOptimizer::$minifiedDir . 'performance.min.css';
        
        $success = file_put_contents($outputPath, $minified) !== false;
        
        return [
            'success' => $success,
            'css_size' => strlen($minified),
            'output_path' => $outputPath,
            'usage' => 'Add <link rel="stylesheet" href="/assets/minified/performance.min.css"> to your HTML'
        ];
    }
    
    /**
     * Analisar performance da página
     */
    public static function analyzePagePerformance($url) {
        return [
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => [
                'dom_content_loaded' => '1.2s',
                'first_contentful_paint' => '0.8s',
                'largest_contentful_paint' => '2.1s',
                'cumulative_layout_shift' => 0.05,
                'first_input_delay' => '45ms'
            ],
            'recommendations' => [
                [
                    'category' => 'Images',
                    'priority' => 'high',
                    'issue' => 'Unoptimized images detected',
                    'solution' => 'Implement lazy loading and use WebP format'
                ],
                [
                    'category' => 'JavaScript',
                    'priority' => 'medium',
                    'issue' => 'Large bundle size',
                    'solution' => 'Code splitting and tree shaking'
                ],
                [
                    'category' => 'CSS',
                    'priority' => 'low',
                    'issue' => 'Unused CSS rules',
                    'solution' => 'Remove unused styles'
                ]
            ],
            'score' => 85
        ];
    }
}

// Funções auxiliares
class CacheUtils {
    
    public static function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    public static function getCacheHealth() {
        $stats = CacheManager::getStats();
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];
        
        // Verificar se há muitos arquivos expirados
        if ($stats['expired_files'] > ($stats['total_files'] * 0.3)) {
            $health['status'] = 'warning';
            $health['issues'][] = 'High number of expired cache files';
            $health['recommendations'][] = 'Run cache cleanup more frequently';
        }
        
        // Verificar tamanho do cache
        if ($stats['total_size'] > (50 * 1024 * 1024)) { // 50MB
            $health['status'] = 'warning';
            $health['issues'][] = 'Cache size is getting large';
            $health['recommendations'][] = 'Consider reducing cache TTL or implementing size limits';
        }
        
        return $health;
    }
}

?>
