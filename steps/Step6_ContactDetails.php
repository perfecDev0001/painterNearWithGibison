<section class="step step--contact-details" aria-labelledby="step-contact-details-label">
    <div class="step__header">
        <div class="step__icon" aria-hidden="true">📞</div>
        <h2 id="step-contact-details-label" class="step__title">Contact Details</h2>
        <p class="step__description">We'll use these details to send you your personalized quotes</p>
    </div>
    
    <div class="step__form">
        <div class="form-group">
            <label class="form-label" for="fullname">
                <span class="label-icon">👤</span>
                Full Name *
            </label>
            <input type="text" id="fullname" name="fullname" class="form-input" required 
                   placeholder="Enter your full name" autocomplete="name" 
                   value="<?php echo isset($wizard) ? htmlspecialchars($wizard->getStepData()['fullname'] ?? '') : ''; ?>" />
        </div>
        
        <div class="form-group">
            <label class="form-label" for="email">
                <span class="label-icon">📧</span>
                Email Address *
            </label>
            <input type="email" id="email" name="email" class="form-input" required 
                   placeholder="Enter your email address" autocomplete="email" 
                   value="<?php echo isset($wizard) ? htmlspecialchars($wizard->getStepData()['email'] ?? '') : ''; ?>" />
        </div>
        
        <div class="form-group">
            <label class="form-label" for="phone">
                <span class="label-icon">📱</span>
                Phone Number *
            </label>
            <input type="tel" id="phone" name="phone" class="form-input" required 
                   pattern="[0-9\-\+ ]{10,15}" placeholder="Enter your phone number" autocomplete="tel" 
                   value="<?php echo isset($wizard) ? htmlspecialchars($wizard->getStepData()['phone'] ?? '') : ''; ?>" />
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <span class="btn-icon">🎯</span>
                Get My Free Quotes
            </button>
        </div>
    </div>

<style>
.step--contact-details {
    max-width: 600px;
    margin: 2rem auto;
    padding: 2rem;
    background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
    border-radius: 1rem;
    box-shadow: 0 8px 32px rgba(0,176,80,0.15);
    border: 1px solid #e5e7eb;
}

.step__header {
    text-align: center;
    margin-bottom: 2rem;
}

.step__icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.step__title {
    color: #00b050;
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.5px;
}

.step__description {
    color: #666;
    font-size: 1.1rem;
    margin: 0;
    line-height: 1.5;
}

.step__form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #00b050;
    margin-bottom: 0.5rem;
    font-size: 1rem;
}

.label-icon {
    font-size: 1.2rem;
}

.form-input {
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 1rem;
    transition: all 0.2s ease;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: #00b050;
    box-shadow: 0 0 0 3px rgba(0,176,80,0.1);
    transform: translateY(-1px);
}

.form-input:valid {
    border-color: #00b050;
}

.form-input.invalid {
    border-color: #dc2626;
    box-shadow: 0 0 0 3px rgba(220,38,38,0.1);
}

.form-input.valid {
    border-color: #00b050;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2300b050'%3e%3cpath d='M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem;
}

.form-actions {
    margin-top: 1rem;
    text-align: center;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #00b050 0%, #009140 100%);
    color: white;
    border: none;
    border-radius: 0.75rem;
    padding: 1rem 2rem;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 16px rgba(0,176,80,0.2);
    min-width: 200px;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #009140 0%, #007a30 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,176,80,0.3);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-primary.loading {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
}

.btn-primary.loading .btn-icon {
    animation: spin 1s linear infinite;
}

.btn-icon {
    font-size: 1.2rem;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive design */
@media (max-width: 768px) {
    .step--contact-details {
        margin: 1rem;
        padding: 1.5rem;
    }
    
    .step__title {
        font-size: 1.75rem;
    }
    
    .form-input {
        padding: 0.75rem;
    }
    
    .btn-primary {
        width: 100%;
        padding: 0.875rem 1.5rem;
    }
}

/* Focus management for accessibility */
.form-group.focused .form-label {
    color: #00b050;
}

.form-group.focused .form-input {
    border-color: #00b050;
}

/* Animation for form appearance */
.step--contact-details {
    animation: slideInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Enhanced form interaction for Step 6 - Updated for new styling
document.addEventListener('DOMContentLoaded', function() {
    const contactFormContainer = document.querySelector('.step--contact-details .step__form');
    const parentForm = document.querySelector('form'); // Find the actual form wrapper
    if (!contactFormContainer || !parentForm) return;
    
    const inputs = contactFormContainer.querySelectorAll('.form-input');
    const submitButton = contactFormContainer.querySelector('.btn-primary');
    const formGroups = contactFormContainer.querySelectorAll('.form-group');
    
    // Add loading state on form submission
    parentForm.addEventListener('submit', function(e) {
        if (submitButton) {
            submitButton.classList.add('loading');
            const btnIcon = submitButton.querySelector('.btn-icon');
            const originalText = submitButton.innerHTML;
            
            submitButton.innerHTML = '<span class="btn-icon">⏳</span>Processing...';
            
            // Store original content for potential restoration
            submitButton.dataset.originalContent = originalText;
        }
    });
    
    // Real-time validation feedback with enhanced styling
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const isValid = this.checkValidity() && this.value.trim() !== '';
            
            if (isValid) {
                this.classList.remove('invalid');
                this.classList.add('valid');
            } else if (this.value.trim() !== '') {
                this.classList.remove('valid');
                this.classList.add('invalid');
            } else {
                this.classList.remove('valid', 'invalid');
            }
            
            // Update submit button state
            updateSubmitButtonState();
        });
        
        // Enhanced focus effects
        input.addEventListener('focus', function() {
            this.closest('.form-group').classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.form-group').classList.remove('focused');
        });
    });
    
    // Phone number formatting (UK format)
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // UK phone number formatting
            if (value.startsWith('44')) {
                // International format
                if (value.length > 11) {
                    value = value.replace(/(\d{2})(\d{4})(\d{3})(\d{3})/, '+$1 $2 $3 $4');
                } else if (value.length > 6) {
                    value = value.replace(/(\d{2})(\d{4})(\d+)/, '+$1 $2 $3');
                }
            } else if (value.startsWith('0')) {
                // National format
                if (value.length > 7) {
                    value = value.replace(/(\d{4})(\d{3})(\d{4})/, '$1 $2 $3');
                } else if (value.length > 4) {
                    value = value.replace(/(\d{4})(\d+)/, '$1 $2');
                }
            }
            
            e.target.value = value;
        });
    }
    
    // Update submit button state based on form validity
    function updateSubmitButtonState() {
        const allValid = Array.from(inputs).every(input => 
            input.checkValidity() && input.value.trim() !== ''
        );
        
        if (submitButton) {
            if (allValid) {
                submitButton.style.opacity = '1';
                submitButton.style.transform = 'scale(1)';
            } else {
                submitButton.style.opacity = '0.7';
                submitButton.style.transform = 'scale(0.98)';
            }
        }
    }
    
    // Smooth animations with intersection observer
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
                
                // Stagger animation for form groups
                const formGroups = entry.target.querySelectorAll('.form-group');
                formGroups.forEach((group, index) => {
                    setTimeout(() => {
                        group.style.opacity = '1';
                        group.style.transform = 'translateY(0)';
                    }, index * 100);
                });
            }
        });
    }, { threshold: 0.1 });
    
    observer.observe(contactFormContainer);
    
    // Initialize form groups for staggered animation
    formGroups.forEach(group => {
        group.style.opacity = '0';
        group.style.transform = 'translateY(20px)';
        group.style.transition = 'all 0.3s ease';
    });
    
    // Initial button state check
    updateSubmitButtonState();
    
    // Add success feedback on form completion
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.checkValidity() && this.value.trim() !== '') {
                // Add a subtle success animation
                this.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            }
        });
    });
});
</script>
</section> 