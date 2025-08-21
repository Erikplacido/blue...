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
// REFERRALS FILTERING SYSTEM
// =========================================================

/**
 * Initialize referrals filtering system
 */
function initializeReferralsFilters() {
    const searchInput = document.getElementById('searchReferral');
    const statusFilter = document.getElementById('statusFilter');
    const cityFilter = document.getElementById('cityFilter');
    const dateFilter = document.getElementById('dateFilter');
    const valueSort = document.getElementById('valueSort');
    const clearFiltersBtn = document.getElementById('clearFilters');
    
    if (!searchInput || !statusFilter || !cityFilter || !dateFilter || !valueSort || !clearFiltersBtn) {
        return; // Elements not found, exit gracefully
    }
    
    // Add event listeners
    searchInput.addEventListener('input', debounce(filterReferrals, 300));
    statusFilter.addEventListener('change', filterReferrals);
    cityFilter.addEventListener('change', filterReferrals);
    dateFilter.addEventListener('change', filterReferrals);
    valueSort.addEventListener('change', filterReferrals);
    clearFiltersBtn.addEventListener('click', clearAllFilters);
    
    // Initial filter application
    filterReferrals();
}

/**
 * Main filtering function
 */
function filterReferrals() {
    const searchTerm = document.getElementById('searchReferral').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const cityFilter = document.getElementById('cityFilter').value.toLowerCase();
    const dateFilter = document.getElementById('dateFilter').value;
    const valueSort = document.getElementById('valueSort').value;
    
    const referralItems = document.querySelectorAll('.referral-item');
    let visibleItems = [];
    
    referralItems.forEach(item => {
        let isVisible = true;
        
        // Search filter
        if (searchTerm) {
            const name = item.dataset.name || '';
            const email = item.dataset.email || '';
            const booking = item.dataset.booking || '';
            
            if (!name.includes(searchTerm) && 
                !email.includes(searchTerm) && 
                !booking.includes(searchTerm)) {
                isVisible = false;
            }
        }
        
        // Status filter
        if (statusFilter && item.dataset.status !== statusFilter) {
            isVisible = false;
        }
        
        // City filter
        if (cityFilter && item.dataset.city !== cityFilter) {
            isVisible = false;
        }
        
        // Date filter
        if (dateFilter && !matchesDateFilter(item.dataset.date, dateFilter)) {
            isVisible = false;
        }
        
        // Show/hide item
        if (isVisible) {
            item.style.display = 'flex';
            visibleItems.push(item);
        } else {
            item.style.display = 'none';
        }
    });
    
    // Apply sorting
    if (valueSort && visibleItems.length > 0) {
        applySorting(visibleItems, valueSort);
    }
    
    // Update results counter
    updateResultsCounter(visibleItems.length, referralItems.length);
    
    // Show/hide no results message
    toggleNoResultsMessage(visibleItems.length === 0);
}

/**
 * Check if date matches the selected filter
 */
function matchesDateFilter(itemDate, filterValue) {
    const date = new Date(itemDate);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    switch (filterValue) {
        case 'last7days':
            const last7Days = new Date(today);
            last7Days.setDate(last7Days.getDate() - 7);
            return date >= last7Days;
            
        case 'last30days':
            const last30Days = new Date(today);
            last30Days.setDate(last30Days.getDate() - 30);
            return date >= last30Days;
            
        case 'thismonth':
            return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
            
        case 'lastmonth':
            const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0);
            return date >= lastMonth && date <= lastMonthEnd;
            
        default:
            return true;
    }
}

/**
 * Apply sorting to visible items
 */
function applySorting(items, sortType) {
    const container = document.getElementById('referralsList');
    
    items.sort((a, b) => {
        const aValue = parseFloat(a.dataset.value);
        const bValue = parseFloat(b.dataset.value);
        
        switch (sortType) {
            case 'highest':
                return bValue - aValue;
            case 'lowest':
                return aValue - bValue;
            default:
                return 0;
        }
    });
    
    // Reorder elements in DOM
    items.forEach(item => {
        container.appendChild(item);
    });
}

/**
 * Update results counter
 */
function updateResultsCounter(visible, total) {
    const counter = document.getElementById('resultsCount');
    if (counter) {
        counter.textContent = `Showing ${visible} of ${total} referrals`;
    }
}

/**
 * Toggle no results message
 */
function toggleNoResultsMessage(show) {
    const noResults = document.getElementById('noResults');
    const referralsList = document.getElementById('referralsList');
    
    if (noResults && referralsList) {
        noResults.style.display = show ? 'block' : 'none';
        referralsList.style.display = show ? 'none' : 'flex';
    }
}

/**
 * Clear all filters
 */
function clearAllFilters() {
    document.getElementById('searchReferral').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('cityFilter').value = '';
    document.getElementById('dateFilter').value = '';
    document.getElementById('valueSort').value = '';
    
    filterReferrals();
}

/**
 * Debounce function for search input
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// =========================================================
// INITIALIZATION
// =========================================================

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeReferralDashboard();
    initializeReferralsFilters();
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
