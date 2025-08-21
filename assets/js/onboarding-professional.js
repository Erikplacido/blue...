/**
 * Professional Onboarding JavaScript
 * Blue Cleaning Services - Complete Professional Profile
 */

class ProfessionalOnboarding {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 5;
        this.profileData = {};
        this.selectedSpecialties = [];
        this.selectedServiceTypes = [];
        this.availabilitySchedule = {};
        
        this.init();
    }
    
    init() {
        this.loadOnboardingData();
        this.setupEventListeners();
        this.generateAvailabilityGrid();
        this.loadSpecialties();
    }
    
    setupEventListeners() {
        // Form inputs
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('input', (e) => this.updateProgress());
        });
        
        // Photo upload
        const photoInput = document.getElementById('photoInput');
        if (photoInput) {
            photoInput.addEventListener('change', this.handlePhotoUpload.bind(this));
        }
        
        // CEP lookup
        const cepInput = document.getElementById('postalCode');
        if (cepInput) {
            cepInput.addEventListener('blur', this.lookupAddress.bind(this));
        }
        
        // Phone formatting
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', this.formatPhone.bind(this));
        });
    }
    
    async loadOnboardingData() {
        try {
            const response = await fetch('/api/professionals/onboarding.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to load onboarding data');
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.profileData = data;
                this.populateExistingData(data);
                this.updateProgress(data.profile_completion || 0);
                
                // Se perfil já está completo, ir direto para review
                if (data.profile_completion >= 100) {
                    this.goToStep(5);
                }
            } else {
                this.showError(data.message);
            }
        } catch (error) {
            console.error('Error loading onboarding data:', error);
            this.showError('Erro ao carregar dados do perfil');
        }
    }
    
    populateExistingData(data) {
        if (data.user) {
            const fields = [
                'name', 'phone', 'date_of_birth', 'address', 
                'city', 'state', 'postal_code'
            ];
            
            fields.forEach(field => {
                const input = document.getElementById(field.replace('_', ''));
                if (input && data.user[field]) {
                    input.value = data.user[field];
                }
            });
            
            // Emergency contact
            if (data.user.emergency_contact_name) {
                document.getElementById('emergencyContactName').value = data.user.emergency_contact_name;
            }
            if (data.user.emergency_contact_phone) {
                document.getElementById('emergencyContactPhone').value = data.user.emergency_contact_phone;
            }
            
            // Transport checkbox
            const transportCheckbox = document.getElementById('hasTransport');
            if (transportCheckbox && data.user.has_transport) {
                transportCheckbox.checked = true;
            }
        }
        
        // Load photo if exists
        if (data.user && data.user.avatar) {
            this.showPhotoPreview(data.user.avatar);
        }
        
        // Load specialties
        if (data.skills) {
            data.skills.forEach(skill => {
                this.selectedSpecialties.push(skill.specialty_id);
            });
        }
    }
    
    async loadSpecialties() {
        const specialtiesData = [
            { id: 1, name: 'Limpeza Residencial', icon: '🏠', description: 'Limpeza completa de residências' },
            { id: 2, name: 'Limpeza Comercial', icon: '🏢', description: 'Escritórios e estabelecimentos comerciais' },
            { id: 3, name: 'Limpeza Pós-Obra', icon: '🔨', description: 'Limpeza após reformas e construções' },
            { id: 4, name: 'Limpeza Pesada', icon: '💪', description: 'Limpezas que requerem mais esforço' },
            { id: 5, name: 'Organização', icon: '📦', description: 'Organização de ambientes e objetos' },
            { id: 6, name: 'Jardinagem', icon: '🌱', description: 'Cuidado com plantas e jardins' },
            { id: 7, name: 'Limpeza de Vidros', icon: '🪟', description: 'Especialista em limpeza de vidros' },
            { id: 8, name: 'Cuidado de Idosos', icon: '👴', description: 'Cuidados especiais com idosos' }
        ];
        
        const grid = document.getElementById('specialtyGrid');
        if (!grid) return;
        
        grid.innerHTML = specialtiesData.map(specialty => `
            <div class="specialty-card" data-specialty-id="${specialty.id}" onclick="toggleSpecialty(${specialty.id})">
                <div class="specialty-icon">${specialty.icon}</div>
                <h4 class="heading-5 mb-1">${specialty.name}</h4>
                <p class="text-secondary text-sm">${specialty.description}</p>
            </div>
        `).join('');
        
        // Mark selected specialties
        this.selectedSpecialties.forEach(id => {
            const card = document.querySelector(`[data-specialty-id="${id}"]`);
            if (card) {
                card.classList.add('selected');
            }
        });
    }
    
    generateAvailabilityGrid() {
        const grid = document.getElementById('availabilityGrid');
        if (!grid) return;
        
        const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        const timeSlots = [
            '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
            '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
            '18:00', '19:00', '20:00', '21:00', '22:00'
        ];
        
        // Headers
        let html = '';
        days.forEach(day => {
            html += `<div class="day-header">${day}</div>`;
        });
        
        // Time slots for each day
        timeSlots.forEach(time => {
            days.forEach((day, dayIndex) => {
                const slotId = `slot-${dayIndex}-${time.replace(':', '')}`;
                html += `
                    <div class="time-slot" data-day="${dayIndex}" data-time="${time}" id="${slotId}" 
                         onclick="toggleTimeSlot(${dayIndex}, '${time}')">
                        ${time}
                    </div>
                `;
            });
        });
        
        grid.innerHTML = html;
        
        // Load existing availability
        if (this.profileData.availability_schedule) {
            this.loadExistingAvailability(this.profileData.availability_schedule);
        }
    }
    
    loadExistingAvailability(schedule) {
        if (typeof schedule === 'string') {
            try {
                schedule = JSON.parse(schedule);
            } catch (e) {
                console.error('Error parsing availability schedule:', e);
                return;
            }
        }
        
        Object.keys(schedule).forEach(day => {
            const daySlots = schedule[day];
            daySlots.forEach(time => {
                const slotId = `slot-${day}-${time.replace(':', '')}`;
                const slot = document.getElementById(slotId);
                if (slot) {
                    slot.classList.add('selected');
                }
            });
        });
        
        this.availabilitySchedule = schedule;
    }
    
    async nextStep() {
        if (this.currentStep >= this.totalSteps) return;
        
        // Validate current step
        if (!await this.validateCurrentStep()) {
            return;
        }
        
        // Save current step data
        await this.saveCurrentStepData();
        
        // Move to next step
        this.goToStep(this.currentStep + 1);
    }
    
    prevStep() {
        if (this.currentStep <= 1) return;
        this.goToStep(this.currentStep - 1);
    }
    
    goToStep(stepNumber) {
        // Hide all steps
        document.querySelectorAll('.step').forEach(step => {
            step.classList.remove('active', 'completed');
        });
        
        // Show target step and mark previous as completed
        for (let i = 1; i <= stepNumber; i++) {
            const step = document.querySelector(`[data-step="${i}"]`);
            if (step) {
                if (i < stepNumber) {
                    step.classList.add('completed');
                } else if (i === stepNumber) {
                    step.classList.add('active');
                    step.scrollIntoView({ behavior: 'smooth' });
                }
                
                // Update step number styling
                const stepNumber = step.querySelector('.step-number');
                if (stepNumber && i < stepNumber) {
                    stepNumber.innerHTML = '✓';
                }
            }
        }
        
        this.currentStep = stepNumber;
        
        // Special handling for review step
        if (stepNumber === 5) {
            this.generateReviewSummary();
        }
    }
    
    async validateCurrentStep() {
        switch (this.currentStep) {
            case 1: // Basic Info
                const basicForm = document.getElementById('basicInfoForm');
                return this.validateForm(basicForm);
                
            case 2: // Photo
                // Photo is optional but recommended
                return true;
                
            case 3: // Specialties
                if (this.selectedSpecialties.length === 0) {
                    this.showError('Selecione pelo menos uma especialidade');
                    return false;
                }
                return true;
                
            case 4: // Availability
                if (Object.keys(this.availabilitySchedule).length === 0) {
                    this.showError('Defina pelo menos um horário de disponibilidade');
                    return false;
                }
                return true;
                
            default:
                return true;
        }
    }
    
    validateForm(form) {
        if (!form) return true;
        
        const requiredInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                isValid = false;
            } else {
                input.classList.remove('error');
            }
        });
        
        if (!isValid) {
            this.showError('Preencha todos os campos obrigatórios');
        }
        
        return isValid;
    }
    
    async saveCurrentStepData() {
        try {
            this.showStepLoading(this.currentStep, true);
            
            switch (this.currentStep) {
                case 1: // Basic Info
                    await this.saveBasicInfo();
                    break;
                    
                case 2: // Photo
                    if (document.getElementById('photoInput').files[0]) {
                        await this.uploadPhoto();
                    }
                    break;
                    
                case 3: // Specialties
                    await this.saveSpecialties();
                    break;
                    
                case 4: // Availability
                    await this.saveAvailability();
                    break;
            }
            
            this.showStepLoading(this.currentStep, false);
            
        } catch (error) {
            this.showStepLoading(this.currentStep, false);
            throw error;
        }
    }
    
    async saveBasicInfo() {
        const formData = new FormData(document.getElementById('basicInfoForm'));
        formData.append('action', 'update_basic_info');
        
        const response = await fetch('/api/professionals/onboarding.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }
        
        this.updateProgress(result.profile_completion);
    }
    
    async uploadPhoto() {
        const formData = new FormData();
        const photoInput = document.getElementById('photoInput');
        
        if (!photoInput.files[0]) return;
        
        formData.append('photo', photoInput.files[0]);
        formData.append('action', 'upload_photo');
        
        const response = await fetch('/api/professionals/onboarding.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }
        
        this.updateProgress(result.profile_completion);
        this.showPhotoPreview(result.photo_url);
    }
    
    async saveSpecialties() {
        const formData = new FormData();
        formData.append('action', 'set_specialties');
        formData.append('specialties', JSON.stringify(this.selectedSpecialties));
        formData.append('service_types', JSON.stringify(this.selectedServiceTypes));
        
        const response = await fetch('/api/professionals/onboarding.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }
        
        this.updateProgress(result.profile_completion);
    }
    
    async saveAvailability() {
        const formData = new FormData();
        const maxDistance = document.getElementById('maxDistance').value;
        
        const availabilityData = {
            schedule: this.availabilitySchedule,
            max_distance: maxDistance,
            preferred_areas: []
        };
        
        formData.append('action', 'set_availability');
        formData.append('availability', JSON.stringify(availabilityData));
        
        const response = await fetch('/api/professionals/onboarding.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message);
        }
        
        this.updateProgress(result.profile_completion);
    }
    
    async completeOnboarding() {
        try {
            const button = document.getElementById('completeButtonText');
            const spinner = document.getElementById('step5Spinner');
            
            button.style.display = 'none';
            spinner.style.display = 'inline-block';
            
            const formData = new FormData();
            formData.append('action', 'complete_onboarding');
            
            const response = await fetch('/api/professionals/onboarding.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.goToStep(6); // Welcome step
                this.updateProgress(100);
            } else {
                throw new Error(result.message);
            }
            
        } catch (error) {
            console.error('Complete onboarding error:', error);
            this.showError(error.message);
            
            const button = document.getElementById('completeButtonText');
            const spinner = document.getElementById('step5Spinner');
            
            button.style.display = 'inline';
            spinner.style.display = 'none';
        }
    }
    
    generateReviewSummary() {
        const summary = document.getElementById('completionSummary');
        if (!summary) return;
        
        const checks = [
            {
                label: 'Informações básicas completas',
                completed: this.isBasicInfoComplete(),
                required: true
            },
            {
                label: 'Foto de perfil adicionada',
                completed: this.isPhotoUploaded(),
                required: false
            },
            {
                label: 'Especialidades definidas',
                completed: this.selectedSpecialties.length > 0,
                required: true
            },
            {
                label: 'Disponibilidade configurada',
                completed: Object.keys(this.availabilitySchedule).length > 0,
                required: true
            },
            {
                label: 'Contato de emergência informado',
                completed: this.isEmergencyContactComplete(),
                required: true
            }
        ];
        
        summary.innerHTML = `
            <h4 class="heading-4 mb-3">Status do Perfil</h4>
            ${checks.map(check => `
                <div class="completion-item">
                    <div class="completion-check ${check.completed ? '' : 'pending'}">
                        ${check.completed ? '✓' : '!'}
                    </div>
                    <span class="${check.completed ? '' : 'text-warning'}">${check.label}</span>
                    ${check.required && !check.completed ? '<span class="text-danger ml-2">(obrigatório)</span>' : ''}
                </div>
            `).join('')}
            
            <div class="mt-4 p-3 bg-info-light rounded">
                <h5 class="heading-5 mb-2">📋 Próximos Passos</h5>
                <ul class="text-sm">
                    <li>Você receberá um email de boas-vindas</li>
                    <li>Poderá acessar o dashboard profissional</li>
                    <li>Começará a receber solicitações de serviço</li>
                    <li>Poderá atualizar seu perfil a qualquer momento</li>
                </ul>
            </div>
        `;
    }
    
    isBasicInfoComplete() {
        const requiredFields = ['name', 'phone', 'dateOfBirth', 'address', 'city', 'state', 'postalCode'];
        return requiredFields.every(field => {
            const input = document.getElementById(field);
            return input && input.value.trim();
        });
    }
    
    isPhotoUploaded() {
        const preview = document.getElementById('photoPreview');
        return preview && preview.style.display !== 'none';
    }
    
    isEmergencyContactComplete() {
        const name = document.getElementById('emergencyContactName');
        const phone = document.getElementById('emergencyContactPhone');
        return name && phone && name.value.trim() && phone.value.trim();
    }
    
    showPhotoPreview(src) {
        const preview = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPlaceholder');
        
        if (preview && placeholder) {
            preview.src = src;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        }
    }
    
    updateProgress(percentage = null) {
        if (percentage === null) {
            percentage = this.calculateProgress();
        }
        
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        if (progressFill) {
            progressFill.style.width = `${percentage}%`;
        }
        
        if (progressText) {
            progressText.textContent = `${Math.round(percentage)}% concluído`;
        }
    }
    
    calculateProgress() {
        // This is a simplified calculation
        // The real calculation is done on the server
        const steps = [
            this.isBasicInfoComplete(),
            this.isPhotoUploaded(),
            this.selectedSpecialties.length > 0,
            Object.keys(this.availabilitySchedule).length > 0,
            this.isEmergencyContactComplete()
        ];
        
        const completedSteps = steps.filter(Boolean).length;
        return (completedSteps / steps.length) * 100;
    }
    
    showStepLoading(stepNumber, loading) {
        const spinner = document.getElementById(`step${stepNumber}Spinner`);
        if (spinner) {
            spinner.style.display = loading ? 'inline-block' : 'none';
        }
    }
    
    showError(message) {
        // Create or update error message
        let errorDiv = document.getElementById('error-message');
        
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'error-message';
            errorDiv.className = 'alert alert-danger';
            document.querySelector('.onboarding-container').prepend(errorDiv);
        }
        
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <span class="text-danger mr-2">⚠️</span>
                <span>${message}</span>
                <button type="button" class="ml-auto" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 5000);
    }
    
    showSuccess(message) {
        // Similar to showError but for success messages
        let successDiv = document.getElementById('success-message');
        
        if (!successDiv) {
            successDiv = document.createElement('div');
            successDiv.id = 'success-message';
            successDiv.className = 'alert alert-success';
            document.querySelector('.onboarding-container').prepend(successDiv);
        }
        
        successDiv.innerHTML = `
            <div class="flex items-center">
                <span class="text-success mr-2">✓</span>
                <span>${message}</span>
                <button type="button" class="ml-auto" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        setTimeout(() => {
            if (successDiv.parentElement) {
                successDiv.remove();
            }
        }, 3000);
    }
    
    formatPhone(event) {
        let value = event.target.value.replace(/\D/g, '');
        
        if (value.length >= 11) {
            // Celular: (11) 99999-9999
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 10) {
            // Fixo: (11) 9999-9999
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        } else if (value.length >= 6) {
            value = value.replace(/(\d{2})(\d{4})/, '($1) $2');
        } else if (value.length >= 2) {
            value = value.replace(/(\d{2})/, '($1) ');
        }
        
        event.target.value = value;
    }
    
    async lookupAddress() {
        const cepInput = document.getElementById('postalCode');
        if (!cepInput || !cepInput.value) return;
        
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await response.json();
            
            if (data.erro) {
                throw new Error('CEP não encontrado');
            }
            
            // Fill address fields
            const addressInput = document.getElementById('address');
            const cityInput = document.getElementById('city');
            const stateInput = document.getElementById('state');
            
            if (addressInput && data.logradouro) {
                addressInput.value = data.logradouro;
            }
            if (cityInput && data.localidade) {
                cityInput.value = data.localidade;
            }
            if (stateInput && data.uf) {
                stateInput.value = data.uf;
            }
            
        } catch (error) {
            console.error('CEP lookup error:', error);
        }
    }
    
    goToDashboard() {
        window.location.href = '/professional/dashboard.php';
    }
}

// Global functions for inline event handlers
function toggleSpecialty(specialtyId) {
    const card = document.querySelector(`[data-specialty-id="${specialtyId}"]`);
    if (!card) return;
    
    card.classList.toggle('selected');
    
    if (card.classList.contains('selected')) {
        if (!onboarding.selectedSpecialties.includes(specialtyId)) {
            onboarding.selectedSpecialties.push(specialtyId);
        }
    } else {
        const index = onboarding.selectedSpecialties.indexOf(specialtyId);
        if (index > -1) {
            onboarding.selectedSpecialties.splice(index, 1);
        }
    }
}

function toggleTimeSlot(dayIndex, time) {
    const slotId = `slot-${dayIndex}-${time.replace(':', '')}`;
    const slot = document.getElementById(slotId);
    if (!slot) return;
    
    slot.classList.toggle('selected');
    
    if (!onboarding.availabilitySchedule[dayIndex]) {
        onboarding.availabilitySchedule[dayIndex] = [];
    }
    
    if (slot.classList.contains('selected')) {
        if (!onboarding.availabilitySchedule[dayIndex].includes(time)) {
            onboarding.availabilitySchedule[dayIndex].push(time);
        }
    } else {
        const index = onboarding.availabilitySchedule[dayIndex].indexOf(time);
        if (index > -1) {
            onboarding.availabilitySchedule[dayIndex].splice(index, 1);
        }
        
        // Remove day if no slots selected
        if (onboarding.availabilitySchedule[dayIndex].length === 0) {
            delete onboarding.availabilitySchedule[dayIndex];
        }
    }
}

function previewPhoto(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        onboarding.showError('Tipo de arquivo não permitido. Use JPG, PNG ou WebP');
        input.value = '';
        return;
    }
    
    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        onboarding.showError('Arquivo muito grande. Máximo 5MB');
        input.value = '';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        onboarding.showPhotoPreview(e.target.result);
    };
    reader.readAsDataURL(file);
}

function nextStep() {
    onboarding.nextStep();
}

function prevStep() {
    onboarding.prevStep();
}

function completeOnboarding() {
    onboarding.completeOnboarding();
}

function goToDashboard() {
    onboarding.goToDashboard();
}

// Initialize when DOM is loaded
let onboarding;
document.addEventListener('DOMContentLoaded', function() {
    onboarding = new ProfessionalOnboarding();
});
