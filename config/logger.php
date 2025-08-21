<?php
/**
 * Sistema de Logs Estruturado - Blue Project V2
 * Logger avançado com níveis, contexto e formatação JSON
 */

/**
 * Classe principal para logging estruturado
 */
class Logger {
    
    // Níveis de log (PSR-3 compliant)
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    // Configurações
    private static $config = [
        'log_path' => '../logs/',
        'max_file_size' => 10485760, // 10MB
        'max_files' => 10,
        'format' => 'json', // json, text
        'timezone' => 'Australia/Melbourne',
        'include_trace' => true,
        'async_logging' => false
    ];
    
    // Níveis de prioridade
    private static $levels = [
        self::EMERGENCY => 800,
        self::ALERT => 700,
        self::CRITICAL => 600,
        self::ERROR => 500,
        self::WARNING => 400,
        self::NOTICE => 300,
        self::INFO => 200,
        self::DEBUG => 100
    ];
    
    private static $initialized = false;
    private static $logHandlers = [];
    
    /**
     * Inicializar sistema de logs
     */
    public static function init($config = []) {
        self::$config = array_merge(self::$config, $config);
        
        // Criar diretório de logs se não existir
        if (!is_dir(self::$config['log_path'])) {
            mkdir(self::$config['log_path'], 0755, true);
        }
        
        // Configurar timezone
        date_default_timezone_set(self::$config['timezone']);
        
        // Registrar handlers de erro
        self::registerErrorHandlers();
        
        self::$initialized = true;
        
        // Log de inicialização
        self::info('Logger system initialized', [
            'config' => self::$config,
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit')
        ]);
        
        return true;
    }
    
    /**
     * Log de emergência - sistema inutilizável
     */
    public static function emergency($message, array $context = []) {
        return self::log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log de alerta - ação deve ser tomada imediatamente
     */
    public static function alert($message, array $context = []) {
        return self::log(self::ALERT, $message, $context);
    }
    
    /**
     * Log crítico - condições críticas
     */
    public static function critical($message, array $context = []) {
        return self::log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log de erro - erros de runtime que não requerem ação imediata
     */
    public static function error($message, array $context = []) {
        return self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Log de aviso - ocorrências excepcionais que não são erros
     */
    public static function warning($message, array $context = []) {
        return self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Log de aviso - eventos normais mas significativos
     */
    public static function notice($message, array $context = []) {
        return self::log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log informativo - eventos interessantes
     */
    public static function info($message, array $context = []) {
        return self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log de debug - informações de debug detalhadas
     */
    public static function debug($message, array $context = []) {
        return self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log genérico
     */
    public static function log($level, $message, array $context = []) {
        if (!self::$initialized) {
            self::init();
        }
        
        $logEntry = self::createLogEntry($level, $message, $context);
        
        // Escrever nos arquivos
        self::writeToFile($level, $logEntry);
        
        // Enviar para handlers externos
        self::sendToHandlers($level, $logEntry);
        
        return true;
    }
    
    /**
     * Criar entrada de log estruturada
     */
    private static function createLogEntry($level, $message, array $context = []) {
        $entry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'extra' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
            ]
        ];
        
        // Adicionar informações de request HTTP
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $entry['http'] = [
                'method' => $_SERVER['REQUEST_METHOD'],
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => self::getClientIP(),
                'referrer' => $_SERVER['HTTP_REFERER'] ?? null
            ];
        }
        
        // Adicionar stack trace para erros
        if (self::$config['include_trace'] && in_array($level, [self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY])) {
            $entry['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        }
        
        // Adicionar ID de sessão se disponível
        if (session_id()) {
            $entry['session_id'] = session_id();
        }
        
        // Adicionar informações do usuário se disponível
        if (isset($_SESSION['customer_id'])) {
            $entry['user'] = [
                'customer_id' => $_SESSION['customer_id'],
                'type' => 'customer'
            ];
        } elseif (isset($_SESSION['professional_id'])) {
            $entry['user'] = [
                'professional_id' => $_SESSION['professional_id'],
                'type' => 'professional'
            ];
        }
        
        return $entry;
    }
    
    /**
     * Escrever log em arquivo
     */
    private static function writeToFile($level, array $logEntry) {
        $filename = self::$config['log_path'] . 'blue-cleaning-' . date('Y-m-d') . '.log';
        
        // Verificar rotação de arquivos
        self::rotateLogFiles($filename);
        
        // Formatar entrada
        if (self::$config['format'] === 'json') {
            $formatted = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        } else {
            $formatted = sprintf(
                "[%s] %s: %s %s" . PHP_EOL,
                $logEntry['timestamp'],
                $logEntry['level'],
                $logEntry['message'],
                !empty($logEntry['context']) ? json_encode($logEntry['context']) : ''
            );
        }
        
        // Escrever arquivo
        file_put_contents($filename, $formatted, FILE_APPEND | LOCK_EX);
        
        // Log específico por nível para erros críticos
        if (in_array($level, [self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY])) {
            $errorFile = self::$config['log_path'] . 'errors-' . date('Y-m-d') . '.log';
            file_put_contents($errorFile, $formatted, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Rotacionar arquivos de log
     */
    private static function rotateLogFiles($filename) {
        if (!file_exists($filename)) {
            return;
        }
        
        if (filesize($filename) >= self::$config['max_file_size']) {
            $timestamp = date('H-i-s');
            $rotatedName = $filename . '.' . $timestamp . '.rotated';
            rename($filename, $rotatedName);
            
            // Limpar arquivos antigos
            self::cleanOldLogFiles();
        }
    }
    
    /**
     * Limpar arquivos de log antigos
     */
    private static function cleanOldLogFiles() {
        $logFiles = glob(self::$config['log_path'] . '*.log*');
        
        if (count($logFiles) > self::$config['max_files']) {
            // Ordenar por data de modificação
            usort($logFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remover arquivos mais antigos
            $filesToRemove = array_slice($logFiles, 0, count($logFiles) - self::$config['max_files']);
            foreach ($filesToRemove as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Registrar handlers de erro PHP
     */
    private static function registerErrorHandlers() {
        // Handler de erros PHP
        set_error_handler([self::class, 'handlePhpError']);
        
        // Handler de exceções não capturadas
        set_exception_handler([self::class, 'handleUncaughtException']);
        
        // Handler de shutdown para erros fatais
        register_shutdown_function([self::class, 'handleFatalError']);
    }
    
    /**
     * Handler para erros PHP
     */
    public static function handlePhpError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $level = self::ERROR;
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                $level = self::CRITICAL;
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                $level = self::WARNING;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $level = self::NOTICE;
                break;
        }
        
        self::log($level, $message, [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'error_type' => self::getErrorType($severity)
        ]);
        
        return true;
    }
    
    /**
     * Handler para exceções não capturadas
     */
    public static function handleUncaughtException($exception) {
        self::critical('Uncaught exception: ' . $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
    
    /**
     * Handler para erros fatais
     */
    public static function handleFatalError() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::emergency('Fatal error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    }
    
    /**
     * Obter IP do cliente
     */
    private static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Obter tipo de erro PHP
     */
    private static function getErrorType($type) {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $types[$type] ?? 'UNKNOWN';
    }
    
    /**
     * Adicionar handler personalizado
     */
    public static function addHandler($name, callable $handler) {
        self::$logHandlers[$name] = $handler;
    }
    
    /**
     * Enviar para handlers externos
     */
    private static function sendToHandlers($level, array $logEntry) {
        foreach (self::$logHandlers as $name => $handler) {
            try {
                call_user_func($handler, $level, $logEntry);
            } catch (Exception $e) {
                // Handler falhou, escrever em arquivo de erro
                file_put_contents(
                    self::$config['log_path'] . 'handler-errors.log',
                    sprintf("[%s] Handler '%s' failed: %s\n", date('c'), $name, $e->getMessage()),
                    FILE_APPEND | LOCK_EX
                );
            }
        }
    }
    
    /**
     * Obter estatísticas de logs
     */
    public static function getStats() {
        $logFiles = glob(self::$config['log_path'] . '*.log');
        $totalSize = 0;
        $fileCount = count($logFiles);
        
        foreach ($logFiles as $file) {
            $totalSize += filesize($file);
        }
        
        return [
            'total_files' => $fileCount,
            'total_size' => $totalSize,
            'total_size_formatted' => self::formatBytes($totalSize),
            'log_path' => self::$config['log_path'],
            'oldest_file' => $fileCount > 0 ? date('Y-m-d H:i:s', filemtime(min($logFiles))) : null,
            'newest_file' => $fileCount > 0 ? date('Y-m-d H:i:s', filemtime(max($logFiles))) : null
        ];
    }
    
    /**
     * Pesquisar logs
     */
    public static function searchLogs($query, $level = null, $limit = 100) {
        $logFiles = glob(self::$config['log_path'] . '*.log');
        $results = [];
        $count = 0;
        
        foreach ($logFiles as $file) {
            if ($count >= $limit) break;
            
            $handle = fopen($file, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false && $count < $limit) {
                    $logEntry = json_decode($line, true);
                    
                    if (!$logEntry) continue;
                    
                    // Filtrar por nível se especificado
                    if ($level && strtolower($logEntry['level']) !== strtolower($level)) {
                        continue;
                    }
                    
                    // Pesquisar na mensagem e contexto
                    $searchText = strtolower($logEntry['message'] . ' ' . json_encode($logEntry['context']));
                    if (strpos($searchText, strtolower($query)) !== false) {
                        $results[] = $logEntry;
                        $count++;
                    }
                }
                fclose($handle);
            }
        }
        
        return array_reverse($results); // Mais recentes primeiro
    }
    
    /**
     * Formatar bytes
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * Contexto específico para aplicação
 */
class BlueLogger extends Logger {
    
    /**
     * Log de booking
     */
    public static function logBooking($action, $bookingId, array $context = []) {
        $context['booking_id'] = $bookingId;
        $context['module'] = 'booking';
        
        return self::info("Booking {$action}", $context);
    }
    
    /**
     * Log de pagamento
     */
    public static function logPayment($action, $paymentId, array $context = []) {
        $context['payment_id'] = $paymentId;
        $context['module'] = 'payment';
        
        return self::info("Payment {$action}", $context);
    }
    
    /**
     * Log de usuário
     */
    public static function logUser($action, $userId, $userType = 'customer', array $context = []) {
        $context['user_id'] = $userId;
        $context['user_type'] = $userType;
        $context['module'] = 'user';
        
        return self::info("User {$action}", $context);
    }
    
    /**
     * Log de API
     */
    public static function logAPI($endpoint, $method, $statusCode, array $context = []) {
        $context['endpoint'] = $endpoint;
        $context['method'] = $method;
        $context['status_code'] = $statusCode;
        $context['module'] = 'api';
        
        $level = $statusCode >= 400 ? self::WARNING : self::INFO;
        return self::log($level, "API {$method} {$endpoint} - {$statusCode}", $context);
    }
    
    /**
     * Log de performance
     */
    public static function logPerformance($operation, $duration, array $context = []) {
        $context['operation'] = $operation;
        $context['duration_ms'] = $duration;
        $context['module'] = 'performance';
        
        $level = $duration > 5000 ? self::WARNING : self::INFO; // > 5 segundos é warning
        return self::log($level, "Performance: {$operation} took {$duration}ms", $context);
    }
    
    /**
     * Log de segurança
     */
    public static function logSecurity($event, $severity = 'medium', array $context = []) {
        $context['security_event'] = $event;
        $context['severity'] = $severity;
        $context['module'] = 'security';
        
        $level = $severity === 'high' ? self::CRITICAL : ($severity === 'medium' ? self::WARNING : self::NOTICE);
        return self::log($level, "Security: {$event}", $context);
    }
}

// Auto-inicializar se incluído diretamente
if (!defined('LOGGER_LOADED')) {
    define('LOGGER_LOADED', true);
    
    // Configuração baseada no ambiente
    $logConfig = [
        'log_path' => __DIR__ . '/../logs/',
        'include_trace' => !($_ENV['APP_ENV'] === 'production' ?? false)
    ];
    
    Logger::init($logConfig);
}

?>
