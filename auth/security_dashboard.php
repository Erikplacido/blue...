<?php
/**
 * =========================================================
 * PROJETO BLUE V2 - DASHBOARD DE SEGURANÇA
 * =========================================================
 * 
 * @file auth/security_dashboard.php
 * @description Dashboard para monitoramento de segurança
 * @version 2.0
 * @date 2025-08-07
 */

require_once __DIR__ . '/SecurityMiddleware.php';
require_once __DIR__ . '/AuthManager.php';
require_once __DIR__ . '/RateLimiter.php';

// Aplica proteção de segurança - somente admins
security_protect([
    'require_auth' => true,
    'allowed_roles' => ['admin'],
    'require_csrf' => true
]);

$auth = AuthManager::getInstance();
$rateLimiter = RateLimiter::getInstance();

// Gera token CSRF para formulários
$csrfToken = $auth->generateCSRFToken();

// Obtém relatórios de segurança
$securityReport = $rateLimiter->getSecurityReport();
$currentUser = $auth->getCurrentUser();

// Processa ações administrativas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'reset_rate_limit':
            $key = $_POST['key'] ?? '';
            if ($key) {
                $rateLimiter->reset($key);
                $message = "Rate limit reset for: $key";
            }
            break;
            
        case 'clear_security_logs':
            $logFile = __DIR__ . '/../logs/security.log';
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
                $message = "Security logs cleared";
            }
            break;
    }
}

// Lê logs de segurança recentes
function getRecentSecurityLogs(int $limit = 50): array {
    $logFile = __DIR__ . '/../logs/security.log';
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    $logs = [];
    
    foreach (array_slice($lines, -$limit) as $line) {
        $log = json_decode($line, true);
        if ($log) {
            $logs[] = $log;
        }
    }
    
    return array_reverse($logs);
}

$recentLogs = getRecentSecurityLogs();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - Blue Admin</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/blue.css">
    
    <style>
        :root {
            --admin-bg: #1f2937;
            --admin-surface: #374151;
            --admin-border: #4b5563;
            --admin-text: #f9fafb;
            --admin-text-secondary: #d1d5db;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        body {
            background: var(--admin-bg);
            color: var(--admin-text);
            font-family: 'Inter', sans-serif;
            margin: 0;
            min-height: 100vh;
        }
        
        .header {
            background: var(--admin-surface);
            border-bottom: 1px solid var(--admin-border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            color: var(--admin-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--admin-text-secondary);
        }
        
        .container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-value.success { color: var(--success-color); }
        .stat-value.warning { color: var(--warning-color); }
        .stat-value.danger { color: var(--danger-color); }
        .stat-value.info { color: var(--info-color); }
        
        .stat-label {
            color: var(--admin-text-secondary);
            font-size: 0.9rem;
        }
        
        .section {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-content {
            padding: 1.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--admin-border);
        }
        
        .table th {
            font-weight: 600;
            color: var(--admin-text);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .table td {
            color: var(--admin-text-secondary);
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color); }
        .badge-info { background: rgba(59, 130, 246, 0.1); color: var(--info-color); }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--info-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-secondary {
            background: var(--admin-border);
            color: var(--admin-text);
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
        }
        
        .log-entry {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }
        
        .log-timestamp {
            color: var(--info-color);
            font-weight: 600;
        }
        
        .log-event {
            color: var(--warning-color);
            font-weight: 600;
        }
        
        .log-ip {
            color: var(--danger-color);
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--info-color);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .refresh-btn:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>
            <i class="fas fa-shield-alt"></i>
            Security Dashboard
        </h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($currentUser['email']) ?></span>
            <a href="../auth/login.php?logout=1" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>
    
    <div class="container">
        <?php if (isset($message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Estatísticas de Segurança -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value info"><?= $securityReport['statistics']['total_attempts'] ?></div>
                <div class="stat-label">Total Attempts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value danger"><?= $securityReport['total_blocked_keys'] ?></div>
                <div class="stat-label">Active Blocks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value warning"><?= $securityReport['statistics']['blocked_attempts'] ?></div>
                <div class="stat-label">Blocked Attempts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value success"><?= $securityReport['statistics']['success_rate'] ?>%</div>
                <div class="stat-label">Success Rate</div>
            </div>
        </div>
        
        <!-- Bloqueios Ativos -->
        <?php if (!empty($securityReport['active_blocks'])): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-ban"></i>
                    Active Rate Limits
                </h2>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="clear_all_blocks">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Clear All
                    </button>
                </form>
            </div>
            <div class="section-content">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Attempts</th>
                            <th>Blocked Until</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($securityReport['active_blocks'] as $block): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($block['key']) ?></code></td>
                            <td><span class="badge badge-danger"><?= $block['attempts'] ?></span></td>
                            <td><?= $block['blocked_until'] ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="reset_rate_limit">
                                    <input type="hidden" name="key" value="<?= htmlspecialchars($block['key']) ?>">
                                    <button type="submit" class="btn btn-secondary">
                                        <i class="fas fa-undo"></i>
                                        Reset
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Logs de Segurança -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Recent Security Events
                </h2>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="clear_security_logs">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Clear Logs
                    </button>
                </form>
            </div>
            <div class="section-content">
                <?php if (empty($recentLogs)): ?>
                    <p class="text-secondary">No security events recorded.</p>
                <?php else: ?>
                    <?php foreach (array_slice($recentLogs, 0, 20) as $log): ?>
                    <div class="log-entry">
                        <span class="log-timestamp"><?= htmlspecialchars($log['timestamp']) ?></span>
                        <span class="log-event"><?= htmlspecialchars($log['event']) ?></span>
                        <span class="log-ip"><?= htmlspecialchars($log['ip']) ?></span>
                        <?php if (!empty($log['uri'])): ?>
                        <br><small>URI: <?= htmlspecialchars($log['uri']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($log['data'])): ?>
                        <br><small>Data: <?= htmlspecialchars(json_encode($log['data'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <button class="refresh-btn" onclick="location.reload()">
        <i class="fas fa-sync-alt"></i>
    </button>
    
    <script>
        // Auto-refresh a cada 30 segundos
        setInterval(() => {
            location.reload();
        }, 30000);
        
        // Confirmar ações perigosas
        document.querySelectorAll('form button.btn-danger').forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
