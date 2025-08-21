<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Treinamentos - Blue Cleaning</title>
    <link rel="stylesheet" href="../liquid-glass-components.css">
    <link rel="stylesheet" href="../assets/css/inclusion-layout.css">
    <style>
        .trainings-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
        }

        .welcome-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .progress-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .progress-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .progress-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .progress-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .progress-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .progress-label {
            color: var(--text-secondary);
            text-transform: uppercase;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .trainings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .training-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .training-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .training-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .training-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .training-type {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .type-onboarding {
            background: var(--primary-color-light);
            color: var(--primary-color);
        }

        .type-skill {
            background: var(--info-color-light);
            color: var(--info-color);
        }

        .type-certification {
            background: var(--warning-color-light);
            color: var(--warning-color);
        }

        .training-status {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-assigned {
            background: var(--info-color-light);
            color: var(--info-color);
        }

        .status-in-progress {
            background: var(--warning-color-light);
            color: var(--warning-color);
        }

        .status-completed {
            background: var(--success-color-light);
            color: var(--success-color);
        }

        .status-failed {
            background: var(--danger-color-light);
            color: var(--danger-color);
        }

        .training-content {
            margin-bottom: 2rem;
        }

        .training-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .training-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .training-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .progress-section {
            margin-bottom: 1.5rem;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--glass-bg-light);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            border-radius: var(--radius-full);
            transition: width 0.3s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
        }

        .training-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .btn-training {
            flex: 1;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .certificate-section {
            background: var(--success-color-light);
            border: 2px solid var(--success-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1rem;
            text-align: center;
        }

        .certificate-icon {
            font-size: 2.5rem;
            color: var(--success-color);
            margin-bottom: 1rem;
        }

        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--glass-bg);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            align-items: center;
        }

        .search-input {
            flex: 1;
            position: relative;
        }

        .search-input input {
            width: 100%;
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .trainings-container {
                margin: 1rem;
                padding: 1rem;
            }

            .progress-overview {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .trainings-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .training-actions {
                flex-direction: column;
            }

            .training-meta {
                font-size: 0.75rem;
                gap: 0.5rem;
            }
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--glass-border-color);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="trainings-container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1 class="heading-2 mb-2">Bem-vindo aos Treinamentos Blue Cleaning! 👋</h1>
            <p class="text-lg text-secondary">Complete os treinamentos para se tornar um profissional certificado</p>
        </div>

        <!-- Progress Overview -->
        <div class="progress-overview" id="progressOverview">
            <div class="progress-card">
                <div class="progress-icon">📚</div>
                <div class="progress-number" id="totalTrainings">0</div>
                <div class="progress-label">Total de Treinamentos</div>
            </div>
            
            <div class="progress-card">
                <div class="progress-icon">🎯</div>
                <div class="progress-number" id="inProgressTrainings">0</div>
                <div class="progress-label">Em Andamento</div>
            </div>
            
            <div class="progress-card">
                <div class="progress-icon">✅</div>
                <div class="progress-number" id="completedTrainings">0</div>
                <div class="progress-label">Concluídos</div>
            </div>
            
            <div class="progress-card">
                <div class="progress-icon">📊</div>
                <div class="progress-number" id="overallProgress">0%</div>
                <div class="progress-label">Progresso Geral</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-input">
                <span class="search-icon">🔍</span>
                <input type="text" class="form-input" id="searchInput" placeholder="Buscar treinamentos...">
            </div>
            
            <select class="form-input" id="statusFilter">
                <option value="">Todos os status</option>
                <option value="assigned">Atribuídos</option>
                <option value="in_progress">Em Andamento</option>
                <option value="completed">Concluídos</option>
                <option value="failed">Reprovados</option>
            </select>
            
            <select class="form-input" id="typeFilter">
                <option value="">Todos os tipos</option>
                <option value="onboarding">Onboarding</option>
                <option value="skill">Habilidade</option>
                <option value="certification">Certificação</option>
            </select>
        </div>

        <!-- Trainings Grid -->
        <div class="trainings-grid" id="trainingsGrid">
            <!-- Training cards will be loaded here -->
        </div>

        <!-- Loading State -->
        <div class="text-center" id="loadingState" style="display: none;">
            <div class="spinner mx-auto mb-4" style="width: 3rem; height: 3rem;"></div>
            <p class="text-secondary">Carregando seus treinamentos...</p>
        </div>

        <!-- Empty State -->
        <div class="empty-state" id="emptyState" style="display: none;">
            <div class="empty-icon">📚</div>
            <h3 class="heading-4 mb-2">Nenhum treinamento encontrado</h3>
            <p class="text-secondary">Você ainda não tem treinamentos atribuídos ou os filtros não retornaram resultados.</p>
            <button type="button" class="btn btn-primary mt-3" onclick="location.reload()">
                🔄 Atualizar Lista
            </button>
        </div>
    </div>

    <script src="../assets/js/candidate-trainings.js"></script>
</body>
</html>
