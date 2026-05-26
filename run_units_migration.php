<?php
// Safe migration to add new unit columns if missing
require_once 'dbconnect.php';

$stmts = [
    "ALTER TABLE units ADD COLUMN currency VARCHAR(10) DEFAULT 'KES'",
    "ALTER TABLE units ADD COLUMN availability_date DATE NULL",
    "ALTER TABLE units ADD COLUMN furnished TINYINT(1) DEFAULT 0",
    "ALTER TABLE units ADD COLUMN parking TINYINT(1) DEFAULT 0",
    "ALTER TABLE units ADD COLUMN utilities_included VARCHAR(255) NULL",
    "ALTER TABLE units ADD COLUMN tenant_id INT NULL",
    "ALTER TABLE units ADD COLUMN featured_image VARCHAR(255) NULL",
    "ALTER TABLE units ADD INDEX idx_tenant_id (tenant_id)"
];

foreach ($stmts as $s) {
    try {
        if ($conn->query($s) === TRUE) {
            echo "OK: $s\n";
        } else {
            // print error but continue
            echo "SKIP/ERR: " . $conn->error . " for statement: $s\n";
        }
    } catch (Exception $e) {
        echo "EXC: " . $e->getMessage() . "\n";
    }
}

// add foreign key if tenants table exists and fk not present
$check = $conn->query("SELECT COUNT(*) as cnt FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='units' AND REFERENCED_TABLE_NAME='tenants'");
$row = $check->fetch_assoc();
if (intval($row['cnt']) === 0) {
    $fk = "ALTER TABLE units ADD CONSTRAINT fk_units_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL";
    if ($conn->query($fk) === TRUE) echo "OK: added fk_units_tenant\n"; else echo "SKIP/ERR: " . $conn->error . " for FK\n";
}

echo "Done.\n";
$conn->close();
