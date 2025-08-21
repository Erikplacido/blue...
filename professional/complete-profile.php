<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete seu Perfil - Blue Cleaning Services</title>
    <link rel="stylesheet" href="../liquid-glass-components.css">
    <link rel="stylesheet" href="../assets/css/inclusion-layout.css">
    <style>
        .onboarding-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-radius: var(--radius-lg);
            border: var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--glass-bg-light);
            border-radius: var(--radius-full);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--radius-full);
            transition: width 0.3s ease;
        }

        .onboarding-steps {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .step {
            opacity: 0.6;
            pointer-events: none;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .step.active {
            opacity: 1;
            pointer-events: all;
            transform: translateY(0);
        }

        .step.completed {
            opacity: 0.8;
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border-color);
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--glass-bg-light);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .step.completed .step-number {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 2rem;
            border: 2px dashed var(--glass-border-color);
            border-radius: var(--radius-lg);
            background: var(--glass-bg-light);
            transition: all 0.3s ease;
        }

        .photo-upload:hover {
            border-color: var(--primary-color);
            background: var(--glass-bg);
        }

        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: var(--radius-full);
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .photo-placeholder {
            width: 120px;
            height: 120px;
            background: var(--glass-bg);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 2rem;
            border: 2px dashed var(--glass-border-color);
        }

        .specialty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .specialty-card {
            padding: 1rem;
            background: var(--glass-bg-light);
            border-radius: var(--radius-md);
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .specialty-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .specialty-card.selected {
            border-color: var(--primary-color);
            background: var(--primary-color-light);
        }

        .specialty-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .availability-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .day-header {
            text-align: center;
            font-weight: 600;
            padding: 0.5rem;
            background: var(--glass-bg);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
        }

        .time-slot {
            padding: 0.25rem;
            text-align: center;
            background: var(--glass-bg-light);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.75rem;
        }

        .time-slot:hover {
            background: var(--primary-color-light);
        }

        .time-slot.selected {
            background: var(--primary-color);
            color: white;
        }

        .step-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border-color);
        }

        .btn-secondary {
            background: var(--glass-bg);
            color: var(--text-primary);
            border: 1px solid var(--glass-border-color);
        }

        .btn-secondary:hover {
            background: var(--glass-bg-light);
        }

        .completion-summary {
            background: var(--glass-bg-light);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .completion-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }

        .completion-check {
            width: 20px;
            height: 20px;
            border-radius: var(--radius-full);
            background: var(--success-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
        }

        .completion-check.pending {
            background: var(--warning-color);
        }

        .welcome-message {
            text-align: center;
            padding: 3rem 2rem;
        }

        .welcome-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .onboarding-container {
                margin: 1rem;
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .specialty-grid {
                grid-template-columns: 1fr;
            }

            .availability-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .step-buttons {
                flex-direction: column;
                gap: 1rem;
            }
        }

        /* Animações para transições */
        .fade-in {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading states */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--glass-border-color);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="text-center mb-4">
            <h1 class="heading-2 mb-2">Complete seu Perfil Profissional</h1>
            <p class="text-secondary">Finalize o cadastro para começar a receber solicitações de serviço</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
        </div>

        <div class="text-center mb-4">
            <span id="progressText" class="text-sm font-medium">0% concluído</span>
        </div>

        <!-- Steps -->
        <div class="onboarding-steps" id="onboardingSteps">
            <!-- Step 1: Basic Info -->
            <div class="step active" data-step="1">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div>
                        <h3 class="heading-4 mb-1">Informações Básicas</h3>
                        <p class="text-secondary text-sm">Complete seus dados pessoais</p>
                    </div>
                </div>

                <form class="form-grid" id="basicInfoForm">
                    <div class="form-group">
                        <label class="form-label" for="name">Nome Completo *</label>
                        <input type="text" id="name" name="name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Telefone *</label>
                        <input type="tel" id="phone" name="phone" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="dateOfBirth">Data de Nascimento *</label>
                        <input type="date" id="dateOfBirth" name="date_of_birth" class="form-input" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="address">Endereço *</label>
                        <input type="text" id="address" name="address" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="city">Cidade *</label>
                        <input type="text" id="city" name="city" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="state">Estado *</label>
                        <input type="text" id="state" name="state" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="postalCode">CEP *</label>
                        <input type="text" id="postalCode" name="postal_code" class="form-input" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="bio">Apresentação (opcional)</label>
                        <textarea id="bio" name="bio" class="form-input" rows="3" 
                            placeholder="Conte um pouco sobre você e sua experiência..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="emergencyContactName">Contato de Emergência - Nome *</label>
                        <input type="text" id="emergencyContactName" name="emergency_contact_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="emergencyContactPhone">Contato de Emergência - Telefone *</label>
                        <input type="tel" id="emergencyContactPhone" name="emergency_contact_phone" class="form-input" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="hasTransport" name="has_transport">
                            <span class="checkmark"></span>
                            Possui transporte próprio
                        </label>
                    </div>
                </form>

                <div class="step-buttons">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Próximo
                        <span class="spinner" id="step1Spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Profile Photo -->
            <div class="step" data-step="2">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div>
                        <h3 class="heading-4 mb-1">Foto de Perfil</h3>
                        <p class="text-secondary text-sm">Adicione uma foto profissional</p>
                    </div>
                </div>

                <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                    <div class="photo-placeholder" id="photoPlaceholder">
                        📷
                    </div>
                    <img class="photo-preview" id="photoPreview" style="display: none;">
                    <div>
                        <h4 class="heading-5 mb-1">Clique para adicionar foto</h4>
                        <p class="text-secondary text-sm">JPG, PNG ou WebP - máx. 5MB</p>
                    </div>
                    <input type="file" id="photoInput" name="photo" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                </div>

                <div class="step-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">Voltar</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Próximo
                        <span class="spinner" id="step2Spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 3: Specialties -->
            <div class="step" data-step="3">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <div>
                        <h3 class="heading-4 mb-1">Especialidades</h3>
                        <p class="text-secondary text-sm">Selecione suas áreas de expertise</p>
                    </div>
                </div>

                <div class="specialty-grid" id="specialtyGrid">
                    <!-- Specialties will be loaded dynamically -->
                </div>

                <div class="step-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">Voltar</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Próximo
                        <span class="spinner" id="step3Spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 4: Availability -->
            <div class="step" data-step="4">
                <div class="step-header">
                    <div class="step-number">4</div>
                    <div>
                        <h3 class="heading-4 mb-1">Disponibilidade</h3>
                        <p class="text-secondary text-sm">Defina seus horários de trabalho</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Distância máxima para atendimento (km)</label>
                    <input type="number" id="maxDistance" name="max_distance" class="form-input" value="50" min="1" max="100">
                </div>

                <div class="availability-grid" id="availabilityGrid">
                    <!-- Availability grid will be generated -->
                </div>

                <div class="step-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">Voltar</button>
                    <button type="button" class="btn btn-primary" onclick="nextStep()">
                        Próximo
                        <span class="spinner" id="step4Spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 5: Review & Complete -->
            <div class="step" data-step="5">
                <div class="step-header">
                    <div class="step-number">5</div>
                    <div>
                        <h3 class="heading-4 mb-1">Revisão Final</h3>
                        <p class="text-secondary text-sm">Verifique suas informações antes de finalizar</p>
                    </div>
                </div>

                <div class="completion-summary" id="completionSummary">
                    <!-- Summary will be generated -->
                </div>

                <div class="step-buttons">
                    <button type="button" class="btn btn-secondary" onclick="prevStep()">Voltar</button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="completeOnboarding()">
                        <span id="completeButtonText">Finalizar Perfil</span>
                        <span class="spinner" id="step5Spinner" style="display: none;"></span>
                    </button>
                </div>
            </div>

            <!-- Step 6: Welcome -->
            <div class="step" data-step="6">
                <div class="welcome-message">
                    <div class="welcome-icon">🎉</div>
                    <h2 class="heading-2 mb-2">Bem-vindo à Equipe Blue Cleaning!</h2>
                    <p class="text-lg mb-4">Seu perfil foi criado com sucesso. Agora você pode começar a receber solicitações de serviço.</p>
                    <button type="button" class="btn btn-primary btn-lg" onclick="goToDashboard()">
                        Ir para Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/onboarding-professional.js"></script>
</body>
</html>
