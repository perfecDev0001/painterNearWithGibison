-- Lead Payment System Database Schema

-- Add payment tracking columns to leads table
ALTER TABLE leads ADD COLUMN IF NOT EXISTS payment_count INT DEFAULT 0;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS is_payment_active BOOLEAN DEFAULT TRUE;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS lead_price DECIMAL(10,2) DEFAULT 15.00;
ALTER TABLE leads ADD COLUMN IF NOT EXISTS max_payments INT DEFAULT 3;

-- Painter payment methods (Stripe integration)
CREATE TABLE IF NOT EXISTS painter_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    painter_id INT NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    stripe_payment_method_id VARCHAR(255) NOT NULL,
    payment_method_type VARCHAR(50) DEFAULT 'card',
    card_brand VARCHAR(50),
    card_last4 VARCHAR(4),
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_stripe_pm (stripe_payment_method_id)
);

-- Lead payments tracking
CREATE TABLE IF NOT EXISTS lead_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    painter_id INT NOT NULL,
    stripe_payment_intent_id VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GBP',
    payment_status ENUM('pending', 'succeeded', 'failed', 'canceled') DEFAULT 'pending',
    payment_method_id VARCHAR(255),
    payment_number INT NOT NULL, -- 1st, 2nd, or 3rd payment for this lead
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment_intent (stripe_payment_intent_id),
    INDEX idx_lead_payments_painter (painter_id),
    INDEX idx_lead_payments_lead (lead_id)
);

-- Payment system configuration
CREATE TABLE IF NOT EXISTS payment_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default payment configuration
INSERT INTO payment_config (config_key, config_value, description) VALUES
('stripe_publishable_key', '', 'Stripe publishable key for frontend'),
('stripe_secret_key', '', 'Stripe secret key for backend'),
('stripe_webhook_secret', '', 'Stripe webhook endpoint secret'),
('default_lead_price', '15.00', 'Default price per lead access in GBP'),
('max_payments_per_lead', '3', 'Maximum number of payments before lead deactivation'),
('payment_enabled', 'true', 'Whether payment system is enabled'),
('auto_deactivate_leads', 'true', 'Auto deactivate leads after max payments reached')
ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;

-- Lead access tracking (who paid to access which leads)
CREATE TABLE IF NOT EXISTS lead_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    painter_id INT NOT NULL,
    payment_id INT NOT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (painter_id) REFERENCES painters(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES lead_payments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_lead_access (lead_id, painter_id)
);

-- Payment analytics view
CREATE OR REPLACE VIEW lead_payment_analytics AS
SELECT 
    l.id as lead_id,
    l.job_title,
    l.location,
    l.payment_count,
    l.is_payment_active,
    l.lead_price,
    COUNT(lp.id) as total_payments,
    SUM(CASE WHEN lp.payment_status = 'succeeded' THEN lp.amount ELSE 0 END) as total_revenue,
    COUNT(DISTINCT lp.painter_id) as unique_painters_paid,
    l.created_at as lead_created,
    MAX(lp.created_at) as last_payment_date
FROM leads l
LEFT JOIN lead_payments lp ON l.id = lp.lead_id
GROUP BY l.id; 