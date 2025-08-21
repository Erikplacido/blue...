<?php
/**
 * Blue Cleaning Services - Navigation Hub
 * Portal de navegação completo para todas as páginas do projeto
 */

require_once 'config.php';
require_once 'includes/env-loader.php';

// Função para verificar se uma página existe e está acessível
function checkPageStatus($path) {
    if (file_exists($path)) {
        if (is_readable($path)) {
            return 'active';
        } else {
            return 'restricted';
        }
    } else {
        return 'missing';
    }
}

// Função para obter informações básicas do sistema
function getSystemInfo() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $connection = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Contar usuários por tipo
        $stats = [];
        
        // Contar bookings
        $stmt = $connection->query("SELECT COUNT(*) FROM bookings");
        $stats['bookings'] = $stmt->fetchColumn();
        
        // Contar profissionais ativos
        $stmt = $connection->query("SELECT COUNT(*) FROM professionals WHERE is_active = 1");
        $stats['professionals'] = $stmt->fetchColumn();
        
        // Contar serviços
        $stmt = $connection->query("SELECT COUNT(*) FROM services WHERE is_active = 1");
        $stats['services'] = $stmt->fetchColumn();
        
        // Último booking
        $stmt = $connection->query("SELECT created_at FROM bookings ORDER BY created_at DESC LIMIT 1");
        $lastBooking = $stmt->fetchColumn();
        $stats['last_booking'] = $lastBooking ? date('d/m/Y H:i', strtotime($lastBooking)) : 'Nenhum';
        
        return $stats;
        
    } catch (Exception $e) {
        return [
            'bookings' => 'N/A',
            'professionals' => 'N/A',
            'services' => 'N/A',
            'last_booking' => 'N/A'
        ];
    }
}

$systemInfo = getSystemInfo();

// Definir todas as páginas organizadas por categoria
$pages = [
    'main' => [
        'title' => '🏠 Páginas Principais',
        'pages' => [
            'index.html' => 'Página Inicial',
            'home.html' => 'Home Alternativa',
            'booking3.php' => '🎯 Sistema de Reservas Principal',
            'booking2.php' => 'Sistema de Reservas Backup',
            'navigation.php' => '🧭 Portal de Navegação (Esta página)',
        ]
    ],
    'customer' => [
        'title' => '👥 Área do Cliente',
        'pages' => [
            'customer/dashboard.php' => '📊 Dashboard do Cliente',
            'customer/subscription-management.php' => '⚙️ Gerenciar Assinaturas',
            'customer/profile.php' => '👤 Perfil do Cliente',
        ]
    ],
    'professional' => [
        'title' => '👷 Área Profissional',
        'pages' => [
            'professional/dashboard.php' => '📊 Dashboard Profissional',
            'professional/schedule.php' => '📅 Agenda/Cronograma',
            'professional/jobs.php' => '💼 Trabalhos Disponíveis',
            'professional/profile.php' => '👤 Perfil Profissional',
        ]
    ],
    'admin' => [
        'title' => '🔧 Área Administrativa',
        'pages' => [
            'admin/dashboard.php' => '📊 Dashboard Admin',
            'admin/reports.php' => '📋 Relatórios',
            'admin/training-management.php' => '🎓 Gestão de Treinamentos',
            'admin/bookings.php' => '📅 Gerenciar Reservas',
            'admin/customers.php' => '👥 Gerenciar Clientes',
            'admin/professionals.php' => '👷 Gerenciar Profissionais',
        ]
    ],
    'auth' => [
        'title' => '🔐 Autenticação e Segurança',
        'pages' => [
            'auth/login.php' => '🔑 Login',
            'auth/register.php' => '📝 Cadastro',
            'auth/logout.php' => '🚪 Logout',
            'auth/forgot-password.php' => '🔐 Recuperar Senha',
            'auth/security_dashboard.php' => '🛡️ Dashboard de Segurança',
            'auth/error_page.php' => '❌ Página de Erro',
        ]
    ],
    'payment' => [
        'title' => '💳 Sistema de Pagamentos',
        'pages' => [
            'payment_history.php' => '📜 Histórico de Pagamentos',
            'payment/subscription-success.php' => '✅ Pagamento Bem-sucedido',
            'payment/subscription-cancel.php' => '❌ Pagamento Cancelado',
            'booking-confirmation.php' => '✅ Confirmação de Reserva',
            'booking-confirmation-stripe.php' => '✅ Confirmação Stripe',
        ]
    ],
    'api' => [
        'title' => '🔌 APIs e Endpoints',
        'pages' => [
            'api/booking.php' => '📅 API de Reservas',
            'api/dashboard-complete.php' => '📊 API Dashboard Completo',
            'api/analytics.php' => '📈 API de Analytics',
            'api/availability-complete.php' => '📅 API Disponibilidade',
            'api/check-availability.php' => '🔍 Verificar Disponibilidade',
            'api/get_available_times.php' => '🕐 Horários Disponíveis',
            'api/get_available_days.php' => '📅 Dias Disponíveis',
            'api/stripe-checkout-unified-final.php' => '💳 Checkout Stripe Final',
            'api/system-config-dynamic.php' => '⚙️ Configuração Dinâmica',
            'api/validate-coupon.php' => '🎫 Validar Cupom',
        ]
    ],
    'utilities' => [
        'title' => '🛠️ Utilitários e Ferramentas',
        'pages' => [
            'utils/help.php' => '❓ Ajuda',
            'utils/project_map.php' => '🗺️ Mapa do Projeto',
            'utils/analyze_schema.php' => '🔍 Analisar Schema',
            'utils/check-table-structure.php' => '📊 Estrutura de Tabelas',
            'simple-price-check.php' => '💰 Verificador de Preços',
            'comprehensive-price-analysis.php' => '📊 Análise Completa de Preços',
            'database_analysis.php' => '🗄️ Análise do Banco de Dados',
        ]
    ],
    'system' => [
        'title' => '⚙️ Sistema e Configuração',
        'pages' => [
            'setup-coupon-system.php' => '🎫 Setup Sistema de Cupons',
            'setup_dynamic_tables.php' => '📊 Setup Tabelas Dinâmicas',
            'configure-minimum-quantities.php' => '📊 Configurar Quantidades',
            'populate-inclusions.php' => '📝 Popular Inclusões',
            'update-service-extras.php' => '➕ Atualizar Extras',
            'config.php' => '⚙️ Configurações',
        ]
    ],
    'referral' => [
        'title' => '🎯 Sistema de Indicações',
        'pages' => [
            'referralclub.php' => '🎯 Clube de Indicações',
            'referralclub2.php' => '🎯 Clube de Indicações v2',
            'referralclub3.php' => '🎯 Clube de Indicações v3',
            'referral_processor.php' => '🔄 Processador de Indicações',
            'setup_booking_referral_integration.php' => '🔗 Integração Indicações',
        ]
    ],
    'support' => [
        'title' => '💬 Suporte e Documentação',
        'pages' => [
            'support.php' => '💬 Suporte',
            'tracking.php' => '📦 Rastreamento',
            'PROJECT_DOCUMENTATION.md' => '📚 Documentação Completa',
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blue Cleaning Services - Portal de Navegação</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .system-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .categories {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .category {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .category:hover {
            transform: translateY(-5px);
        }

        .category-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .category-pages {
            padding: 20px;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            background: #f8f9fa;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .page-link:hover {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1976d2;
            transform: translateX(5px);
        }

        .page-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-name {
            font-weight: 500;
        }

        .page-path {
            font-size: 0.8rem;
            color: #6c757d;
            font-family: monospace;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-missing {
            background: #f8d7da;
            color: #721c24;
        }

        .status-restricted {
            background: #fff3cd;
            color: #856404;
        }

        .search-box {
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
            padding: 15px 20px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        .search-box:focus {
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .filters {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #2c3e50;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .filter-btn:hover, .filter-btn.active {
            background: #2c3e50;
            color: white;
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            font-size: 0.85rem;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .categories {
                grid-template-columns: 1fr;
            }

            .system-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-gem"></i> Blue Cleaning Services</h1>
            <p>Portal de Navegação Completo do Sistema</p>
            
            <div class="system-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['bookings'] ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['professionals'] ?></div>
                    <div class="stat-label">Profissionais</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['services'] ?></div>
                    <div class="stat-label">Serviços</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['last_booking'] ?></div>
                    <div class="stat-label">Último Booking</div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <input type="text" id="searchBox" class="search-box" placeholder="🔍 Buscar páginas...">
        
        <div class="filters">
            <button class="filter-btn active" data-filter="all">Todas</button>
            <button class="filter-btn" data-filter="active">✅ Ativas</button>
            <button class="filter-btn" data-filter="missing">❌ Ausentes</button>
            <button class="filter-btn" data-filter="restricted">⚠️ Restritas</button>
        </div>

        <!-- Legend -->
        <div class="legend">
            <div class="legend-item">
                <span class="status-badge status-active">✓</span>
                <span>Página Ativa</span>
            </div>
            <div class="legend-item">
                <span class="status-badge status-missing">✗</span>
                <span>Página Não Encontrada</span>
            </div>
            <div class="legend-item">
                <span class="status-badge status-restricted">⚠</span>
                <span>Acesso Restrito</span>
            </div>
        </div>

        <!-- Categories -->
        <div class="categories" id="categoriesContainer">
            <?php foreach ($pages as $categoryKey => $category): ?>
                <div class="category" data-category="<?= $categoryKey ?>">
                    <div class="category-header">
                        <?= $category['title'] ?>
                    </div>
                    <div class="category-pages">
                        <?php foreach ($category['pages'] as $path => $name): ?>
                            <?php 
                                $status = checkPageStatus($path);
                                $statusClass = "status-$status";
                                $statusIcon = $status === 'active' ? '✓' : ($status === 'missing' ? '✗' : '⚠');
                            ?>
                            <a href="<?= $status === 'active' ? $path : '#' ?>" 
                               class="page-link <?= $status !== 'active' ? 'disabled' : '' ?>"
                               data-status="<?= $status ?>"
                               data-path="<?= $path ?>"
                               data-name="<?= strtolower($name) ?>">
                                <div class="page-info">
                                    <span class="page-name"><?= $name ?></span>
                                    <span class="page-path"><?= $path ?></span>
                                </div>
                                <span class="status-badge <?= $statusClass ?>"><?= $statusIcon ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Blue Cleaning Services</strong> - Portal de Navegação v2.1</p>
            <p>Sistema completo com <?= array_sum(array_map(function($cat) { return count($cat['pages']); }, $pages)) ?> páginas catalogadas</p>
            <p><small>Última atualização: <?= date('d/m/Y H:i') ?></small></p>
        </div>
    </div>

    <script>
        // Funcionalidade de busca
        document.getElementById('searchBox').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const pageLinks = document.querySelectorAll('.page-link');
            
            pageLinks.forEach(link => {
                const name = link.getAttribute('data-name');
                const path = link.getAttribute('data-path').toLowerCase();
                const shouldShow = name.includes(searchTerm) || path.includes(searchTerm);
                
                link.style.display = shouldShow ? 'flex' : 'none';
            });

            // Ocultar categorias vazias
            document.querySelectorAll('.category').forEach(category => {
                const visibleLinks = category.querySelectorAll('.page-link[style="display: flex"], .page-link:not([style])');
                category.style.display = visibleLinks.length > 0 ? 'block' : 'none';
            });
        });

        // Funcionalidade de filtros
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Atualizar botões ativos
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');
                const pageLinks = document.querySelectorAll('.page-link');

                pageLinks.forEach(link => {
                    const status = link.getAttribute('data-status');
                    const shouldShow = filter === 'all' || filter === status;
                    link.style.display = shouldShow ? 'flex' : 'none';
                });

                // Ocultar categorias vazias
                document.querySelectorAll('.category').forEach(category => {
                    const visibleLinks = category.querySelectorAll('.page-link[style="display: flex"], .page-link:not([style*="none"])');
                    category.style.display = visibleLinks.length > 0 ? 'block' : 'none';
                });
            });
        });

        // Prevenir cliques em páginas não funcionais
        document.querySelectorAll('.page-link[data-status="missing"], .page-link[data-status="restricted"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const status = this.getAttribute('data-status');
                const path = this.getAttribute('data-path');
                const message = status === 'missing' 
                    ? `Página não encontrada: ${path}` 
                    : `Acesso restrito a: ${path}`;
                alert(message);
            });
        });

        // Estatísticas em tempo real
        console.log('📊 Blue Cleaning Services Navigation Portal');
        console.log('📄 Total de páginas catalogadas: <?= array_sum(array_map(function($cat) { return count($cat["pages"]); }, $pages)) ?>');
        console.log('📂 Categorias: <?= count($pages) ?>');
    </script>
</body>
</html> 
            'services' => 'N/A',
            'last_booking' => 'N/A'
        ];
    }
}

$systemInfo = getSystemInfo();

// Definir estrutura de páginas organizadas
$pageStructure = [
    'admin' => [
        'title' => '🔐 Administração',
        'description' => 'Gestão completa do sistema',
        'icon' => 'fas fa-user-shield',
        'color' => '#ff6b6b',
        'pages' => [
            ['path' => 'admin/dashboard.php', 'title' => 'Dashboard Principal', 'icon' => 'fas fa-tachometer-alt'],
            ['path' => 'admin/reports.php', 'title' => 'Relatórios', 'icon' => 'fas fa-chart-line'],
            ['path' => 'admin/training-management.php', 'title' => 'Gestão de Treinamentos', 'icon' => 'fas fa-graduation-cap']
        ]
    ],
    'customer' => [
        'title' => '👥 Clientes',
        'description' => 'Portal do cliente',
        'icon' => 'fas fa-users',
        'color' => '#4ecdc4',
        'pages' => [
            ['path' => 'customer/dashboard.php', 'title' => 'Dashboard Cliente', 'icon' => 'fas fa-home'],
            ['path' => 'customer/subscription-management.php', 'title' => 'Gerenciar Assinatura', 'icon' => 'fas fa-credit-card'],
            ['path' => 'payment_history.php', 'title' => 'Histórico de Pagamentos', 'icon' => 'fas fa-history']
        ]
    ],
    'professional' => [
        'title' => '👔 Profissionais',
        'description' => 'Portal do profissional',
        'icon' => 'fas fa-hard-hat',
        'color' => '#45b7d1',
        'pages' => [
            ['path' => 'professional/dashboard.php', 'title' => 'Dashboard Profissional', 'icon' => 'fas fa-briefcase'],
            ['path' => 'professional/availability.php', 'title' => 'Gerenciar Disponibilidade', 'icon' => 'fas fa-calendar-alt'],
            ['path' => 'professional/complete-profile.php', 'title' => 'Completar Perfil', 'icon' => 'fas fa-user-edit'],
            ['path' => 'professional/register.php', 'title' => 'Cadastro Profissional', 'icon' => 'fas fa-user-plus']
        ]
    ],
    'public' => [
        'title' => '🌐 Páginas Públicas',
        'description' => 'Acesso livre',
        'icon' => 'fas fa-globe',
        'color' => '#4facfe',
        'pages' => [
            ['path' => 'index.html', 'title' => 'Página Inicial', 'icon' => 'fas fa-home'],
            ['path' => 'booking3.php', 'title' => 'Nova Reserva (V3)', 'icon' => 'fas fa-calendar-plus'],
            ['path' => 'booking-confirmation.php', 'title' => 'Confirmação de Reserva', 'icon' => 'fas fa-check-circle'],
            ['path' => 'support.php', 'title' => 'Suporte', 'icon' => 'fas fa-headset'],
            ['path' => 'referralclub3.php', 'title' => 'Clube de Indicação', 'icon' => 'fas fa-gift']
        ]
    ],
    'api' => [
        'title' => '🔌 APIs',
        'description' => 'Endpoints do sistema',
        'icon' => 'fas fa-code',
        'color' => '#43e97b',
        'pages' => [
            ['path' => 'api/stripe-checkout.php', 'title' => 'Stripe Checkout', 'icon' => 'fas fa-credit-card'],
            ['path' => 'api/validate-discount.php', 'title' => 'Validar Cupom', 'icon' => 'fas fa-percent'],
            ['path' => 'api/check-availability.php', 'title' => 'Verificar Disponibilidade', 'icon' => 'fas fa-clock'],
            ['path' => 'api/dashboard-complete.php', 'title' => 'Dashboard API', 'icon' => 'fas fa-chart-bar'],
            ['path' => 'api/pause-subscription.php', 'title' => 'Pausar Assinatura', 'icon' => 'fas fa-pause']
        ]
    ]
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🗺️ Central de Navegação - Booking OK</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .system-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }

        .section-card:hover {
            transform: translateY(-10px);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-icon {
            font-size: 2.5rem;
            margin-right: 20px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
            color: white;
            background: var(--section-color);
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .section-description {
            font-size: 0.9rem;
            color: #666;
        }

        .page-grid {
            display: grid;
            gap: 12px;
        }

        .page-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .page-item:hover {
            background: #f8f9fa;
            transform: translateX(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--section-color);
        }

        .page-icon {
            margin-right: 15px;
            width: 25px;
            text-align: center;
            font-size: 1.2rem;
        }

        .page-title {
            flex: 1;
            font-weight: 500;
        }

        .page-status {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            margin-left: 15px;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-missing { background: #f8d7da; color: #721c24; }
        .status-restricted { background: #fff3cd; color: #856404; }

        .quick-actions {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-decoration: none;
            color: white;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            background: linear-gradient(135deg, var(--btn-color), var(--btn-color-dark));
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.2);
        }

        .action-btn i {
            margin-right: 12px;
            font-size: 1.3rem;
        }

        @media (max-width: 768px) {
            .sections-grid {
                grid-template-columns: 1fr;
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .system-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header with System Stats -->
        <div class="header">
            <h1><i class="fas fa-compass"></i> Central de Navegação</h1>
            <p>Sistema completo de gestão - Booking OK Platform</p>
            
            <div class="system-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['bookings'] ?></div>
                    <div class="stat-label">Total de Reservas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['professionals'] ?></div>
                    <div class="stat-label">Profissionais Ativos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $systemInfo['services'] ?></div>
                    <div class="stat-label">Serviços Disponíveis</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= date('d/m') ?></div>
                    <div class="stat-label">Última Atualização</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-rocket"></i> Acesso Rápido às Principais Funções</h2>
            <div class="action-grid">
                <a href="booking3.php" class="action-btn" style="--btn-color: #667eea; --btn-color-dark: #764ba2;">
                    <i class="fas fa-calendar-plus"></i> Nova Reserva
                </a>
                <a href="admin/dashboard.php" class="action-btn" style="--btn-color: #ff6b6b; --btn-color-dark: #ee5a52;">
                    <i class="fas fa-tachometer-alt"></i> Painel Admin
                </a>
                <a href="customer/dashboard.php" class="action-btn" style="--btn-color: #4ecdc4; --btn-color-dark: #44a08d;">
                    <i class="fas fa-user-circle"></i> Portal Cliente
                </a>
                <a href="professional/dashboard.php" class="action-btn" style="--btn-color: #45b7d1; --btn-color-dark: #2980b9;">
                    <i class="fas fa-briefcase"></i> Portal Profissional
                </a>
            </div>
        </div>

        <!-- Main Sections Grid -->
        <div class="sections-grid">
            <?php foreach ($pageStructure as $sectionKey => $section): ?>
                <div class="section-card" style="--section-color: <?= $section['color'] ?>;">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="<?= $section['icon'] ?>"></i>
                        </div>
                        <div>
                            <div class="section-title"><?= $section['title'] ?></div>
                            <div class="section-description"><?= $section['description'] ?></div>
                        </div>
                    </div>
                    
                    <div class="page-grid">
                        <?php foreach ($section['pages'] as $page): ?>
                            <?php 
                                $status = checkPageStatus($page['path']);
                                $statusText = '';
                                $statusClass = '';
                                
                                switch ($status) {
                                    case 'active':
                                        $statusText = 'Ativo';
                                        $statusClass = 'status-active';
                                        break;
                                    case 'missing':
                                        $statusText = 'Não Encontrado';
                                        $statusClass = 'status-missing';
                                        break;
                                    case 'restricted':
                                        $statusText = 'Restrito';
                                        $statusClass = 'status-restricted';
                                        break;
                                }
                            ?>
                            <a href="<?= $page['path'] ?>" class="page-item" style="--section-color: <?= $section['color'] ?>;">
                                <i class="<?= $page['icon'] ?> page-icon"></i>
                                <span class="page-title"><?= $page['title'] ?></span>
                                <span class="page-status <?= $statusClass ?>"><?= $statusText ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Tools & Debug Section -->
            <div class="section-card" style="--section-color: #ffecd2;">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <div class="section-title">🛠️ Ferramentas de Debug</div>
                        <div class="section-description">Diagnóstico e testes</div>
                    </div>
                </div>
                
                <div class="page-grid">
                    <a href="debug-checkout.html" class="page-item" style="--section-color: #ffecd2;">
                        <i class="fas fa-bug page-icon"></i>
                        <span class="page-title">Debug Checkout</span>
                        <span class="page-status <?= checkPageStatus('debug-checkout.html') == 'active' ? 'status-active' : 'status-missing' ?>">
                            <?= checkPageStatus('debug-checkout.html') == 'active' ? 'Ativo' : 'Não Encontrado' ?>
                        </span>
                    </a>
                    <a href="live-price-monitor.html" class="page-item" style="--section-color: #ffecd2;">
                        <i class="fas fa-chart-line page-icon"></i>
                        <span class="page-title">Monitor de Preços</span>
                        <span class="page-status <?= checkPageStatus('live-price-monitor.html') == 'active' ? 'status-active' : 'status-missing' ?>">
                            <?= checkPageStatus('live-price-monitor.html') == 'active' ? 'Ativo' : 'Não Encontrado' ?>
                        </span>
                    </a>
                    <a href="stripe-experience-demo.html" class="page-item" style="--section-color: #ffecd2;">
                        <i class="fas fa-eye page-icon"></i>
                        <span class="page-title">Demo Stripe Experience</span>
                        <span class="page-status <?= checkPageStatus('stripe-experience-demo.html') == 'active' ? 'status-active' : 'status-missing' ?>">
                            <?= checkPageStatus('stripe-experience-demo.html') == 'active' ? 'Ativo' : 'Não Encontrado' ?>
                        </span>
                    </a>
                    <a href="production-diagnostic.html" class="page-item" style="--section-color: #ffecd2;">
                        <i class="fas fa-stethoscope page-icon"></i>
                        <span class="page-title">Diagnóstico de Produção</span>
                        <span class="page-status <?= checkPageStatus('production-diagnostic.html') == 'active' ? 'status-active' : 'status-missing' ?>">
                            <?= checkPageStatus('production-diagnostic.html') == 'active' ? 'Ativo' : 'Não Encontrado' ?>
                        </span>
                    </a>
                </div>
            </div>

            <!-- Candidate Section -->
            <div class="section-card" style="--section-color: #f093fb;">
                <div class="section-header">
                    <div class="section-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div>
                        <div class="section-title">🎓 Portal de Candidatos</div>
                        <div class="section-description">Treinamentos e certificações</div>
                    </div>
                </div>
                
                <div class="page-grid">
                    <a href="candidate/trainings.php" class="page-item" style="--section-color: #f093fb;">
                        <i class="fas fa-book-reader page-icon"></i>
                        <span class="page-title">Treinamentos</span>
                        <span class="page-status <?= checkPageStatus('candidate/trainings.php') == 'active' ? 'status-active' : 'status-missing' ?>">
                            <?= checkPageStatus('candidate/trainings.php') == 'active' ? 'Ativo' : 'Não Encontrado' ?>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Adicionar efeitos de hover e animações
            document.querySelectorAll('.page-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    // Se a página não existe, avisar o usuário
                    if (this.querySelector('.status-missing')) {
                        e.preventDefault();
                        alert('Esta página ainda não foi criada ou não está acessível.');
                        return false;
                    }
                    
                    // Log da navegação
                    console.log('Navegando para:', this.href);
                });
            });

            // Adicionar indicadores visuais de status
            document.querySelectorAll('.page-status').forEach(status => {
                const text = status.textContent.trim();
                if (text === 'Ativo') {
                    status.title = 'Página funcionando normalmente';
                } else if (text === 'Não Encontrado') {
                    status.title = 'Página não encontrada no sistema';
                } else if (text === 'Restrito') {
                    status.title = 'Acesso restrito ou permissões necessárias';
                }
            });

            // Atualizar estatísticas em tempo real (simulação)
            setInterval(function() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('pt-BR');
                document.querySelector('.stat-card:last-child .stat-number').textContent = timeString;
                document.querySelector('.stat-card:last-child .stat-label').textContent = 'Última Atualização';
            }, 30000); // Atualizar a cada 30 segundos
        });
    </script>
</body>
</html>
