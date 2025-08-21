<?php
/**
 * Sistema de Cadastro de Profissionais - Blue Project V2
 * Sistema escalável para múltiplos tipos de serviços profissionais
 */

session_start();

// Configuração de tipos de serviços disponíveis
$serviceTypes = [
    'cleaning' => [
        'name' => 'House Cleaning',
        'icon' => 'fas fa-broom',
        'color' => '#10b981',
        'specializations' => ['Regular Cleaning', 'Deep Cleaning', 'Move-in/Move-out', 'Office Cleaning', 'Window Cleaning'],
        'required_equipment' => ['Vacuum', 'Cleaning Supplies', 'Microfiber Cloths'],
        'certification_required' => false,
        'insurance_required' => true,
        'base_rate' => 25.00
    ],
    'gardening' => [
        'name' => 'Garden Maintenance',
        'icon' => 'fas fa-seedling',
        'color' => '#059669',
        'specializations' => ['Lawn Mowing', 'Hedge Trimming', 'Garden Design', 'Tree Pruning', 'Landscaping'],
        'required_equipment' => ['Lawn Mower', 'Hedge Trimmer', 'Garden Tools'],
        'certification_required' => false,
        'insurance_required' => true,
        'base_rate' => 30.00
    ],
    'handyman' => [
        'name' => 'Handyman Services',
        'icon' => 'fas fa-tools',
        'color' => '#f59e0b',
        'specializations' => ['General Repairs', 'Furniture Assembly', 'Painting', 'Minor Plumbing', 'Electrical Work'],
        'required_equipment' => ['Tool Kit', 'Drill', 'Measuring Tools'],
        'certification_required' => true,
        'insurance_required' => true,
        'base_rate' => 45.00
    ],
    'plumbing' => [
        'name' => 'Plumbing Services',
        'icon' => 'fas fa-wrench',
        'color' => '#3b82f6',
        'specializations' => ['Leak Repairs', 'Pipe Installation', 'Drain Cleaning', 'Water Heater Service'],
        'required_equipment' => ['Pipe Wrenches', 'Snake Tool', 'Pressure Tester'],
        'certification_required' => true,
        'insurance_required' => true,
        'base_rate' => 65.00
    ],
    'electrical' => [
        'name' => 'Electrical Services',
        'icon' => 'fas fa-bolt',
        'color' => '#8b5cf6',
        'specializations' => ['Outlet Installation', 'Light Fixture', 'Switch Repairs', 'Safety Inspections'],
        'required_equipment' => ['Multimeter', 'Wire Strippers', 'Electrical Tools'],
        'certification_required' => true,
        'insurance_required' => true,
        'base_rate' => 75.00
    ],
    'painting' => [
        'name' => 'Painting Services',
        'icon' => 'fas fa-paint-roller',
        'color' => '#ef4444',
        'specializations' => ['Interior Painting', 'Exterior Painting', 'Wall Preparation', 'Decorative Finishes'],
        'required_equipment' => ['Brushes', 'Rollers', 'Drop Cloths', 'Ladders'],
        'certification_required' => false,
        'insurance_required' => true,
        'base_rate' => 35.00
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Registration - Blue Services</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/blue.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .registration-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .registration-header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .registration-header h1 {
            font-size: 2.5rem;
            margin: 0 0 10px;
            font-weight: 700;
        }
        
        .registration-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .registration-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            gap: 20px;
        }
        
        .step {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 15px 25px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
        }
        
        .step.completed {
            background: rgba(16, 185, 129, 0.3);
            border-color: rgba(16, 185, 129, 0.5);
        }
        
        .registration-form {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
        }
        
        .service-selection {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .service-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .service-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1);
        }
        
        .service-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        
        .service-card input[type="checkbox"] {
            position: absolute;
            top: 15px;
            right: 15px;
            transform: scale(1.2);
        }
        
        .service-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
            color: white;
        }
        
        .service-card h3 {
            margin: 0 0 10px;
            font-size: 1.3rem;
            color: #1f2937;
        }
        
        .service-specializations {
            margin: 15px 0;
        }
        
        .specialization-tag {
            display: inline-block;
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            margin: 2px;
        }
        
        .base-rate {
            font-weight: 600;
            color: #059669;
            font-size: 1.1rem;
            margin-top: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-upload {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .file-upload.has-file {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.05);
        }
        
        .file-upload-icon {
            font-size: 2rem;
            color: #9ca3af;
            margin-bottom: 10px;
        }
        
        .availability-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .day-card {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .day-card.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .day-name {
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .time-inputs {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .time-inputs input {
            padding: 6px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .progress-bar {
            background: #f3f4f6;
            border-radius: 10px;
            height: 8px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .verification-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .requirements-checklist {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
        }
        
        .requirement-icon.pending {
            background: #f59e0b;
        }
        
        .requirement-icon.completed {
            background: #10b981;
        }
        
        @media (max-width: 768px) {
            .registration-container {
                padding: 15px;
            }
            
            .registration-form {
                padding: 25px;
            }
            
            .service-selection {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .availability-grid {
                grid-template-columns: 1fr;
            }
            
            .registration-steps {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <!-- Header -->
        <div class="registration-header">
            <h1>Become a Blue Professional</h1>
            <p>Join our network of trusted service professionals and start earning on your schedule</p>
        </div>

        <!-- Progress Steps -->
        <div class="registration-steps">
            <div class="step active" data-step="1">
                <i class="fas fa-clipboard-list"></i>
                <span>Service Selection</span>
            </div>
            <div class="step" data-step="2">
                <i class="fas fa-user"></i>
                <span>Personal Info</span>
            </div>
            <div class="step" data-step="3">
                <i class="fas fa-file-alt"></i>
                <span>Documentation</span>
            </div>
            <div class="step" data-step="4">
                <i class="fas fa-calendar"></i>
                <span>Availability</span>
            </div>
            <div class="step" data-step="5">
                <i class="fas fa-check-circle"></i>
                <span>Verification</span>
            </div>
        </div>

        <!-- Registration Form -->
        <div class="registration-form">
            <form id="professionalRegistrationForm">
                <!-- Progress Bar -->
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: 20%"></div>
                </div>

                <!-- Step 1: Service Selection -->
                <div class="step-content active" data-step="1">
                    <h2>What services do you provide?</h2>
                    <p>Select all the services you're qualified and equipped to perform. You can add more services later.</p>
                    
                    <div class="service-selection">
                        <?php foreach ($serviceTypes as $key => $service): ?>
                        <div class="service-card" data-service="<?= $key ?>">
                            <input type="checkbox" name="services[]" value="<?= $key ?>" id="service_<?= $key ?>">
                            <div class="service-icon" style="background: <?= $service['color'] ?>">
                                <i class="<?= $service['icon'] ?>"></i>
                            </div>
                            <h3><?= $service['name'] ?></h3>
                            <div class="service-specializations">
                                <?php foreach (array_slice($service['specializations'], 0, 3) as $spec): ?>
                                <span class="specialization-tag"><?= $spec ?></span>
                                <?php endforeach; ?>
                                <?php if (count($service['specializations']) > 3): ?>
                                <span class="specialization-tag">+<?= count($service['specializations']) - 3 ?> more</span>
                                <?php endif; ?>
                            </div>
                            <div class="base-rate">From $<?= number_format($service['base_rate'], 0) ?>/hour</div>
                            
                            <?php if ($service['certification_required']): ?>
                            <div class="verification-status">
                                <i class="fas fa-certificate"></i>
                                <span>Certification Required</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Personal Information -->
                <div class="step-content" data-step="2">
                    <h2>Personal Information</h2>
                    <p>Tell us about yourself and your professional background.</p>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="first_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="last_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="mobile">Mobile Number *</label>
                            <input type="tel" id="mobile" name="mobile" placeholder="+61 4XX XXX XXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="dateOfBirth">Date of Birth *</label>
                            <input type="date" id="dateOfBirth" name="date_of_birth" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Prefer not to say</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="streetAddress">Street Address *</label>
                            <input type="text" id="streetAddress" name="street_address" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="suburb">Suburb *</label>
                            <input type="text" id="suburb" name="suburb" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="state">State *</label>
                            <select id="state" name="state" required>
                                <option value="">Select State</option>
                                <option value="NSW">New South Wales</option>
                                <option value="VIC">Victoria</option>
                                <option value="QLD">Queensland</option>
                                <option value="WA">Western Australia</option>
                                <option value="SA">South Australia</option>
                                <option value="TAS">Tasmania</option>
                                <option value="ACT">Australian Capital Territory</option>
                                <option value="NT">Northern Territory</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="postcode">Postcode *</label>
                            <input type="number" id="postcode" name="postcode" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="serviceRadius">Service Radius (km) *</label>
                            <select id="serviceRadius" name="service_radius" required>
                                <option value="5">5 km</option>
                                <option value="10">10 km</option>
                                <option value="15">15 km</option>
                                <option value="25">25 km</option>
                                <option value="50">50 km</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="yearsExperience">Years of Experience *</label>
                            <select id="yearsExperience" name="years_experience" required>
                                <option value="">Select Experience</option>
                                <option value="0-1">0-1 years</option>
                                <option value="2-5">2-5 years</option>
                                <option value="6-10">6-10 years</option>
                                <option value="11-15">11-15 years</option>
                                <option value="15+">15+ years</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="languages">Languages Spoken</label>
                        <input type="text" id="languages" name="languages" placeholder="e.g., English, Spanish, Mandarin">
                    </div>
                    
                    <div class="form-group">
                        <label for="profilePhoto">Profile Photo *</label>
                        <div class="file-upload" onclick="document.getElementById('profilePhotoInput').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-camera"></i>
                            </div>
                            <p>Click to upload your professional photo</p>
                            <small>JPG or PNG, max 5MB</small>
                        </div>
                        <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" style="display: none;">
                    </div>
                </div>

                <!-- Step 3: Documentation -->
                <div class="step-content" data-step="3">
                    <h2>Required Documentation</h2>
                    <p>Upload the required documents for verification. All information is securely stored and encrypted.</p>
                    
                    <div class="requirements-checklist">
                        <h3>Document Requirements</h3>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <span>Valid Australian Driver's License</span>
                        </div>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <span>Police Check (within 12 months)</span>
                        </div>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <span>Public Liability Insurance</span>
                        </div>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <span>ABN Registration</span>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="abnNumber">ABN Number *</label>
                            <input type="text" id="abnNumber" name="abn_number" placeholder="XX XXX XXX XXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="tfnNumber">Tax File Number *</label>
                            <input type="text" id="tfnNumber" name="tfn_number" placeholder="XXX XXX XXX" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Driver's License *</label>
                        <div class="file-upload" onclick="document.getElementById('licenseInput').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <p>Upload clear photo of both sides</p>
                            <small>PDF or Image files accepted</small>
                        </div>
                        <input type="file" id="licenseInput" name="drivers_license" accept="image/*,.pdf" style="display: none;" multiple>
                    </div>
                    
                    <div class="form-group">
                        <label>Police Check *</label>
                        <div class="file-upload" onclick="document.getElementById('policeCheckInput').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <p>Upload National Police Check certificate</p>
                            <small>Must be within 12 months</small>
                        </div>
                        <input type="file" id="policeCheckInput" name="police_check" accept=".pdf,image/*" style="display: none;">
                    </div>
                    
                    <div class="form-group">
                        <label>Public Liability Insurance *</label>
                        <div class="file-upload" onclick="document.getElementById('insuranceInput').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <p>Upload insurance certificate</p>
                            <small>Minimum $2M coverage required</small>
                        </div>
                        <input type="file" id="insuranceInput" name="insurance_certificate" accept=".pdf,image/*" style="display: none;">
                    </div>
                    
                    <div class="form-group">
                        <label>Professional Certifications (if applicable)</label>
                        <div class="file-upload" onclick="document.getElementById('certificationsInput').click()">
                            <div class="file-upload-icon">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <p>Upload any relevant certifications</p>
                            <small>Trade licenses, qualifications, etc.</small>
                        </div>
                        <input type="file" id="certificationsInput" name="certifications" accept=".pdf,image/*" style="display: none;" multiple>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="bankName">Bank Name *</label>
                            <select id="bankName" name="bank_name" required>
                                <option value="">Select Bank</option>
                                <option value="commonwealth">Commonwealth Bank</option>
                                <option value="westpac">Westpac</option>
                                <option value="anz">ANZ</option>
                                <option value="nab">National Australia Bank</option>
                                <option value="ing">ING</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="bsb">BSB *</label>
                            <input type="text" id="bsb" name="bsb" placeholder="XXX-XXX" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="accountNumber">Account Number *</label>
                            <input type="text" id="accountNumber" name="account_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="accountName">Account Name *</label>
                            <input type="text" id="accountName" name="account_name" required>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Availability -->
                <div class="step-content" data-step="4">
                    <h2>Set Your Availability</h2>
                    <p>When are you available to work? You can change this anytime in your dashboard.</p>
                    
                    <div class="form-group">
                        <label>Working Days & Hours</label>
                        <div class="availability-grid">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach ($days as $day): 
                            ?>
                            <div class="day-card" data-day="<?= strtolower($day) ?>">
                                <div class="day-name"><?= substr($day, 0, 3) ?></div>
                                <input type="checkbox" name="available_days[]" value="<?= strtolower($day) ?>" id="day_<?= strtolower($day) ?>">
                                <div class="time-inputs">
                                    <input type="time" name="<?= strtolower($day) ?>_start" placeholder="Start" disabled>
                                    <input type="time" name="<?= strtolower($day) ?>_end" placeholder="End" disabled>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="maxJobsPerDay">Maximum Jobs Per Day</label>
                            <select id="maxJobsPerDay" name="max_jobs_per_day">
                                <option value="1">1 job</option>
                                <option value="2">2 jobs</option>
                                <option value="3" selected>3 jobs</option>
                                <option value="4">4 jobs</option>
                                <option value="5">5 jobs</option>
                                <option value="6">6 jobs</option>
                                <option value="8">8 jobs</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="minimumNotice">Minimum Notice Time</label>
                            <select id="minimumNotice" name="minimum_notice">
                                <option value="2">2 hours</option>
                                <option value="4">4 hours</option>
                                <option value="8">8 hours</option>
                                <option value="24" selected>24 hours</option>
                                <option value="48">48 hours</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="transportType">Transportation</label>
                            <select id="transportType" name="transport_type" required>
                                <option value="">Select Option</option>
                                <option value="own_vehicle">Own Vehicle</option>
                                <option value="public_transport">Public Transport</option>
                                <option value="bicycle">Bicycle</option>
                                <option value="walking">Walking (local area only)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ownEquipment">Equipment</label>
                            <select id="ownEquipment" name="own_equipment" required>
                                <option value="">Select Option</option>
                                <option value="full">I have all necessary equipment</option>
                                <option value="partial">I have some equipment</option>
                                <option value="none">I need equipment provided</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="emergency_available" value="1">
                            Available for emergency/last-minute jobs (higher pay rates)
                        </label>
                    </div>
                </div>

                <!-- Step 5: Verification -->
                <div class="step-content" data-step="5">
                    <h2>Application Submitted!</h2>
                    <p>Thank you for applying to become a Blue Professional. Here's what happens next:</p>
                    
                    <div class="requirements-checklist">
                        <h3>Verification Process</h3>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <span>1</span>
                            </div>
                            <div>
                                <strong>Document Review</strong>
                                <p>We'll verify your documents within 2-5 business days</p>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <span>2</span>
                            </div>
                            <div>
                                <strong>Background Check</strong>
                                <p>Automated verification of your background and references</p>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <span>3</span>
                            </div>
                            <div>
                                <strong>Skills Assessment</strong>
                                <p>Online quiz and video interview (if required)</p>
                            </div>
                        </div>
                        <div class="requirement-item">
                            <div class="requirement-icon pending">
                                <span>4</span>
                            </div>
                            <div>
                                <strong>Trial Period</strong>
                                <p>3 supervised jobs to ensure quality standards</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="verification-status">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Application ID: #PROF<?= date('Ymd') . rand(1000, 9999) ?></strong>
                            <p>You'll receive email updates at each stage of the verification process.</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="navigation-buttons">
                    <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">Previous</button>
                    <button type="button" class="btn btn-primary" id="nextBtn">Next Step</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">Submit Application</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        class ProfessionalRegistration {
            constructor() {
                this.currentStep = 1;
                this.totalSteps = 5;
                this.formData = {};
                this.init();
            }

            init() {
                this.bindEvents();
                this.updateProgress();
            }

            bindEvents() {
                // Navigation buttons
                document.getElementById('nextBtn').addEventListener('click', () => this.nextStep());
                document.getElementById('prevBtn').addEventListener('click', () => this.prevStep());
                document.getElementById('submitBtn').addEventListener('click', (e) => this.submitForm(e));

                // Service card selection
                document.querySelectorAll('.service-card').forEach(card => {
                    card.addEventListener('click', (e) => {
                        if (e.target.type !== 'checkbox') {
                            const checkbox = card.querySelector('input[type="checkbox"]');
                            checkbox.checked = !checkbox.checked;
                        }
                        card.classList.toggle('selected', card.querySelector('input[type="checkbox"]').checked);
                    });
                });

                // File upload handling
                document.querySelectorAll('input[type="file"]').forEach(input => {
                    input.addEventListener('change', (e) => this.handleFileUpload(e));
                });

                // Day availability
                document.querySelectorAll('.day-card').forEach(card => {
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    const timeInputs = card.querySelectorAll('input[type="time"]');
                    
                    checkbox.addEventListener('change', () => {
                        card.classList.toggle('selected', checkbox.checked);
                        timeInputs.forEach(input => {
                            input.disabled = !checkbox.checked;
                            if (checkbox.checked) {
                                input.value = input.name.includes('start') ? '09:00' : '17:00';
                            } else {
                                input.value = '';
                            }
                        });
                    });
                });
            }

            nextStep() {
                if (this.validateCurrentStep()) {
                    this.saveCurrentStepData();
                    
                    if (this.currentStep < this.totalSteps) {
                        this.currentStep++;
                        this.showStep(this.currentStep);
                        this.updateProgress();
                    }
                }
            }

            prevStep() {
                if (this.currentStep > 1) {
                    this.currentStep--;
                    this.showStep(this.currentStep);
                    this.updateProgress();
                }
            }

            showStep(step) {
                // Hide all steps
                document.querySelectorAll('.step-content').forEach(content => {
                    content.classList.remove('active');
                });

                // Show current step
                document.querySelector(`.step-content[data-step="${step}"]`).classList.add('active');

                // Update step indicators
                document.querySelectorAll('.step').forEach((stepEl, index) => {
                    stepEl.classList.remove('active', 'completed');
                    if (index + 1 < step) {
                        stepEl.classList.add('completed');
                    } else if (index + 1 === step) {
                        stepEl.classList.add('active');
                    }
                });

                // Update navigation buttons
                document.getElementById('prevBtn').style.display = step > 1 ? 'block' : 'none';
                document.getElementById('nextBtn').style.display = step < this.totalSteps ? 'block' : 'none';
                document.getElementById('submitBtn').style.display = step === this.totalSteps ? 'block' : 'none';
            }

            updateProgress() {
                const progress = (this.currentStep / this.totalSteps) * 100;
                document.getElementById('progressFill').style.width = `${progress}%`;
            }

            validateCurrentStep() {
                const currentStepElement = document.querySelector(`.step-content[data-step="${this.currentStep}"]`);
                
                switch (this.currentStep) {
                    case 1:
                        const selectedServices = currentStepElement.querySelectorAll('input[type="checkbox"]:checked');
                        if (selectedServices.length === 0) {
                            alert('Please select at least one service you can provide.');
                            return false;
                        }
                        break;
                        
                    case 2:
                        const requiredFields = currentStepElement.querySelectorAll('input[required], select[required]');
                        for (let field of requiredFields) {
                            if (!field.value.trim()) {
                                field.focus();
                                alert(`Please fill in the ${field.labels[0]?.textContent || 'required field'}.`);
                                return false;
                            }
                        }
                        break;
                        
                    case 3:
                        const requiredFiles = ['profilePhotoInput', 'licenseInput', 'policeCheckInput', 'insuranceInput'];
                        for (let fileId of requiredFiles) {
                            const fileInput = document.getElementById(fileId);
                            if (!fileInput.files.length) {
                                alert(`Please upload the required ${fileInput.labels[0]?.textContent || 'document'}.`);
                                return false;
                            }
                        }
                        break;
                        
                    case 4:
                        const selectedDays = currentStepElement.querySelectorAll('input[name="available_days[]"]:checked');
                        if (selectedDays.length === 0) {
                            alert('Please select at least one day you\'re available to work.');
                            return false;
                        }
                        break;
                }
                
                return true;
            }

            saveCurrentStepData() {
                const currentStepElement = document.querySelector(`.step-content[data-step="${this.currentStep}"]`);
                const formData = new FormData();
                
                // Save all form fields from current step
                const inputs = currentStepElement.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        if (input.checked) {
                            formData.append(input.name, input.value);
                        }
                    } else if (input.type === 'file') {
                        if (input.files.length > 0) {
                            for (let file of input.files) {
                                formData.append(input.name, file);
                            }
                        }
                    } else {
                        formData.append(input.name, input.value);
                    }
                });
                
                this.formData[`step_${this.currentStep}`] = formData;
            }

            handleFileUpload(event) {
                const input = event.target;
                const uploadContainer = input.closest('.file-upload') || input.parentElement.querySelector('.file-upload');
                
                if (input.files.length > 0) {
                    uploadContainer.classList.add('has-file');
                    const fileName = input.files.length > 1 ? 
                        `${input.files.length} files selected` : 
                        input.files[0].name;
                    
                    let textElement = uploadContainer.querySelector('p');
                    textElement.textContent = fileName;
                } else {
                    uploadContainer.classList.remove('has-file');
                }
            }

            async submitForm(event) {
                event.preventDefault();
                
                if (!this.validateCurrentStep()) {
                    return;
                }
                
                this.saveCurrentStepData();
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Submitting...';
                submitBtn.disabled = true;
                
                try {
                    // Combine all form data
                    const finalFormData = new FormData();
                    
                    // Add all saved data from previous steps
                    Object.values(this.formData).forEach(stepData => {
                        for (let [key, value] of stepData.entries()) {
                            finalFormData.append(key, value);
                        }
                    });
                    
                    // Submit to server
                    const response = await fetch('/api/professional/register.php', {
                        method: 'POST',
                        body: finalFormData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message and redirect
                        alert('Application submitted successfully! You will receive email updates on your verification status.');
                        window.location.href = '/professional/dashboard.php';
                    } else {
                        throw new Error(result.message || 'Registration failed');
                    }
                    
                } catch (error) {
                    console.error('Registration error:', error);
                    alert('There was an error submitting your application. Please try again.');
                } finally {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            }
        }

        // Initialize the registration system
        document.addEventListener('DOMContentLoaded', () => {
            new ProfessionalRegistration();
        });
    </script>
</body>
</html>
