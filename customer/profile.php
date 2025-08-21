<?php
/**
 * Blue Cleaning Services - Customer Profile Management
 * Página de perfil do cliente baseada no modal altamente otimizado do booking3.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/env-loader.php';
require_once __DIR__ . '/../auth/SecurityMiddleware.php';

// Aplicar middleware de segurança
security_protect([
    'rate_limit' => ['max_requests' => 20, 'window' => 3600],
    'require_csrf' => ($_SERVER['REQUEST_METHOD'] === 'POST')
]);

// Simular dados do usuário (em produção, buscar do banco de dados)
$user_data = [
    'id' => 123,
    'full_name' => 'Erik Placido',
    'email' => 'erik@blueproject.com',
    'phone' => '+61 400 123 456',
    'address' => '123 Collins Street, Melbourne VIC 3000',
    'suburb' => 'Melbourne',
    'postcode' => '3000',
    'state' => 'VIC',
    'created_at' => '2024-01-15',
    'last_login' => date('Y-m-d H:i:s'),
    'preferences' => [
        'email_notifications' => true,
        'sms_notifications' => false,
        'marketing_emails' => true
    ]
];

$current_tab = $_GET['tab'] ?? 'profile';
$message = '';
$message_type = '';

// Processamento do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF token
    $csrf_token = $_POST['_csrf_token'] ?? '';
    
    // Em produção, verificar o token CSRF aqui
    // if (!hash_equals($_SESSION['_csrf_token'], $csrf_token)) {
    //     die('CSRF token mismatch');
    // }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            // Validar e atualizar dados do perfil
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if (empty($full_name) || empty($phone) || empty($address)) {
                $message = 'Please fill in all required fields.';
                $message_type = 'error';
            } else {
                // Em produção, atualizar no banco de dados
                $user_data['full_name'] = $full_name;
                $user_data['phone'] = $phone;
                $user_data['address'] = $address;
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = 'Please fill in all password fields.';
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $message_type = 'error';
            } elseif (strlen($new_password) < 8) {
                $message = 'Password must be at least 8 characters long.';
                $message_type = 'error';
            } else {
                // Em produção, verificar senha atual e atualizar
                $message = 'Password changed successfully!';
                $message_type = 'success';
            }
            break;
            
        case 'update_preferences':
            // Atualizar preferências de notificação
            $user_data['preferences']['email_notifications'] = isset($_POST['email_notifications']);
            $user_data['preferences']['sms_notifications'] = isset($_POST['sms_notifications']);
            $user_data['preferences']['marketing_emails'] = isset($_POST['marketing_emails']);
            
            $message = 'Notification preferences updated successfully!';
            $message_type = 'success';
            break;
    }
}

// Gerar CSRF token
$csrf_token = bin2hex(random_bytes(32));
// Em produção, armazenar em sessão: $_SESSION['_csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - Blue Cleaning Services</title>
    
    <!-- Headers de segurança -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Project Styles -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="../assets/css/modal-redesign.css">
    
    <style>
        :root {
            --glass-surface: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.3);
            --text-primary: #333;
            --text-secondary: #666;
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px 0;
        }

        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.25),
                0 8px 32px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            overflow: hidden;
        }

        .profile-header {
            background: var(--primary-gradient);
            color: white;
            padding: 32px;
            text-align: center;
            position: relative;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(255, 255, 255, 0.3);
        }

        .profile-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 16px;
        }

        .profile-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .profile-subtitle {
            margin: 8px 0 0 0;
            opacity: 0.8;
            font-size: 16px;
            font-weight: 400;
        }

        .nav-tabs-custom {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0 32px;
            margin: 0;
        }

        .nav-tabs-custom .nav-item {
            margin: 0;
        }

        .nav-tabs-custom .nav-link {
            background: none;
            border: none;
            color: white;
            padding: 16px 24px;
            font-weight: 500;
            opacity: 0.7;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-tabs-custom .nav-link:hover,
        .nav-tabs-custom .nav-link.active {
            opacity: 1;
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-tabs-custom .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 2px 2px 0 0;
        }

        .tab-content {
            padding: 40px;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .form-section h3 {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-section h3 i {
            color: #667eea;
            font-size: 18px;
        }

        .form-group-modern {
            margin-bottom: 24px;
        }

        .form-label-modern {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control-modern {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control-modern:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn-save-modern {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-save-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary-modern {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-primary);
            border: 2px solid #e2e8f0;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary-modern:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        .alert-modern {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: rgba(72, 187, 120, 0.1);
            color: #2d5a3d;
            border: 1px solid rgba(72, 187, 120, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #7f1d1d;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .switch-modern {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch-modern input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider-modern {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 24px;
        }

        .slider-modern:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .slider-modern {
            background-color: #667eea;
        }

        input:checked + .slider-modern:before {
            transform: translateX(26px);
        }

        .preference-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .preference-label {
            font-weight: 500;
            color: var(--text-primary);
        }

        .preference-description {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.5);
            padding: 24px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-gradient);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 10px;
            }
            
            .tab-content {
                padding: 24px;
            }
            
            .nav-tabs-custom {
                padding: 0 24px;
            }
            
            .nav-tabs-custom .nav-link {
                padding: 12px 16px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-card">
            <!-- Header -->
            <div class="profile-header">
                <div class="profile-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h1>Account Management</h1>
                <p class="profile-subtitle">Manage your profile, preferences, and account settings</p>
            </div>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $current_tab === 'profile' ? 'active' : '' ?>" 
                       href="?tab=profile" role="tab">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $current_tab === 'password' ? 'active' : '' ?>" 
                       href="?tab=password" role="tab">
                        <i class="fas fa-lock"></i> Password
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $current_tab === 'bank' ? 'active' : '' ?>" 
                       href="?tab=bank" role="tab">
                        <i class="fas fa-credit-card"></i> Bank Details
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $current_tab === 'history' ? 'active' : '' ?>" 
                       href="?tab=history" role="tab">
                        <i class="fas fa-history"></i> Payment History
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= $current_tab === 'referrals' ? 'active' : '' ?>" 
                       href="?tab=referrals" role="tab">
                        <i class="fas fa-users"></i> All Referrals
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <?php if ($message): ?>
                    <div class="alert-modern <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
                        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($current_tab === 'profile'): ?>
                    <!-- Profile Tab -->
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-user"></i> Full Name
                                </label>
                                <input type="text" 
                                       name="full_name" 
                                       value="<?= htmlspecialchars($user_data['full_name']) ?>" 
                                       class="form-control-modern" 
                                       required>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-envelope"></i> Email
                                </label>
                                <input type="email" 
                                       value="<?= htmlspecialchars($user_data['email']) ?>" 
                                       class="form-control-modern" 
                                       readonly
                                       style="background: rgba(0,0,0,0.05); color: #666;">
                                <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">
                                    Email cannot be changed. Contact support if needed.
                                </small>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-phone"></i> Phone
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       value="<?= htmlspecialchars($user_data['phone']) ?>" 
                                       class="form-control-modern" 
                                       required>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </label>
                                <input type="text" 
                                       name="address" 
                                       value="<?= htmlspecialchars($user_data['address']) ?>" 
                                       class="form-control-modern" 
                                       required>
                            </div>
                            
                            <div style="display: flex; gap: 16px; margin-top: 32px;">
                                <button type="submit" class="btn-save-modern">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <button type="button" class="btn-secondary-modern" onclick="location.reload()">
                                    <i class="fas fa-undo"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="form-section">
                        <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="preference-item">
                                <div>
                                    <div class="preference-label">Email Notifications</div>
                                    <div class="preference-description">Receive booking confirmations and updates via email</div>
                                </div>
                                <label class="switch-modern">
                                    <input type="checkbox" name="email_notifications" <?= $user_data['preferences']['email_notifications'] ? 'checked' : '' ?>>
                                    <span class="slider-modern"></span>
                                </label>
                            </div>
                            
                            <div class="preference-item">
                                <div>
                                    <div class="preference-label">SMS Notifications</div>
                                    <div class="preference-description">Receive reminders and updates via SMS</div>
                                </div>
                                <label class="switch-modern">
                                    <input type="checkbox" name="sms_notifications" <?= $user_data['preferences']['sms_notifications'] ? 'checked' : '' ?>>
                                    <span class="slider-modern"></span>
                                </label>
                            </div>
                            
                            <div class="preference-item">
                                <div>
                                    <div class="preference-label">Marketing Emails</div>
                                    <div class="preference-description">Receive special offers and promotions</div>
                                </div>
                                <label class="switch-modern">
                                    <input type="checkbox" name="marketing_emails" <?= $user_data['preferences']['marketing_emails'] ? 'checked' : '' ?>>
                                    <span class="slider-modern"></span>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-save-modern" style="margin-top: 24px;">
                                <i class="fas fa-save"></i> Update Preferences
                            </button>
                        </form>
                    </div>

                <?php elseif ($current_tab === 'password'): ?>
                    <!-- Password Tab -->
                    <div class="form-section">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-key"></i> Current Password
                                </label>
                                <input type="password" 
                                       name="current_password" 
                                       class="form-control-modern" 
                                       required>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-lock"></i> New Password
                                </label>
                                <input type="password" 
                                       name="new_password" 
                                       class="form-control-modern" 
                                       minlength="8" 
                                       required>
                                <small style="color: #666; font-size: 12px; margin-top: 4px; display: block;">
                                    Password must be at least 8 characters long
                                </small>
                            </div>
                            
                            <div class="form-group-modern">
                                <label class="form-label-modern">
                                    <i class="fas fa-check-circle"></i> Confirm New Password
                                </label>
                                <input type="password" 
                                       name="confirm_password" 
                                       class="form-control-modern" 
                                       required>
                            </div>
                            
                            <button type="submit" class="btn-save-modern">
                                <i class="fas fa-save"></i> Change Password
                            </button>
                        </form>
                    </div>

                <?php elseif ($current_tab === 'bank'): ?>
                    <!-- Bank Details Tab -->
                    <div class="form-section">
                        <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
                        
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-credit-card" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>Payment methods are managed securely through Stripe.</p>
                            <p>Your payment information is encrypted and secure.</p>
                            <button class="btn-save-modern" onclick="alert('This feature will redirect to secure payment management.')">
                                <i class="fas fa-external-link-alt"></i> Manage Payment Methods
                            </button>
                        </div>
                    </div>

                <?php elseif ($current_tab === 'history'): ?>
                    <!-- Payment History Tab -->
                    <div class="form-section">
                        <h3><i class="fas fa-history"></i> Payment History</h3>
                        
                        <div class="stats-row">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="stat-value">$315.00</div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-value">3</div>
                                <div class="stat-label">Services Completed</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-value">4.9</div>
                                <div class="stat-label">Average Rating</div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-receipt" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                            <p>Your complete payment history and invoices will be displayed here.</p>
                            <p>Integration with booking system in progress.</p>
                        </div>
                    </div>

                <?php elseif ($current_tab === 'referrals'): ?>
                    <!-- Referrals Tab -->
                    <div class="form-section">
                        <h3><i class="fas fa-users"></i> Referral Program</h3>
                        
                        <div class="stats-row">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="stat-value">2</div>
                                <div class="stat-label">Successful Referrals</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-gift"></i>
                                </div>
                                <div class="stat-value">$50.00</div>
                                <div class="stat-label">Credits Earned</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-link"></i>
                                </div>
                                <div class="stat-value">BC2024ERK</div>
                                <div class="stat-label">Your Referral Code</div>
                            </div>
                        </div>
                        
                        <div style="background: rgba(102, 126, 234, 0.1); padding: 24px; border-radius: 16px; margin-top: 24px;">
                            <h4 style="color: #667eea; margin-bottom: 16px;">
                                <i class="fas fa-bullhorn"></i> Share and Earn
                            </h4>
                            <p style="margin-bottom: 16px; color: #555;">
                                Refer friends and family to Blue Cleaning Services and earn $25 credit for each successful booking!
                            </p>
                            <div style="display: flex; gap: 12px;">
                                <button class="btn-save-modern" onclick="copyReferralLink()">
                                    <i class="fas fa-copy"></i> Copy Referral Link
                                </button>
                                <button class="btn-secondary-modern" onclick="shareReferral()">
                                    <i class="fas fa-share"></i> Share
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Back to Dashboard Link -->
    <div style="text-align: center; margin-top: 24px;">
        <a href="../customer/dashboard.php" class="btn-secondary-modern">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Adicionar interatividade
        function copyReferralLink() {
            const referralCode = 'BC2024ERK';
            const referralLink = `https://bluecleaningservices.com.au/booking3.php?ref=${referralCode}`;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(referralLink).then(() => {
                    showNotification('Referral link copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = referralLink;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('Referral link copied to clipboard!', 'success');
            }
        }

        function shareReferral() {
            const referralCode = 'BC2024ERK';
            const referralLink = `https://bluecleaningservices.com.au/booking3.php?ref=${referralCode}`;
            const shareText = `Check out Blue Cleaning Services! Use my referral code ${referralCode} and we both get $25 credit. Book now: ${referralLink}`;

            if (navigator.share) {
                navigator.share({
                    title: 'Blue Cleaning Services Referral',
                    text: shareText,
                    url: referralLink
                });
            } else {
                // Fallback - abrir email
                const emailSubject = encodeURIComponent('Blue Cleaning Services Referral');
                const emailBody = encodeURIComponent(shareText);
                window.open(`mailto:?subject=${emailSubject}&body=${emailBody}`);
            }
        }

        function showNotification(message, type = 'success') {
            // Criar notificação dinâmica
            const notification = document.createElement('div');
            notification.className = `alert-modern ${type === 'success' ? 'alert-success' : 'alert-error'}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                min-width: 300px;
                animation: slideIn 0.3s ease-out;
            `;

            document.body.appendChild(notification);

            // Remover após 4 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 4000);
        }

        // Adicionar animações CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Validação de formulários
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = '#ef4444';
                            
                            // Remover destaque após edição
                            field.addEventListener('input', function() {
                                this.style.borderColor = '#e2e8f0';
                            }, { once: true });
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Please fill in all required fields.', 'error');
                    }
                });
            });

            // Validação de senha em tempo real
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (newPassword && confirmPassword) {
                function validatePasswords() {
                    if (newPassword.value && confirmPassword.value) {
                        if (newPassword.value !== confirmPassword.value) {
                            confirmPassword.style.borderColor = '#ef4444';
                            return false;
                        } else {
                            confirmPassword.style.borderColor = '#10b981';
                            return true;
                        }
                    }
                    return true;
                }
                
                newPassword.addEventListener('input', validatePasswords);
                confirmPassword.addEventListener('input', validatePasswords);
            }
        });

        console.log('✅ Customer Profile page loaded successfully');
    </script>
</body>
</html>
