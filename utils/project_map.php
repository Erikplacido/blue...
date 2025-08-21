<?php
/**
 * MAPA FUNCIONAL DO PROJETO BLUE - SISTEMA DE NAVEGAÇÃO E TESTES
 * ================================================================
 * 
 * Este mapa interativo permite navegar entre todas as páginas do projeto
 * e testar suas funcionalidades de forma organizada.
 * 
 * @author Blue Project Team
 * @version 2.0
 * @date 07/08/2025
 */

// Configurações do projeto
$projectConfig = [
    'name' => 'Blue Facility Services',
    'version' => '2.0',
    'description' => 'Sistema de reservas avançado com design Liquid Glass',
    'base_url' => 'http://localhost:8002', // Ajustar conforme necessário
    'status' => 'Active'
];

// Estrutura completa do projeto com todas as páginas e suas conexões
$projectStructure = [
    'core_pages' => [
        'index.html' => [
            'title' => 'Homepage',
            'description' => 'Página inicial do projeto com PWA',
            'path' => 'index.html',
            'type' => 'HTML',
            'status' => 'active',
            'features' => ['PWA', 'Service Worker', 'Liquid Glass Design'],
            'connects_to' => ['booking.php', 'booking2.php', 'professional/register.php']
        ],
        'booking.php' => [
            'title' => 'Sistema de Reservas Principal',
            'description' => 'Página principal para reservas de serviços',
            'path' => 'booking.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Calendário', 'Cálculo de preços', 'Validação 48h', 'Stripe'],
            'connects_to' => ['booking-confirmation.php', 'api/booking.php']
        ],
        'booking2.php' => [
            'title' => 'Sistema de Reservas Avançado',
            'description' => 'Versão avançada com funcionalidades extras',
            'path' => 'booking2.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Recorrência', 'Descontos', 'Pause/Cancel', 'Contratos'],
            'connects_to' => ['booking-confirmation.php', 'customer/dashboard.php']
        ],
        'booking-confirmation.php' => [
            'title' => 'Confirmação de Reserva',
            'description' => 'Página de confirmação após reserva bem-sucedida',
            'path' => 'booking-confirmation.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Detalhes da reserva', 'Próximos passos', 'Impressão'],
            'connects_to' => ['customer/dashboard.php', 'payment_history.php']
        ]
    ],
    
    'customer_area' => [
        'customer/dashboard.php' => [
            'title' => 'Dashboard do Cliente',
            'description' => 'Área do cliente para gerenciar reservas',
            'path' => 'customer/dashboard.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Minhas reservas', 'Histórico', 'Configurações'],
            'connects_to' => ['customer/subscription-management.php', 'payment_history.php']
        ],
        'customer/subscription-management.php' => [
            'title' => 'Gestão de Assinaturas',
            'description' => 'Gerenciamento de assinaturas do cliente',
            'path' => 'customer/subscription-management.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Pausar', 'Cancelar', 'Modificar', 'Histórico'],
            'connects_to' => ['api/pause-subscription.php', 'api/cancel-subscription.php']
        ]
    ],
    
    'professional_area' => [
        'professional/dashboard.php' => [
            'title' => 'Dashboard Profissional',
            'description' => 'Interface estilo Uber para profissionais',
            'path' => 'professional/dashboard.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Mapa', 'Jobs disponíveis', 'Agenda', 'Ganhos'],
            'connects_to' => ['professional/availability.php', 'api/professional/location.php']
        ],
        'professional/register.php' => [
            'title' => 'Cadastro de Profissionais',
            'description' => 'Registro de novos profissionais',
            'path' => 'professional/register.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Formulário completo', 'Upload docs', 'Validação'],
            'connects_to' => ['professional/dashboard.php', 'api/professional/register.php']
        ],
        'professional/availability.php' => [
            'title' => 'Disponibilidade',
            'description' => 'Gerenciar horários disponíveis',
            'path' => 'professional/availability.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Calendário', 'Horários', 'Bloqueios'],
            'connects_to' => ['professional/dashboard.php']
        ]
    ],
    
    'support_pages' => [
        'help.php' => [
            'title' => 'Central de Ajuda',
            'description' => 'FAQ e suporte ao cliente',
            'path' => 'help.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['FAQ', 'Chat support', 'Tutoriais'],
            'connects_to' => ['api/chat/messages.php']
        ],
        'payment_history.php' => [
            'title' => 'Histórico de Pagamentos',
            'description' => 'Histórico completo de transações',
            'path' => 'payment_history.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Faturas', 'Recibos', 'Filtros', 'Export'],
            'connects_to' => ['customer/dashboard.php']
        ],
        'tracking.php' => [
            'title' => 'Rastreamento de Serviços',
            'description' => 'Acompanhamento em tempo real',
            'path' => 'tracking.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['GPS', 'Status em tempo real', 'Notificações'],
            'connects_to' => ['api/professional/location.php']
        ],
        'referralclub.php' => [
            'title' => 'Programa de Indicação',
            'description' => 'Sistema de referência e recompensas',
            'path' => 'referralclub.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Códigos de desconto', 'Comissões', 'Ranking'],
            'connects_to' => ['referralclub2.php', 'api/validate-discount.php']
        ],
        'referralclub2.php' => [
            'title' => 'Programa de Indicação v2',
            'description' => 'Versão avançada do programa',
            'path' => 'referralclub2.php',
            'type' => 'PHP',
            'status' => 'active',
            'features' => ['Níveis VIP', 'Bônus especiais', 'Analytics'],
            'connects_to' => ['referralclub.php']
        ]
    ],
    
    'showcase_pages' => [
        'showcase.html' => [
            'title' => 'Showcase do Design',
            'description' => 'Demonstração dos componentes',
            'path' => 'showcase.html',
            'type' => 'HTML',
            'status' => 'demo',
            'features' => ['Liquid Glass', 'Componentes', 'Tokens'],
            'connects_to' => []
        ],
        'subscription-card-example.html' => [
            'title' => 'Exemplo de Card',
            'description' => 'Exemplo de cartão de assinatura',
            'path' => 'subscription-card-example.html',
            'type' => 'HTML',
            'status' => 'demo',
            'features' => ['Card design', 'Animações'],
            'connects_to' => []
        ]
    ],
    
    'api_endpoints' => [
        'api/booking.php' => [
            'title' => 'API de Reservas',
            'description' => 'Processamento de reservas',
            'path' => 'api/booking.php',
            'type' => 'API',
            'status' => 'active',
            'features' => ['POST /create', 'PUT /update', 'GET /details'],
            'connects_to' => ['api/booking/create.php']
        ],
        'api/check-availability.php' => [
            'title' => 'Verificar Disponibilidade',
            'description' => 'Checagem de horários disponíveis',
            'path' => 'api/check-availability.php',
            'type' => 'API',
            'status' => 'active',
            'features' => ['Calendário', 'Slots', 'Validações'],
            'connects_to' => []
        ],
        'api/validate-discount.php' => [
            'title' => 'Validar Desconto',
            'description' => 'Validação de cupons e códigos',
            'path' => 'api/validate-discount.php',
            'type' => 'API',
            'status' => 'active',
            'features' => ['Cupons', 'Códigos promo', 'Limites'],
            'connects_to' => []
        ],
        'api/pause-subscription.php' => [
            'title' => 'Pausar Assinatura',
            'description' => 'API para pausar assinaturas',
            'path' => 'api/pause-subscription.php',
            'type' => 'API',
            'status' => 'active',
            'features' => ['Pause tiers', 'Políticas', 'Stripe'],
            'connects_to' => []
        ],
        'api/cancel-subscription.php' => [
            'title' => 'Cancelar Assinatura',
            'description' => 'API para cancelamentos',
            'path' => 'api/cancel-subscription.php',
            'type' => 'API',
            'status' => 'active',
            'features' => ['Penalidades', 'Refunds', 'Stripe'],
            'connects_to' => []
        ]
    ]
];

// Função para obter estatísticas do projeto
function getProjectStats($structure) {
    $stats = [
        'total_pages' => 0,
        'active_pages' => 0,
        'api_endpoints' => 0,
        'demo_pages' => 0
    ];
    
    foreach ($structure as $section => $pages) {
        foreach ($pages as $page) {
            $stats['total_pages']++;
            
            if ($page['status'] === 'active') {
                $stats['active_pages']++;
            }
            
            if ($page['type'] === 'API') {
                $stats['api_endpoints']++;
            }
            
            if ($page['status'] === 'demo') {
                $stats['demo_pages']++;
            }
        }
    }
    
    return $stats;
}

$projectStats = getProjectStats($projectStructure);

// Função para verificar se a página existe
function pageExists($path) {
    return file_exists(__DIR__ . '/' . $path);
}

// Função para obter status da página
function getPageStatus($path) {
    if (pageExists($path)) {
        return ['exists' => true, 'size' => filesize(__DIR__ . '/' . $path)];
    }
    return ['exists' => false, 'size' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa do Projeto - <?= $projectConfig['name'] ?></title>
    
    <!-- CSS do projeto -->
    <link rel="stylesheet" href="assets/css/blue.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --demo-color: #8b5cf6;
        }
        
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .project-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .project-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .project-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 10px 0 0 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 1rem;
        }
        
        .section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 25px 0;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .page-card {
            border: 2px solid #f3f4f6;
            border-radius: 15px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .page-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
        }
        
        .page-card.active {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05), rgba(16, 185, 129, 0.1));
        }
        
        .page-card.demo {
            border-color: var(--demo-color);
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05), rgba(139, 92, 246, 0.1));
        }
        
        .page-card.api {
            border-color: var(--info-color);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05), rgba(59, 130, 246, 0.1));
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .page-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .page-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-demo {
            background: #f3e8ff;
            color: #7c2d12;
        }
        
        .status-api {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .page-description {
            color: #6b7280;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .page-path {
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 15px;
        }
        
        .page-features {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .feature-tag {
            background: #e5e7eb;
            color: #374151;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .page-connections {
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .connections-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .connection-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .connection-link {
            background: #667eea;
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .connection-link:hover {
            background: #5a67d8;
            transform: scale(1.05);
        }
        
        .page-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .file-info {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
        }
        
        .exists {
            background: rgba(16, 185, 129, 0.8);
        }
        
        .missing {
            background: rgba(239, 68, 68, 0.8);
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .search-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: border-color 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .pages-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .project-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header do Projeto -->
    <header class="project-header">
        <div class="project-title">
            <i class="fas fa-gem"></i>
            <?= $projectConfig['name'] ?>
        </div>
        <div class="project-subtitle">
            Versão <?= $projectConfig['version'] ?> • <?= $projectConfig['description'] ?>
        </div>
    </header>

    <div class="container">
        <!-- Estatísticas do Projeto -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $projectStats['total_pages'] ?></div>
                <div class="stat-label">Total de Páginas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $projectStats['active_pages'] ?></div>
                <div class="stat-label">Páginas Ativas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $projectStats['api_endpoints'] ?></div>
                <div class="stat-label">APIs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $projectStats['demo_pages'] ?></div>
                <div class="stat-label">Demos</div>
            </div>
        </div>

        <!-- Caixa de Pesquisa -->
        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="🔍 Pesquisar páginas, APIs ou funcionalidades...">
        </div>

        <!-- Legenda -->
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: var(--success-color);"></div>
                <span>Página Ativa</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--info-color);"></div>
                <span>API Endpoint</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--demo-color);"></div>
                <span>Demo/Showcase</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: var(--danger-color);"></div>
                <span>Arquivo Ausente</span>
            </div>
        </div>

        <?php foreach ($projectStructure as $sectionKey => $pages): ?>
        <div class="section" id="section-<?= $sectionKey ?>">
            <h2 class="section-title">
                <div class="section-icon">
                    <?php
                    $icons = [
                        'core_pages' => 'fa-home',
                        'customer_area' => 'fa-user',
                        'professional_area' => 'fa-briefcase',
                        'support_pages' => 'fa-life-ring',
                        'showcase_pages' => 'fa-eye',
                        'api_endpoints' => 'fa-code'
                    ];
                    ?>
                    <i class="fas <?= $icons[$sectionKey] ?? 'fa-folder' ?>"></i>
                </div>
                <?php
                $sectionTitles = [
                    'core_pages' => 'Páginas Principais',
                    'customer_area' => 'Área do Cliente',
                    'professional_area' => 'Área do Profissional',
                    'support_pages' => 'Páginas de Suporte',
                    'showcase_pages' => 'Demos e Showcase',
                    'api_endpoints' => 'APIs e Endpoints'
                ];
                echo $sectionTitles[$sectionKey] ?? ucfirst(str_replace('_', ' ', $sectionKey));
                ?>
            </h2>
            
            <div class="pages-grid">
                <?php foreach ($pages as $pagePath => $pageInfo): ?>
                <?php $fileStatus = getPageStatus($pageInfo['path']); ?>
                <div class="page-card <?= $pageInfo['status'] ?> <?= $pageInfo['type'] === 'API' ? 'api' : '' ?>" data-page="<?= $pagePath ?>">
                    <div class="file-info <?= $fileStatus['exists'] ? 'exists' : 'missing' ?>">
                        <?= $fileStatus['exists'] ? '✓ ' . number_format($fileStatus['size']) . ' bytes' : '✗ Missing' ?>
                    </div>
                    
                    <div class="page-header">
                        <h3 class="page-title"><?= htmlspecialchars($pageInfo['title']) ?></h3>
                        <span class="page-status status-<?= $pageInfo['status'] ?>"><?= $pageInfo['status'] ?></span>
                    </div>
                    
                    <p class="page-description"><?= htmlspecialchars($pageInfo['description']) ?></p>
                    
                    <div class="page-path"><?= htmlspecialchars($pageInfo['path']) ?></div>
                    
                    <?php if (!empty($pageInfo['features'])): ?>
                    <div class="page-features">
                        <?php foreach ($pageInfo['features'] as $feature): ?>
                        <span class="feature-tag"><?= htmlspecialchars($feature) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($pageInfo['connects_to'])): ?>
                    <div class="page-connections">
                        <div class="connections-title">Conecta com:</div>
                        <div class="connection-list">
                            <?php foreach ($pageInfo['connects_to'] as $connection): ?>
                            <a href="#" class="connection-link" onclick="highlightPage('<?= $connection ?>')">
                                <?= htmlspecialchars(basename($connection, '.php')) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="page-actions">
                        <?php if ($fileStatus['exists'] && $pageInfo['type'] !== 'API'): ?>
                        <a href="<?= htmlspecialchars($pageInfo['path']) ?>" target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i>
                            Abrir Página
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($pageInfo['type'] === 'API'): ?>
                        <button class="btn btn-secondary" onclick="testAPI('<?= $pageInfo['path'] ?>')">
                            <i class="fas fa-play"></i>
                            Testar API
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-secondary" onclick="showPageDetails('<?= $pagePath ?>')">
                            <i class="fas fa-info-circle"></i>
                            Detalhes
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- JavaScript para funcionalidade interativa -->
    <script>
        // Dados do projeto para JavaScript
        const projectData = <?= json_encode($projectStructure) ?>;
        
        // Função de pesquisa
        function setupSearch() {
            const searchInput = document.getElementById('searchInput');
            const pageCards = document.querySelectorAll('.page-card');
            
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                
                pageCards.forEach(card => {
                    const title = card.querySelector('.page-title').textContent.toLowerCase();
                    const description = card.querySelector('.page-description').textContent.toLowerCase();
                    const features = Array.from(card.querySelectorAll('.feature-tag')).map(tag => tag.textContent.toLowerCase()).join(' ');
                    const path = card.querySelector('.page-path').textContent.toLowerCase();
                    
                    const matches = title.includes(query) || description.includes(query) || features.includes(query) || path.includes(query);
                    
                    if (matches || query === '') {
                        card.style.display = 'block';
                        card.parentElement.parentElement.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Esconder seções vazias
                document.querySelectorAll('.section').forEach(section => {
                    const visibleCards = section.querySelectorAll('.page-card[style="display: block"], .page-card:not([style*="none"])');
                    section.style.display = visibleCards.length > 0 ? 'block' : 'none';
                });
            });
        }
        
        // Destacar página específica
        function highlightPage(pagePath) {
            // Remove destaque anterior
            document.querySelectorAll('.page-card').forEach(card => {
                card.style.border = '';
                card.style.boxShadow = '';
            });
            
            // Encontra e destaca a página
            const targetCard = document.querySelector(`[data-page="${pagePath}"]`);
            if (targetCard) {
                targetCard.style.border = '3px solid #f59e0b';
                targetCard.style.boxShadow = '0 0 20px rgba(245, 158, 11, 0.5)';
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        // Mostrar detalhes da página
        function showPageDetails(pagePath) {
            // Encontra os dados da página
            let pageData = null;
            for (const section in projectData) {
                if (projectData[section][pagePath]) {
                    pageData = projectData[section][pagePath];
                    break;
                }
            }
            
            if (pageData) {
                alert(`Detalhes da Página:\n\nTítulo: ${pageData.title}\nTipo: ${pageData.type}\nStatus: ${pageData.status}\nPath: ${pageData.path}\n\nDescrição:\n${pageData.description}\n\nFuncionalidades:\n• ${pageData.features.join('\n• ')}`);
            }
        }
        
        // Testar API (simulação)
        function testAPI(apiPath) {
            alert(`Testando API: ${apiPath}\n\nEsta funcionalidade simularia uma requisição para a API.\nImplementar integração real conforme necessário.`);
        }
        
        // Estatísticas em tempo real
        function updateStats() {
            const existingFiles = document.querySelectorAll('.file-info.exists').length;
            const missingFiles = document.querySelectorAll('.file-info.missing').length;
            
            console.log(`Arquivos encontrados: ${existingFiles}`);
            console.log(`Arquivos ausentes: ${missingFiles}`);
        }
        
        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            setupSearch();
            updateStats();
            
            console.log('🚀 Mapa do Projeto Blue carregado com sucesso!');
            console.log('📊 Total de páginas mapeadas:', Object.keys(projectData).reduce((total, section) => total + Object.keys(projectData[section]).length, 0));
        });
        
        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'f':
                        e.preventDefault();
                        document.getElementById('searchInput').focus();
                        break;
                    case 'h':
                        e.preventDefault();
                        window.location.hash = '#section-core_pages';
                        break;
                }
            }
        });
    </script>
</body>
</html>
