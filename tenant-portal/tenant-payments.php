<?php
session_start();
require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/../integrations.php';

if (empty($_SESSION['tenant_id'])) {
    header('Location: tenant-login.php');
    exit;
}
$tenant_id = intval($_SESSION['tenant_id']);

$tenant = $conn->query("SELECT id, first_name, last_name, email, phone FROM tenants WHERE id = {$tenant_id}")->fetch_assoc();
$lease = $conn->query("SELECT l.*, p.title AS property_title FROM leases l LEFT JOIN properties p ON l.property_id = p.id WHERE l.tenant_id = {$tenant_id} AND l.status = 'Active' LIMIT 1")->fetch_assoc();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'mpesa';
    $transaction_id = $_POST['transaction_id'] ?? '';
    $reference = $_POST['reference'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    $property_id = intval($lease['property_id'] ?? 0);
    $lease_id = intval($lease['id'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO rent_payments (tenant_id, lease_id, property_id, amount_paid, payment_date, payment_method, transaction_id, reference, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param('iiidsssss', $tenant_id, $lease_id, $property_id, $amount_paid, $payment_date, $payment_method, $transaction_id, $reference, $status);
        if ($stmt->execute()) {
            $success = 'Payment record saved. Please keep your M-PESA transaction code for reconciliation.';
        } else {
            $error = 'Could not save payment: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = 'Unable to prepare payment record.';
    }
}

$paidThisMonth = 0;
$dueThisMonth = 0;
if ($lease) {
    $lease_id = intval($lease['id']);
    $paidRow = $conn->query("SELECT COALESCE(SUM(amount_paid),0) as paid FROM rent_payments WHERE lease_id = {$lease_id} AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetch_assoc();
    $paidThisMonth = floatval($paidRow['paid'] ?? 0);
    $dueThisMonth = max(0, floatval($lease['monthly_rent']) - $paidThisMonth);
}

$history = $conn->query("SELECT * FROM rent_payments WHERE tenant_id = {$tenant_id} ORDER BY payment_date DESC LIMIT 50");

$tenant_name = htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name']);
$tenant_email = htmlspecialchars($tenant['email']);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Payments</title>
    <link href="../css/bootstrap.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/style-mob.css" rel="stylesheet">
    <style>
        .payment-summary { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .payment-card { border: 1px solid #e3e6f0; border-radius: .5rem; padding: 1rem; background: #fff; flex: 1; min-width: 220px; }
        .payment-card h5 { margin-bottom: .5rem; }
    </style>
</head>
<body class="container-fluid" style="min-width: 0; word-wrap: break-word; padding: 15px;">
  <nav class="mb-4">
    <a class="btn btn-sm btn-outline-secondary" href="tenant-dashboard.php">Dashboard</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-payments.php">Payments</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-maintenance.php">Maintenance</a>
    <a class="btn btn-sm btn-outline-primary" href="tenant-documents.php">Documents</a>
    <a class="btn btn-sm btn-outline-secondary" href="tenant-logout.php">Logout</a>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3>Payments</h3>
      <p class="mb-0 text-muted">Logged in as <?php echo $tenant_name; ?><?php if (!empty($tenant_email)): ?> &ndash; <?php echo $tenant_email; ?><?php endif; ?></p>
    </div>
    <a href="tenant-dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>

  <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if ($lease): ?>
    <div class="payment-summary">
      <div class="payment-card">
        <h5>Active Lease</h5>
        <p><strong>Property:</strong> <?php echo htmlspecialchars($lease['property_title']); ?></p>
        <p><strong>Rent:</strong> KES <?php echo number_format($lease['monthly_rent']); ?></p>
        <p><strong>Lease:</strong> <?php echo htmlspecialchars($lease['lease_start_date']); ?> to <?php echo htmlspecialchars($lease['lease_end_date']); ?></p>
      </div>
      <div class="payment-card">
        <h5>Current Month</h5>
        <p><strong>Paid:</strong> KES <?php echo number_format($paidThisMonth, 2); ?></p>
        <p><strong>Due:</strong> KES <?php echo number_format($dueThisMonth, 2); ?></p>
        <button id="receivePaymentButton" type="button" class="btn btn-success btn-block mt-3">Receive Payment</button>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Pay with M-PESA</h5>
            <p>Use the M-PESA STK push request to pay directly from your phone.</p>
            <form id="stkPushForm">
              <input type="hidden" name="lease_id" value="<?php echo intval($lease['id']); ?>">
              <div class="form-group">
                <label>Amount</label>
                <input type="number" name="amount" class="form-control" step="0.01" value="<?php echo number_format(max($dueThisMonth, $lease['monthly_rent']), 2, '.', ''); ?>" required>
              </div>
              <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($tenant['phone'] ?? ''); ?>" required>
              </div>
              <div class="form-group">
                <label>Account Reference</label>
                <input type="text" name="account_reference" class="form-control" value="Rent-<?php echo intval($lease['id']); ?>" required>
              </div>
              <div class="form-group">
                <label>Transaction Description</label>
                <input type="text" name="transaction_desc" class="form-control" value="Rent payment for <?php echo htmlspecialchars($lease['property_title']); ?>" required>
              </div>
              <button type="submit" class="btn btn-primary">Send M-PESA Request</button>
            </form>
            <div id="stkResponse" class="mt-3"></div>
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Manual payment record</h5>
            <form id="manualPaymentForm" method="post">
              <input type="hidden" name="action" value="record_payment">
              <div class="form-group"><label>Amount Paid</label><input type="number" name="amount_paid" step="0.01" class="form-control" required></div>
              <div class="form-group"><label>Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
              <div class="form-group"><label>Payment Method</label>
                <select name="payment_method" class="form-control" required>
                  <option value="mpesa">M-PESA</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="cash">Cash</option>
                  <option value="cheque">Cheque</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="form-group"><label>Transaction ID</label><input type="text" name="transaction_id" class="form-control" placeholder="M-PESA code or bank reference"></div>
              <div class="form-group"><label>Reference / Notes</label><input type="text" name="reference" class="form-control"></div>
              <div class="form-group"><label>Status</label>
                <select name="status" class="form-control">
                  <option value="pending">Pending</option>
                  <option value="paid">Paid</option>
                  <option value="partial">Partial</option>
                  <option value="failed">Failed</option>
                </select>
              </div>
              <button class="btn btn-primary">Record Payment</button>
            </form>
          </div>
        </div>
      </div>
    </div>

  <?php else: ?>
    <div class="alert alert-warning">No active lease was found for your account.</div>
  <?php endif; ?>

  <hr>
  <h4>Payment History</h4>
  <table class="table table-striped">
    <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Transaction</th><th>Reference</th></tr></thead>
    <tbody>
      <?php while ($row = $history->fetch_assoc()): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['payment_date']); ?></td>
          <td>KES <?php echo number_format($row['amount_paid']); ?></td>
          <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
          <td><?php echo htmlspecialchars($row['status']); ?></td>
          <td><?php echo htmlspecialchars($row['transaction_id']); ?></td>
          <td><?php echo htmlspecialchars($row['reference']); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <script>
    document.getElementById('stkPushForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var form = event.target;
      var data = new URLSearchParams(new FormData(form));
      var resultElement = document.getElementById('stkResponse');
      resultElement.innerHTML = '<div class="alert alert-info">Sending request to M-PESA...</div>';
      fetch('../mpesa_payment_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data.toString()
      })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (json.status === 'success') {
          resultElement.innerHTML = '<div class="alert alert-success">' + json.message + '</div>';
        } else {
          resultElement.innerHTML = '<div class="alert alert-danger">' + (json.message || 'Unable to send M-PESA request.') + '</div>';
        }
      })
      .catch(function () {
        resultElement.innerHTML = '<div class="alert alert-danger">Unable to contact payment server.</div>';
      });
    });

    var receiveButton = document.getElementById('receivePaymentButton');
    if (receiveButton) {
      receiveButton.addEventListener('click', function () {
        var manualForm = document.getElementById('manualPaymentForm');
        if (manualForm) {
          manualForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
          var amountInput = manualForm.querySelector('input[name="amount_paid"]');
          if (amountInput) {
            amountInput.focus();
          }
        }
      });
    }
  </script>
</body>
</html>
