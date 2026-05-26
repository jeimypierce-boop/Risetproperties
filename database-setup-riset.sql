-- Riset Properties Database Setup
-- Run this SQL in your MySQL database (e.g., phpMyAdmin or MySQL CLI)

CREATE DATABASE IF NOT EXISTS riset_properties;
USE riset_properties;

-- ==================== USERS TABLE ====================
-- For Admin, Landlord, Staff, and Teacher accounts
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'landlord', 'staff', 'teacher') DEFAULT 'staff',
    status VARCHAR(50) DEFAULT 'Active',
    avatar VARCHAR(255) DEFAULT 'images/user/1.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TENANTS TABLE ====================
-- For property tenants/residents
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    city VARCHAR(100),
    country VARCHAR(100),
    tenant_id VARCHAR(50) NOT NULL UNIQUE,
    date_of_birth DATE,
    password VARCHAR(255),
    status VARCHAR(50) DEFAULT 'Active',
    avatar VARCHAR(255) DEFAULT 'images/user/1.png',
    move_in_date DATE,
    move_out_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== PROPERTIES TABLE ====================
-- For property listings
CREATE TABLE IF NOT EXISTS properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    property_type VARCHAR(100),
    category VARCHAR(100),
    bedrooms INT,
    bathrooms INT,
    square_feet INT,
    unit_count INT DEFAULT 1,
    price DECIMAL(12, 2),
    currency VARCHAR(10) DEFAULT 'KES',
    location VARCHAR(255),
    city VARCHAR(100),
    country VARCHAR(100),
    landlord_id INT,
    status ENUM('Available', 'Occupied', 'Maintenance', 'Archived') DEFAULT 'Available',
    image_url VARCHAR(255),
    featured_image VARCHAR(255),
    contact_person VARCHAR(100),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(150),
    availability_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_city (city),
    INDEX idx_landlord_id (landlord_id),
    INDEX idx_property_type (property_type),
    FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== PROPERTY IMAGES TABLE ====================
-- For multiple property images
CREATE TABLE IF NOT EXISTS property_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_property_id (property_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== VIEWINGS TABLE ====================
-- For scheduled property viewings/events
CREATE TABLE IF NOT EXISTS viewings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT,
    tenant_id INT,
    viewing_date DATE NOT NULL,
    viewing_time TIME,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    notes TEXT,
    organizer_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property_id (property_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_viewing_date (viewing_date),
    INDEX idx_status (status),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== MAINTENANCE TASKS TABLE ====================
-- For property maintenance and repair tasks
CREATE TABLE IF NOT EXISTS maintenance_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    task_type VARCHAR(100),
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
    assigned_to INT,
    scheduled_date DATE,
    completion_date DATE,
    estimated_cost DECIMAL(10, 2),
    actual_cost DECIMAL(10, 2),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property_id (property_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned_to (assigned_to),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== ENQUIRIES TABLE ====================
-- For all types of enquiries (property, tenant, viewing, maintenance, general)
CREATE TABLE IF NOT EXISTS enquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquiry_type ENUM('property', 'tenant', 'viewing', 'maintenance', 'common') DEFAULT 'common',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255),
    message LONGTEXT,
    property_id INT,
    tenant_id INT,
    related_to VARCHAR(255),
    status ENUM('New', 'In Progress', 'Resolved', 'Closed') DEFAULT 'New',
    replied TINYINT DEFAULT 0,
    reply_message LONGTEXT,
    replied_by INT,
    replied_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enquiry_type (enquiry_type),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_property_id (property_id),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (replied_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== LEASES TABLE ====================
-- For tenant lease agreements
CREATE TABLE IF NOT EXISTS leases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    property_id INT NOT NULL,
    lease_start_date DATE NOT NULL,
    lease_end_date DATE NOT NULL,
    monthly_rent DECIMAL(12, 2) NOT NULL,
    deposit DECIMAL(12, 2),
    status ENUM('Active', 'Inactive', 'Ended', 'Terminated') DEFAULT 'Active',
    lease_document VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_property_id (property_id),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== PAYMENTS TABLE ====================
-- For tracking rent payments and transactions
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lease_id INT,
    tenant_id INT,
    property_id INT,
    amount DECIMAL(12, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    status ENUM('Pending', 'Completed', 'Failed', 'Refunded') DEFAULT 'Pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lease_id (lease_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_status (status),
    FOREIGN KEY (lease_id) REFERENCES leases(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== SAMPLE DATA ====================

-- Insert sample admin user
INSERT INTO users (username, email, first_name, last_name, phone, password, role, status) VALUES
('admin', 'admin@risetproperties.com', 'Admin', 'User', '+254700000000', '$2y$10$zFBS1zPZvWztAjJCSCJfu..UE9h6t2zHoRKM52gYoaBW9qlzVGD3G', 'admin', 'Active'),
('landlord', 'landlord@risetproperties.com', 'James', 'Wambua', '+254701234567', '$2y$10$zFBS1zPZvWztAjJCSCJfu..UE9h6t2zHoRKM52gYoaBW9qlzVGD3G', 'landlord', 'Active'),
('staff', 'staff@risetproperties.com', 'Staff', 'Member', '+254702345678', '$2y$10$zFBS1zPZvWztAjJCSCJfu..UE9h6t2zHoRKM52gYoaBW9qlzVGD3G', 'staff', 'Active');

-- Insert sample tenants
INSERT INTO tenants (first_name, last_name, phone, email, city, country, tenant_id, date_of_birth, status) VALUES
('John', 'Smith', '+254700111111', 'john.smith@example.com', 'Nairobi', 'Kenya', 'TN001', '1990-06-03', 'Active'),
('Jane', 'Doe', '+254700222222', 'jane.doe@example.com', 'Nairobi', 'Kenya', 'TN002', '1987-02-16', 'Active'),
('Michael', 'Johnson', '+254700333333', 'michael.johnson@example.com', 'Kisumu', 'Kenya', 'TN003', '1992-06-21', 'Active');

-- Insert sample properties
INSERT INTO properties (title, description, property_type, category, bedrooms, bathrooms, square_feet, price, currency, location, city, country, landlord_id, status, contact_person, contact_phone, contact_email, availability_date) VALUES
('3-bed Apartment Westlands', 'Modern 3-bedroom apartment in Westlands', 'Apartment', 'Residential', 3, 2, 1200, 85000, 'KES', 'Westlands, Nairobi', 'Nairobi', 'Kenya', 2, 'Available', 'James Wambua', '+254701234567', 'landlord@risetproperties.com', '2026-06-01'),
('2-bed Townhouse Kilimani', 'Spacious 2-bedroom townhouse', 'Townhouse', 'Residential', 2, 2, 950, 65000, 'KES', 'Kilimani, Nairobi', 'Nairobi', 'Kenya', 2, 'Available', 'James Wambua', '+254701234567', 'landlord@risetproperties.com', '2026-06-15'),
('4-bed Villa Upper Hill', 'Luxury 4-bedroom villa with compound', 'Villa', 'Residential', 4, 3, 2500, 150000, 'KES', 'Upper Hill, Nairobi', 'Nairobi', 'Kenya', 2, 'Occupied', 'James Wambua', '+254701234567', 'landlord@risetproperties.com', '2026-05-01');

-- Insert sample viewings
INSERT INTO viewings (property_id, tenant_id, viewing_date, viewing_time, status, organizer_id) VALUES
(1, 1, '2026-05-20', '10:00:00', 'Scheduled', 2),
(2, 2, '2026-05-21', '14:00:00', 'Scheduled', 2),
(3, 3, '2026-05-22', '15:00:00', 'Completed', 2);

-- Insert sample maintenance tasks
INSERT INTO maintenance_tasks (property_id, title, description, task_type, priority, status, assigned_to, scheduled_date, created_by) VALUES
(1, 'Fix broken window', 'Master bedroom window needs fixing', 'Repair', 'Medium', 'Pending', 3, '2026-06-05', 2),
(2, 'Paint walls', 'Repaint living room and kitchen', 'Maintenance', 'Low', 'In Progress', 3, '2026-06-10', 2),
(3, 'AC servicing', 'Service all air conditioning units', 'Maintenance', 'High', 'Completed', 3, '2026-05-15', 2);

-- Insert sample enquiries
INSERT INTO enquiries (enquiry_type, name, email, phone, subject, message, property_id, status) VALUES
('property', 'Prospective Tenant 1', 'prospect1@email.com', '+254700444444', 'Inquiry about 3-bed Apartment', 'Is the apartment still available? What are the terms?', 1, 'New'),
('property', 'Prospective Tenant 2', 'prospect2@email.com', '+254700555555', 'Question about Townhouse', 'Does it have parking space?', 2, 'New'),
('maintenance', 'John Smith', 'john.smith@example.com', '+254700111111', 'Maintenance Request', 'The tap in the bathroom is leaking', 3, 'New');
