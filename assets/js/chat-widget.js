/* 
 * Blue Services - Support Chat Widget
 * Floating chat widget for instant customer support
 */

// Chat Widget CSS
const chatWidgetStyles = `
    .blue-chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .chat-trigger {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        transition: all 0.3s ease;
        color: white;
        font-size: 24px;
    }

    .chat-trigger:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(37, 99, 235, 0.6);
    }

    .chat-trigger.minimized {
        transform: rotate(45deg);
    }

    .chat-window {
        position: absolute;
        bottom: 80px;
        right: 0;
        width: 350px;
        height: 500px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        overflow: hidden;
        transform: scale(0) translateY(20px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
    }

    .chat-window.open {
        transform: scale(1) translateY(0);
        opacity: 1;
        pointer-events: all;
    }

    .chat-header {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: white;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chat-header-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .agent-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    .agent-details h4 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    .agent-status {
        font-size: 12px;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .status-dot {
        width: 6px;
        height: 6px;
        background: #10b981;
        border-radius: 50%;
    }

    .close-chat {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        padding: 4px;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        max-height: 350px;
    }

    .message {
        margin-bottom: 12px;
        display: flex;
        gap: 8px;
    }

    .message.user {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
    }

    .message.user .message-avatar {
        background: #2563eb;
        color: white;
    }

    .message-content {
        max-width: 80%;
        padding: 8px 12px;
        border-radius: 12px;
        font-size: 14px;
        line-height: 1.4;
    }

    .message.agent .message-content {
        background: #f3f4f6;
        color: #374151;
        border-bottom-left-radius: 4px;
    }

    .message.user .message-content {
        background: #2563eb;
        color: white;
        border-bottom-right-radius: 4px;
    }

    .message-time {
        font-size: 11px;
        opacity: 0.6;
        margin-top: 4px;
    }

    .typing-indicator {
        display: none;
        padding: 8px 12px;
        background: #f3f4f6;
        border-radius: 12px;
        border-bottom-left-radius: 4px;
        max-width: 60px;
    }

    .typing-dots {
        display: flex;
        gap: 2px;
    }

    .typing-dot {
        width: 6px;
        height: 6px;
        background: #9ca3af;
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }

    .typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 60%, 100% {
            transform: translateY(0);
        }
        30% {
            transform: translateY(-8px);
        }
    }

    .chat-input-container {
        padding: 16px;
        border-top: 1px solid #e5e7eb;
        background: white;
    }

    .chat-input-wrapper {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .chat-input {
        flex: 1;
        border: 1px solid #d1d5db;
        border-radius: 20px;
        padding: 8px 16px;
        font-size: 14px;
        resize: none;
        max-height: 80px;
        min-height: 36px;
        outline: none;
        transition: border-color 0.2s;
    }

    .chat-input:focus {
        border-color: #2563eb;
    }

    .send-button {
        width: 36px;
        height: 36px;
        background: #2563eb;
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: background 0.2s;
    }

    .send-button:hover {
        background: #1d4ed8;
    }

    .send-button:disabled {
        background: #d1d5db;
        cursor: not-allowed;
    }

    .quick-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .quick-action {
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 6px 12px;
        font-size: 12px;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
    }

    .quick-action:hover {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    @media (max-width: 480px) {
        .chat-window {
            width: calc(100vw - 40px);
            height: calc(100vh - 120px);
            bottom: 80px;
            right: 20px;
        }
    }
`;

class BlueChatWidget {
    constructor() {
        this.isOpen = false;
        this.messages = [];
        this.init();
    }

    init() {
        // Inject CSS
        this.injectStyles();
        
        // Create widget HTML
        this.createWidget();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Initialize with welcome message
        this.addMessage('agent', 'Olá! Como posso ajudar você hoje?');
        
        // Setup quick actions
        this.setupQuickActions();
    }

    injectStyles() {
        const style = document.createElement('style');
        style.textContent = chatWidgetStyles;
        document.head.appendChild(style);
    }

    createWidget() {
        const widget = document.createElement('div');
        widget.className = 'blue-chat-widget';
        widget.innerHTML = `
            <div class="chat-window" id="chatWindow">
                <div class="chat-header">
                    <div class="chat-header-info">
                        <div class="agent-avatar">👨‍💼</div>
                        <div class="agent-details">
                            <h4>Suporte Blue</h4>
                            <div class="agent-status">
                                <span class="status-dot"></span>
                                Online agora
                            </div>
                        </div>
                    </div>
                    <button class="close-chat" id="closeChat">×</button>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <div class="quick-actions" id="quickActions">
                        <button class="quick-action" data-action="booking">Como agendar?</button>
                        <button class="quick-action" data-action="cancel">Cancelar serviço</button>
                        <button class="quick-action" data-action="payment">Problemas de pagamento</button>
                        <button class="quick-action" data-action="quality">Qualidade do serviço</button>
                    </div>
                    
                    <div class="typing-indicator" id="typingIndicator">
                        <div class="typing-dots">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
                
                <div class="chat-input-container">
                    <div class="chat-input-wrapper">
                        <textarea 
                            class="chat-input" 
                            id="chatInput" 
                            placeholder="Digite sua mensagem..."
                            rows="1"
                        ></textarea>
                        <button class="send-button" id="sendButton">
                            <span>▲</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <button class="chat-trigger" id="chatTrigger">
                <span id="triggerIcon">💬</span>
            </button>
        `;
        
        document.body.appendChild(widget);
    }

    setupEventListeners() {
        const trigger = document.getElementById('chatTrigger');
        const closeBtn = document.getElementById('closeChat');
        const sendBtn = document.getElementById('sendButton');
        const input = document.getElementById('chatInput');
        const quickActions = document.getElementById('quickActions');

        // Toggle chat
        trigger.addEventListener('click', () => this.toggleChat());
        closeBtn.addEventListener('click', () => this.closeChat());

        // Send message
        sendBtn.addEventListener('click', () => this.sendMessage());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Auto-resize textarea
        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 80) + 'px';
        });

        // Quick actions
        quickActions.addEventListener('click', (e) => {
            if (e.target.classList.contains('quick-action')) {
                this.handleQuickAction(e.target.dataset.action);
            }
        });
    }

    setupQuickActions() {
        this.quickActionResponses = {
            booking: 'Para agendar um serviço, acesse nosso site e selecione o tipo de serviço desejado. Você pode escolher data, horário e endereço. Precisa de ajuda com algum passo específico?',
            cancel: 'Você pode cancelar seu agendamento até 24 horas antes sem custos. Acesse "Meus Agendamentos" na sua conta ou me informe o número do seu agendamento para ajudar.',
            payment: 'Estou aqui para ajudar com problemas de pagamento. Você pode me contar qual dificuldade está enfrentando? Cartão recusado, cobrança incorreta ou outro problema?',
            quality: 'Lamento saber que houve algum problema com a qualidade. Poderia me contar o que aconteceu? Vou garantir que isso seja resolvido rapidamente.'
        };
    }

    toggleChat() {
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }

    openChat() {
        this.isOpen = true;
        const window = document.getElementById('chatWindow');
        const trigger = document.getElementById('chatTrigger');
        const icon = document.getElementById('triggerIcon');
        
        window.classList.add('open');
        trigger.classList.add('minimized');
        icon.textContent = '×';
        
        // Focus input
        setTimeout(() => {
            document.getElementById('chatInput').focus();
        }, 300);

        // Track chat open event
        this.trackEvent('chat_opened');
    }

    closeChat() {
        this.isOpen = false;
        const window = document.getElementById('chatWindow');
        const trigger = document.getElementById('chatTrigger');
        const icon = document.getElementById('triggerIcon');
        
        window.classList.remove('open');
        trigger.classList.remove('minimized');
        icon.textContent = '💬';

        // Track chat close event
        this.trackEvent('chat_closed');
    }

    sendMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Add user message
        this.addMessage('user', message);
        input.value = '';
        input.style.height = 'auto';
        
        // Show typing indicator
        this.showTyping();
        
        // Simulate agent response
        setTimeout(() => {
            this.hideTyping();
            this.handleUserMessage(message);
        }, 1000 + Math.random() * 2000);

        // Track message sent
        this.trackEvent('message_sent', { message: message });
    }

    addMessage(sender, content) {
        const messagesContainer = document.getElementById('chatMessages');
        const time = new Date().toLocaleTimeString('pt-BR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        messageDiv.innerHTML = `
            <div class="message-avatar">
                ${sender === 'user' ? '👤' : '👨‍💼'}
            </div>
            <div>
                <div class="message-content">${content}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
        
        // Insert before typing indicator
        const typingIndicator = document.getElementById('typingIndicator');
        messagesContainer.insertBefore(messageDiv, typingIndicator);
        
        // Scroll to bottom
        this.scrollToBottom();
        
        // Store message
        this.messages.push({ sender, content, time });
    }

    handleQuickAction(action) {
        const response = this.quickActionResponses[action];
        if (response) {
            // Hide quick actions after first use
            document.getElementById('quickActions').style.display = 'none';
            
            this.showTyping();
            setTimeout(() => {
                this.hideTyping();
                this.addMessage('agent', response);
            }, 1000);

            // Track quick action
            this.trackEvent('quick_action_used', { action: action });
        }
    }

    handleUserMessage(message) {
        const lowerMessage = message.toLowerCase();
        let response = '';

        // Simple keyword-based responses
        if (lowerMessage.includes('agendar') || lowerMessage.includes('marcar')) {
            response = 'Para agendar, você pode usar nosso site ou app. Qual tipo de serviço você precisa? Temos limpeza, jardinagem, reparos e muito mais!';
        } else if (lowerMessage.includes('cancelar')) {
            response = 'Para cancelar, preciso do número do seu agendamento. Você pode encontrá-lo no email de confirmação ou na sua conta. Qual é o número?';
        } else if (lowerMessage.includes('pagamento') || lowerMessage.includes('cartão')) {
            response = 'Aceitamos cartão de crédito/débito, PIX e transferência. Se está tendo problemas, posso ajudar a verificar. Qual é a dificuldade específica?';
        } else if (lowerMessage.includes('preço') || lowerMessage.includes('valor')) {
            response = 'Os preços variam conforme o tipo e tamanho do serviço. Posso fazer uma estimativa para você. Que tipo de serviço precisa?';
        } else if (lowerMessage.includes('profissional') || lowerMessage.includes('funcionário')) {
            response = 'Todos nossos profissionais são verificados e avaliados pelos clientes. Se teve algum problema, me conte para que possamos resolver!';
        } else if (lowerMessage.includes('obrigad') || lowerMessage.includes('valeu')) {
            response = 'De nada! Fico feliz em ajudar. Se precisar de mais alguma coisa, estarei aqui! 😊';
        } else {
            response = 'Entendi sua mensagem. Para uma resposta mais detalhada, nossa equipe especializada pode ajudar melhor. Gostaria que transferisse para um atendente?';
        }

        this.addMessage('agent', response);
    }

    showTyping() {
        document.getElementById('typingIndicator').style.display = 'block';
        this.scrollToBottom();
    }

    hideTyping() {
        document.getElementById('typingIndicator').style.display = 'none';
    }

    scrollToBottom() {
        const container = document.getElementById('chatMessages');
        container.scrollTop = container.scrollHeight;
    }

    trackEvent(event, data = {}) {
        // Analytics tracking
        if (typeof gtag !== 'undefined') {
            gtag('event', event, {
                event_category: 'chat_widget',
                event_label: 'support_chat',
                ...data
            });
        }
        
        // Console log for debugging
        console.log('Chat Event:', event, data);
    }

    // Public API methods
    addMessageAPI(sender, content) {
        this.addMessage(sender, content);
    }

    openChatAPI() {
        this.openChat();
    }

    closeChatAPI() {
        this.closeChat();
    }
}

// Initialize chat widget when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.BlueChatWidget = new BlueChatWidget();
});

// Global functions for integration
window.openSupportChat = () => {
    if (window.BlueChatWidget) {
        window.BlueChatWidget.openChatAPI();
    }
};

window.addChatMessage = (sender, content) => {
    if (window.BlueChatWidget) {
        window.BlueChatWidget.addMessageAPI(sender, content);
    }
};

/* 
 * Usage Examples:
 * 
 * // Open chat programmatically
 * window.openSupportChat();
 * 
 * // Add a message from agent
 * window.addChatMessage('agent', 'Como posso ajudar?');
 * 
 * // Add a message from user
 * window.addChatMessage('user', 'Preciso de ajuda');
 */
