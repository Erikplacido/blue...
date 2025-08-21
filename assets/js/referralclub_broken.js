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
 */s Design System
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
 * Share referral code with Web Share API or clipboard fallback
 */
function shareReferralCode() {
    const code = window.ReferralClub.userData.referral_code;
    const shareText = `🏠 Save money on professional house cleaning! Use my referral code: ${code} at Blue Project. Get amazing service with liquid glass quality! 💎`;
    const shareUrl = `${window.location.origin}/booking2.php?ref=${code}`;
    
    if (navigator.share) {
        navigator.share({
            title: 'Blue Project Referral',
            text: shareText,
            url: shareUrl
        });
    } else {
        // Fallback - copy to clipboard
        navigator.clipboard.writeText(`${shareText}\n\n🔗 Book now: ${shareUrl}`);
        alert('Referral link copied to clipboard!');
    }
}

/**
 * Toggle password visibility for password fields
 */
function togglePassword(fieldId) {
    const passwordField = document.getElementById(fieldId);
    const toggleIcon = document.getElementById(fieldId + '-icon');
    const toggleButton = toggleIcon.parentElement;
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
        toggleButton.classList.add('active');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
        toggleButton.classList.remove('active');
    }
}

/**
 * Request withdrawal with validation
 */
function requestWithdrawal() {
    const availableAmount = window.ReferralClub.userData.upcoming_payment;
    const minPayout = window.ReferralClub.config.minimum_payout;
    
    if (availableAmount < minPayout) {
        alert(`Minimum withdrawal amount is $${minPayout.toFixed(2)}. You currently have $${availableAmount.toFixed(2)} available.`);
        return;
    }
    
    // In a real app, this would open a withdrawal form/modal
    if (confirm(`Request withdrawal of $${availableAmount.toFixed(2)}?`)) {
        alert('Withdrawal request submitted! You will receive payment within 5-7 business days.');
    }
}

/**
 * Send password reset link to user's email
 */
function sendPasswordResetLink() {
    const userEmail = 'erik@blueproject.com'; // In real app, get from user data
    const btn = event.target;
    const originalContent = btn.innerHTML;
    
    // Show loading state
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    btn.disabled = true;
    
    // Simulate API call delay
    setTimeout(() => {
        // Show success state
        btn.innerHTML = '<i class="fas fa-check"></i> Link Sent!';
        
        // Show confirmation message
        alert(`Password reset link has been sent to ${userEmail}. Please check your email and follow the instructions to reset your password.`);
        
        // Reset button after 3 seconds
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }, 3000);
    }, 2000);
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

// =========================================================
// INITIALIZATION AND EVENT HANDLERS
// =========================================================

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
// EVENT LISTENERS
// =========================================================

/**
 * DOM Content Loaded Event
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the dashboard
    initializeReferralDashboard();
    
    // Add resize listener for responsive adjustments
    window.addEventListener('resize', function() {
        const table = document.querySelector('.lg-table');
        if (table) {
            if (window.innerWidth < 768) {
                table.style.fontSize = 'var(--lg-text-small)';
            } else {
                table.style.fontSize = '';
            }
        }
    });
});

// =========================================================
// UTILITY FUNCTIONS
// =========================================================

/**
 * Format currency for display
 */
function formatCurrency(amount) {
    return '$' + amount.toFixed(2);
}

/**
 * Format date for display
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
 * Get status icon HTML
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
