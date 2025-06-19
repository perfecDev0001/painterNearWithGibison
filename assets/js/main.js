/**
 * Main JavaScript for Painter Near Me
 * Handles UI interactions, form validation, and accessibility enhancements
 */

// Wrap all code in an IIFE to avoid global scope pollution
(function() {
    'use strict';
    
    /**
     * Initialize mobile navigation
     */
    function initMobileNav() {
        try {
            const navToggle = document.querySelector('.nav__toggle');
            const navLinks = document.querySelector('.nav__links');
            
            if (!navToggle || !navLinks) return;
            
            navToggle.addEventListener('click', function() {
                const isExpanded = navToggle.getAttribute('aria-expanded') === 'true';
                navToggle.setAttribute('aria-expanded', !isExpanded);
                navLinks.classList.toggle('nav__links--open');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                const isNavOpen = navLinks.classList.contains('nav__links--open');
                const isClickInsideNav = navLinks.contains(event.target) || navToggle.contains(event.target);
                
                if (isNavOpen && !isClickInsideNav) {
                    navToggle.setAttribute('aria-expanded', 'false');
                    navLinks.classList.remove('nav__links--open');
                }
            });
        } catch (error) {
            console.error('Error initializing mobile navigation:', error);
        }
    }
    
    /**
     * Initialize form validation
     */
    function initFormValidation() {
        try {
            const form = document.querySelector('.quote-form');
            if (!form) return;
            
            // Add input event listeners for real-time validation feedback
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                field.addEventListener('input', function() {
                    validateField(field);
                });
                
                field.addEventListener('blur', function() {
                    validateField(field);
                });
            });
            
            // Email field validation
            const emailField = form.querySelector('input[type="email"]');
            if (emailField) {
                emailField.addEventListener('blur', function() {
                    if (emailField.value && !isValidEmail(emailField.value)) {
                        showFieldError(emailField, 'Please enter a valid email address');
                    }
                });
            }
            
            // Phone field validation
            const phoneField = form.querySelector('input[name="phone"]');
            if (phoneField) {
                phoneField.addEventListener('blur', function() {
                    if (phoneField.value && !isValidUKPhone(phoneField.value)) {
                        showFieldError(phoneField, 'Please enter a valid UK phone number');
                    }
                });
            }
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                let hasErrors = false;
                
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        hasErrors = true;
                    }
                });
                
                // Additional validation for specific fields
                if (emailField && emailField.value && !isValidEmail(emailField.value)) {
                    showFieldError(emailField, 'Please enter a valid email address');
                    hasErrors = true;
                }
                
                if (phoneField && phoneField.value && !isValidUKPhone(phoneField.value)) {
                    showFieldError(phoneField, 'Please enter a valid UK phone number');
                    hasErrors = true;
                }
                
                if (hasErrors) {
                    e.preventDefault();
                    const firstError = form.querySelector('.input-error');
                    if (firstError) {
                        firstError.focus();
                        // Scroll to first error with a small offset
                        const yOffset = -100;
                        const y = firstError.getBoundingClientRect().top + window.pageYOffset + yOffset;
                        window.scrollTo({top: y, behavior: 'smooth'});
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing form validation:', error);
        }
    }
    
    /**
     * Validate a single form field
     * 
     * @param {HTMLElement} field - The field to validate
     * @return {boolean} - Whether validation passed
     */
    function validateField(field) {
        // Check if empty
        if (field.hasAttribute('required') && !field.value.trim()) {
            showFieldError(field, 'This field is required');
            return false;
        }
        
        // Check min length
        const minLength = field.getAttribute('minlength');
        if (minLength && field.value.length < parseInt(minLength, 10)) {
            showFieldError(field, `Must be at least ${minLength} characters`);
            return false;
        }
        
        // Check pattern
        if (field.hasAttribute('pattern') && field.value) {
            const pattern = new RegExp(field.getAttribute('pattern'));
            if (!pattern.test(field.value)) {
                showFieldError(field, field.getAttribute('data-error-message') || 'Invalid format');
                return false;
            }
        }
        
        // Validation passed
        clearFieldError(field);
        return true;
    }
    
    /**
     * Show error message for a field
     * 
     * @param {HTMLElement} field - The field with error
     * @param {string} message - Error message to display
     */
    function showFieldError(field, message) {
        field.classList.add('input-error');
        
        // Find or create error message element
        let errorElement = field.nextElementSibling;
        if (!errorElement || !errorElement.classList.contains('error-message')) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            errorElement.setAttribute('aria-live', 'polite');
            field.parentNode.insertBefore(errorElement, field.nextSibling);
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        // Add error state for screen readers
        field.setAttribute('aria-invalid', 'true');
        if (!field.hasAttribute('aria-describedby')) {
            const errorId = 'error-' + Math.random().toString(36).substr(2, 9);
            errorElement.id = errorId;
            field.setAttribute('aria-describedby', errorId);
        }
    }
    
    /**
     * Clear error message for a field
     * 
     * @param {HTMLElement} field - The field to clear error for
     */
    function clearFieldError(field) {
        field.classList.remove('input-error');
        field.setAttribute('aria-invalid', 'false');
        
        const errorElement = field.nextElementSibling;
        if (errorElement && errorElement.classList.contains('error-message')) {
            errorElement.style.display = 'none';
        }
    }
    
    /**
     * Initialize smooth scrolling for anchor links
     */
    function initSmoothScrolling() {
        try {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const target = document.querySelector(targetId);
                    if (target) {
                        e.preventDefault();
                        const yOffset = -60; // Header offset
                        const y = target.getBoundingClientRect().top + window.pageYOffset + yOffset;
                        window.scrollTo({top: y, behavior: 'smooth'});
                        
                        // Update focus for accessibility
                        target.setAttribute('tabindex', '-1');
                        target.focus({preventScroll: true});
                    }
                });
            });
        } catch (error) {
            console.error('Error initializing smooth scrolling:', error);
        }
    }
    
    /**
     * Initialize performance monitoring
     */
    function initPerformanceMonitoring() {
        try {
            if ('performance' in window) {
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        const perfData = performance.getEntriesByType('navigation')[0];
                        const pageLoadTime = perfData.loadEventEnd - perfData.startTime;
                        
                        // Log performance data
                        if (pageLoadTime > 3000) {
                            console.warn('Page load time is slow:', Math.round(pageLoadTime) + 'ms');
                        }
                        
                        // Send performance data to analytics if available
                        if (window.dataLayer) {
                            window.dataLayer.push({
                                'event': 'performance',
                                'pageLoadTime': Math.round(pageLoadTime)
                            });
                        }
                    }, 0);
                });
            }
        } catch (error) {
            console.error('Error initializing performance monitoring:', error);
        }
    }
    
    /**
     * Validate email format
     * 
     * @param {string} email - Email to validate
     * @return {boolean} - Whether email is valid
     */
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    /**
     * Validate UK phone number format
     * 
     * @param {string} phone - Phone number to validate
     * @return {boolean} - Whether phone number is valid
     */
    function isValidUKPhone(phone) {
        // Remove all non-digit characters
        const digits = phone.replace(/\D/g, '');
        
        // UK mobile: starts with 07 and is 11 digits
        if (/^07\d{9}$/.test(digits)) {
            return true;
        }
        
        // UK landline: starts with 01, 02, 03, 08, 09 and is 10 or 11 digits
        if (/^(01|02|03|08|09)\d{8,9}$/.test(digits)) {
            return true;
        }
        
        // International format with + prefix
        if (/^\+\d{10,15}$/.test(phone.replace(/\s/g, ''))) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Initialize all functionality when DOM is ready
     */
    function init() {
        initMobileNav();
        initFormValidation();
        initSmoothScrolling();
        initPerformanceMonitoring();
    }
    
    // Run initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();