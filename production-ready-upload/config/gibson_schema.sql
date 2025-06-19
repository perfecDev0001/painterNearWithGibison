-- Gibson AI Complete Database Schema for Painter Marketplace
-- This schema contains all the tables required for the painter marketplace application

-- Core user management
CREATE TABLE IF NOT EXISTS `role` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_role_name` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `user` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL UNIQUE KEY,
    `password_hash` varchar(255) NOT NULL,
    `role_id` bigint NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `role` (`id`)
);

CREATE TABLE IF NOT EXISTS `user_session` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `user_id` bigint NOT NULL,
    `token` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE IF NOT EXISTS `user_password_reset` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `user_id` bigint NOT NULL,
    `token` varchar(255) NOT NULL UNIQUE KEY,
    `expires_at` datetime NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

-- Painter profile management
CREATE TABLE IF NOT EXISTS `painter_profile` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `user_id` bigint NOT NULL,
    `business_name` varchar(255) NOT NULL,
    `phone` varchar(20) NOT NULL,
    `years_experience` int NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

CREATE TABLE IF NOT EXISTS `painter_portfolio_image` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `painter_id` bigint NOT NULL,
    `image_url` varchar(500) NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`painter_id`) REFERENCES `painter_profile` (`id`)
);

-- Service categorization system
CREATE TABLE IF NOT EXISTS `service_category` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_service_category` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `service_type` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_service_type` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `service_surface` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_service_surface` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `service_property_type` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_service_property_type` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `service_specialization` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_service_specialization` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `service` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `category_id` bigint NOT NULL,
    `type_id` bigint NOT NULL,
    `surface_id` bigint NOT NULL,
    `property_type_id` bigint NOT NULL,
    `specialization_id` bigint NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `service_category` (`id`),
    FOREIGN KEY (`type_id`) REFERENCES `service_type` (`id`),
    FOREIGN KEY (`surface_id`) REFERENCES `service_surface` (`id`),
    FOREIGN KEY (`property_type_id`) REFERENCES `service_property_type` (`id`),
    FOREIGN KEY (`specialization_id`) REFERENCES `service_specialization` (`id`)
);

CREATE TABLE IF NOT EXISTS `painter_service` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `painter_id` bigint NOT NULL,
    `service_id` bigint NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_painter_service` UNIQUE (`painter_id`, `service_id`),
    FOREIGN KEY (`painter_id`) REFERENCES `painter_profile` (`id`),
    FOREIGN KEY (`service_id`) REFERENCES `service` (`id`)
);

CREATE TABLE IF NOT EXISTS `painter_service_area` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `painter_id` bigint NOT NULL,
    `area` varchar(255) NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`painter_id`) REFERENCES `painter_profile` (`id`)
);

-- Job leads and lead management
CREATE TABLE IF NOT EXISTS `job_lead_status` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `status_name` varchar(50) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_job_lead_status` UNIQUE (`status_name`)
);

CREATE TABLE IF NOT EXISTS `job_lead` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `customer_name` varchar(255) NOT NULL,
    `customer_phone` varchar(20) NOT NULL,
    `customer_email` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `budget` decimal(10, 2) NOT NULL,
    `preferred_start_date` date NOT NULL,
    `max_claims` int NOT NULL DEFAULT 3,
    `status_id` bigint NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`status_id`) REFERENCES `job_lead_status` (`id`)
);

CREATE TABLE IF NOT EXISTS `job_lead_address` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `job_lead_id` bigint NOT NULL,
    `street` varchar(255) NOT NULL,
    `city` varchar(100) NOT NULL,
    `postcode` varchar(20) NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_lead_id`) REFERENCES `job_lead` (`id`)
);

-- Payment processing with Stripe integration
CREATE TABLE IF NOT EXISTS `stripe_payment` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `painter_id` bigint NOT NULL,
    `amount` decimal(10, 2) NOT NULL,
    `currency` varchar(3) NOT NULL,
    `external_id` varchar(255) NOT NULL UNIQUE KEY,
    `status` varchar(50) NOT NULL,
    `payment_date` datetime NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`painter_id`) REFERENCES `painter_profile` (`id`)
);

CREATE TABLE IF NOT EXISTS `payment_method` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `painter_id` bigint NOT NULL,
    `external_method_id` varchar(255) NOT NULL,
    `type` varchar(50) NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`painter_id`) REFERENCES `painter_profile` (`id`)
);

CREATE TABLE IF NOT EXISTS `lead_claim` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `job_lead_id` bigint NOT NULL,
    `painter_id` bigint NOT NULL,
    `paid` boolean NOT NULL DEFAULT false,
    `payment_id` bigint,
    `claimed_at` datetime NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_lead_claim` UNIQUE (`job_lead_id`, `painter_id`),
    FOREIGN KEY (`job_lead_id`) REFERENCES `job_lead` (`id`),
    FOREIGN KEY (`painter_id`) REFERENCES `painter_profile` (`id`),
    FOREIGN KEY (`payment_id`) REFERENCES `stripe_payment` (`id`)
);

CREATE TABLE IF NOT EXISTS `invoice` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `payment_id` bigint NOT NULL,
    `number` varchar(100) NOT NULL UNIQUE KEY,
    `amount` decimal(10, 2) NOT NULL,
    `currency` varchar(3) NOT NULL,
    `issued_date` datetime NOT NULL,
    `paid_date` datetime,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`payment_id`) REFERENCES `stripe_payment` (`id`)
);

-- Notification system
CREATE TABLE IF NOT EXISTS `notification_type` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(100) NOT NULL,
    `description` varchar(255),
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `unique_notification_type` UNIQUE (`name`)
);

CREATE TABLE IF NOT EXISTS `notification` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `type_id` bigint NOT NULL,
    `message` text NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`type_id`) REFERENCES `notification_type` (`id`)
);

CREATE TABLE IF NOT EXISTS `notification_recipient` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `notification_id` bigint NOT NULL,
    `user_id` bigint NOT NULL,
    `is_read` boolean NOT NULL DEFAULT false,
    `read_at` datetime,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`notification_id`) REFERENCES `notification` (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);

-- Reporting and analytics system
CREATE TABLE IF NOT EXISTS `report` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `name` varchar(255) NOT NULL,
    `type` varchar(50) NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `report_export` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `report_id` bigint NOT NULL,
    `export_format` varchar(10) NOT NULL,
    `file_url` varchar(500) NOT NULL,
    `exported_at` datetime NOT NULL,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`report_id`) REFERENCES `report` (`id`)
);

CREATE TABLE IF NOT EXISTS `report_schedule` (
    `id` bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `uuid` varchar(36) NOT NULL UNIQUE KEY,
    `report_id` bigint NOT NULL,
    `schedule_cron` varchar(100) NOT NULL,
    `next_run` datetime,
    `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`report_id`) REFERENCES `report` (`id`)
);

-- Insert default data
INSERT IGNORE INTO `role` (`uuid`, `name`, `description`) VALUES 
('admin-role-uuid', 'admin', 'Administrator role with full access'),
('painter-role-uuid', 'painter', 'Painter role with painter access');

INSERT IGNORE INTO `job_lead_status` (`uuid`, `status_name`, `description`) VALUES
('status-new-uuid', 'new', 'New lead, available for claims'),
('status-claimed-uuid', 'claimed', 'Lead has been claimed by painters'),
('status-completed-uuid', 'completed', 'Job has been completed');

INSERT IGNORE INTO `notification_type` (`uuid`, `name`, `description`) VALUES
('notif-new-lead-uuid', 'new_lead', 'Notification for new job leads'),
('notif-payment-uuid', 'payment_received', 'Notification for payment confirmation'),
('notif-claim-uuid', 'lead_claimed', 'Notification when lead is claimed'); 