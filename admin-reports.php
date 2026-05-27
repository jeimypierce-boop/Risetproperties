<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();

// Get filter parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$property_filter = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$tenant_filter = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;

$landlordFilter = '';
if ($landlord_id) {
    $landlordFilter = " AND p.landlord_id = " . intval($landlord_id);
}

// Key Metrics
$metrics_query = "SELECT 
    COUNT(DISTINCT l.id) as total_active_leases,
    COUNT(DISTINCT p.id) as total_properties,
    SUM(l.monthly_rent) as total_monthly_rent,
    SUM(CASE WHEN l.status = 'Active' THEN l.monthly_rent ELSE 0 END) as active_rent,
    COALESCE(SUM(CASE WHEN rp.payment_date LIKE '" . date('Y-m') . "%' THEN rp.amount_paid ELSE 0 END), 0) as current_month_collected
FROM leases l
JOIN properties p ON l.property_id = p.id
LEFT JOIN rent_payments rp ON l.id = rp.lease_id AND rp.payment_date LIKE '" . date('Y-m') . "%'
WHERE l.status = 'Active'" . $landlordFilter;

$metrics = $conn->query($metrics_query)->fetch_assoc();

// Outstanding Amount
$outstanding_query = "SELECT 
    COALESCE(SUM(l.monthly_rent - COALESCE((SELECT COALESCE(SUM(amount_paid), 0) FROM rent_payments WHERE lease_id = l.id AND payment_date LIKE '" . date('Y-m') . "%'), 0)), 0) as outstanding_current_month,
    COALESCE(SUM(l.monthly_rent - COALESCE((SELECT COALESCE(SUM(amount_paid), 0) FROM rent_payments WHERE lease_id = l.id), 0)), 0) as total_outstanding
FROM leases l
JOIN properties p ON l.property_id = p.id
WHERE l.status = 'Active'" . $landlordFilter;

$outstanding = $conn->query($outstanding_query)->fetch_assoc();

// Rent Roll Report with filtering
$rent_roll_query = "SELECT 
    l.id, CONCAT(t.first_name, ' ', t.last_name) as tenant_name,
    p.title as property_title,
    l.monthly_rent,
    COALESCE(SUM(CASE WHEN rp.payment_date BETWEEN '" . $date_from . "' AND '" . $date_to . "' THEN rp.amount_paid ELSE 0 END), 0) as paid_period,
    l.monthly_rent - COALESCE(SUM(CASE WHEN rp.payment_date LIKE '" . date('Y-m') . "%' THEN rp.amount_paid ELSE 0 END), 0) as outstanding,
    l.lease_start_date, l.lease_end_date, l.status
FROM leases l
JOIN tenants t ON l.tenant_id = t.id
JOIN properties p ON l.property_id = p.id
LEFT JOIN rent_payments rp ON l.id = rp.lease_id
WHERE l.status = 'Active'" . $landlordFilter;

if ($property_filter) {
    $rent_roll_query .= " AND p.id = " . $property_filter;
}
if ($tenant_filter) {
    $rent_roll_query .= " AND t.id = " . $tenant_filter;
}

$rent_roll_query .= " GROUP BY l.id ORDER BY p.title, t.first_name";
$rent_roll = $conn->query($rent_roll_query);

// Aged Receivables
$aged_receivables_query = "SELECT 
    l.id, CONCAT(t.first_name, ' ', t.last_name) as tenant_name,
    p.title as property_title,
    l.monthly_rent,
    SUM(l.monthly_rent) - COALESCE(SUM(rp.amount_paid), 0) as arrears,
    DATEDIFF(NOW(), MAX(rp.payment_date)) as days_since_last_payment,
    l.status
FROM leases l
JOIN tenants t ON l.tenant_id = t.id
JOIN properties p ON l.property_id = p.id
LEFT JOIN rent_payments rp ON l.id = rp.lease_id
WHERE l.status = 'Active'" . $landlordFilter;

if ($property_filter) {
    $aged_receivables_query .= " AND p.id = " . $property_filter;
}

$aged_receivables_query .= " GROUP BY l.id HAVING arrears > 0 ORDER BY days_since_last_payment DESC";
$aged_receivables = $conn->query($aged_receivables_query);

// Payment History
$payment_history_query = "SELECT 
    rp.id, rp.payment_date, rp.amount_paid, rp.payment_method, rp.status,
    l.monthly_rent,
    CONCAT(t.first_name, ' ', t.last_name) as tenant_name,
    p.title as property_title
FROM rent_payments rp
JOIN leases l ON rp.lease_id = l.id
JOIN tenants t ON l.tenant_id = t.id
JOIN properties p ON l.property_id = p.id
WHERE rp.payment_date BETWEEN '" . $date_from . "' AND '" . $date_to . "'" . $landlordFilter;

if ($property_filter) {
    $payment_history_query .= " AND p.id = " . $property_filter;
}

$payment_history_query .= " ORDER BY rp.payment_date DESC LIMIT 100";
$payment_history = $conn->query($payment_history_query);

// Occupancy Analysis
$occupancy_query = "SELECT 
    p.id, p.title as property_title,
    (CASE WHEN p.unit_count IS NOT NULL AND p.unit_count > 0 THEN p.unit_count ELSE GREATEST(COUNT(CASE WHEN l.status = 'Active' THEN 1 END), 1) END) as total_units,
    COUNT(CASE WHEN l.status = 'Active' THEN 1 END) as occupied_units,
    ROUND(
        (COUNT(CASE WHEN l.status = 'Active' THEN 1 END) / (CASE WHEN p.unit_count IS NOT NULL AND p.unit_count > 0 THEN p.unit_count ELSE GREATEST(COUNT(CASE WHEN l.status = 'Active' THEN 1 END), 1) END)) * 100,
        1
    ) as occupancy_rate
FROM properties p
LEFT JOIN leases l ON p.id = l.property_id
WHERE 1=1" . $landlordFilter;

if ($property_filter) {
    $occupancy_query .= " AND p.id = " . $property_filter;
}

$occupancy_query .= " GROUP BY p.id ORDER BY p.title";
$occupancy = $conn->query($occupancy_query);

// Monthly Revenue Trend
$financials_query = "SELECT 
    DATE_FORMAT(rp.payment_date, '%Y-%m') as month,
    SUM(CASE WHEN rp.status = 'paid' THEN rp.amount_paid ELSE 0 END) as revenue,
    COUNT(*) as transactions,
    SUM(CASE WHEN rp.status = 'pending' THEN rp.amount_paid ELSE 0 END) as pending
FROM rent_payments rp
JOIN leases l ON rp.lease_id = l.id
JOIN properties p ON l.property_id = p.id
WHERE rp.payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)" . $landlordFilter;

if ($property_filter) {
    $financials_query .= " AND p.id = " . $property_filter;
}

$financials_query .= " GROUP BY DATE_FORMAT(rp.payment_date, '%Y-%m') ORDER BY month DESC";
$financials_result = $conn->query($financials_query);
$financials = $financials_result ? $financials_result->fetch_all(MYSQLI_ASSOC) : [];

// Payment Status Distribution
$payment_status_query = "SELECT 
    rp.status,
    COUNT(*) as count,
    SUM(rp.amount_paid) as total_amount
FROM rent_payments rp
JOIN leases l ON rp.lease_id = l.id
JOIN properties p ON l.property_id = p.id
WHERE rp.payment_date LIKE '" . date('Y-m') . "%'" . $landlordFilter;

if ($property_filter) {
    $payment_status_query .= " AND p.id = " . $property_filter;
}

$payment_status_query .= " GROUP BY rp.status";
$payment_status_result = $conn->query($payment_status_query);
$payment_status = $payment_status_result ? $payment_status_result->fetch_all(MYSQLI_ASSOC) : [];

// Get properties for filter dropdown
$properties_list = $conn->query("SELECT id, title AS property_title FROM properties WHERE 1=1" . $landlordFilter . " ORDER BY title");

// Get tenants for filter dropdown
$tenants_list = $conn->query("SELECT DISTINCT t.id, CONCAT(t.first_name, ' ', t.last_name) as name 
FROM tenants t 
JOIN leases l ON t.id = l.tenant_id 
JOIN properties p ON l.property_id = p.id 
WHERE 1=1" . $landlordFilter . " ORDER BY t.first_name");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reports & Analytics - Riset Property Ltd</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/style-mob.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tempusdominus-bootstrap-4/5.39.0/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />
    <style>
        .metrics-card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #667eea; }
        .metrics-card .metric-value { font-size: 24px; font-weight: bold; color: #667eea; }
        .metrics-card .metric-label { font-size: 12px; color: #888; text-transform: uppercase; margin-top: 5px; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); position: relative; height: 400px; }
        .report-section { margin-bottom: 30px; }
        .report-filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-group { margin-bottom: 15px; }
        .filter-group label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #666; }
        .filter-group input, .filter-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
        .btn-export { background: #667eea; color: white; padding: 10px 20px; border-radius: 4px; cursor: pointer; border: none; margin-right: 10px; margin-bottom: 10px; font-size: 12px; }
        .btn-export:hover { background: #764ba2; }
        .btn-secondary { background: #888; color: white; padding: 10px 20px; border-radius: 4px; cursor: pointer; border: none; font-size: 12px; }
        .btn-secondary:hover { background: #666; }
        .btn-filter { background: #667eea; color: white; }
        .btn-filter:hover { background: #764ba2; }
        .report-tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 20px; flex-wrap: wrap; }
        .report-tabs .tab-btn { padding: 12px 20px; cursor: pointer; border: none; background: none; color: #888; font-weight: bold; border-bottom: 3px solid transparent; }
        .report-tabs .tab-btn.active { color: #667eea; border-bottom-color: #667eea; }
        .report-tabs .tab-btn:hover { color: #667eea; }
        .report-content { display: none; }
        .report-content.active { display: block; }
        table.report-table { width: 100%; border-collapse: collapse; }
        table.report-table th { background: #f5f5f5; padding: 12px; text-align: left; font-weight: bold; font-size: 12px; color: #333; border-bottom: 2px solid #ddd; }
        table.report-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 12px; }
        table.report-table tr:hover { background: #f9f9f9; }
        .label { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .label-success { background: #d4edda; color: #155724; }
        .label-danger { background: #f8d7da; color: #721c24; }
        .label-warning { background: #fff3cd; color: #856404; }
        .label-info { background: #d1ecf1; color: #0c5460; }
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
            <?php include 'top_user_menu.php'; ?>
        </div>
    </div>

    <div class="container-fluid sb2">
        <div class="row">
            <div class="sb2-1">
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-reports.php" class="menu-active"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Reports</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Reports & Analytics</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3" style="padding: 20px;">
                    
                    <!-- Key Metrics Summary -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                        <div class="metrics-card">
                            <div class="metric-value"><?php echo intval($metrics['total_active_leases']); ?></div>
                            <div class="metric-label">Active Leases</div>
                        </div>
                        <div class="metrics-card">
                            <div class="metric-value">KES <?php echo number_format($metrics['total_monthly_rent']); ?></div>
                            <div class="metric-label">Total Monthly Rent</div>
                        </div>
                        <div class="metrics-card">
                            <div class="metric-value">KES <?php echo number_format($metrics['current_month_collected']); ?></div>
                            <div class="metric-label">Current Month Collected</div>
                        </div>
                        <div class="metrics-card" style="border-left-color: #dc3545;">
                            <div class="metric-value" style="color: #dc3545;">KES <?php echo number_format($outstanding['outstanding_current_month']); ?></div>
                            <div class="metric-label">Outstanding This Month</div>
                        </div>
                    </div>

                    <!-- Report Filters -->
                    <div class="report-filters">
                        <h4 style="margin-top: 0;">Filter Reports</h4>
                        <form method="GET" action="">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                                <div class="filter-group">
                                    <label>From Date</label>
                                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                                </div>
                                <div class="filter-group">
                                    <label>To Date</label>
                                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Property</label>
                                    <select name="property_id">
                                        <option value="0">All Properties</option>
                                        <?php while($prop = $properties_list->fetch_assoc()): ?>
                                            <option value="<?php echo $prop['id']; ?>" <?php echo $property_filter == $prop['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prop['property_title']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Tenant</label>
                                    <select name="tenant_id">
                                        <option value="0">All Tenants</option>
                                        <?php while($tenant = $tenants_list->fetch_assoc()): ?>
                                            <option value="<?php echo $tenant['id']; ?>" <?php echo $tenant_filter == $tenant['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tenant['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div style="display: flex; align-items: flex-end;">
                                    <button type="submit" class="btn-filter" style="width: 100%;"><i class="fa fa-filter"></i> Apply Filters</button>
                                </div>
                                <div style="display: flex; align-items: flex-end;">
                                    <a href="admin-reports.php" class="btn-secondary" style="width: 100%; text-align: center; text-decoration: none;"><i class="fa fa-refresh"></i> Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Report Tabs -->
                    <div class="report-tabs">
                        <button class="tab-btn active" onclick="switchTab(event, 'summary')">Summary</button>
                        <button class="tab-btn" onclick="switchTab(event, 'rent-roll')">Rent Roll</button>
                        <button class="tab-btn" onclick="switchTab(event, 'aged-receivables')">Aged Receivables</button>
                        <button class="tab-btn" onclick="switchTab(event, 'payment-history')">Payment History</button>
                        <button class="tab-btn" onclick="switchTab(event, 'occupancy')">Occupancy</button>
                    </div>

                    <!-- Summary Tab -->
                    <div id="summary" class="report-content active">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(45%, 1fr)); gap: 20px;">
                            <div class="chart-container">
                                <h4>Monthly Revenue Trend (Last 12 Months)</h4>
                                <canvas id="revenueChart"></canvas>
                            </div>
                            <div class="chart-container">
                                <h4>Payment Status Distribution</h4>
                                <canvas id="paymentStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Rent Roll Tab -->
                    <div id="rent-roll" class="report-content">
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button class="btn-export" onclick="printReport('rent-roll-table')"><i class="fa fa-print"></i> Print</button>
                        </div>
                        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto;">
                            <table class="report-table" id="rent-roll-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property / Unit</th>
                                        <th>Monthly Rent</th>
                                        <th>Paid (Period)</th>
                                        <th>Outstanding</th>
                                        <th>Status</th>
                                        <th>Lease Period</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_rent = 0;
                                    $total_paid = 0;
                                    $total_outstanding = 0;
                                    while($row = $rent_roll->fetch_assoc()): 
                                        $total_rent += $row['monthly_rent'];
                                        $total_paid += $row['paid_period'];
                                        $total_outstanding += $row['outstanding'];
                                        $status = $row['outstanding'] > 0 ? '<span class="label label-danger">Pending</span>' : '<span class="label label-success">Paid</span>';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['property_title']); ?></td>
                                        <td>KES <?php echo number_format($row['monthly_rent']); ?></td>
                                        <td>KES <?php echo number_format($row['paid_period']); ?></td>
                                        <td><strong>KES <?php echo number_format($row['outstanding']); ?></strong></td>
                                        <td><?php echo $status; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($row['lease_start_date'])) . ' - ' . date('M d, Y', strtotime($row['lease_end_date'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <tr style="background: #f9f9f9; font-weight: bold;">
                                        <td colspan="2">TOTAL</td>
                                        <td>KES <?php echo number_format($total_rent); ?></td>
                                        <td>KES <?php echo number_format($total_paid); ?></td>
                                        <td>KES <?php echo number_format($total_outstanding); ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Aged Receivables Tab -->
                    <div id="aged-receivables" class="report-content">
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button class="btn-export" onclick="printReport('aged-receivables-table')"><i class="fa fa-print"></i> Print</button>
                        </div>
                        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto;">
                            <table class="report-table" id="aged-receivables-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property / Unit</th>
                                        <th>Monthly Rent</th>
                                        <th>Arrears (KES)</th>
                                        <th>Days Since Payment</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($aged_receivables->num_rows == 0) {
                                        echo '<tr><td colspan="6" style="text-align: center; padding: 20px;">No outstanding arrears</td></tr>';
                                    }
                                    while($row = $aged_receivables->fetch_assoc()): 
                                        $priority = $row['days_since_last_payment'] > 90 ? '<span class="label label-danger">High Priority</span>' : ($row['days_since_last_payment'] > 30 ? '<span class="label label-warning">Medium Priority</span>' : '<span class="label label-info">Low Priority</span>');
                                        $priority_null = is_null($row['days_since_last_payment']) ? '<span class="label label-danger">No Payment</span>' : $priority;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['property_title']); ?></td>
                                        <td>KES <?php echo number_format($row['monthly_rent']); ?></td>
                                        <td><strong>KES <?php echo number_format($row['arrears']); ?></strong></td>
                                        <td><?php echo $row['days_since_last_payment'] ?? 'Never'; ?> days</td>
                                        <td><?php echo $priority_null; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payment History Tab -->
                    <div id="payment-history" class="report-content">
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button class="btn-export" onclick="printReport('payment-history-table')"><i class="fa fa-print"></i> Print</button>
                        </div>
                        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto;">
                            <table class="report-table" id="payment-history-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($payment_history->num_rows == 0) {
                                        echo '<tr><td colspan="6" style="text-align: center; padding: 20px;">No payments in this period</td></tr>';
                                    }
                                    while($row = $payment_history->fetch_assoc()): 
                                        $status_label = $row['status'] == 'paid' ? '<span class="label label-success">Paid</span>' : '<span class="label label-warning">Pending</span>';
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['tenant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['property_title']); ?></td>
                                        <td>KES <?php echo number_format($row['amount_paid']); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($row['payment_method'])); ?></td>
                                        <td><?php echo $status_label; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Occupancy Tab -->
                    <div id="occupancy" class="report-content">
                        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                            <button class="btn-export" onclick="printReport('occupancy-table')"><i class="fa fa-print"></i> Print</button>
                        </div>
                        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow-x: auto;">
                            <table class="report-table" id="occupancy-table">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Total Units</th>
                                        <th>Occupied Units</th>
                                        <th>Vacant Units</th>
                                        <th>Occupancy Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $avg_occupancy = 0;
                                    $property_count = 0;
                                    while($row = $occupancy->fetch_assoc()): 
                                        $avg_occupancy += $row['occupancy_rate'];
                                        $property_count++;
                                        $vacant = $row['total_units'] - $row['occupied_units'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['property_title']); ?></td>
                                        <td><?php echo intval($row['total_units']); ?></td>
                                        <td><?php echo intval($row['occupied_units']); ?></td>
                                        <td><?php echo intval($vacant); ?></td>
                                        <td>
                                            <span class="label" style="background: <?php echo $row['occupancy_rate'] >= 80 ? '#d4edda; color: #155724;' : ($row['occupancy_rate'] >= 50 ? '#fff3cd; color: #856404;' : '#f8d7da; color: #721c24;'); ?>">
                                                <?php echo number_format($row['occupancy_rate'], 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($property_count > 0): ?>
                                    <tr style="background: #f9f9f9; font-weight: bold;">
                                        <td>AVERAGE OCCUPANCY</td>
                                        <td colspan="4">
                                            <span class="label" style="background: #d4edda; color: #155724;">
                                                <?php echo number_format($avg_occupancy / $property_count, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($f) { return $f['month']; }, $financials)); ?>,
                datasets: [{
                    label: 'Revenue (KES)',
                    data: <?php echo json_encode(array_map(function($f) { return floatval($f['revenue']); }, $financials)); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Payment Status Chart
        const statusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        const paymentStatusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function($p) { return ucfirst($p['status']); }, $payment_status)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(function($p) { return intval($p['count']); }, $payment_status)); ?>,
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom' }
                }
            }
        });

        function switchTab(event, tabName) {
            event.preventDefault();
            
            // Hide all content
            var contents = document.getElementsByClassName('report-content');
            for (var i = 0; i < contents.length; i++) {
                contents[i].classList.remove('active');
            }
            
            // Remove active from all buttons
            var buttons = document.getElementsByClassName('tab-btn');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].classList.remove('active');
            }
            
            // Show selected content and mark button as active
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function printReport(tableId) {
            const printContent = document.getElementById(tableId).outerHTML;
            const newWindow = window.open('', '', 'width=1200,height=800');
            newWindow.document.write(`
                <html>
                <head>
                    <title>Report</title>
                </head>
                <body>
                    <h2>Report - ${new Date().toLocaleDateString()}</h2>
                    ${printContent}
                    <script>
                        window.print();
                        window.close();
                    </script>
                </body>
                </html>
            `);
        }

    </script>
</body>
</html>
<?php $conn->close(); ?>





