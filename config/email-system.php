<?php
/**
 * Sistema de Email Completo - Blue Project V2
 * Gerenciamento de templates, SMTP e notificações automáticas
 */

require_once __DIR__ . '/email-templates.php';

class EmailSystem {
    
    // Configurações SMTP
    private static $smtpConfig = [
        'host' => 'smtp.gmail.com', // ou seu provedor SMTP
        'port' => 587,
        'security' => 'tls', // ou 'ssl'
        'username' => 'noreply@bluecleaningservices.com.au',
        'password' => 'your_app_password_here', // Use App Password para Gmail
        'from_email' => 'noreply@bluecleaningservices.com.au',
        'from_name' => 'Blue Cleaning Services',
        'reply_to' => 'support@bluecleaningservices.com.au'
    ];
    
    // Configurações gerais
    private static $config = [
        'enabled' => true,
        'debug_mode' => false,
        'log_emails' => true,
        'queue_emails' => false, // Para implementação futura
        'max_retries' => 3,
        'retry_delay' => 300, // 5 minutos
        'default_timezone' => 'Australia/Sydney'
    ];
    
    /**
     * Envia email usando PHPMailer
     */
    public static function send($to, $subject, $htmlBody, $textBody = null, $attachments = []) {
        try {
            if (!self::$config['enabled']) {
                self::log('Email sending disabled');
                return ['success' => false, 'message' => 'Email system disabled'];
            }
            
            // Se PHPMailer não estiver disponível, simular envio
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                return self::simulateEmail($to, $subject, $htmlBody);
            }
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configurar SMTP
            $mail->isSMTP();
            $mail->Host = self::$smtpConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = self::$smtpConfig['username'];
            $mail->Password = self::$smtpConfig['password'];
            $mail->SMTPSecure = self::$smtpConfig['security'];
            $mail->Port = self::$smtpConfig['port'];
            
            // Configurar remetente
            $mail->setFrom(self::$smtpConfig['from_email'], self::$smtpConfig['from_name']);
            $mail->addReplyTo(self::$smtpConfig['reply_to'], self::$smtpConfig['from_name']);
            
            // Configurar destinatário
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    $mail->addAddress($recipient);
                }
            } else {
                $mail->addAddress($to);
            }
            
            // Configurar conteúdo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?? strip_tags($htmlBody);
            $mail->CharSet = 'UTF-8';
            
            // Adicionar anexos
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $mail->addAttachment($attachment);
                }
            }
            
            // Enviar
            $mail->send();
            
            self::log("Email sent successfully to: $to");
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            self::log("PHPMailer Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            self::log("Email Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Simula envio de email para desenvolvimento
     */
    private static function simulateEmail($to, $subject, $htmlBody) {
        $logMessage = "SIMULATED EMAIL:\n";
        $logMessage .= "To: $to\n";
        $logMessage .= "Subject: $subject\n";
        $logMessage .= "Body Preview: " . substr(strip_tags($htmlBody), 0, 100) . "...\n";
        $logMessage .= "Sent at: " . date('Y-m-d H:i:s') . "\n";
        $logMessage .= str_repeat('-', 50) . "\n";
        
        self::log($logMessage);
        
        return ['success' => true, 'message' => 'Email simulated successfully'];
    }
    
    /**
     * Envia email de confirmação de booking
     */
    public static function sendBookingConfirmation($booking, $customer) {
        $template = EmailTemplates::getBookingConfirmationTemplate();
        
        $variables = [
            'customer_name' => $customer['name'] ?? 'Valued Customer',
            'booking_id' => $booking['booking_id'],
            'service_type' => ucfirst(str_replace('-', ' ', $booking['service_type'])),
            'service_date' => self::formatDate($booking['first_service_date']),
            'service_time' => $booking['time_window'] ?? 'To be confirmed',
            'service_address' => $booking['service_address'] ?? 'Not specified',
            'total_amount' => self::formatCurrency($booking['final_amount']),
            'recurrence' => self::formatRecurrence($booking['recurrence_pattern']),
            'next_billing_date' => $booking['next_billing_date'] ? self::formatDate($booking['next_billing_date']) : 'N/A',
            'dashboard_url' => self::getBaseUrl() . '/customer/dashboard.php',
            'support_email' => self::$smtpConfig['reply_to']
        ];
        
        $html = self::replaceVariables($template['html'], $variables);
        $text = self::replaceVariables($template['text'], $variables);
        $subject = self::replaceVariables($template['subject'], $variables);
        
        return self::send($customer['email'], $subject, $html, $text);
    }
    
    /**
     * Envia email de confirmação de pagamento
     */
    public static function sendPaymentConfirmation($booking, $payment) {
        $template = EmailTemplates::getPaymentConfirmationTemplate();
        
        $variables = [
            'customer_name' => $booking['customer_name'] ?? 'Valued Customer',
            'payment_amount' => self::formatCurrency($payment['amount']),
            'payment_date' => self::formatDate($payment['payment_date'] ?? date('Y-m-d')),
            'service_date' => self::formatDate($booking['first_service_date']),
            'invoice_url' => self::getBaseUrl() . '/invoice.php?booking_id=' . $booking['booking_id'],
            'dashboard_url' => self::getBaseUrl() . '/customer/dashboard.php'
        ];
        
        $html = self::replaceVariables($template['html'], $variables);
        $text = self::replaceVariables($template['text'], $variables);
        $subject = self::replaceVariables($template['subject'], $variables);
        
        return self::send($booking['customer_email'], $subject, $html, $text);
    }
    
    /**
     * Envia email de falha de pagamento
     */
    public static function sendPaymentFailed($booking, $attempt = 1) {
        $template = EmailTemplates::getPaymentFailedTemplate();
        
        $variables = [
            'customer_name' => $booking['customer_name'] ?? 'Valued Customer',
            'amount' => self::formatCurrency($booking['final_amount']),
            'attempt_number' => $attempt,
            'max_attempts' => self::$config['max_retries'],
            'update_payment_url' => self::getBaseUrl() . '/customer/subscription-management.php',
            'support_email' => self::$smtpConfig['reply_to']
        ];
        
        $html = self::replaceVariables($template['html'], $variables);
        $text = self::replaceVariables($template['text'], $variables);
        $subject = self::replaceVariables($template['subject'], $variables);
        
        return self::send($booking['customer_email'], $subject, $html, $text);
    }
    
    /**
     * Envia email de confirmação de pausa
     */
    public static function sendPauseConfirmation($booking, $pauseDetails) {
        $template = EmailTemplates::getPauseConfirmationTemplate();
        
        $variables = [
            'customer_name' => $booking['customer_name'] ?? 'Valued Customer',
            'start_date' => self::formatDate($pauseDetails['start_date']),
            'end_date' => self::formatDate($pauseDetails['end_date']),
            'duration' => $pauseDetails['duration'] . ' days',
            'fee' => $pauseDetails['is_free'] ? 'FREE' : self::formatCurrency($pauseDetails['fee']),
            'resume_date' => self::formatDate($pauseDetails['end_date']),
            'dashboard_url' => self::getBaseUrl() . '/customer/subscription-management.php'
        ];
        
        $html = self::replaceVariables($template['html'], $variables);
        $text = self::replaceVariables($template['text'], $variables);
        $subject = self::replaceVariables($template['subject'], $variables);
        
        return self::send($booking['customer_email'], $subject, $html, $text);
    }
    
    /**
     * Envia email de confirmação de cancelamento
     */
    public static function sendCancellationConfirmation($booking, $cancellationDetails) {
        $template = EmailTemplates::getCancellationConfirmationTemplate();
        
        $variables = [
            'customer_name' => $booking['customer_name'] ?? 'Valued Customer',
            'cancellation_date' => self::formatDate(date('Y-m-d')),
            'penalty_amount' => self::formatCurrency($cancellationDetails['penalty_amount'] ?? 0),
            'refund_amount' => self::formatCurrency($cancellationDetails['refund_amount'] ?? 0),
            'reason' => $cancellationDetails['reason'] ?? 'Not specified',
            'final_service_date' => $booking['last_service_date'] ?? 'To be confirmed',
            'support_email' => self::$smtpConfig['reply_to']
        ];
        
        $html = self::replaceVariables($template['html'], $variables);
        $text = self::replaceVariables($template['text'], $variables);
        $subject = self::replaceVariables($template['subject'], $variables);
        
        return self::send($booking['customer_email'], $subject, $html, $text);
    }
    
    /**
     * Envia email de boas-vindas para profissionais
     */
    public static function sendProfessionalWelcome($professional) {
        $template = EmailTemplates::getProfessionalWelcomeTemplate();
        
        $variables = [
            'professional_name' => $professional['personal_info']['full_name'] ?? 'Professional',
            'application_id' => $professional['application_id'],
            'verification_url' => self::getBaseUrl() . '/professional/verification-status.php?id=' . $professional['application_id'],
            'expected_timeframe' => '5-10 business days',
            'support_email' => self::$smtpConfig['reply_to']
        ];
        
        $html = self::replaceVariables($template['html'], $variables);
        $text = self::replaceVariables($template['text'], $variables);
        $subject = self::replaceVariables($template['subject'], $variables);
        
        return self::send($professional['personal_info']['email'], $subject, $html, $text);
    }
    
    /**
     * Envia notificação de disputa/chargeback
     */
    public static function sendChargebackNotification($booking, $dispute) {
        $adminEmail = 'admin@bluecleaningservices.com.au';
        
        $subject = "🚨 Chargeback Alert - Booking #{$booking['booking_id']}";
        
        $html = "
        <h2>Chargeback Notification</h2>
        <p><strong>Booking ID:</strong> {$booking['booking_id']}</p>
        <p><strong>Customer:</strong> {$booking['customer_name']} ({$booking['customer_email']})</p>
        <p><strong>Amount:</strong> " . self::formatCurrency($dispute['amount']) . "</p>
        <p><strong>Reason:</strong> {$dispute['reason']}</p>
        <p><strong>Disputed at:</strong> {$dispute['disputed_at']}</p>
        
        <p>Please review this dispute and take appropriate action in the Stripe dashboard.</p>
        ";
        
        return self::send($adminEmail, $subject, $html);
    }
    
    /**
     * Substitui variáveis no template
     */
    private static function replaceVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }
    
    /**
     * Formata data para exibição
     */
    private static function formatDate($date, $format = 'd/m/Y') {
        if (empty($date)) return 'Not specified';
        
        try {
            $dateObj = new DateTime($date);
            $dateObj->setTimezone(new DateTimeZone(self::$config['default_timezone']));
            return $dateObj->format($format);
        } catch (Exception $e) {
            return $date; // Retorna original se não conseguir formatar
        }
    }
    
    /**
     * Formata valor monetário
     */
    private static function formatCurrency($amount) {
        return '$' . number_format((float)$amount, 2);
    }
    
    /**
     * Formata tipo de recorrência
     */
    private static function formatRecurrence($pattern) {
        $patterns = [
            'one-time' => 'One-time service',
            'weekly' => 'Weekly',
            'fortnightly' => 'Fortnightly', 
            'monthly' => 'Monthly'
        ];
        
        return $patterns[$pattern] ?? ucfirst(str_replace('-', ' ', $pattern));
    }
    
    /**
     * Obtém URL base do sistema
     */
    private static function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
    
    /**
     * Log de emails
     */
    private static function log($message) {
        if (self::$config['log_emails']) {
            error_log('[EMAIL SYSTEM] ' . $message);
        }
    }
    
    /**
     * Configurar SMTP
     */
    public static function configureSMTP($config) {
        self::$smtpConfig = array_merge(self::$smtpConfig, $config);
    }
    
    /**
     * Testar configuração de email
     */
    public static function testEmail($toEmail = null) {
        $testEmail = $toEmail ?? self::$smtpConfig['reply_to'];
        
        $html = "
        <h2>Email Test - Blue Cleaning Services</h2>
        <p>This is a test email to verify your email configuration.</p>
        <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
        <p><strong>SMTP Host:</strong> " . self::$smtpConfig['host'] . "</p>
        <p>If you received this email, your configuration is working correctly!</p>
        ";
        
        return self::send($testEmail, 'Email Configuration Test', $html);
    }
    
    /**
     * Obter estatísticas de email (para implementação futura)
     */
    public static function getEmailStats() {
        // Implementar com banco de dados
        return [
            'total_sent' => 0,
            'total_failed' => 0,
            'last_sent' => null
        ];
    }
}

// Configurações específicas para desenvolvimento
if (!defined('EMAIL_SYSTEM_LOADED')) {
    define('EMAIL_SYSTEM_LOADED', true);
    
    // Detectar ambiente
    $isLocal = in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1']);
    
    if ($isLocal) {
        // Em desenvolvimento, usar simulação
        EmailSystem::$config['debug_mode'] = true;
        error_log('Email System loaded - Development mode (simulated emails)');
    } else {
        error_log('Email System loaded - Production mode');
    }
}

?>
