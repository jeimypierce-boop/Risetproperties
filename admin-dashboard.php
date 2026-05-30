<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();

// Dashboard Metrics
$metrics = [
    'active_properties' => $conn->query("SELECT COUNT(*) as count FROM properties WHERE status = 'active'" . ($landlord_id ? " AND landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'],
    'active_tenants' => $conn->query("SELECT COUNT(DISTINCT t.id) as count FROM tenants t JOIN leases l ON t.id = l.tenant_id JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'],
    'active_leases' => $conn->query("SELECT COUNT(*) as count FROM leases l JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'],
    'monthly_revenue' => $conn->query("SELECT COALESCE(SUM(rp.amount_paid), 0) as total FROM rent_payments rp LEFT JOIN properties p ON rp.property_id = p.id WHERE DATE_FORMAT(rp.payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['total'],
    'outstanding_rent' => $conn->query("SELECT COALESCE(SUM(l.monthly_rent) - COALESCE(SUM(rp.amount_paid), 0), 0) as total FROM leases l JOIN properties p ON l.property_id = p.id LEFT JOIN rent_payments rp ON l.id = rp.lease_id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['total'],
    'maintenance_pending' => $conn->query("SELECT COUNT(*) as count FROM maintenance_tasks m JOIN properties p ON m.property_id = p.id WHERE m.status = 'pending'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'],
    'occupancy_rate' => round(($conn->query("SELECT COUNT(*) as count FROM leases l JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'] / max(1, $conn->query("SELECT COUNT(*) as count FROM properties WHERE status = 'active'" . ($landlord_id ? " AND landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'])) * 100, 1),
    'new_tenants_today' => $conn->query("SELECT COUNT(DISTINCT t.id) as count FROM tenants t JOIN leases l ON t.id = l.tenant_id JOIN properties p ON l.property_id = p.id WHERE DATE(t.created_at) = CURDATE()" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""))->fetch_assoc()['count'],
    'unread_messages' => $conn->query("SELECT COUNT(*) as count FROM communications WHERE status = 'unread'")->fetch_assoc()['count']
];

// Recent Activities
$recent_activities = $conn->query("SELECT * FROM (
    SELECT 'lease' as type, DATE(l.created_at) as date, CONCAT('Lease created') as action FROM leases l JOIN properties p ON l.property_id = p.id" . ($landlord_id ? " WHERE p.landlord_id = " . intval($landlord_id) : "") . " ORDER BY l.created_at DESC LIMIT 5
    UNION ALL
    SELECT 'payment' as type, DATE(rp.payment_date) as date, CONCAT('Payment received: KES ', rp.amount_paid) as action FROM rent_payments rp LEFT JOIN properties p ON rp.property_id = p.id" . ($landlord_id ? " WHERE p.landlord_id = " . intval($landlord_id) : "") . " ORDER BY rp.payment_date DESC LIMIT 5
    UNION ALL
    SELECT 'maintenance' as type, DATE(m.created_at) as date, CONCAT('Maintenance: ', m.title) as action FROM maintenance_tasks m JOIN properties p ON m.property_id = p.id" . ($landlord_id ? " WHERE p.landlord_id = " . intval($landlord_id) : "") . " ORDER BY m.created_at DESC LIMIT 5
) as activities ORDER BY date DESC LIMIT 10");

$top_properties = $conn->query("SELECT p.title as property_title, p.status, COUNT(l.id) as lease_count
FROM properties p
LEFT JOIN leases l ON l.property_id = p.id" . ($landlord_id ? " WHERE p.landlord_id = " . intval($landlord_id) : "") . "
GROUP BY p.id ORDER BY lease_count DESC LIMIT 5");

$recent_payments = $conn->query("SELECT rp.amount_paid, rp.payment_date, rp.payment_method, t.first_name, t.last_name, p.title as property_title
FROM rent_payments rp
LEFT JOIN leases l ON rp.lease_id = l.id
LEFT JOIN tenants t ON l.tenant_id = t.id
LEFT JOIN properties p ON l.property_id = p.id" . ($landlord_id ? " WHERE p.landlord_id = " . intval($landlord_id) : "") . "
ORDER BY rp.payment_date DESC LIMIT 5");

// Lease Expiries (Next 30 days)
$upcoming_expiries = $conn->query("SELECT l.*, t.first_name, t.last_name, p.title as property_title,
    DATEDIFF(l.end_date, NOW()) as days_until_expiry
FROM leases l
JOIN tenants t ON l.tenant_id = t.id
JOIN properties p ON l.property_id = p.id
WHERE l.status = 'active' AND DATEDIFF(l.end_date, NOW()) BETWEEN 0 AND 30" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : "") . "
ORDER BY l.end_date ASC");

// Outstanding Rent
$outstanding_rent = $conn->query("SELECT COALESCE(SUM(l.rent_amount) - COALESCE(SUM(rp.amount_paid), 0), 0) as total
FROM leases l
LEFT JOIN rent_payments rp ON l.id = rp.lease_id
JOIN properties p ON l.property_id = p.id
WHERE l.status = 'active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""));
$outstanding = $outstanding_rent->fetch_assoc()['total'];

$user_info = get_user_info();
$user_role_label = get_user_role_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Dashboard - Riset Property Management System</title>
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
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .metric-card h3 { margin: 15px 0; font-size: 32px; font-weight: bold; }
        .metric-card p { margin: 0; font-size: 14px; opacity: 0.9; }
        .metric-card.revenue { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .metric-card.pending { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .metric-card.maintenance { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .metric-card.occupancy { background: linear-gradient(135deg, #43a047 0%, #66bb6a 100%); }
        
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .quick-link {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
        }
        .quick-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .quick-link i { font-size: 32px; color: #667eea; margin-bottom: 10px; }
        .quick-link p { margin: 5px 0 0 0; font-size: 12px; font-weight: bold; }
        
        .activity-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-lease { background: #667eea; color: white; }
        .badge-payment { background: #43e97b; color: white; }
        .badge-maintenance { background: #f39c12; color: white; }
        
        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin: 25px 0 15px 0;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
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
                    <a class='waves-effect btn-noti' href="admin-communications.php" title="Messages">
                        <i class="fa fa-bell-o" aria-hidden="true"></i><span><?php echo $metrics['unread_messages']; ?></span>
                    </a>
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
                        <li><h5>Property Management System <span>Dashboard</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php" class="menu-active"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-properties.php"><i class="fa fa-building" aria-hidden="true"></i> Properties</a></li>
                        <li><a href="admin-user-all.php"><i class="fa fa-users" aria-hidden="true"></i> Tenants</a></li>
                        <li><a href="admin-leases.php"><i class="fa fa-file-contract" aria-hidden="true"></i> Leases</a></li>
                        <li><a href="admin-rent-payments.php"><i class="fa fa-money" aria-hidden="true"></i> Payments</a></li>
                        <li><a href="admin-maintenance.php"><i class="fa fa-wrench" aria-hidden="true"></i> Maintenance</a></li>
                        <li><a href="admin-reports.php"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Reports</a></li>
                        <li><a href="admin-communications.php"><i class="fa fa-envelope" aria-hidden="true"></i> Communications</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Dashboard</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3" style="padding: 20px;">
                    <h2 style="margin-bottom: 10px;">Welcome to Riset Properties Dashboard</h2>
                    <p style="color: #999; margin-bottom: 20px;">Real-time Property Management System - Complete overview of all operations</p>

                    <!-- Key Metrics -->
                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <i class="fa fa-building" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['active_properties']; ?></h3>
                                <p>Active Properties</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <i class="fa fa-users" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['active_tenants']; ?></h3>
                                <p>Active Tenants</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card occupancy">
                                <i class="fa fa-percent" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['occupancy_rate']; ?>%</h3>
                                <p>Occupancy Rate</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card revenue">
                                <i class="fa fa-money" style="font-size: 24px;"></i>
                                <h3>KES <?php echo number_format($metrics['monthly_revenue']); ?></h3>
                                <p>This Month Revenue</p>
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 10px;">
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card pending">
                                <i class="fa fa-exclamation-circle" style="font-size: 24px;"></i>
                                <h3>KES <?php echo number_format($outstanding); ?></h3>
                                <p>Outstanding Rent</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <i class="fa fa-file-contract" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['active_leases']; ?></h3>
                                <p>Active Leases</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card maintenance">
                                <i class="fa fa-wrench" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['maintenance_pending']; ?></h3>
                                <p>Pending Maintenance</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="metric-card">
                                <i class="fa fa-envelope" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['unread_messages']; ?></h3>
                                <p>Unread Messages</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="section-title"><i class="fa fa-link"></i> Quick Actions</div>
                    <div class="quick-links">
                        <a href="admin-properties.php" class="quick-link">
                            <i class="fa fa-plus-circle"></i>
                            <p>Add Property</p>
                        </a>
                        <a href="admin-user-add.php" class="quick-link">
                            <i class="fa fa-user-plus"></i>
                            <p>Create Tenant</p>
                        </a>
                        <a href="admin-leases.php" class="quick-link">
                            <i class="fa fa-file-contract"></i>
                            <p>Create Lease</p>
                        </a>
                        <a href="admin-rent-payments.php" class="quick-link">
                            <i class="fa fa-money"></i>
                            <p>Record Payment</p>
                        </a>
                        <a href="admin-maintenance.php" class="quick-link">
                            <i class="fa fa-wrench"></i>
                            <p>Maintenance</p>
                        </a>
                        <a href="admin-reports.php" class="quick-link">
                            <i class="fa fa-bar-chart"></i>
                            <p>Reports</p>
                        </a>
                    </div>

                    <!-- Featured Overview -->
                    <div class="row" style="margin-top: 30px;">
                        <div class="col-md-4">
                            <div class="metric-card" style="background: linear-gradient(135deg,#005C97 0%,#3678D6 100%);">
                                <i class="fa fa-calendar" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['new_tenants_today']; ?></h3>
                                <p>New Tenants Today</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric-card" style="background: linear-gradient(135deg,#FB8072 0%,#F56C7A 100%);">
                                <i class="fa fa-clock-o" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['maintenance_pending']; ?></h3>
                                <p>Maintenance Requests</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric-card" style="background: linear-gradient(135deg,#FFBE76 0%,#FF6B6B 100%);">
                                <i class="fa fa-envelope" style="font-size: 24px;"></i>
                                <h3><?php echo $metrics['unread_messages']; ?></h3>
                                <p>Unread Messages</p>
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-top: 30px;">
                        <div class="col-md-6">
                            <div class="section-title"><i class="fa fa-list"></i> Latest Payments Received</div>
                            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 15px; max-height: 360px; overflow-y: auto;">
                                <?php if ($recent_payments && $recent_payments->num_rows): ?>
                                    <?php while ($payment = $recent_payments->fetch_assoc()): ?>
                                        <div class="activity-item">
                                            <div>
                                                <strong>KES <?php echo number_format($payment['amount_paid']); ?></strong>
                                                <br>
                                                <small style="color: #999;"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?> â€” <?php echo htmlspecialchars($payment['property_title']); ?></small>
                                            </div>
                                            <small style="color: #999;"><?php echo htmlspecialchars($payment['payment_date']); ?></small>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: #999; padding: 20px;">No recent payment records found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title"><i class="fa fa-building-o"></i> Top Properties</div>
                            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 15px; max-height: 360px; overflow-y: auto;">
                                <?php if ($top_properties && $top_properties->num_rows): ?>
                                    <?php while ($property = $top_properties->fetch_assoc()): ?>
                                        <div class="activity-item">
                                            <div>
                                                <strong><?php echo htmlspecialchars($property['property_title']); ?></strong>
                                                <br>
                                                <small style="color: #999;">Status: <?php echo htmlspecialchars($property['status']); ?></small>
                                            </div>
                                            <span class="badge badge-payment"><?php echo $property['lease_count']; ?> leases</span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p style="text-align: center; color: #999; padding: 20px;">No properties found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Two Column Layout -->
                    <div class="row" style="margin-top: 30px;">
                        <!-- Upcoming Lease Expiries -->
                        <div class="col-md-6">
                            <div class="section-title"><i class="fa fa-calendar"></i> Lease Expiries (Next 30 Days)</div>
                            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 15px; max-height: 350px; overflow-y: auto;">
                                <?php 
                                $has_expiries = false;
                                while($row = $upcoming_expiries->fetch_assoc()): 
                                    $has_expiries = true;
                                    $urgency_class = $row['days_until_expiry'] < 7 ? 'label-danger' : 'label-warning';
                                ?>
                                <div class="activity-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></strong>
                                        <br>
                                        <small style="color: #999;"><?php echo htmlspecialchars($row['property_title']); ?></small>
                                    </div>
                                    <span class="label <?php echo $urgency_class; ?>"><?php echo $row['days_until_expiry']; ?> days</span>
                                </div>
                                <?php endwhile; 
                                if (!$has_expiries): ?>
                                <p style="text-align: center; color: #999; padding: 20px;">No leases expiring in the next 30 days</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Activities -->
                        <div class="col-md-6">
                            <div class="section-title"><i class="fa fa-history"></i> Recent Activities</div>
                            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 15px; max-height: 350px; overflow-y: auto;">
                                <?php 
                                while($row = $recent_activities->fetch_assoc()): 
                                    $badge_class = 'badge-' . $row['type'];
                                ?>
                                <div class="activity-item">
                                    <div>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo strtoupper($row['type']); ?></span>
                                        <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($row['action']); ?></p>
                                    </div>
                                    <small style="color: #999;"><?php echo $row['date']; ?></small>
                                </div>
                                <?php endwhile; ?>
                            </div>
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
    <script src="js/admin-delete.js"></script>
</body>
</html>
<?php $conn->close(); ?>





