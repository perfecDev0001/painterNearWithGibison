<style>
/* Modern Step 6 Contact Details Enhancement */
.step--contact-details {
    position: relative;
    padding: 2rem 0;
}

.step--contact-details .step__icon {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    margin: 0 auto 2rem auto;
    font-size: 2rem;
    color: white;
    box-shadow: 
        0 8px 32px rgba(102, 126, 234, 0.3),
        0 4px 16px rgba(0, 0, 0, 0.1);
    border: 4px solid rgba(255, 255, 255, 0.9);
    animation: iconPulse 2s ease-in-out infinite;
}

@keyframes iconPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.step--contact-details .step__form {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 24px;
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.1),
        0 8px 32px rgba(0, 0, 0, 0.05),
        inset 0 1px 0 rgba(255, 255, 255, 0.6);
    padding: 3rem 2.5rem;
    max-width: 500px;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
}

.step--contact-details .step__form::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
    background-size: 200% 100%;
    animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 100% { background-position: 200% 0; }
    50% { background-position: -200% 0; }
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.step--contact-details #step-contact-details-label {
    text-align: center;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 2rem;
    font-weight: 800;
    margin: 0 0 2rem 0;
    letter-spacing: -0.02em;
    display: block;
}

.step--contact-details .step__label {
    display: block;
    font-weight: 600;
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    letter-spacing: 0.01em;
}

.step--contact-details .step__input {
    width: 100%;
    padding: 1.2rem 1.5rem;
    border: 2px solid rgba(148, 163, 184, 0.2);
    border-radius: 16px;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
    margin-bottom: 1.5rem;
    font-family: inherit;
}

.step--contact-details .step__input:focus {
    border-color: #667eea;
    outline: none;
    box-shadow: 
        0 0 0 4px rgba(102, 126, 234, 0.1),
        0 8px 24px rgba(102, 126, 234, 0.15);
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-1px);
}

.step--contact-details .step__input:hover {
    border-color: rgba(102, 126, 234, 0.4);
    background: rgba(255, 255, 255, 0.9);
}

.step--contact-details .step__input::placeholder {
    color: #94a3b8;
    font-weight: 400;
}

.step--contact-details .step__button {
    width: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-weight: 600;
    border: none;
    border-radius: 16px;
    padding: 1.2rem 0;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
    font-family: inherit;
    margin-top: 1rem;
}

.step--contact-details .step__button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.step--contact-details .step__button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
}

.step--contact-details .step__button:hover::before {
    left: 100%;
}

.step--contact-details .step__button:active {
    transform: translateY(0);
}

.step--contact-details .step__button:focus-visible {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}

/* Input validation styles */
.step--contact-details .step__input:invalid {
    border-color: rgba(239, 68, 68, 0.5);
}

.step--contact-details .step__input:invalid:focus {
    border-color: #ef4444;
    box-shadow: 
        0 0 0 4px rgba(239, 68, 68, 0.1),
        0 8px 24px rgba(239, 68, 68, 0.15);
}

.step--contact-details .step__input:valid {
    border-color: rgba(34, 197, 94, 0.3);
}

.step--contact-details .step__input:valid:focus {
    border-color: #22c55e;
    box-shadow: 
        0 0 0 4px rgba(34, 197, 94, 0.1),
        0 8px 24px rgba(34, 197, 94, 0.15);
}

/* Loading animation for form submission */
.step--contact-details .step__button.loading {
    pointer-events: none;
    opacity: 0.8;
}

.step--contact-details .step__button.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid transparent;
    border-top: 2px solid #fff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive design */
@media (max-width: 768px) {
    .step--contact-details .step__form {
        padding: 2.5rem 1.5rem;
        margin: 0 1rem;
    }
    
    .step--contact-details #step-contact-details-label {
        font-size: 1.75rem;
        margin-bottom: 1.5rem;
    }
    
    .step--contact-details .step__input {
        padding: 1rem 1.25rem;
        font-size: 0.95rem;
    }
    
    .step--contact-details .step__button {
        padding: 1rem 0;
        font-size: 1rem;
    }
    
    .step--contact-details .step__icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .step--contact-details .step__form {
        padding: 2rem 1.25rem;
        border-radius: 20px;
        margin: 0 0.5rem;
    }
    
    .step--contact-details #step-contact-details-label {
        font-size: 1.5rem;
    }
    
    .step--contact-details .step__input {
        margin-bottom: 1.25rem;
    }
}
</style>

<section class="step step--contact-details" aria-labelledby="step-contact-details-label">
    <div class="step__icon" aria-hidden="true">👤</div>
    <form method="post" class="step__form">
        <span id="step-contact-details-label" class="step__label">Contact Details</span>
        <label class="step__label" for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" class="step__input" required placeholder="Enter your full name" />
        <label class="step__label" for="email">Email Address</label>
        <input type="email" id="email" name="email" class="step__input" required placeholder="Enter your email address" />
        <label class="step__label" for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" class="step__input" required pattern="[0-9\-\+ ]{10,15}" placeholder="Enter your phone number" />
        <button type="submit" class="step__button step__button--next">Get Quotes</button>
    </form>
</section>

<script>
// Enhanced form interaction for Step 6
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.querySelector('.step--contact-details .step__form');
    if (!contactForm) return;
    
    const inputs = contactForm.querySelectorAll('.step__input');
    const submitButton = contactForm.querySelector('.step__button');
    
    // Add loading state on form submission
    contactForm.addEventListener('submit', function(e) {
        if (submitButton) {
            submitButton.classList.add('loading');
            submitButton.textContent = 'Processing...';
        }
    });
    
    // Real-time validation feedback
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.classList.remove('invalid');
                this.classList.add('valid');
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
            }
        });
        
        // Enhanced focus effects
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
    
    // Phone number formatting
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{3})/, '$1-$2');
            }
            e.target.value = value;
        });
    }
    
    // Smooth animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    });
    
    observer.observe(contactForm);
});
</script> 