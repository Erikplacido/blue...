/**
 * =========================================================
 * PROJETO BLUE V2 - REFERRAL CLUB DASHBOARD SCRIPTS
 * =========================================================
 * 
 * @file referralclub.js
 * @description Scripts específicos do dashboard de referência
 * @version 2.0
 * @date 2025-08-05
 * @dependencies Font Awesome, Liquid Glass Design System
 */

// =========================================================
// GLOBAL CONFIGURATION
// =========================================================

window.ReferralClub = window.ReferralClub || {};

// =========================================================
// CORE FUNCTIONS
// =========================================================

/**
 * Copy referral code function with Liquid Glass feedback
 */
function copyReferralCode() {
    const code = document.getElementById('referralCode').textContent;
    const btn = document.getElementById('copyBtn');
    
    navigator.clipboard.writeText(code).then(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('copied');
        
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
            btn.classList.remove('copied');
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = code;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
            btn.classList.remove('copied');
        }, 2000);
    });
}

/**
 * Share referral code via Web Share API with fallback
 */
function shareReferralCode() {
    const code = document.getElementById('referralCode').textContent;
    const text = `Join Blue Project using my referral code: ${code}`;
    const url = `${window.location.origin}/booking2.php?ref=${code}`;
    
    if (navigator.share) {
        navigator.share({
            title: 'Blue Project Referral',
            text: text,
            url: url
        });
    } else {
        // Fallback: copy to clipboard
        const shareText = `${text}\n${url}`;
        navigator.clipboard.writeText(shareText).then(() => {
            alert('Referral link copied to clipboard!');
        });
    }
}

/**
 * Request withdrawal simulation
 */
function requestWithdrawal() {
    // This would connect to a real API in production
    const amount = prompt('Enter withdrawal amount:');
    if (amount && parseFloat(amount) > 0) {
        alert(`Withdrawal request for $${parseFloat(amount).toFixed(2)} submitted!`);
    }
}

/**
 * Open account management modal
 */
function openAccountModal() {
    const modal = document.getElementById('accountModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Add entrance animation
    setTimeout(() => {
        modal.querySelector('.modal-container').style.transform = 'scale(1)';
        modal.querySelector('.modal-container').style.opacity = '1';
    }, 10);
}

/**
 * Close account management modal
 */
function closeAccountModal() {
    const modal = document.getElementById('accountModal');
    
    // Add exit animation
    modal.querySelector('.modal-container').style.transform = 'scale(0.95)';
    modal.querySelector('.modal-container').style.opacity = '0';
    
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 200);
}

/**
 * Switch between modal tabs
 */
function switchTab(tabName) {
    // Remove active class from all tabs and content
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked tab and corresponding content
    event.target.classList.add('active');
    document.getElementById(tabName + 'Tab').classList.add('active');
}

/**
 * Auto-update stats (in a real app, this would use WebSocket or polling)
 */
function updateStats() {
    // Simulate real-time updates
    console.log('Stats updated');
}

/**
 * Initialize page functionality
 */
function initializeReferralDashboard() {
    console.log('Referral Club Dashboard loaded - Liquid Glass Design System');
    
    // Add entrance animations with stagger effect
    const statCards = document.querySelectorAll('.lg-card--stat');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('lg-animate-fade-in');
    });
    
    // Add liquid glass interaction effects
    const buttons = document.querySelectorAll('.lg-btn--action, .lg-btn--copy');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Enhance table responsiveness
    const table = document.querySelector('.lg-table');
    if (table && window.innerWidth < 768) {
        table.style.fontSize = 'var(--lg-text-small)';
    }
    
    // Initialize modal functionality
    setupModalEventListeners();
}

/**
 * Setup modal event listeners
 */
function setupModalEventListeners() {
    const modal = document.getElementById('accountModal');
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeAccountModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeAccountModal();
        }
    });
    
    // Handle form submissions
    document.querySelectorAll('.account-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleFormSubmission(this);
        });
    });
    
    // Initialize modal container styles for animation
    const container = modal.querySelector('.modal-container');
    container.style.transform = 'scale(0.95)';
    container.style.opacity = '0';
    container.style.transition = 'all 0.2s cubic-bezier(0.23,1,0.32,1)';
}

/**
 * Handle form submission
 */
function handleFormSubmission(form) {
    const activeTab = document.querySelector('.tab-btn.active').textContent.trim();
    
    // Show loading state
    const submitBtn = form.querySelector('.form-btn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Reset button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        // Show success message
        alert(`${activeTab} updated successfully!`);
        
        // Optionally close modal after profile update
        if (activeTab.includes('Profile')) {
            setTimeout(() => {
                closeAccountModal();
            }, 1000);
        }
    }, 1500);
}

// =========================================================
// ADDITIONAL UTILITY FUNCTIONS
// =========================================================

/**
 * Toggle password visibility
 */
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + '-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Send password reset link
 */
function sendPasswordResetLink() {
    alert('Password reset link sent to your email!');
}

// =========================================================
// FORMAT FUNCTIONS
// =========================================================

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

/**
 * Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-AU', {
        day: '2-digit',
        month: '2-digit', 
        year: 'numeric'
    });
}

/**
 * Get status icon
 */
function getStatusIcon(status) {
    switch(status.toLowerCase()) {
        case 'paid':
            return '<i class="fas fa-check-circle"></i>';
        case 'pending':
            return '<i class="fas fa-clock"></i>';
        case 'active':
            return '<i class="fas fa-sync-alt"></i>';
        default:
            return '<i class="fas fa-question-circle"></i>';
    }
}

// =========================================================
// INITIALIZATION
// =========================================================

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeReferralDashboard();
});

// =========================================================
// EXPORT FOR MODULE SYSTEMS (if needed)
// =========================================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        copyReferralCode,
        shareReferralCode,
        requestWithdrawal,
        updateStats,
        initializeReferralDashboard,
        openAccountModal,
        closeAccountModal,
        switchTab,
        setupModalEventListeners,
        handleFormSubmission,
        togglePassword,
        sendPasswordResetLink,
        formatCurrency,
        formatDate,
        getStatusIcon
    };
}
