<?php
/**
 * Templates de Email - Blue Project V2
 * Templates HTML e texto para todos os tipos de email
 */

class EmailTemplates {
    
    // CSS base para todos os emails
    private static $baseCSS = "
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f8fafc; }
            .container { max-width: 600px; margin: 0 auto; background: white; }
            .header { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: white; padding: 40px 30px; text-align: center; }
            .logo { font-size: 28px; font-weight: bold; margin-bottom: 10px; }
            .header-subtitle { opacity: 0.9; font-size: 16px; }
            .content { padding: 40px 30px; }
            .footer { background: #f1f5f9; padding: 30px; text-align: center; color: #64748b; font-size: 14px; }
            .button { display: inline-block; background: #3b82f6; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
            .button:hover { background: #2563eb; }
            .info-box { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 20px; margin: 20px 0; }
            .success-box { background: #f0fdf4; border-left: 4px solid #22c55e; padding: 20px; margin: 20px 0; }
            .warning-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 20px; margin: 20px 0; }
            .danger-box { background: #fef2f2; border-left: 4px solid #ef4444; padding: 20px; margin: 20px 0; }
            .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            .details-table th, .details-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
            .details-table th { background: #f8fafc; font-weight: 600; }
            h2 { color: #1e293b; margin-top: 30px; }
            .highlight { background: #fef3c7; padding: 2px 6px; border-radius: 4px; }
        </style>
    ";
    
    /**
     * Template de confirmação de booking
     */
    public static function getBookingConfirmationTemplate() {
        return [
            'subject' => '✅ Booking Confirmed - {service_type} on {service_date}',
            'html' => self::$baseCSS . '
                <div class="container">
                    <div class="header">
                        <div class="logo">🔵 Blue Cleaning Services</div>
                        <div class="header-subtitle">Your booking is confirmed!</div>
                    </div>
                    
                    <div class="content">
                        <h2>Hello {customer_name}!</h2>
                        
                        <div class="success-box">
                            <strong>🎉 Great news!</strong> Your {service_type} booking has been confirmed and payment processed successfully.
                        </div>
                        
                        <h3>📋 Booking Details</h3>
                        <table class="details-table">
                            <tr><th>Booking ID</th><td><strong>{booking_id}</strong></td></tr>
                            <tr><th>Service</th><td>{service_type}</td></tr>
                            <tr><th>Date & Time</th><td>{service_date} - {service_time}</td></tr>
                            <tr><th>Address</th><td>{service_address}</td></tr>
                            <tr><th>Frequency</th><td>{recurrence}</td></tr>
                            <tr><th>Total Amount</th><td><strong>{total_amount}</strong></td></tr>
                        </table>
                        
                        <div class="info-box">
                            <strong>📅 Next Billing Date:</strong> {next_billing_date}<br>
                            <strong>💳 Payment Method:</strong> Your saved payment method will be charged automatically.
                        </div>
                        
                        <h3>🎯 What happens next?</h3>
                        <ul>
                            <li>Our professional will arrive within your selected time window</li>
                            <li>You\'ll receive a notification when they\'re on the way</li>
                            <li>For recurring services, we\'ll charge your card automatically before each service</li>
                            <li>You can manage your booking anytime in your dashboard</li>
                        </ul>
                        
                        <div style="text-align: center;">
                            <a href="{dashboard_url}" class="button">View Dashboard</a>
                        </div>
                        
                        <h3>❓ Need Help?</h3>
                        <p>If you have any questions or need to make changes, contact us at <strong>{support_email}</strong> or visit your dashboard.</p>
                    </div>
                    
                    <div class="footer">
                        <p>Thank you for choosing Blue Cleaning Services!</p>
                        <p>This email was sent automatically. Please do not reply to this address.</p>
                    </div>
                </div>
            ',
            'text' => '
                BOOKING CONFIRMATION - Blue Cleaning Services
                
                Hello {customer_name}!
                
                Your {service_type} booking has been confirmed and payment processed successfully.
                
                BOOKING DETAILS:
                - Booking ID: {booking_id}
                - Service: {service_type}
                - Date & Time: {service_date} - {service_time}
                - Address: {service_address}
                - Frequency: {recurrence}
                - Total Amount: {total_amount}
                - Next Billing: {next_billing_date}
                
                WHAT HAPPENS NEXT:
                - Our professional will arrive within your selected time window
                - You\'ll receive a notification when they\'re on the way
                - For recurring services, we\'ll charge your card automatically
                - You can manage your booking anytime in your dashboard
                
                Dashboard: {dashboard_url}
                
                Need help? Contact us at {support_email}
                
                Thank you for choosing Blue Cleaning Services!
            '
        ];
    }
    
    /**
     * Template de confirmação de pagamento
     */
    public static function getPaymentConfirmationTemplate() {
        return [
            'subject' => '💳 Payment Received - {payment_amount} for your cleaning service',
            'html' => self::$baseCSS . '
                <div class="container">
                    <div class="header">
                        <div class="logo">🔵 Blue Cleaning Services</div>
                        <div class="header-subtitle">Payment Confirmation</div>
                    </div>
                    
                    <div class="content">
                        <h2>Hello {customer_name}!</h2>
                        
                        <div class="success-box">
                            <strong>✅ Payment Received!</strong> Your payment of <strong>{payment_amount}</strong> has been processed successfully.
                        </div>
                        
                        <h3>💳 Payment Details</h3>
                        <table class="details-table">
                            <tr><th>Amount</th><td><strong>{payment_amount}</strong></td></tr>
                            <tr><th>Payment Date</th><td>{payment_date}</td></tr>
                            <tr><th>Service Date</th><td>{service_date}</td></tr>
                            <tr><th>Status</th><td><span class="highlight">Paid</span></td></tr>
                        </table>
                        
                        <div class="info-box">
                            <strong>📄 Invoice:</strong> Your detailed invoice is available in your dashboard.
                        </div>
                        
                        <div style="text-align: center;">
                            <a href="{invoice_url}" class="button">View Invoice</a>
                            <a href="{dashboard_url}" class="button" style="background: #6b7280;">Dashboard</a>
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>Thank you for your business!</p>
                        <p>Blue Cleaning Services - Making your home spotless</p>
                    </div>
                </div>
            ',
            'text' => '
                PAYMENT CONFIRMATION - Blue Cleaning Services
                
                Hello {customer_name}!
                
                Your payment of {payment_amount} has been processed successfully.
                
                PAYMENT DETAILS:
                - Amount: {payment_amount}
                - Payment Date: {payment_date}
                - Service Date: {service_date}
                - Status: Paid
                
                Invoice: {invoice_url}
                Dashboard: {dashboard_url}
                
                Thank you for your business!
                Blue Cleaning Services
            '
        ];
    }
    
    /**
     * Template de falha de pagamento
     */
    public static function getPaymentFailedTemplate() {
        return [
            'subject' => '⚠️ Payment Failed - Action Required for your {amount} payment',
            'html' => self::$baseCSS . '
                <div class="container">
                    <div class="header">
                        <div class="logo">🔵 Blue Cleaning Services</div>
                        <div class="header-subtitle">Payment Issue - Action Required</div>
                    </div>
                    
                    <div class="content">
                        <h2>Hello {customer_name}!</h2>
                        
                        <div class="warning-box">
                            <strong>⚠️ Payment Failed</strong><br>
                            We were unable to process your payment of <strong>{amount}</strong>.
                        </div>
                        
                        <h3>🔄 What we\'re doing:</h3>
                        <ul>
                            <li>This is attempt <strong>{attempt_number}</strong> of {max_attempts}</li>
                            <li>We\'ll automatically retry your payment in the next few days</li>
                            <li>Your service will continue as normal during retry attempts</li>
                        </ul>
                        
                        <div class="info-box">
                            <strong>💡 Common reasons for payment failures:</strong><br>
                            • Insufficient funds in your account<br>
                            • Expired or blocked card<br>
                            • Incorrect billing address<br>
                            • Bank security restrictions
                        </div>
                        
                        <h3>✅ What you can do:</h3>
                        <p>To avoid service interruption, please update your payment method or ensure sufficient funds are available.</p>
                        
                        <div style="text-align: center;">
                            <a href="{update_payment_url}" class="button">Update Payment Method</a>
                        </div>
                        
                        <div class="danger-box">
                            <strong>⏰ Important:</strong> If all {max_attempts} payment attempts fail, your service may be temporarily suspended until payment is resolved.
                        </div>
                        
                        <h3>❓ Need Help?</h3>
                        <p>If you\'re having trouble, our support team is here to help: <strong>{support_email}</strong></p>
                    </div>
                    
                    <div class="footer">
                        <p>Blue Cleaning Services Support Team</p>
                        <p>We\'re here to help resolve this quickly!</p>
                    </div>
                </div>
            ',
            'text' => '
                PAYMENT FAILED - ACTION REQUIRED
                Blue Cleaning Services
                
                Hello {customer_name}!
                
                We were unable to process your payment of {amount}.
                
                RETRY INFORMATION:
                - This is attempt {attempt_number} of {max_attempts}
                - We\'ll automatically retry in the next few days
                - Your service continues during retry attempts
                
                COMMON REASONS:
                - Insufficient funds
                - Expired or blocked card
                - Incorrect billing address
                - Bank security restrictions
                
                ACTION REQUIRED:
                Please update your payment method to avoid service interruption.
                
                Update Payment: {update_payment_url}
                
                Need help? Contact: {support_email}
                
                Blue Cleaning Services Support Team
            '
        ];
    }
    
    /**
     * Template de confirmação de pausa
     */
    public static function getPauseConfirmationTemplate() {
        return [
            'subject' => '⏸️ Service Paused - Resuming on {resume_date}',
            'html' => self::$baseCSS . '
                <div class="container">
                    <div class="header">
                        <div class="logo">🔵 Blue Cleaning Services</div>
                        <div class="header-subtitle">Service Pause Confirmation</div>
                    </div>
                    
                    <div class="content">
                        <h2>Hello {customer_name}!</h2>
                        
                        <div class="info-box">
                            <strong>⏸️ Service Paused</strong><br>
                            Your cleaning service has been paused as requested.
                        </div>
                        
                        <h3>📅 Pause Details</h3>
                        <table class="details-table">
                            <tr><th>Pause Start</th><td>{start_date}</td></tr>
                            <tr><th>Pause End</th><td>{end_date}</td></tr>
                            <tr><th>Duration</th><td>{duration}</td></tr>
                            <tr><th>Pause Fee</th><td><strong>{fee}</strong></td></tr>
                            <tr><th>Resume Date</th><td><strong>{resume_date}</strong></td></tr>
                        </table>
                        
                        <div class="success-box">
                            <strong>✅ What happens now:</strong><br>
                            • No services will be scheduled during the pause period<br>
                            • No charges will be made during the pause<br>
                            • Your service will automatically resume on {resume_date}<br>
                            • You can end the pause early anytime from your dashboard
                        </div>
                        
                        <div style="text-align: center;">
                            <a href="{dashboard_url}" class="button">Manage Subscription</a>
                        </div>
                        
                        <h3>🔄 Need to Resume Early?</h3>
                        <p>You can end your pause anytime before {end_date} through your dashboard. Your regular service schedule will resume immediately.</p>
                    </div>
                    
                    <div class="footer">
                        <p>Blue Cleaning Services</p>
                        <p>We\'ll be ready when you are!</p>
                    </div>
                </div>
            ',
            'text' => '
                SERVICE PAUSE CONFIRMATION
                Blue Cleaning Services
                
                Hello {customer_name}!
                
                Your cleaning service has been paused as requested.
                
                PAUSE DETAILS:
                - Start Date: {start_date}
                - End Date: {end_date}
                - Duration: {duration}
                - Fee: {fee}
                - Resume Date: {resume_date}
                
                WHAT HAPPENS NOW:
                - No services during pause period
                - No charges during pause
                - Automatic resume on {resume_date}
                - Can end pause early anytime
                
                Dashboard: {dashboard_url}
                
                Blue Cleaning Services
                We\'ll be ready when you are!
            '
        ];
    }
    
    /**
     * Template de confirmação de cancelamento
     */
    public static function getCancellationConfirmationTemplate() {
        return [
            'subject' => '❌ Subscription Cancelled - We\'re sorry to see you go',
            'html' => self::$baseCSS . '
                <div class="container">
                    <div class="header">
                        <div class="logo">🔵 Blue Cleaning Services</div>
                        <div class="header-subtitle">Cancellation Confirmation</div>
                    </div>
                    
                    <div class="content">
                        <h2>Hello {customer_name}!</h2>
                        
                        <div class="warning-box">
                            <strong>❌ Subscription Cancelled</strong><br>
                            Your cleaning service subscription has been cancelled as requested.
                        </div>
                        
                        <h3>📋 Cancellation Details</h3>
                        <table class="details-table">
                            <tr><th>Cancellation Date</th><td>{cancellation_date}</td></tr>
                            <tr><th>Final Service Date</th><td>{final_service_date}</td></tr>
                            <tr><th>Cancellation Reason</th><td>{reason}</td></tr>
                            <tr><th>Penalty Amount</th><td><strong>{penalty_amount}</strong></td></tr>
                            <tr><th>Refund Amount</th><td><strong>{refund_amount}</strong></td></tr>
                        </table>
                        
                        <div class="info-box">
                            <strong>💳 Payment Information:</strong><br>
                            • Any penalty charges have been processed<br>
                            • Refunds (if applicable) will appear in 3-5 business days<br>
                            • Final invoice will be sent separately
                        </div>
                        
                        <h3>🤝 We\'d love to have you back!</h3>
                        <p>While we\'re sorry to see you go, we understand that circumstances change. If you ever need our services again, we\'ll be here with the same quality and care you experienced.</p>
                        
                        <h3>📝 Feedback Welcome</h3>
                        <p>Your feedback helps us improve. If you have a moment, please let us know how we could have served you better by replying to <strong>{support_email}</strong></p>
                        
                        <div class="success-box">
                            <strong>✨ Thank you!</strong><br>
                            Thank you for choosing Blue Cleaning Services. We appreciate the trust you placed in us.
                        </div>
                    </div>
                    
                    <div class="footer">
                        <p>Blue Cleaning Services Team</p>
                        <p>Wishing you all the best!</p>
                    </div>
                </div>
            ',
            'text' => '
                SUBSCRIPTION CANCELLATION CONFIRMATION
                Blue Cleaning Services
                
                Hello {customer_name}!
                
                Your cleaning service subscription has been cancelled as requested.
                
                CANCELLATION DETAILS:
                - Cancellation Date: {cancellation_date}
                - Final Service Date: {final_service_date}
                - Reason: {reason}
                - Penalty Amount: {penalty_amount}
                - Refund Amount: {refund_amount}
                
                PAYMENT INFORMATION:
                - Penalty charges have been processed
                - Refunds will appear in 3-5 business days
                - Final invoice will be sent separately
                
                We\'d love to have you back someday!
                
                Feedback welcome at: {support_email}
                
                Thank you for choosing Blue Cleaning Services!
                Blue Cleaning Services Team
            '
        ];
    }
    
    /**
     * Template de boas-vindas para profissionais
     */
    public static function getProfessionalWelcomeTemplate() {
        return [
            'subject' => '🎉 Welcome to Blue Cleaning Services - Application Received!',
            'html' => self::$baseCSS . '
                <div class="container">
                    <div class="header">
                        <div class="logo">🔵 Blue Cleaning Services</div>
                        <div class="header-subtitle">Professional Network</div>
                    </div>
                    
                    <div class="content">
                        <h2>Welcome {professional_name}!</h2>
                        
                        <div class="success-box">
                            <strong>🎉 Application Received!</strong><br>
                            Thank you for applying to join our professional network. We\'re excited to review your application!
                        </div>
                        
                        <h3>📋 Application Details</h3>
                        <table class="details-table">
                            <tr><th>Application ID</th><td><strong>{application_id}</strong></td></tr>
                            <tr><th>Status</th><td><span class="highlight">Under Review</span></td></tr>
                            <tr><th>Expected Timeframe</th><td>{expected_timeframe}</td></tr>
                        </table>
                        
                        <h3>🔄 What happens next?</h3>
                        <ol>
                            <li><strong>Document Verification</strong> - We review your submitted documents</li>
                            <li><strong>Background Check</strong> - Automated verification of your credentials</li>
                            <li><strong>Skills Assessment</strong> - Online assessment of your cleaning expertise</li>
                            <li><strong>Trial Period</strong> - Supervised trial bookings to ensure quality</li>
                            <li><strong>Full Activation</strong> - Welcome to the Blue family! 🎉</li>
                        </ol>
                        
                        <div style="text-align: center;">
                            <a href="{verification_url}" class="button">Track Your Application</a>
                        </div>
                        
                        <div class="info-box">
                            <strong>💡 Pro Tip:</strong> Keep your phone handy! We may call you during the verification process to clarify any details.
                        </div>
                        
                        <h3>❓ Questions?</h3>
                        <p>If you have any questions about the process, feel free to reach out to our team at <strong>{support_email}</strong></p>
                    </div>
                    
                    <div class="footer">
                        <p>Blue Cleaning Services - Professional Network</p>
                        <p>Building the future of cleaning services together</p>
                    </div>
                </div>
            ',
            'text' => '
                WELCOME TO BLUE CLEANING SERVICES
                Professional Network Application
                
                Welcome {professional_name}!
                
                Thank you for applying to join our professional network!
                
                APPLICATION DETAILS:
                - Application ID: {application_id}
                - Status: Under Review
                - Expected Timeframe: {expected_timeframe}
                
                VERIFICATION PROCESS:
                1. Document Verification
                2. Background Check
                3. Skills Assessment
                4. Trial Period
                5. Full Activation
                
                Track your application: {verification_url}
                
                Questions? Contact: {support_email}
                
                Blue Cleaning Services - Professional Network
                Building the future of cleaning services together
            '
        ];
    }
}

?>
