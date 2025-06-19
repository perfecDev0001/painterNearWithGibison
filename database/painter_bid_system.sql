-- Painter Bid System Database Schema

-- Create the main painter_bid table
CREATE TABLE IF NOT EXISTS painter_bid (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    lead_id INT NOT NULL,
    painter_id INT NOT NULL,
    bid_amount DECIMAL(10,2) NOT NULL,
    message TEXT NOT NULL,
    timeline VARCHAR(255) NOT NULL,
    materials_included BOOLEAN DEFAULT FALSE,
    warranty_period INT DEFAULT 0 COMMENT 'Warranty period in months',
    warranty_details TEXT,
    project_approach TEXT,
    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
    submitted_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key constraints (assuming these tables exist)
    INDEX idx_painter_bid_lead (lead_id),
    INDEX idx_painter_bid_painter (painter_id),
    INDEX idx_painter_bid_status (status),
    INDEX idx_painter_bid_submitted (submitted_at),
    
    -- Ensure one bid per painter per lead
    UNIQUE KEY unique_painter_lead_bid (painter_id, lead_id)
);

-- Insert default bid statuses configuration
INSERT INTO payment_config (config_key, config_value, description) VALUES
('bid_auto_expire_days', '30', 'Number of days before bids automatically expire'),
('max_bids_per_lead', '10', 'Maximum number of bids allowed per lead'),
('min_bid_amount', '50.00', 'Minimum bid amount in GBP'),
('max_bid_amount', '50000.00', 'Maximum bid amount in GBP'),
('bid_notifications_enabled', 'true', 'Whether to send bid notifications'),
('bid_status_tracking', 'true', 'Whether to track bid status changes')
ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
updated_at = CURRENT_TIMESTAMP;

-- Create bid analytics view
CREATE OR REPLACE VIEW painter_bid_analytics AS
SELECT 
    pb.id as bid_id,
    pb.lead_id,
    pb.painter_id,
    pb.bid_amount,
    pb.status,
    pb.submitted_at,
    pb.timeline,
    pb.materials_included,
    pb.warranty_period,
    DATEDIFF(NOW(), pb.submitted_at) as days_since_submitted,
    CASE 
        WHEN pb.status = 'accepted' THEN 'Won'
        WHEN pb.status = 'rejected' THEN 'Lost'
        WHEN pb.status = 'withdrawn' THEN 'Withdrawn'
        WHEN DATEDIFF(NOW(), pb.submitted_at) > 30 THEN 'Expired'
        ELSE 'Active'
    END as bid_status_label
FROM painter_bid pb
ORDER BY pb.submitted_at DESC;

-- Create bid summary view for leads
CREATE OR REPLACE VIEW lead_bid_summary AS
SELECT 
    lead_id,
    COUNT(*) as total_bids,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bids,
    COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_bids,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_bids,
    MIN(bid_amount) as lowest_bid,
    MAX(bid_amount) as highest_bid,
    AVG(bid_amount) as average_bid,
    MIN(submitted_at) as first_bid_date,
    MAX(submitted_at) as latest_bid_date
FROM painter_bid
GROUP BY lead_id;

-- Create painter bid performance view
CREATE OR REPLACE VIEW painter_bid_performance AS
SELECT 
    painter_id,
    COUNT(*) as total_bids_submitted,
    COUNT(CASE WHEN status = 'accepted' THEN 1 END) as bids_won,
    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as bids_lost,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as bids_pending,
    ROUND(
        (COUNT(CASE WHEN status = 'accepted' THEN 1 END) * 100.0 / COUNT(*)), 2
    ) as win_rate_percentage,
    AVG(bid_amount) as average_bid_amount,
    MIN(bid_amount) as lowest_bid_amount,
    MAX(bid_amount) as highest_bid_amount,
    MIN(submitted_at) as first_bid_date,
    MAX(submitted_at) as latest_bid_date
FROM painter_bid
GROUP BY painter_id
HAVING COUNT(*) > 0; 