// Payment Management JavaScript for Stripe Integration
class PaymentManager {
    constructor() {
        this.stripe = null;
        this.elements = null;
        this.cardElement = null;
        this.config = null;
        this.init();
    }

    async init() {
        try {
            // Load configuration
            await this.loadConfig();

            // Initialize Stripe
            if (this.config && this.config.stripe_publishable_key) {
                this.stripe = Stripe(this.config.stripe_publishable_key);
                this.elements = this.stripe.elements();
            }

            // Setup UI event listeners
            this.setupEventListeners();

        } catch (error) {
            console.error('Failed to initialize payment manager:', error);
            this.showError('Failed to initialize payment system');
        }
    }

    async loadConfig() {
        try {
            const response = await fetch('/api/payment-api.php/config');
            const data = await response.json();

            if (data.success) {
                this.config = data.config;
            } else {
                throw new Error('Failed to load payment configuration');
            }
        } catch (error) {
            console.error('Error loading config:', error);
            throw error;
        }
    }

    setupEventListeners() {
        // Add payment method button
        const addPaymentBtn = document.getElementById('add-payment-method-btn');
        if (addPaymentBtn) {
            addPaymentBtn.addEventListener('click', () => this.showAddPaymentMethod());
        }

        // Purchase lead buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.purchase-lead-btn')) {
                const leadId = e.target.dataset.leadId;
                this.showPurchaseDialog(leadId);
            }
        });

        // Remove payment method buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.remove-payment-method-btn')) {
                const paymentMethodId = e.target.dataset.paymentMethodId;
                this.removePaymentMethod(paymentMethodId);
            }
        });
    }

    showAddPaymentMethod() {
        const modal = this.createModal('Add Payment Method', `
            <div class="payment-form">
                <div id="card-element" class="payment-card-element">
                    <!-- Stripe Elements will create form elements here -->
                </div>
                <div id="card-errors" class="payment-errors" role="alert"></div>
                <div class="payment-form-actions">
                    <button id="save-payment-method" class="btn btn-primary">
                        <i class="bi bi-credit-card"></i> Save Payment Method
                    </button>
                    <button id="cancel-payment-method" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        `);

        // Create card element
        setTimeout(() => {
            this.cardElement = this.elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#424770',
                        '::placeholder': {
                            color: '#aab7c4',
                        },
                    },
                },
            });

            this.cardElement.mount('#card-element');

            // Handle real-time validation errors from the card Element
            this.cardElement.on('change', ({ error }) => {
                const displayError = document.getElementById('card-errors');
                if (error) {
                    displayError.textContent = error.message;
                } else {
                    displayError.textContent = '';
                }
            });

            // Save payment method
            document.getElementById('save-payment-method').addEventListener('click', () => {
                this.savePaymentMethod();
            });

            // Cancel
            document.getElementById('cancel-payment-method').addEventListener('click', () => {
                this.closeModal(modal);
            });
        }, 100);
    }

    async savePaymentMethod() {
        try {
            this.showLoading('Saving payment method...');

            // Create payment method
            const { error, paymentMethod } = await this.stripe.createPaymentMethod({
                type: 'card',
                card: this.cardElement,
            });

            if (error) {
                throw new Error(error.message);
            }

            // Save to backend
            const response = await fetch('/api/payment-api.php/setup-payment-method', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_method_id: paymentMethod.id,
                    is_default: true
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Payment method saved successfully');
                this.closeAllModals();
                this.refreshPaymentMethods();
            } else {
                throw new Error(data.error || 'Failed to save payment method');
            }

        } catch (error) {
            console.error('Error saving payment method:', error);
            this.showError(error.message);
        } finally {
            this.hideLoading();
        }
    }

    async showPurchaseDialog(leadId) {
        try {
            // Get lead details and payment methods
            const [leadResponse, methodsResponse] = await Promise.all([
                fetch(`/api/leads.php/${leadId}`),
                fetch('/api/payment-api.php/payment-methods')
            ]);

            const leadData = await leadResponse.json();
            const methodsData = await methodsResponse.json();

            if (!leadData.success || !methodsData.success) {
                throw new Error('Failed to load payment information');
            }

            const lead = leadData.lead;
            const methods = methodsData.methods;

            if (methods.length === 0) {
                this.showError('Please add a payment method first');
                return;
            }

            const modal = this.createModal('Purchase Lead Access', `
                <div class="purchase-dialog">
                    <div class="lead-info">
                        <h3>${this.escapeHtml(lead.job_title)}</h3>
                        <p class="location">${this.escapeHtml(lead.location)}</p>
                        <p class="description">${this.escapeHtml(lead.job_description)}</p>
                    </div>
                    
                    <div class="payment-info">
                        <div class="price">
                            <span class="price-label">Price:</span>
                            <span class="price-value">£${parseFloat(lead.lead_price).toFixed(2)}</span>
                        </div>
                        
                        <div class="payment-method-selection">
                            <label for="payment-method-select">Payment Method:</label>
                            <select id="payment-method-select" class="payment-method-select">
                                ${methods.map(method => `
                                    <option value="${method.stripe_payment_method_id}" ${method.is_default ? 'selected' : ''}>
                                        ${method.card_brand.toUpperCase()} •••• ${method.card_last4}
                                        ${method.is_default ? ' (Default)' : ''}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                    </div>
                    
                    <div class="purchase-actions">
                        <button id="confirm-purchase" class="btn btn-primary">
                            <i class="bi bi-credit-card"></i> Purchase Access
                        </button>
                        <button id="cancel-purchase" class="btn btn-secondary">Cancel</button>
                    </div>
                </div>
            `);

            // Setup event listeners
            document.getElementById('confirm-purchase').addEventListener('click', () => {
                const selectedPaymentMethod = document.getElementById('payment-method-select').value;
                this.purchaseLead(leadId, selectedPaymentMethod, modal);
            });

            document.getElementById('cancel-purchase').addEventListener('click', () => {
                this.closeModal(modal);
            });

        } catch (error) {
            console.error('Error showing purchase dialog:', error);
            this.showError(error.message);
        }
    }

    async purchaseLead(leadId, paymentMethodId, modal) {
        try {
            this.showLoading('Processing payment...');

            const response = await fetch('/api/payment-api.php/purchase-lead', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lead_id: leadId,
                    payment_method_id: paymentMethodId
                })
            });

            const data = await response.json();

            if (data.success) {
                if (data.status === 'succeeded') {
                    this.showSuccess('Payment successful! You now have access to this lead.');
                    this.closeModal(modal);
                    // Refresh the page or update UI to show lead details
                    window.location.reload();
                } else if (data.requires_action) {
                    // Handle 3D Secure authentication
                    await this.handle3DSecure(data.client_secret, data.payment_intent_id);
                } else {
                    throw new Error('Payment failed: ' + data.status);
                }
            } else {
                throw new Error(data.error || 'Payment failed');
            }

        } catch (error) {
            console.error('Error purchasing lead:', error);
            this.showError(error.message);
        } finally {
            this.hideLoading();
        }
    }

    async handle3DSecure(clientSecret, paymentIntentId) {
        try {
            const { error } = await this.stripe.confirmCardPayment(clientSecret);

            if (error) {
                throw new Error(error.message);
            }

            // Confirm payment on backend
            const response = await fetch('/api/payment-api.php/confirm-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_intent_id: paymentIntentId
                })
            });

            const data = await response.json();

            if (data.success && data.access_granted) {
                this.showSuccess('Payment confirmed! You now have access to this lead.');
                window.location.reload();
            } else {
                throw new Error('Payment confirmation failed');
            }

        } catch (error) {
            console.error('3D Secure error:', error);
            this.showError(error.message);
        }
    }

    async removePaymentMethod(paymentMethodId) {
        if (!confirm('Are you sure you want to remove this payment method?')) {
            return;
        }

        try {
            this.showLoading('Removing payment method...');

            const response = await fetch('/api/payment-api.php/payment-method', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_method_id: paymentMethodId
                })
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Payment method removed');
                this.refreshPaymentMethods();
            } else {
                throw new Error(data.error || 'Failed to remove payment method');
            }

        } catch (error) {
            console.error('Error removing payment method:', error);
            this.showError(error.message);
        } finally {
            this.hideLoading();
        }
    }

    async refreshPaymentMethods() {
        const container = document.getElementById('payment-methods-container');
        if (!container) return;

        try {
            const response = await fetch('/api/payment-api.php/payment-methods');
            const data = await response.json();

            if (data.success) {
                this.renderPaymentMethods(data.methods, container);
            }
        } catch (error) {
            console.error('Error refreshing payment methods:', error);
        }
    }

    renderPaymentMethods(methods, container) {
        container.innerHTML = methods.map(method => `
            <div class="payment-method-card ${method.is_default ? 'default' : ''}">
                <div class="payment-method-info">
                    <div class="card-brand">${method.card_brand.toUpperCase()}</div>
                    <div class="card-number">•••• •••• •••• ${method.card_last4}</div>
                    ${method.is_default ? '<span class="default-badge">Default</span>' : ''}
                </div>
                <div class="payment-method-actions">
                    <button class="remove-payment-method-btn btn btn-outline-danger btn-sm" 
                            data-payment-method-id="${method.stripe_payment_method_id}">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Utility methods
    createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'payment-modal-overlay';
        modal.innerHTML = `
            <div class="payment-modal">
                <div class="payment-modal-header">
                    <h3>${title}</h3>
                    <button class="payment-modal-close">&times;</button>
                </div>
                <div class="payment-modal-content">
                    ${content}
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close on overlay click or close button
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.classList.contains('payment-modal-close')) {
                this.closeModal(modal);
            }
        });

        return modal;
    }

    closeModal(modal) {
        modal.remove();
    }

    closeAllModals() {
        document.querySelectorAll('.payment-modal-overlay').forEach(modal => modal.remove());
    }

    showLoading(message = 'Processing...') {
        const loader = document.createElement('div');
        loader.id = 'payment-loader';
        loader.className = 'payment-loader';
        loader.innerHTML = `
            <div class="payment-loader-content">
                <div class="payment-spinner"></div>
                <p>${message}</p>
            </div>
        `;
        document.body.appendChild(loader);
    }

    hideLoading() {
        const loader = document.getElementById('payment-loader');
        if (loader) loader.remove();
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `payment-notification payment-notification--${type}`;
        notification.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${message}
            <button class="payment-notification-close">&times;</button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => notification.remove(), 5000);

        // Manual close
        notification.querySelector('.payment-notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// CSS Styles for payment UI components
const paymentStyles = `
.payment-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.payment-modal {
    background: white;
    border-radius: 0.8rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.payment-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.payment-modal-header h3 {
    margin: 0;
    color: #00b050;
}

.payment-modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.payment-modal-content {
    padding: 1.5rem;
}

.payment-card-element {
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.payment-errors {
    color: #dc3545;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.payment-form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.purchase-dialog .lead-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}

.purchase-dialog .lead-info h3 {
    margin: 0 0 0.5rem 0;
    color: #00b050;
}

.purchase-dialog .location {
    color: #666;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.payment-info {
    margin-bottom: 1.5rem;
}

.price {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.price-value {
    color: #00b050;
    font-size: 1.5rem;
}

.payment-method-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    font-size: 0.9rem;
}

.purchase-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.payment-method-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
}

.payment-method-card.default {
    border-color: #00b050;
    background: #f8fff8;
}

.payment-method-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-brand {
    font-weight: 600;
    color: #00b050;
}

.default-badge {
    background: #00b050;
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.7rem;
    font-weight: 600;
}

.payment-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1001;
}

.payment-loader-content {
    background: white;
    padding: 2rem;
    border-radius: 0.8rem;
    text-align: center;
}

.payment-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #00b050;
    border-radius: 50%;
    animation: payment-spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes payment-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.payment-notification {
    position: fixed;
    top: 1rem;
    right: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    z-index: 1002;
    animation: slideInRight 0.3s ease;
}

.payment-notification--success {
    background: #28a745;
}

.payment-notification--error {
    background: #dc3545;
}

.payment-notification-close {
    background: none;
    border: none;
    color: inherit;
    font-size: 1.2rem;
    cursor: pointer;
    margin-left: 1rem;
}

@keyframes slideInRight {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.btn-primary {
    background: #00b050;
    color: white;
    border-color: #00b050;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border-color: #6c757d;
}

.btn-outline-danger {
    background: transparent;
    color: #dc3545;
    border-color: #dc3545;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = paymentStyles;
document.head.appendChild(styleSheet);

// Initialize payment manager when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.paymentManager = new PaymentManager();
}); 