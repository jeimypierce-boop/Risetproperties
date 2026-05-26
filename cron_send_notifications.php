<?php
// Cron job scaffold to send rent reminders (SMS / Email) for overdue or upcoming rents
require_once 'dbconnect.php';
require_once 'integrations.php';

// Find active leases where this month's payments are less than the monthly rent
$sql = "SELECT l.id as lease_id, l.monthly_rent, t.id as tenant_id, t.first_name, t.last_name, t.phone, t.email
        FROM leases l
        JOIN tenants t ON l.tenant_id = t.id
        WHERE l.status = 'Active' AND (
            COALESCE((SELECT SUM(rp.amount_paid) FROM rent_payments rp WHERE rp.lease_id = l.id AND DATE_FORMAT(rp.payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')), 0) < l.monthly_rent
        )";

$res = $conn->query($sql);
if (!$res) {
    log_integration('cron_send_notifications query error: ' . $conn->error);
    exit;
}

while ($row = $res->fetch_assoc()) {
    $tenantName = trim($row['first_name'] . ' ' . $row['last_name']);
    $phone = $row['phone'];
    $email = $row['email'];
    $due = number_format($row['monthly_rent']);
    $subject = "Rent reminder: KES {$due} due";
    $message = "Dear {$tenantName},\n\nThis is a reminder that rent of KES {$due} for your unit is due or partially unpaid for this month. Please pay or contact us to discuss arrangements.\n\nThank you.\n";

    // Send SMS (logs by default)
    $smsResult = send_sms($phone, $message);
    // Send email as well if available
    if (!empty($email)) {
        $emailResult = send_email($email, $subject, nl2br(htmlspecialchars($message)));
    } else {
        $emailResult = false;
    }

    log_integration("Reminder sent for lease {$row['lease_id']} to tenant {$row['tenant_id']}: sms=" . ($smsResult ? 'ok' : 'fail') . " email=" . ($emailResult ? 'ok' : 'none'));
}

$conn->close();
?>
