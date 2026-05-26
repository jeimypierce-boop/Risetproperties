<?php
require_once 'dbconnect.php';

$landlord_id = null;
$date_from = date('Y-m-01');
$date_to = date('Y-m-d');
$property_filter = 0;
$tenant_filter = 0;
$landlordFilter = '';
if ($landlord_id) {
    $landlordFilter = ' AND p.landlord_id = ' . intval($landlord_id);
}

$queries = [];
$queries['metrics'] = "SELECT COUNT(DISTINCT l.id) as total_active_leases, COUNT(DISTINCT p.id) as total_properties, SUM(l.monthly_rent) as total_monthly_rent, SUM(CASE WHEN l.status = 'Active' THEN l.monthly_rent ELSE 0 END) as active_rent, COALESCE(SUM(CASE WHEN rp.payment_date LIKE '" . date('Y-m') . "%' THEN rp.amount_paid ELSE 0 END), 0) as current_month_collected FROM leases l JOIN properties p ON l.property_id = p.id LEFT JOIN rent_payments rp ON l.id = rp.lease_id AND rp.payment_date LIKE '" . date('Y-m') . "%' WHERE l.status = 'Active'" . $landlordFilter;
$queries['rent_roll'] = "SELECT l.id, CONCAT(t.first_name, ' ', t.last_name) as tenant_name, p.title as property_title, l.monthly_rent, COALESCE(SUM(CASE WHEN rp.payment_date BETWEEN '" . $date_from . "' AND '" . $date_to . "' THEN rp.amount_paid ELSE 0 END), 0) as paid_period, l.monthly_rent - COALESCE(SUM(CASE WHEN rp.payment_date LIKE '" . date('Y-m') . "%' THEN rp.amount_paid ELSE 0 END), 0) as outstanding, l.lease_start_date, l.lease_end_date, l.status FROM leases l JOIN tenants t ON l.tenant_id = t.id JOIN properties p ON l.property_id = p.id LEFT JOIN rent_payments rp ON l.id = rp.lease_id WHERE l.status = 'Active'" . $landlordFilter . " GROUP BY l.id ORDER BY p.title, t.first_name";
$queries['aged'] = "SELECT l.id, CONCAT(t.first_name, ' ', t.last_name) as tenant_name, p.title as property_title, l.monthly_rent, SUM(l.monthly_rent) - COALESCE(SUM(rp.amount_paid), 0) as arrears, DATEDIFF(NOW(), MAX(rp.payment_date)) as days_since_last_payment, l.status FROM leases l JOIN tenants t ON l.tenant_id = t.id JOIN properties p ON l.property_id = p.id LEFT JOIN rent_payments rp ON l.id = rp.lease_id WHERE l.status = 'Active'" . $landlordFilter . " GROUP BY l.id HAVING arrears > 0 ORDER BY days_since_last_payment DESC";
$queries['payment_history'] = "SELECT rp.id, rp.payment_date, rp.amount_paid, rp.payment_method, rp.status, l.monthly_rent, CONCAT(t.first_name, ' ', t.last_name) as tenant_name, p.title as property_title FROM rent_payments rp JOIN leases l ON rp.lease_id = l.id JOIN tenants t ON l.tenant_id = t.id JOIN properties p ON l.property_id = p.id WHERE rp.payment_date BETWEEN '" . $date_from . "' AND '" . $date_to . "'" . $landlordFilter . " ORDER BY rp.payment_date DESC LIMIT 100";

foreach ($queries as $name => $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        echo strtoupper($name) . " ERROR: " . $conn->error . "\n";
    } else {
        echo strtoupper($name) . " OK rows=" . $result->num_rows . "\n";
    }
}
