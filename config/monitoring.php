<?php
/**
 * Sistema de Error Tracking e Performance Monitoring - Blue Project V2
 * Integração com Sentry, performance tracking e health checks
 */

require_once 'logger.php';

/**
 * Sistema de Error Tracking
 */
class ErrorTracker {
    
    private static $config = [
        'sentry_dsn' => null, // Configurar com seu DSN do Sentry
        'environment' => 'development',
        'release' => '2.0.0',
        'sample_rate' => 1.0,
        'traces_sample_rate' => 0.1,
        'error_reporting_enabled' => true,
        'performance_monitoring_enabled' => true
    ];
    
    private static $initialized = false;
    private static $errorCounts = [];
    private static $performanceMetrics = [];
    
    /**
     * Inicializar error tracking
     */
    public static function init($config = []) {
        self::$config = array_merge(self::$config, $config);
        
        // Configurar Sentry se DSN estiver disponível
        if (self::$config['sentry_dsn'] && class_exists('\\Sentry\\init')) {
            \Sentry\init([
                'dsn' => self::$config['sentry_dsn'],
                'environment' => self::$config['environment'],
                'release' => self::$config['release'],
                'sample_rate' => self::$config['sample_rate'],
                'traces_sample_rate' => self::$config['traces_sample_rate'],
                'before_send' => [self::class, 'beforeSendSentry']
            ]);
        }
        
        // Registrar handlers
        self::registerHandlers();
        
        self::$initialized = true;
        
        BlueLogger::info('Error tracking system initialized', [
            'sentry_enabled' => !empty(self::$config['sentry_dsn']),
            'environment' => self::$config['environment']
        ]);
        
        return true;
    }
    
    /**
     * Capturar exceção
     */
    public static function captureException($exception, array $context = []) {
        // Incrementar contador
        $exceptionClass = get_class($exception);
        self::$errorCounts[$exceptionClass] = (self::$errorCounts[$exceptionClass] ?? 0) + 1;
        
        // Log local
        BlueLogger::error('Exception captured: ' . $exception->getMessage(), array_merge($context, [
            'exception' => $exceptionClass,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]));
        
        // Enviar para Sentry se configurado
        if (function_exists('\\Sentry\\captureException')) {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($exception, $context) {
                if (!empty($context)) {
                    $scope->setContext('additional_info', $context);
                }
                
                // Adicionar tags específicas da aplicação
                $scope->setTag('module', $context['module'] ?? 'unknown');
                $scope->setTag('user_type', $_SESSION['user_type'] ?? 'guest');
                
                \Sentry\captureException($exception);
            });
        }
        
        return true;
    }
    
    /**
     * Capturar mensagem de erro
     */
    public static function captureMessage($message, $level = 'error', array $context = []) {
        // Log local
        BlueLogger::log($level, $message, $context);
        
        // Enviar para Sentry
        if (function_exists('\\Sentry\\captureMessage')) {
            \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message, $level, $context) {
                if (!empty($context)) {
                    $scope->setContext('additional_info', $context);
                }
                
                \Sentry\captureMessage($message, self::getSentryLevel($level));
            });
        }
        
        return true;
    }
    
    /**
     * Capturar erro customizado
     */
    public static function captureError($error, array $context = []) {
        $errorData = [
            'error_type' => $error['type'] ?? 'custom_error',
            'error_message' => $error['message'] ?? 'Unknown error',
            'error_code' => $error['code'] ?? null,
            'user_impact' => $error['user_impact'] ?? 'unknown',
            'context' => $context
        ];
        
        // Log estruturado
        BlueLogger::error('Custom error captured', $errorData);
        
        // Análise de impacto
        self::analyzeErrorImpact($errorData);
        
        return true;
    }
    
    /**
     * Registrar handlers de erro
     */
    private static function registerHandlers() {
        // Handler para erros PHP não capturados
        set_error_handler([self::class, 'handlePhpError'], E_ALL);
        
        // Handler para exceções não capturadas
        set_exception_handler([self::class, 'handleUncaughtException']);
        
        // Handler para shutdown
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    
    /**
     * Handler para erros PHP
     */
    public static function handlePhpError($severity, $message, $file, $line, $context = null) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorData = [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => $context
        ];
        
        // Determinar se deve ser enviado para tracking
        if ($severity >= E_WARNING || self::$config['error_reporting_enabled']) {
            self::captureError([
                'type' => 'php_error',
                'message' => $message,
                'code' => $severity
            ], $errorData);
        }
        
        return true;
    }
    
    /**
     * Handler para exceções não capturadas
     */
    public static function handleUncaughtException($exception) {
        self::captureException($exception, ['uncaught' => true]);
    }
    
    /**
     * Handler para shutdown
     */
    public static function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::captureError([
                'type' => 'fatal_error',
                'message' => $error['message'],
                'code' => $error['type']
            ], $error);
        }
    }
    
    /**
     * Analisar impacto do erro
     */
    private static function analyzeErrorImpact(array $errorData) {
        $impact = [
            'severity' => 'low',
            'affected_users' => 0,
            'business_impact' => 'minimal',
            'requires_immediate_attention' => false
        ];
        
        // Analisar baseado no tipo de erro
        $errorType = $errorData['error_type'] ?? '';
        $errorMessage = strtolower($errorData['error_message'] ?? '');
        
        // Erros críticos
        if (strpos($errorMessage, 'payment') !== false || 
            strpos($errorMessage, 'stripe') !== false ||
            strpos($errorMessage, 'database') !== false) {
            $impact['severity'] = 'critical';
            $impact['business_impact'] = 'high';
            $impact['requires_immediate_attention'] = true;
        }
        
        // Erros de alta frequência
        $errorKey = md5($errorType . $errorMessage);
        if (!isset(self::$errorCounts[$errorKey])) {
            self::$errorCounts[$errorKey] = 0;
        }
        self::$errorCounts[$errorKey]++;
        
        if (self::$errorCounts[$errorKey] > 10) { // 10+ occorrências
            $impact['severity'] = 'high';
            $impact['requires_immediate_attention'] = true;
        }
        
        // Log do impacto
        BlueLogger::warning('Error impact analysis', array_merge($errorData, [
            'impact_analysis' => $impact
        ]));
        
        // Alertas automáticos
        if ($impact['requires_immediate_attention']) {
            self::sendAlert($errorData, $impact);
        }
        
        return $impact;
    }
    
    /**
     * Enviar alerta
     */
    private static function sendAlert(array $errorData, array $impact) {
        // Log crítico
        BlueLogger::critical('ALERT: Critical error detected', [
            'error' => $errorData,
            'impact' => $impact,
            'timestamp' => date('c'),
            'requires_action' => true
        ]);
        
        // TODO: Implementar notificações (Slack, email, SMS)
        // self::sendSlackNotification($errorData, $impact);
        // self::sendEmailAlert($errorData, $impact);
    }
    
    /**
     * Converter nível para Sentry
     */
    private static function getSentryLevel($level) {
        $mapping = [
            'emergency' => \Sentry\Severity::fatal(),
            'alert' => \Sentry\Severity::fatal(),
            'critical' => \Sentry\Severity::fatal(),
            'error' => \Sentry\Severity::error(),
            'warning' => \Sentry\Severity::warning(),
            'notice' => \Sentry\Severity::info(),
            'info' => \Sentry\Severity::info(),
            'debug' => \Sentry\Severity::debug()
        ];
        
        return $mapping[$level] ?? \Sentry\Severity::error();
    }
    
    /**
     * Callback antes de enviar para Sentry
     */
    public static function beforeSendSentry(\Sentry\Event $event, ?\Sentry\EventHint $hint): ?\Sentry\Event {
        // Filtrar eventos sensíveis
        if ($event->getMessage() && strpos($event->getMessage(), 'password') !== false) {
            return null; // Não enviar eventos com passwords
        }
        
        // Adicionar contexto específico da aplicação
        $event->setTag('application', 'blue_cleaning');
        $event->setTag('version', self::$config['release']);
        
        return $event;
    }
    
    /**
     * Obter estatísticas de erro
     */
    public static function getErrorStats() {
        return [
            'error_counts' => self::$errorCounts,
            'total_errors' => array_sum(self::$errorCounts),
            'most_frequent_errors' => self::getMostFrequentErrors(),
            'error_rate' => self::calculateErrorRate(),
            'last_updated' => date('c')
        ];
    }
    
    /**
     * Obter erros mais frequentes
     */
    private static function getMostFrequentErrors() {
        arsort(self::$errorCounts);
        return array_slice(self::$errorCounts, 0, 10, true);
    }
    
    /**
     * Calcular taxa de erro
     */
    private static function calculateErrorRate() {
        // Implementação básica - em produção usar métricas reais
        $totalRequests = $_SESSION['request_count'] ?? 100;
        $totalErrors = array_sum(self::$errorCounts);
        
        return $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;
    }
}

/**
 * Sistema de Performance Monitoring
 */
class PerformanceMonitor {
    
    private static $metrics = [];
    private static $thresholds = [
        'response_time' => 2000, // 2 segundos
        'memory_usage' => 128 * 1024 * 1024, // 128MB
        'query_time' => 1000, // 1 segundo
        'api_response_time' => 5000 // 5 segundos
    ];
    
    /**
     * Iniciar medição de performance
     */
    public static function startTimer($operation) {
        self::$metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'operation' => $operation
        ];
        
        return $operation;
    }
    
    /**
     * Finalizar medição de performance
     */
    public static function endTimer($operation) {
        if (!isset(self::$metrics[$operation])) {
            return null;
        }
        
        $startData = self::$metrics[$operation];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metrics = [
            'operation' => $operation,
            'duration_ms' => round(($endTime - $startData['start_time']) * 1000, 2),
            'memory_used' => $endMemory - $startData['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => date('c')
        ];
        
        // Log performance
        BlueLogger::logPerformance($operation, $metrics['duration_ms'], $metrics);
        
        // Verificar thresholds
        self::checkPerformanceThresholds($metrics);
        
        // Limpar métrica
        unset(self::$metrics[$operation]);
        
        return $metrics;
    }
    
    /**
     * Verificar thresholds de performance
     */
    private static function checkPerformanceThresholds(array $metrics) {
        $violations = [];
        
        // Verificar tempo de resposta
        if ($metrics['duration_ms'] > self::$thresholds['response_time']) {
            $violations[] = [
                'metric' => 'response_time',
                'value' => $metrics['duration_ms'],
                'threshold' => self::$thresholds['response_time'],
                'severity' => 'warning'
            ];
        }
        
        // Verificar uso de memória
        if ($metrics['peak_memory'] > self::$thresholds['memory_usage']) {
            $violations[] = [
                'metric' => 'memory_usage',
                'value' => $metrics['peak_memory'],
                'threshold' => self::$thresholds['memory_usage'],
                'severity' => 'warning'
            ];
        }
        
        // Log violations
        if (!empty($violations)) {
            BlueLogger::warning('Performance threshold violations detected', [
                'operation' => $metrics['operation'],
                'violations' => $violations,
                'metrics' => $metrics
            ]);
        }
    }
    
    /**
     * Obter métricas de performance
     */
    public static function getMetrics() {
        return [
            'active_timers' => count(self::$metrics),
            'thresholds' => self::$thresholds,
            'system_metrics' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
            ]
        ];
    }
}

/**
 * Sistema de Health Checks
 */
class HealthChecker {
    
    private static $checks = [];
    
    /**
     * Registrar health check
     */
    public static function registerCheck($name, callable $checkFunction, $critical = false) {
        self::$checks[$name] = [
            'function' => $checkFunction,
            'critical' => $critical,
            'last_run' => null,
            'last_status' => null,
            'last_error' => null
        ];
    }
    
    /**
     * Executar todos os health checks
     */
    public static function runAllChecks() {
        $results = [
            'overall_status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => [],
            'critical_failures' => 0,
            'warning_count' => 0
        ];
        
        foreach (self::$checks as $name => $check) {
            $checkResult = self::runSingleCheck($name);
            $results['checks'][$name] = $checkResult;
            
            if ($checkResult['status'] === 'failed') {
                if ($check['critical']) {
                    $results['critical_failures']++;
                    $results['overall_status'] = 'critical';
                } else {
                    $results['warning_count']++;
                    if ($results['overall_status'] === 'healthy') {
                        $results['overall_status'] = 'warning';
                    }
                }
            }
        }
        
        // Log resultado
        BlueLogger::info('Health check completed', $results);
        
        return $results;
    }
    
    /**
     * Executar health check individual
     */
    public static function runSingleCheck($name) {
        if (!isset(self::$checks[$name])) {
            return [
                'status' => 'failed',
                'message' => 'Check not found',
                'timestamp' => date('c')
            ];
        }
        
        $check = &self::$checks[$name];
        $startTime = microtime(true);
        
        try {
            $result = call_user_func($check['function']);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            $checkResult = [
                'status' => $result['status'] ?? 'passed',
                'message' => $result['message'] ?? 'Check passed',
                'duration_ms' => $duration,
                'timestamp' => date('c'),
                'critical' => $check['critical']
            ];
            
            if (isset($result['data'])) {
                $checkResult['data'] = $result['data'];
            }
            
            $check['last_run'] = time();
            $check['last_status'] = $checkResult['status'];
            $check['last_error'] = null;
            
        } catch (Exception $e) {
            $checkResult = [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'timestamp' => date('c'),
                'critical' => $check['critical']
            ];
            
            $check['last_error'] = $e->getMessage();
            
            BlueLogger::error("Health check '{$name}' failed", [
                'check_name' => $name,
                'error' => $e->getMessage(),
                'critical' => $check['critical']
            ]);
        }
        
        return $checkResult;
    }
    
    /**
     * Obter status resumido
     */
    public static function getStatus() {
        $results = self::runAllChecks();
        
        return [
            'status' => $results['overall_status'],
            'critical_failures' => $results['critical_failures'],
            'warning_count' => $results['warning_count'],
            'total_checks' => count(self::$checks),
            'timestamp' => $results['timestamp']
        ];
    }
}

// Registrar health checks padrão
HealthChecker::registerCheck('database', function() {
    // Verificar conexão com banco de dados
    try {
        // Simulação - implementar conexão real
        $connected = true; // mysqli_ping($connection);
        
        return [
            'status' => $connected ? 'passed' : 'failed',
            'message' => $connected ? 'Database connection OK' : 'Database connection failed',
            'data' => [
                'connection_time_ms' => rand(10, 50)
            ]
        ];
    } catch (Exception $e) {
        return [
            'status' => 'failed',
            'message' => $e->getMessage()
        ];
    }
}, true); // Crítico

HealthChecker::registerCheck('disk_space', function() {
    $freeBytes = disk_free_space('.');
    $totalBytes = disk_total_space('.');
    $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
    
    $status = $usedPercent > 90 ? 'failed' : ($usedPercent > 80 ? 'warning' : 'passed');
    
    return [
        'status' => $status,
        'message' => "Disk usage: {$usedPercent}%",
        'data' => [
            'free_space' => $freeBytes,
            'total_space' => $totalBytes,
            'used_percent' => $usedPercent
        ]
    ];
}, false);

HealthChecker::registerCheck('memory', function() {
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    // Converter memory_limit para bytes
    $limitBytes = self::parseMemoryLimit($memoryLimit);
    $usedPercent = ($memoryUsage / $limitBytes) * 100;
    
    $status = $usedPercent > 90 ? 'failed' : ($usedPercent > 80 ? 'warning' : 'passed');
    
    return [
        'status' => $status,
        'message' => "Memory usage: {$usedPercent}%",
        'data' => [
            'memory_used' => $memoryUsage,
            'memory_limit' => $limitBytes,
            'used_percent' => $usedPercent
        ]
    ];
}, false);

// Auto-inicializar se incluído diretamente
if (!defined('ERROR_TRACKER_LOADED')) {
    define('ERROR_TRACKER_LOADED', true);
    
    // Configuração baseada no ambiente
    $trackingConfig = [
        'environment' => $_ENV['APP_ENV'] ?? 'development',
        'sentry_dsn' => $_ENV['SENTRY_DSN'] ?? null
    ];
    
    ErrorTracker::init($trackingConfig);
}

?>
