<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'record_payment') {
        $tenant_id = intval($_POST['tenant_id']);
        $lease_id = intval($_POST['lease_id']);
        $property_id = null;
        $amount_paid = floatval($_POST['amount_paid']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $transaction_id = $_POST['transaction_id'] ?? '';
        $reference = $_POST['reference'] ?? '';
        $status = $_POST['status'] ?? 'paid';

        if ($lease_id > 0) {
            $leaseInfo = $conn->query("SELECT tenant_id, property_id FROM leases WHERE id = " . intval($lease_id) . " LIMIT 1");
            if ($leaseInfo && $leaseInfo->num_rows > 0) {
                $leaseRow = $leaseInfo->fetch_assoc();
                if (empty($tenant_id)) {
                    $tenant_id = intval($leaseRow['tenant_id']);
                }
                $property_id = intval($leaseRow['property_id']);
            }
        }

        if ($landlord_id && $property_id) {
            $check = $conn->query("SELECT landlord_id FROM properties WHERE id = " . intval($property_id) . " LIMIT 1");
            if (!$check || $check->num_rows === 0 || intval($check->fetch_assoc()['landlord_id']) !== $landlord_id) {
                $error_msg = "Invalid payment or property selected not owned by you.";
            }
        }

        $inserted = false;

        // Prefer inserting into rent_payments if the table exists
        $checkRp = $conn->query("SHOW TABLES LIKE 'rent_payments'");
        if ($checkRp && $checkRp->num_rows > 0) {
            $stmt = $conn->prepare("INSERT INTO rent_payments (tenant_id, lease_id, property_id, amount_paid, payment_date, payment_method, transaction_id, reference, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param('iiidsssss', $tenant_id, $lease_id, $property_id, $amount_paid, $payment_date, $payment_method, $transaction_id, $reference, $status);
                if ($stmt->execute()) {
                    $success_msg = "Payment recorded successfully!";
                    $inserted = true;
                } else {
                    $error_msg = "Error recording payment: " . $stmt->error;
                }
                $stmt->close();
            }
        }

        // Fallback to `payments` table if present and `rent_payments` not used
        if (!$inserted) {
            $checkP = $conn->query("SHOW TABLES LIKE 'payments'");
            if ($checkP && $checkP->num_rows > 0) {
                $stmt2 = $conn->prepare("INSERT INTO payments (lease_id, tenant_id, property_id, amount, payment_date, payment_method, transaction_id, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if ($stmt2) {
                    // try to determine property_id from lease
                    $property_id = null;
                    $leaseRes = $conn->query("SELECT property_id FROM leases WHERE id = " . intval($lease_id));
                    if ($leaseRes && $leaseRes->num_rows > 0) {
                        $property_id = $leaseRes->fetch_assoc()['property_id'];
                    }
                    $notes = $reference;
                    $types = 'iiidsssss';
                    // bind_param requires variables for each value
                    $stmt2->bind_param($types, $lease_id, $tenant_id, $property_id, $amount_paid, $payment_date, $payment_method, $transaction_id, $status, $notes);
                    if ($stmt2->execute()) {
                        $success_msg = "Payment recorded successfully (payments table)!";
                        $inserted = true;
                    } else {
                        $error_msg = "Error recording payment (payments): " . $stmt2->error;
                    }
                    $stmt2->close();
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_payment') {
    $payment_id = intval($_POST['payment_id']);
    $status = $_POST['status'] ?? 'paid';
    $transaction_id = $_POST['transaction_id'] ?? '';
    $reference = $_POST['reference'] ?? '';

    $updateStmt = $conn->prepare("UPDATE rent_payments SET status = ?, transaction_id = ?, reference = ?, updated_at = NOW() WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param('sssi', $status, $transaction_id, $reference, $payment_id);
        if ($updateStmt->execute()) {
            $success_msg = 'Payment updated successfully.';
        } else {
            $error_msg = 'Error updating payment: ' . $updateStmt->error;
        }
        $updateStmt->close();
    } else {
        $error_msg = 'Unable to prepare update statement.';
    }
}

// Fetch payment records
$sql = "SELECT rp.*, t.first_name, t.last_name, t.email, l.monthly_rent, p.title AS property_title FROM rent_payments rp
        LEFT JOIN tenants t ON rp.tenant_id = t.id
        LEFT JOIN leases l ON rp.lease_id = l.id
        LEFT JOIN properties p ON rp.property_id = p.id";
if ($landlord_id) {
    $sql .= " WHERE p.landlord_id = " . intval($landlord_id);
}
$sql .= " ORDER BY rp.payment_date DESC LIMIT 100";
$result = $conn->query($sql);

// Calculate payment summary
$summarySql = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN rp.status = 'paid' THEN rp.amount_paid ELSE 0 END) as total_paid,
    SUM(CASE WHEN rp.status = 'pending' THEN rp.amount_paid ELSE 0 END) as total_pending,
    SUM(CASE WHEN rp.status = 'partial' THEN rp.amount_paid ELSE 0 END) as total_partial
FROM rent_payments rp
LEFT JOIN properties p ON rp.property_id = p.id";
if ($landlord_id) {
    $summarySql .= " WHERE p.landlord_id = " . intval($landlord_id);
}
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult ? $summaryResult->fetch_assoc() : ['total_paid' => 0, 'total_pending' => 0, 'total_partial' => 0];

$outstandingSql = "SELECT COALESCE(SUM(l.monthly_rent) - COALESCE((SELECT SUM(rp.amount_paid) FROM rent_payments rp WHERE rp.lease_id = l.id AND rp.status = 'paid' AND DATE_FORMAT(rp.payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')), 0), 0) as total_outstanding FROM leases l JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'";
if ($landlord_id) {
    $outstandingSql .= " AND p.landlord_id = " . intval($landlord_id);
}
$outstandingResult = $conn->query($outstandingSql);
$outstanding = $outstandingResult ? $outstandingResult->fetch_assoc()['total_outstanding'] : 0;

$user_info = get_user_info();
$user_initials = get_user_initials();
$user_role_label = get_user_role_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Rent Payment Tracking - Riset Property Ltd</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/style-mob.css" rel="stylesheet" />
    <style>
        .payment-summary { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .summary-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; flex: 1; min-width: 200px; }
        .summary-card h3 { margin: 10px 0; font-size: 24px; }
        .summary-card.success { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .summary-card.danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    </style>
</head>
<body>
    <div class="container-fluid sb1">
        <div class="row">
            <div class="col-md-2 col-sm-3 col-xs-6 sb1-1">
                <a href="#" class="btn-close-menu"><i class="fa fa-times" aria-hidden="true"></i></a>
                <a href="#" class="atab-menu"><i class="fa fa-bars tab-menu" aria-hidden="true"></i></a>
                <a href="admin-dashboard-modern.php" class="logo"><img src="images/logo1.png" alt="" /></a>
            </div>
            <div class="col-md-6 col-sm-6 mob-hide">
                <form class="app-search">
                    <input type="text" placeholder="Search..." class="form-control">
                    <a href="#"><i class="fa fa-search"></i></a>
                </form>
            </div>
            <div class="col-md-2 tab-hide">
                <div class="top-not-cen">
                    <a class='waves-effect btn-noti' href="#"><i class="fa fa-bell-o" aria-hidden="true"></i><span>3</span></a>
                </div>
            </div>
            <?php include 'top_user_menu.php'; ?>
        </div>
    </div>

    <div class="container-fluid sb2">
        <div class="row">
            <div class="sb2-1">
                <div class="sb2-12">
                    <ul>
                        <li><img src="images/placeholder.jpg" alt=""></li>
                        <li>
                            <h5>Rent Payment Tracking <span>Monitor All Payments</span></h5>
                            <p class="text-muted">Logged in as <?php echo htmlspecialchars($user_info['name'] ?: 'Account'); ?><?php if (!empty($user_role_label)) { echo ' (' . htmlspecialchars($user_role_label) . ')'; } ?></p>
                        </li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-rent-payments.php" class="menu-active"><i class="fa fa-money" aria-hidden="true"></i> Rent Payments</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Rent Payment Tracking</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <!-- Payment Summary -->
                            <div class="payment-summary">
                                <div class="summary-card">
                                    <i class="fa fa-check-circle" style="font-size: 24px;"></i>
                                    <h3>KES <?php echo number_format($summary['total_paid'] ?? 0); ?></h3>
                                    <p>Total Paid</p>
                                </div>
                                <div class="summary-card danger">
                                    <i class="fa fa-clock-o" style="font-size: 24px;"></i>
                                    <h3>KES <?php echo number_format($summary['total_pending'] ?? 0); ?></h3>
                                    <p>Total Pending</p>
                                </div>
                                <div class="summary-card" style="background: linear-gradient(135deg, #ffb347 0%, #ffcc33 100%);">
                                    <i class="fa fa-adjust" style="font-size: 24px;"></i>
                                    <h3>KES <?php echo number_format($summary['total_partial'] ?? 0); ?></h3>
                                    <p>Total Partial</p>
                                </div>
                                <div class="summary-card success">
                                    <i class="fa fa-list" style="font-size: 24px;"></i>
                                    <h3><?php echo $summary['total_payments'] ?? 0; ?></h3>
                                    <p>Total Transactions</p>
                                </div>
                                <div class="summary-card" style="background: linear-gradient(135deg, #ff5f6d 0%, #ffc371 100%);">
                                    <i class="fa fa-exclamation-triangle" style="font-size: 24px;"></i>
                                    <h3>KES <?php echo number_format($outstanding ?? 0); ?></h3>
                                    <p>Current Outstanding</p>
                                </div>
                            </div>

                            <div class="box-inn-sp">
                                <div class="inn-title">
                                    <h4>Payment Records</h4>
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#newPaymentModal"><i class="fa fa-plus"></i> Record Payment</button>
                                </div>

                                <?php if (isset($success_msg)): ?>
                                    <div class="alert alert-success"><?php echo $success_msg; ?></div>
                                <?php endif; ?>
                                <?php if (isset($error_msg)): ?>
                                    <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                                <?php endif; ?>

                                <div class="tab-inn">
                                    <div class="table-responsive table-desi">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Tenant</th>
                                                    <th>Property</th>
                                                    <th>Lease Rent</th>
                                                    <th>Amount Paid</th>
                                                    <th>Method</th>
                                                    <th>Transaction</th>
                                                    <th>Status</th>
                                                    <th>Reference</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = $result->fetch_assoc()): 
                                                    $status_class = $row['status'] == 'paid' ? 'label-success' : ($row['status'] == 'pending' ? 'label-warning' : 'label-danger');
                                                ?>
                                                <tr>
                                                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['property_title'] ?? 'N/A'); ?></td>
                                                    <td>KES <?php echo number_format($row['monthly_rent'] ?? 0); ?></td>
                                                    <td><strong>KES <?php echo number_format($row['amount_paid']); ?></strong></td>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                                                    <td><?php echo htmlspecialchars($row['transaction_id'] ?? ''); ?></td>
                                                    <td><span class="label <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                                    <td><?php echo htmlspecialchars($row['reference']); ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info btn-view-receipt" data-payment-id="<?php echo $row['id']; ?>" data-payment-date="<?php echo date('d M Y', strtotime($row['payment_date'])); ?>" data-tenant="<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name'], ENT_QUOTES); ?>" data-property="<?php echo htmlspecialchars($row['property_title'] ?? 'N/A', ENT_QUOTES); ?>" data-rent="<?php echo number_format($row['monthly_rent'] ?? 0); ?>" data-paid="<?php echo number_format($row['amount_paid']); ?>" data-method="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['payment_method'])), ENT_QUOTES); ?>" data-transaction="<?php echo htmlspecialchars($row['transaction_id'] ?? '', ENT_QUOTES); ?>" data-status="<?php echo ucfirst($row['status']); ?>" data-reference="<?php echo htmlspecialchars($row['reference'] ?? '', ENT_QUOTES); ?>">
                                                            <i class="fa fa-receipt"></i> Receipt
                                                        </button>
                                                        <?php if ($row['status'] !== 'paid'): ?>
                                                            <button type="button" class="btn btn-sm btn-success btn-edit-payment" data-payment-id="<?php echo $row['id']; ?>" data-status="<?php echo htmlspecialchars($row['status'], ENT_QUOTES); ?>" data-transaction="<?php echo htmlspecialchars($row['transaction_id'] ?? '', ENT_QUOTES); ?>" data-reference="<?php echo htmlspecialchars($row['reference'] ?? '', ENT_QUOTES); ?>">
                                                                <i class="fa fa-pencil"></i> Update
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal fade" id="newPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Payment</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                        <input type="hidden" name="action" value="record_payment">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Tenant *</label>
                                    <select name="tenant_id" id="tenantSelect" class="form-control browser-default" required>
                                        <option value="">Select Tenant</option>
                                        <?php 
                                        $tenants = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM tenants");
                                        while($t = $tenants->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Lease *</label>
                                    <select name="lease_id" class="form-control browser-default" required>
                                        <option value="">Select Lease</option>
                                        <?php 
                                        $lease_filter = $landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : "";
                                        $leases = $conn->query("SELECT l.id, l.tenant_id, l.monthly_rent, t.first_name, t.last_name, p.title AS property_title FROM leases l LEFT JOIN tenants t ON l.tenant_id = t.id LEFT JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . $lease_filter);
                                        while($l = $leases->fetch_assoc()): ?>
                                        <option value="<?php echo $l['id']; ?>" data-tenant-id="<?php echo $l['tenant_id']; ?>"><?php echo htmlspecialchars($l['first_name'] . ' ' . $l['last_name'] . ' - ' . $l['property_title'] . ' (KES ' . number_format($l['monthly_rent'], 2) . ')'); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Amount Paid (KES) *</label>
                                    <input type="number" name="amount_paid" class="form-control" step="100" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payment Date *</label>
                                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payment Method *</label>
                                    <select name="payment_method" class="form-control browser-default" required>
                                        <option value="">-- Select Method --</option>
                                        <option value="mpesa">M-PESA</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cash">Cash</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control browser-default">
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                        <option value="partial">Partial</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Transaction ID</label>
                                    <input type="text" name="transaction_id" class="form-control" placeholder="e.g., SJ5211XXXX">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference / Notes</label>
                                    <input type="text" name="reference" class="form-control" placeholder="e.g., M-PESA/Cheque #">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="paymentReceiptModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Receipt</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8" id="receiptDate"></dd>
                        <dt class="col-sm-4">Tenant</dt>
                        <dd class="col-sm-8" id="receiptTenant"></dd>
                        <dt class="col-sm-4">Property</dt>
                        <dd class="col-sm-8" id="receiptProperty"></dd>
                        <dt class="col-sm-4">Lease Rent</dt>
                        <dd class="col-sm-8" id="receiptRent"></dd>
                        <dt class="col-sm-4">Amount Paid</dt>
                        <dd class="col-sm-8" id="receiptPaid"></dd>
                        <dt class="col-sm-4">Method</dt>
                        <dd class="col-sm-8" id="receiptMethod"></dd>
                        <dt class="col-sm-4">Transaction ID</dt>
                        <dd class="col-sm-8" id="receiptTransaction"></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8" id="receiptStatus"></dd>
                        <dt class="col-sm-4">Reference</dt>
                        <dd class="col-sm-8" id="receiptReference"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Payment Modal -->
    <div class="modal fade" id="updatePaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="admin-rent-payments.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Payment</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_payment">
                        <input type="hidden" name="payment_id" id="updatePaymentId" value="">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="updateStatus" class="form-control">
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Transaction ID</label>
                            <input type="text" name="transaction_id" id="updateTransactionId" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Reference</label>
                            <input type="text" name="reference" id="updateReference" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>`r`n    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var leaseSelect = document.querySelector('select[name="lease_id"]');
            var tenantSelect = document.getElementById('tenantSelect');
            if (leaseSelect && tenantSelect) {
                var leaseOptions = Array.from(leaseSelect.options).map(function (opt) {
                    return {
                        value: opt.value,
                        text: opt.textContent,
                        tenantId: opt.dataset.tenantId || '',
                        defaultSelected: opt.defaultSelected
                    };
                });

                function refreshLeaseOptions() {
                    var selectedTenant = tenantSelect.value;
                    leaseSelect.innerHTML = '';

                    var placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = 'Select Lease';
                    placeholder.disabled = true;
                    placeholder.selected = true;
                    leaseSelect.appendChild(placeholder);

                    leaseOptions.forEach(function (optData) {
                        if (!optData.value) return;
                        if (!selectedTenant || optData.tenantId === selectedTenant) {
                            var option = document.createElement('option');
                            option.value = optData.value;
                            option.textContent = optData.text;
                            option.dataset.tenantId = optData.tenantId;
                            if (optData.defaultSelected) {
                                option.selected = true;
                            }
                            leaseSelect.appendChild(option);
                        }
                    });
                }

                tenantSelect.addEventListener('change', refreshLeaseOptions);
                refreshLeaseOptions();

                leaseSelect.addEventListener('change', function () {
                    var selected = this.options[this.selectedIndex];
                    var tenantId = selected.dataset.tenantId || '';
                    if (tenantId && tenantSelect.value !== tenantId) {
                        tenantSelect.value = tenantId;
                    }
                });
            }

            function isBootstrapModalAvailable() {
                return window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function' && jQuery.fn.modal.Constructor && jQuery.fn.modal.Constructor.VERSION;
            }

            function showModal(selector) {
                if (isBootstrapModalAvailable()) {
                    jQuery(selector).modal('show');
                    return;
                }
                var modal = document.querySelector(selector);
                if (!modal) return;
                modal.style.display = 'block';
                modal.classList.add('in');
                modal.setAttribute('aria-modal', 'true');
                modal.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
                if (!document.getElementById('adminModalBackdrop')) {
                    var backdrop = document.createElement('div');
                    backdrop.id = 'adminModalBackdrop';
                    backdrop.className = 'modal-backdrop fade in';
                    document.body.appendChild(backdrop);
                }
            }

            function closeModal(selector) {
                if (isBootstrapModalAvailable()) {
                    jQuery(selector).modal('hide');
                    return;
                }
                var modal = document.querySelector(selector);
                if (!modal) return;
                modal.style.display = 'none';
                modal.classList.remove('in');
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var backdrop = document.getElementById('adminModalBackdrop');
                if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
            }

            document.querySelectorAll('.btn-view-receipt').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('receiptDate').textContent = this.dataset.paymentDate;
                    document.getElementById('receiptTenant').textContent = this.dataset.tenant;
                    document.getElementById('receiptProperty').textContent = this.dataset.property;
                    document.getElementById('receiptRent').textContent = 'KES ' + this.dataset.rent;
                    document.getElementById('receiptPaid').textContent = 'KES ' + this.dataset.paid;
                    document.getElementById('receiptMethod').textContent = this.dataset.method;
                    document.getElementById('receiptTransaction').textContent = this.dataset.transaction;
                    document.getElementById('receiptStatus').textContent = this.dataset.status;
                    document.getElementById('receiptReference').textContent = this.dataset.reference;
                    showModal('#paymentReceiptModal');
                });
            });

            document.querySelectorAll('.btn-edit-payment').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById('updatePaymentId').value = this.dataset.paymentId;
                    document.getElementById('updateStatus').value = this.dataset.status;
                    document.getElementById('updateTransactionId').value = this.dataset.transaction;
                    document.getElementById('updateReference').value = this.dataset.reference;
                    showModal('#updatePaymentModal');
                });
            });

            document.body.addEventListener('click', function (e) {
                var target = e.target;
                if (target && target.getAttribute && target.getAttribute('data-dismiss') === 'modal') {
                    closeModal('#paymentReceiptModal');
                    closeModal('#updatePaymentModal');
                }
                if (target && target.id === 'adminModalBackdrop') {
                    closeModal('#paymentReceiptModal');
                    closeModal('#updatePaymentModal');
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' || e.key === 'Esc') {
                    closeModal('#paymentReceiptModal');
                    closeModal('#updatePaymentModal');
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>




