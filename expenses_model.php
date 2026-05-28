<?php
function ensure_expense_tables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS expense_vendors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        landlord_id INT DEFAULT NULL,
        property_id INT DEFAULT NULL,
        unit_id INT DEFAULT NULL,
        vendor_id INT DEFAULT NULL,
        category VARCHAR(255) DEFAULT NULL,
        reference VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        taxed_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes TEXT,
        attachment VARCHAR(255) DEFAULT NULL,
        expense_date DATE NOT NULL,
        deduction_month VARCHAR(20) DEFAULT NULL,
        deduction_year VARCHAR(10) DEFAULT NULL,
        is_recurring TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_property_id (property_id),
        INDEX idx_unit_id (unit_id),
        INDEX idx_vendor_id (vendor_id),
        INDEX idx_expense_date (expense_date),
        INDEX idx_landlord_id (landlord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $existingColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM expenses");
    while ($row = $result->fetch_assoc()) {
        $existingColumns[] = $row['Field'];
    }
    if (!in_array('unit_id', $existingColumns, true)) {
        $conn->query("ALTER TABLE expenses ADD COLUMN unit_id INT DEFAULT NULL");
    }
    if (!in_array('deduction_month', $existingColumns, true)) {
        $conn->query("ALTER TABLE expenses ADD COLUMN deduction_month VARCHAR(20) DEFAULT NULL");
    }
    if (!in_array('deduction_year', $existingColumns, true)) {
        $conn->query("ALTER TABLE expenses ADD COLUMN deduction_year VARCHAR(10) DEFAULT NULL");
    }
    if (!in_array('is_recurring', $existingColumns, true)) {
        $conn->query("ALTER TABLE expenses ADD COLUMN is_recurring TINYINT(1) NOT NULL DEFAULT 0");
    }
}

function get_expense_categories() {
    return [
        'Utilities', 'Utilities (Paid for Tenant)', 'Electricity', 'Water', 'Gas', 'Internet', 'Telephone',
        'Waste Collection', 'Cleaning', 'Security', 'Repairs', 'Maintenance', 'Plumbing', 'Electrical Repairs',
        'HVAC', 'Painting', 'Landscaping', 'Pest Control', 'Service Charge', 'Rates & Taxes', 'Insurance',
        'Licenses & Permits', 'Bank Charges', 'Professional Fees', 'Legal', 'Legal Fees', 'Accounting',
        'Audit', 'Staff', 'Salaries & Wages', 'Casual Labor', 'Transport', 'Fuel', 'Marketing', 'Advertising',
        'Office Supplies', 'Stationery', 'Software & Subscriptions', 'Equipment', 'Furniture', 'Contractor Fees',
        'Commission', 'Training', 'Travel', 'Hospitality', 'Depreciation', 'Miscellaneous'
    ];
}

function get_expense_vendors($conn) {
    $vendors = [];
    $result = $conn->query("SELECT id, name, created_at FROM expense_vendors ORDER BY name ASC");
    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
    return $vendors;
}

function get_expense_units($conn, $landlord_id = null) {
    $units = [];
    $sql = "SELECT u.id, u.property_id, COALESCE(u.unit_name, u.unit_number) AS label FROM units u";
    if ($landlord_id) {
        $sql .= " JOIN properties p ON u.property_id = p.id WHERE p.landlord_id = " . intval($landlord_id);
    }
    $sql .= " ORDER BY u.unit_number ASC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $units[$row['property_id']][] = $row;
    }
    return $units;
}

function add_expense_vendor($conn, $name) {
    $name = trim($name);
    if ($name === '') {
        return false;
    }
    $stmt = $conn->prepare("INSERT IGNORE INTO expense_vendors (name) VALUES (?)");
    $stmt->bind_param('s', $name);
    $success = $stmt->execute();
    if ($success) {
        $id = $conn->insert_id;
        $stmt->close();
        return $id > 0 ? $id : get_expense_vendor_id($conn, $name);
    }
    $stmt->close();
    return false;
}

function get_expense_vendor_id($conn, $name) {
    $stmt = $conn->prepare("SELECT id FROM expense_vendors WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $stmt->bind_result($id);
    if ($stmt->fetch()) {
        $stmt->close();
        return $id;
    }
    $stmt->close();
    return null;
}

function get_expense_properties($conn, $landlord_id = null) {
    $properties = [];
    $sql = "SELECT id, title FROM properties";
    if ($landlord_id) {
        $sql .= " WHERE landlord_id = " . intval($landlord_id);
    }
    $sql .= " ORDER BY title ASC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $properties[] = $row;
    }
    return $properties;
}

function add_expense($conn, $data) {
    $vendor_id = null;
    if (!empty($data['vendor_id'])) {
        $vendor_id = intval($data['vendor_id']);
    }
    if (empty($vendor_id) && !empty($data['vendor_name'])) {
        $vendor_id = add_expense_vendor($conn, $data['vendor_name']);
    }

    $category = trim($data['category'] ?? '');
    $reference = trim($data['reference'] ?? '');
    $amount = floatval($data['amount'] ?? 0);
    $taxed_amount = floatval($data['taxed_amount'] ?? 0);
    $notes = trim($data['notes'] ?? '');
    $attachment = trim($data['attachment'] ?? null);
    $property_id = !empty($data['property_id']) ? intval($data['property_id']) : null;
    $unit_id = !empty($data['unit_id']) ? intval($data['unit_id']) : null;
    $landlord_id = !empty($data['landlord_id']) ? intval($data['landlord_id']) : null;
    $created_by = !empty($data['created_by']) ? intval($data['created_by']) : null;
    $expense_date = !empty($data['expense_date']) ? $data['expense_date'] : date('Y-m-d');
    $deduction_month = !empty($data['deduction_month']) ? trim($data['deduction_month']) : null;
    $deduction_year = !empty($data['deduction_year']) ? trim($data['deduction_year']) : null;
    $is_recurring = !empty($data['is_recurring']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO expenses (landlord_id, property_id, unit_id, vendor_id, category, reference, amount, taxed_amount, notes, attachment, expense_date, deduction_month, deduction_year, is_recurring, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiiissddsssssii', $landlord_id, $property_id, $unit_id, $vendor_id, $category, $reference, $amount, $taxed_amount, $notes, $attachment, $expense_date, $deduction_month, $deduction_year, $is_recurring, $created_by);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function get_expense_summary($conn, $landlord_id = null) {
    $summary = [
        'total_amount' => 0.0,
        'count' => 0,
    ];
    $sql = "SELECT COALESCE(SUM(amount), 0) as total_amount, COUNT(*) as count FROM expenses";
    $where = [];
    if ($landlord_id) {
        $where[] = "landlord_id = " . intval($landlord_id);
    }
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_assoc();
        $summary['total_amount'] = floatval($row['total_amount']);
        $summary['count'] = intval($row['count']);
    }
    return $summary;
}

function get_expenses($conn, $filters = [], $landlord_id = null) {
    $params = [];
    $types = '';
    $sql = "SELECT e.*, v.name as vendor_name, p.title as property_title, u.unit_number as unit_number FROM expenses e LEFT JOIN expense_vendors v ON e.vendor_id = v.id LEFT JOIN properties p ON e.property_id = p.id LEFT JOIN units u ON e.unit_id = u.id";
    $where = [];

    if ($landlord_id) {
        $where[] = "e.landlord_id = " . intval($landlord_id);
    }
    if (!empty($filters['category'])) {
        $where[] = "e.category = ?";
        $types .= 's';
        $params[] = $filters['category'];
    }
    if (!empty($filters['vendor_id'])) {
        $where[] = "e.vendor_id = ?";
        $types .= 'i';
        $params[] = intval($filters['vendor_id']);
    }
    if (!empty($filters['vendor_name'])) {
        $where[] = "(v.name LIKE ? OR e.vendor_name LIKE ?)";
        $types .= 'ss';
        $params[] = '%' . $filters['vendor_name'] . '%';
        $params[] = '%' . $filters['vendor_name'] . '%';
    }
    if (!empty($filters['property_id'])) {
        $where[] = "e.property_id = ?";
        $types .= 'i';
        $params[] = intval($filters['property_id']);
    }
    if (!empty($filters['reference'])) {
        $where[] = "e.reference LIKE ?";
        $types .= 's';
        $params[] = '%' . $filters['reference'] . '%';
    }
    if (!empty($filters['start_date'])) {
        $where[] = "e.expense_date >= ?";
        $types .= 's';
        $params[] = $filters['start_date'];
    }
    if (!empty($filters['end_date'])) {
        $where[] = "e.expense_date <= ?";
        $types .= 's';
        $params[] = $filters['end_date'];
    }
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";

    if ($types !== '') {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
    }

    return $result;
}
