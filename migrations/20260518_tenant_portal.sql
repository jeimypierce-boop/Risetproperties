-- Migration: Add tenant portal support (rent_payments, maintenance_requests, tenant_documents)
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS rent_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT DEFAULT NULL,
  lease_id INT DEFAULT NULL,
  property_id INT DEFAULT NULL,
  amount_paid DECIMAL(12,2) NOT NULL,
  payment_date DATETIME NOT NULL,
  payment_method VARCHAR(50),
  transaction_id VARCHAR(100),
  reference VARCHAR(255),
  status ENUM('pending','paid','partial','failed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant_id (tenant_id),
  INDEX idx_lease_id (lease_id),
  INDEX idx_payment_date (payment_date)
);

CREATE TABLE IF NOT EXISTS maintenance_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  lease_id INT DEFAULT NULL,
  property_id INT DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('new','assigned','in_progress','closed') DEFAULT 'new',
  priority ENUM('low','medium','high') DEFAULT 'medium',
  assigned_vendor VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tenant_id (tenant_id),
  INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS tenant_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  lease_id INT DEFAULT NULL,
  property_id INT DEFAULT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255),
  mime_type VARCHAR(100),
  file_size INT DEFAULT 0,
  description VARCHAR(255),
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tenant_id (tenant_id)
);

SET FOREIGN_KEY_CHECKS=1;
