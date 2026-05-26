-- Units Management - Database Migration
-- Run this SQL to add units functionality to Riset Properties
-- This allows tracking individual units within a property

-- ==================== UNITS TABLE ====================
-- For storing individual units within properties
CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    unit_name VARCHAR(100) NOT NULL,
    unit_number VARCHAR(50),
    unit_type VARCHAR(100),
    bedrooms INT,
    bathrooms INT,
    square_feet INT,
    monthly_rent DECIMAL(12, 2),
    deposit DECIMAL(12, 2),
    status ENUM('Available', 'Occupied', 'Maintenance', 'Reserved') DEFAULT 'Available',
    description LONGTEXT,
    features TEXT,
    amenities TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_property_id (property_id),
    INDEX idx_status (status),
    INDEX idx_unit_type (unit_type),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== UNIT IMAGES TABLE ====================
-- For storing images of individual units
CREATE TABLE IF NOT EXISTS unit_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    image_title VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_unit_id (unit_id),
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== UPDATE LEASES TABLE ====================
-- Add unit_id reference to leases table if not already present
ALTER TABLE leases ADD COLUMN unit_id INT NULL AFTER property_id;
ALTER TABLE leases ADD CONSTRAINT fk_leases_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;
ALTER TABLE leases ADD INDEX idx_unit_id (unit_id);

-- ==================== UPDATE MAINTENANCE TASKS TABLE ====================
-- Add unit_id reference to maintenance_tasks table if not already present
ALTER TABLE maintenance_tasks ADD COLUMN unit_id INT NULL AFTER property_id;
ALTER TABLE maintenance_tasks ADD CONSTRAINT fk_maintenance_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL;
ALTER TABLE maintenance_tasks ADD INDEX idx_maintenance_unit_id (unit_id);

-- ==================== SAMPLE DATA ====================
-- Insert sample units for property with ID 1
INSERT INTO units (property_id, unit_name, unit_number, unit_type, bedrooms, bathrooms, square_feet, monthly_rent, deposit, status, description) VALUES
(1, 'Unit A', 'A101', 'Studio', 1, 1, 450, 25000, 50000, 'Available', 'Cozy studio apartment with modern furnishings'),
(1, 'Unit B', 'A102', '1-Bedroom', 1, 1, 550, 30000, 60000, 'Occupied', 'Spacious 1-bedroom with balcony'),
(1, 'Unit C', 'A103', '2-Bedroom', 2, 1, 750, 45000, 90000, 'Available', 'Modern 2-bedroom apartment'),
(2, 'Townhouse A', 'TH201', '2-Bedroom', 2, 2, 950, 65000, 130000, 'Available', 'Spacious townhouse with compound'),
(2, 'Townhouse B', 'TH202', '2-Bedroom', 2, 2, 950, 65000, 130000, 'Available', 'Similar townhouse layout');
