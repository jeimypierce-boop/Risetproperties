-- Riset Properties Database Updates for Landlord Management
-- Run these SQL queries to add missing columns to support landlord account management

-- Add last_login column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL;

-- Verify that all necessary indexes exist for users table
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role (role);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_status (status);

-- Verify that all necessary columns exist in users table
-- If any column is missing, add it here:
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT 'images/user/1.png';
ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Active';

-- Create a summary of the users table structure for reference
-- Users with role 'landlord' are independent landlord accounts
-- They can own properties and manage tenants/leases
