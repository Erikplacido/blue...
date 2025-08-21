<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Central de Ajuda - Blue Services">
    
    <title>Central de Ajuda - Blue Services</title>
    
    <link rel="stylesheet" href="liquid-glass-components.css">
    
    <style>
        .help-center {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 2rem 0;
        }

        .help-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 0 1rem;
        }

        .help-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .help-subtitle {
            font-size: 1.2rem;
            color: var(--neutral-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .search-section {
            max-width: 800px;
            margin: 0 auto 3rem;
            padding: 0 1rem;
        }

        .search-box {
            position: relative;
            margin-bottom: 2rem;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--neutral-gray);
            font-size: 1.2rem;
        }

        .quick-topics {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        .topic-tag {
            background: var(--white);
            border: 1px solid #e5e7eb;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: var(--neutral-gray);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .topic-tag:hover,
        .topic-tag.active {
            background: var(--primary-blue);
            color: var(--white);
            border-color: var(--primary-blue);
        }

        .faq-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .faq-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .category-card {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .category-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .category-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
        }

        .category-description {
            color: var(--neutral-gray);
            margin-bottom: 1rem;
        }

        .category-count {
            font-size: 0.9rem;
            color: var(--primary-blue);
            font-weight: 500;
        }

        .faq-section {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .faq-list {
            list-style: none;
        }

        .faq-item {
            border-bottom: 1px solid #f3f4f6;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
        }

        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .faq-question {
            font-weight: 600;
            color: var(--black);
            cursor: pointer;
            padding: 1rem 0;
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: color 0.3s ease;
        }

        .faq-question:hover {
            color: var(--primary-blue);
        }

        .faq-question::after {
            content: "+";
            font-size: 1.5rem;
            font-weight: 300;
            transition: transform 0.3s ease;
        }

        .faq-question.active::after {
            transform: rotate(45deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            color: var(--neutral-gray);
            line-height: 1.6;
        }

        .faq-answer.active {
            max-height: 200px;
            padding-bottom: 1rem;
        }

        .contact-section {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .contact-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 1rem;
        }

        .contact-text {
            color: var(--neutral-gray);
            margin-bottom: 2rem;
        }

        .contact-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .contact-option {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 1.5rem;
            text-decoration: none;
            color: var(--black);
            transition: all 0.3s ease;
        }

        .contact-option:hover {
            background: var(--primary-blue);
            color: var(--white);
            transform: translateY(-2px);
        }

        .contact-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .contact-label {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .contact-info {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .help-title {
                font-size: 2rem;
            }
            
            .faq-categories {
                grid-template-columns: 1fr;
            }
            
            .contact-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="help-center">
        <!-- Header -->
        <div class="help-header">
            <h1 class="help-title">Central de Ajuda</h1>
            <p class="help-subtitle">
                Encontre respostas rápidas para suas dúvidas ou entre em contato conosco
            </p>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" placeholder="Digite sua dúvida aqui..." id="searchInput">
            </div>
            
            <div class="quick-topics">
                <span class="topic-tag active" data-category="all">Todas</span>
                <span class="topic-tag" data-category="booking">Agendamento</span>
                <span class="topic-tag" data-category="payment">Pagamento</span>
                <span class="topic-tag" data-category="service">Serviços</span>
                <span class="topic-tag" data-category="account">Conta</span>
                <span class="topic-tag" data-category="professional">Profissionais</span>
            </div>
        </div>

        <div class="faq-container">
            <!-- Categories -->
            <div class="faq-categories">
                <div class="category-card" onclick="showCategory('booking')">
                    <div class="category-icon">📅</div>
                    <div class="category-title">Agendamento</div>
                    <div class="category-description">Como agendar, cancelar e reagendar serviços</div>
                    <div class="category-count">8 artigos</div>
                </div>

                <div class="category-card" onclick="showCategory('payment')">
                    <div class="category-icon">💳</div>
                    <div class="category-title">Pagamento</div>
                    <div class="category-description">Formas de pagamento, faturas e reembolsos</div>
                    <div class="category-count">6 artigos</div>
                </div>

                <div class="category-card" onclick="showCategory('service')">
                    <div class="category-icon">🧹</div>
                    <div class="category-title">Serviços</div>
                    <div class="category-description">Tipos de serviço, qualidade e garantias</div>
                    <div class="category-count">10 artigos</div>
                </div>

                <div class="category-card" onclick="showCategory('account')">
                    <div class="category-icon">👤</div>
                    <div class="category-title">Minha Conta</div>
                    <div class="category-description">Perfil, senha e preferências</div>
                    <div class="category-count">5 artigos</div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section">
                <h2>Perguntas Frequentes</h2>
                <ul class="faq-list" id="faqList">
                    <!-- Booking FAQs -->
                    <li class="faq-item" data-category="booking">
                        <div class="faq-question">Como posso agendar um serviço?</div>
                        <div class="faq-answer">
                            Você pode agendar através do nosso site ou app. Selecione o tipo de serviço, data, horário e endereço. Após confirmar os detalhes, você receberá uma confirmação por email.
                        </div>
                    </li>

                    <li class="faq-item" data-category="booking">
                        <div class="faq-question">Posso cancelar meu agendamento?</div>
                        <div class="faq-answer">
                            Sim, você pode cancelar até 24 horas antes do serviço sem custos. Cancelamentos com menos de 24 horas podem ter taxas aplicadas.
                        </div>
                    </li>

                    <li class="faq-item" data-category="booking">
                        <div class="faq-question">Como reagendar um serviço?</div>
                        <div class="faq-answer">
                            Acesse sua conta, vá em "Meus Agendamentos" e clique em "Reagendar". Escolha uma nova data e horário disponível.
                        </div>
                    </li>

                    <!-- Payment FAQs -->
                    <li class="faq-item" data-category="payment">
                        <div class="faq-question">Quais formas de pagamento aceitas?</div>
                        <div class="faq-answer">
                            Aceitamos cartões de crédito/débito (Visa, Mastercard, Elo), PIX, transferência bancária e dinheiro (para alguns serviços).
                        </div>
                    </li>

                    <li class="faq-item" data-category="payment">
                        <div class="faq-question">Quando é cobrado o pagamento?</div>
                        <div class="faq-answer">
                            Para cartão de crédito, cobramos após a conclusão do serviço. Para outros métodos, o pagamento pode ser solicitado antecipadamente.
                        </div>
                    </li>

                    <!-- Service FAQs -->
                    <li class="faq-item" data-category="service">
                        <div class="faq-question">Que tipos de serviço vocês oferecem?</div>
                        <div class="faq-answer">
                            Oferecemos limpeza residencial e comercial, jardinagem, reparos domésticos, pintura, serviços elétricos e hidráulicos.
                        </div>
                    </li>

                    <li class="faq-item" data-category="service">
                        <div class="faq-question">Os profissionais são verificados?</div>
                        <div class="faq-answer">
                            Sim, todos os profissionais passam por verificação de antecedentes, validação de experiência e avaliação de qualidade.
                        </div>
                    </li>

                    <li class="faq-item" data-category="service">
                        <div class="faq-question">Há garantia nos serviços?</div>
                        <div class="faq-answer">
                            Oferecemos garantia de satisfação. Se não estiver satisfeito, entraremos em contato para resolver a situação.
                        </div>
                    </li>

                    <!-- Account FAQs -->
                    <li class="faq-item" data-category="account">
                        <div class="faq-question">Como criar uma conta?</div>
                        <div class="faq-answer">
                            Clique em "Criar Conta", preencha seus dados básicos, confirme seu email e pronto! Você já pode agendar serviços.
                        </div>
                    </li>

                    <li class="faq-item" data-category="account">
                        <div class="faq-question">Esqueci minha senha, como recuperar?</div>
                        <div class="faq-answer">
                            Na tela de login, clique em "Esqueci minha senha", digite seu email e siga as instruções enviadas para redefinir.
                        </div>
                    </li>

                    <!-- Professional FAQs -->
                    <li class="faq-item" data-category="professional">
                        <div class="faq-question">Como me tornar um profissional Blue?</div>
                        <div class="faq-answer">
                            Acesse nossa área de profissionais, preencha o cadastro com suas informações e documentos. Após aprovação, você pode começar a receber solicitações.
                        </div>
                    </li>

                    <li class="faq-item" data-category="professional">
                        <div class="faq-question">Como funciona o sistema de avaliações?</div>
                        <div class="faq-answer">
                            Clientes avaliam os profissionais após cada serviço. As avaliações são baseadas em qualidade, pontualidade e atendimento.
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Contact Section -->
            <div class="contact-section">
                <h2 class="contact-title">Não encontrou sua resposta?</h2>
                <p class="contact-text">
                    Nossa equipe de suporte está pronta para ajudar você
                </p>
                
                <div class="contact-options">
                    <a href="#" class="contact-option" onclick="openChat()">
                        <div class="contact-icon">💬</div>
                        <div class="contact-label">Chat ao Vivo</div>
                        <div class="contact-info">Resposta em minutos</div>
                    </a>

                    <a href="tel:+5511999999999" class="contact-option">
                        <div class="contact-icon">📞</div>
                        <div class="contact-label">Telefone</div>
                        <div class="contact-info">(11) 99999-9999</div>
                    </a>

                    <a href="mailto:suporte@blueservices.com.br" class="contact-option">
                        <div class="contact-icon">📧</div>
                        <div class="contact-label">Email</div>
                        <div class="contact-info">suporte@blueservices.com.br</div>
                    </a>

                    <a href="https://wa.me/5511999999999" class="contact-option" target="_blank">
                        <div class="contact-icon">📱</div>
                        <div class="contact-label">WhatsApp</div>
                        <div class="contact-info">Mensagem direta</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // FAQ Toggle Functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const isActive = question.classList.contains('active');
                
                // Close all other FAQs
                document.querySelectorAll('.faq-question').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.remove('active');
                });
                
                // Toggle current FAQ
                if (!isActive) {
                    question.classList.add('active');
                    answer.classList.add('active');
                }
            });
        });

        // Search Functionality
        const searchInput = document.getElementById('searchInput');
        const faqItems = document.querySelectorAll('.faq-item');

        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Category Filter
        function showCategory(category) {
            const topicTags = document.querySelectorAll('.topic-tag');
            
            // Update active tag
            topicTags.forEach(tag => {
                tag.classList.remove('active');
                if (tag.dataset.category === category) {
                    tag.classList.add('active');
                }
            });
            
            // Filter FAQ items
            faqItems.forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Topic tag click handlers
        document.querySelectorAll('.topic-tag').forEach(tag => {
            tag.addEventListener('click', () => {
                const category = tag.dataset.category;
                showCategory(category);
            });
        });

        // Chat functionality
        function openChat() {
            // This would integrate with your chat system
            if (window.openSupportChat) {
                window.openSupportChat();
            } else {
                alert('Chat será aberto em breve!');
            }
        }

        // Auto-expand first FAQ item
        document.addEventListener('DOMContentLoaded', () => {
            const firstQuestion = document.querySelector('.faq-question');
            if (firstQuestion) {
                firstQuestion.click();
            }
        });
    </script>
</body>
</html>
