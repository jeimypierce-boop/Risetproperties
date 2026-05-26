-- Migration: add additional unit fields
ALTER TABLE units
ADD COLUMN IF NOT EXISTS currency VARCHAR(10) DEFAULT 'KES',
ADD COLUMN IF NOT EXISTS availability_date DATE NULL,
ADD COLUMN IF NOT EXISTS furnished TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS parking TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS utilities_included VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS tenant_id INT NULL,
ADD COLUMN IF NOT EXISTS featured_image VARCHAR(255) NULL;

-- Add index for tenant
ALTER TABLE units ADD INDEX IF NOT EXISTS idx_tenant_id (tenant_id);

-- Foreign key for tenant if tenants table exists
ALTER TABLE units ADD CONSTRAINT IF NOT EXISTS fk_units_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL;
