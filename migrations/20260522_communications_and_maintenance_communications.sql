-- Migration: Add communications system and maintenance communication thread support
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS communications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT,
  recipient_id INT,
  recipient_type ENUM('user','tenant') DEFAULT 'user',
  channel ENUM('email', 'sms', 'whatsapp', 'internal') DEFAULT 'internal',
  message_type VARCHAR(100),
  subject VARCHAR(255),
  message LONGTEXT,
  template_key VARCHAR(100),
  template_params JSON,
  status ENUM('pending', 'sent', 'delivered', 'failed', 'read') DEFAULT 'pending',
  delivery_status VARCHAR(100),
  delivery_timestamp DATETIME,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sender_id (sender_id),
  INDEX idx_recipient_id (recipient_id),
  INDEX idx_recipient_type (recipient_type),
  INDEX idx_status (status),
  INDEX idx_channel (channel),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communication_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  channel ENUM('email', 'sms', 'whatsapp') NOT NULL,
  subject VARCHAR(255),
  body LONGTEXT NOT NULL,
  parameters JSON,
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_channel (channel),
  INDEX idx_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_communications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  maintenance_id INT NOT NULL,
  sender_id INT,
  sender_type ENUM('user', 'tenant') DEFAULT 'user',
  message LONGTEXT NOT NULL,
  attachment VARCHAR(255),
  is_read TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_maintenance_id (maintenance_id),
  INDEX idx_sender_id (sender_id),
  FOREIGN KEY (maintenance_id) REFERENCES maintenance_tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE maintenance_tasks ADD COLUMN IF NOT EXISTS comments TEXT;

SET FOREIGN_KEY_CHECKS=1;
