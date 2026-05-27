<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
require_login();

$landlord_id = get_landlord_id();

// Get dashboard statistics
$stats = [
    'properties' => 0,
    'tenants' => 0,
    'leases' => 0,
    'pending_invoices' => 0,
    'monthly_revenue' => 0.0,
    'outstanding_rent' => 0.0,
    'maintenance_pending' => 0,
    'viewings' => 0,
    'unread_messages' => 0
];

// Fetch statistics from database
$result = $conn->query("SELECT COUNT(*) as count FROM properties WHERE deleted_at IS NULL" . ($landlord_id ? " AND landlord_id = " . intval($landlord_id) : ""));
if ($result) {
    $stats['properties'] = $result->fetch_assoc()['count'];
}

if ($landlord_id) {
    $result = $conn->query("SELECT COUNT(DISTINCT t.id) as count FROM tenants t JOIN leases l ON t.id = l.tenant_id JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active' AND p.landlord_id = " . intval($landlord_id));
} else {
    $result = $conn->query("SELECT COUNT(*) as count FROM tenants");
}
if ($result) {
    $stats['tenants'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM leases l JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""));
if ($result) {
    $stats['leases'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COUNT(*) as count FROM rent_payments rp LEFT JOIN properties p ON rp.property_id = p.id" . ($landlord_id ? " WHERE p.landlord_id = " . intval($landlord_id) : " WHERE rp.status = 'pending'") . ($landlord_id ? " AND rp.status = 'pending'" : ""));
if ($result) {
    $stats['pending_invoices'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SELECT COALESCE(SUM(rp.amount_paid), 0) as total FROM rent_payments rp LEFT JOIN properties p ON rp.property_id = p.id WHERE rp.status = 'paid' AND DATE_FORMAT(rp.payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""));
if ($result) {
    $stats['monthly_revenue'] = floatval($result->fetch_assoc()['total']);
}

$result = $conn->query("SELECT COALESCE(SUM(l.monthly_rent) - COALESCE((SELECT SUM(rp.amount_paid) FROM rent_payments rp WHERE rp.lease_id = l.id AND DATE_FORMAT(rp.payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')), 0), 0) as total FROM leases l JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""));
if ($result) {
    $stats['outstanding_rent'] = floatval($result->fetch_assoc()['total']);
}

$result = $conn->query("SELECT COUNT(*) as count FROM maintenance_tasks m JOIN properties p ON m.property_id = p.id WHERE m.status IN ('pending','new','open')" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""));
if ($result) {
    $stats['maintenance_pending'] = $result->fetch_assoc()['count'];
}

$result = $conn->query("SHOW TABLES LIKE 'events'");
if ($result && $result->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as count FROM events");
    if ($result) {
        $stats['viewings'] = $result->fetch_assoc()['count'];
    }
} else {
    $result = $conn->query("SHOW TABLES LIKE 'viewings'");
    if ($result && $result->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM viewings");
        if ($result) {
            $stats['viewings'] = $result->fetch_assoc()['count'];
        }
    }
}

$result = $conn->query("SELECT COUNT(*) as count FROM communications WHERE status = 'unread'");
if ($result) {
    $stats['unread_messages'] = $result->fetch_assoc()['count'];
}

// Get occupancy percentage
$result = $conn->query("SELECT COUNT(*) as total FROM properties WHERE deleted_at IS NULL" . ($landlord_id ? " AND landlord_id = " . intval($landlord_id) : ""));
$total_props = $result ? $result->fetch_assoc()['total'] : 0;
$result = $conn->query("SELECT COUNT(*) as active_leases FROM leases l JOIN properties p ON l.property_id = p.id WHERE l.status = 'Active'" . ($landlord_id ? " AND p.landlord_id = " . intval($landlord_id) : ""));
$active_leases = $result ? $result->fetch_assoc()['active_leases'] : 0;
$occupancy_percent = $total_props > 0 ? round(($active_leases / $total_props) * 100, 1) : 0;
$occupied_props = $active_leases;

$user_info = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard - Riset Property Management</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Riset Property Management Dashboard">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Open Sans', sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        /* Top Header */
        .rd-header {
            background: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e8e8e8;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .rd-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .rd-menu-toggle {
            background: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #333;
        }
        
        .rd-logo-letter {
            font-size: 28px;
            font-weight: bold;
            color: #47C363;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .rd-search-bar {
            flex: 1;
            max-width: 300px;
        }
        
        .rd-search-bar input {
            width: 100%;
            padding: 8px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            font-size: 13px;
            background: #f5f5f5;
        }
        
        .rd-header-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .rd-btn-add {
            background: #47C363;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .rd-notification-badge {
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            position: relative;
        }
        
        .rd-icon-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #333;
            position: relative;
        }
        
        .rd-profile {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #c9e4d3;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #47C363;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Main Content */
        .rd-container {
            padding: 20px;
            padding-bottom: 100px;
        }
        
        .rd-search-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .rd-search-main {
            flex: 1;
        }
        
        .rd-search-main input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 13px;
        }
        
        /* Stat Cards */
        .rd-stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #47C363;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .rd-stat-content {
            flex: 1;
        }
        
        .rd-stat-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .rd-stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .rd-stat-subtext {
            font-size: 12px;
            color: #666;
        }
        
        .rd-stat-icon {
            width: 50px;
            height: 50px;
            background: #e8f5e9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #47C363;
        }
        
        .rd-stat-view-btn {
            background: none;
            border: none;
            color: #47C363;
            font-weight: 600;
            cursor: pointer;
            font-size: 12px;
            text-align: right;
        }
        
        /* Progress Bar */
        .rd-progress-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .rd-progress-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        
        .rd-progress-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .rd-progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rd-progress-fill {
            height: 100%;
            background: #47C363;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .rd-progress-text {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
        
        /* Status Badges */
        .rd-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .rd-badge-green {
            background: #e8f5e9;
            color: #47C363;
        }
        
        .rd-badge-yellow {
            background: #fff3e0;
            color: #f57f17;
        }
        
        .rd-badge-red {
            background: #ffebee;
            color: #d32f2f;
        }
        
        .rd-badge-blue {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        /* Bottom Navigation */
        .rd-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e8e8e8;
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            z-index: 200;
        }
        
        .rd-nav-item {
            flex: 1;
            text-align: center;
            padding: 10px 5px;
            text-decoration: none;
            color: #999;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            cursor: pointer;
        }
        
        .rd-nav-item.active {
            color: #47C363;
            border-top: 2px solid #47C363;
        }
        
        .rd-nav-item i {
            font-size: 18px;
        }
        
        .rd-stat-details {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 12px;
        }
        
        .rd-stat-details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .rd-stat-details-row:last-child {
            border-bottom: none;
        }
        
        .rd-stat-details-label {
            color: #666;
        }
        
        .rd-stat-details-value {
            font-weight: 600;
            color: #333;
        }
        
        /* Sidebar Menu */
        .rd-sidebar {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            max-width: 300px;
            height: 100%;
            background: white;
            z-index: 300;
            overflow-y: auto;
            border-right: 1px solid #e8e8e8;
        }
        
        .rd-sidebar.active {
            display: block;
        }
        
        .rd-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e8e8e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .rd-sidebar-title {
            font-weight: 600;
            font-size: 16px;
        }
        
        .rd-sidebar-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }
        
        .rd-sidebar-menu {
            list-style: none;
        }
        
        .rd-sidebar-menu li {
            border-bottom: 1px solid #f0f0f0;
        }
        
        .rd-sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
        }
        
        .rd-sidebar-menu a:hover,
        .rd-sidebar-menu a.active {
            background: #f0f0f0;
            color: #47C363;
        }
        
        .rd-sidebar-menu i {
            margin-right: 10px;
            width: 18px;
        }
        
        /* Live Chat Button */
        .rd-live-chat {
            position: fixed;
            bottom: 80px;
            right: 20px;
            background: #47C363;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 20px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 150;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .rd-live-chat i {
            font-size: 16px;
        }
        
        /* Status Filter Tabs */
        .rd-status-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding: 0 5px;
        }
        
        .rd-status-tab {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
            color: #666;
        }
        
        .rd-status-tab.active {
            background: #e8f5e9;
            border-color: #47C363;
            color: #47C363;
        }
        
        /* Mobile Responsive Adjustments */
        @media (max-width: 768px) {
            /* Hide all stat cards except Total Units and Occupancy on mobile */
            .rd-stat-card:nth-child(n+3) {
                display: none;
            }
            
            /* Adjust padding for mobile */
            .rd-container {
                padding: 15px;
                padding-bottom: 110px;
            }
            
            .rd-header {
                padding: 10px 15px;
            }
            
            .rd-search-bar {
                display: none;
            }
            
            /* Full width search on mobile */
            .rd-search-section {
                margin-bottom: 15px;
            }
            
            .rd-search-main input {
                border-radius: 20px;
                padding: 10px 15px;
                width: 100%;
            }
            
            /* Adjust stat card for mobile */
            .rd-stat-card {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .rd-stat-value {
                font-size: 28px;
            }
            
            /* Adjust bottom nav for mobile */
            .rd-bottom-nav {
                padding: 10px 0;
            }
            
            .rd-nav-item {
                padding: 8px 5px;
                font-size: 10px;
            }
            
            .rd-nav-item.active {
                background: #f0f8f0;
                border-radius: 12px;
                border-top: none;
                color: #47C363;
            }
            
            .rd-nav-item i {
                font-size: 20px;
            }
            
            .rd-header-right {
                gap: 15px;
            }
            
            /* Live chat button position on mobile */
            .rd-live-chat {
                bottom: 75px;
                right: 15px;
                padding: 10px 16px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .rd-container {
                padding: 12px;
                padding-bottom: 110px;
            }
            
            .rd-header {
                padding: 8px 12px;
            }
            
            .rd-stat-card {
                padding: 12px;
                flex-direction: column;
            }
            
            .rd-stat-icon {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Header -->
    <div class="rd-header">
        <div class="rd-header-left">
            <button class="rd-menu-toggle" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
            <div class="rd-logo-letter">R</div>
            <div class="rd-search-bar">
                <input type="text" placeholder="Search tenant or unit">
            </div>
        </div>
        <div class="rd-header-right">
            <a href="admin-add-property.php" class="rd-btn-add" title="Create New">
                <i class="fa fa-plus"></i>
            </a>
            <a href="admin-communications.php" class="rd-icon-btn" title="Notifications">
                <i class="fa fa-bell-o"></i>
                <span class="rd-notification-badge"><?php echo intval($stats['unread_messages']); ?></span>
            </a>
            <a href="admin-reports.php" class="rd-icon-btn" title="Reports">
                <i class="fa fa-eye"></i>
            </a>
            <span style="font-size: 12px; color: #666; margin-right: 10px;"><?php echo get_user_role_label(); ?></span>
            <a href="admin-setting.html" class="rd-profile" title="Profile"><?php echo strtoupper(substr($user_info['name'], 0, 1)); ?></a>
        </div>
    </div>
    
    <!-- Sidebar Menu -->
    <div class="rd-sidebar" id="sidebar">
        <div class="rd-sidebar-header">
            <div class="rd-sidebar-title">Menu</div>
            <button class="rd-sidebar-close" onclick="toggleSidebar()"><i class="fa fa-times"></i></button>
        </div>
        <ul class="rd-sidebar-menu">
            <li><a href="admin-dashboard-modern.php" class="active"><i class="fa fa-home"></i> Dashboard</a></li>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <li><a href="admin-landlord-all.php"><i class="fa fa-user-tie"></i> Landlords</a></li>
            <?php endif; ?>
            <li><a href="admin-properties.php"><i class="fa fa-building"></i> Properties</a></li>
            <li><a href="admin-user-all.php"><i class="fa fa-users"></i> Tenants</a></li>
            <li><a href="admin-leases.php"><i class="fa fa-file-contract"></i> Leases</a></li>
            <li><a href="admin-rent-payments.php"><i class="fa fa-money"></i> Payments</a></li>
            <li><a href="admin-maintenance.php"><i class="fa fa-wrench"></i> Maintenance</a></li>
            <li><a href="admin-event-all.html"><i class="fa fa-calendar"></i> Viewings</a></li>
            <li><a href="admin-reports.php"><i class="fa fa-bar-chart"></i> Reports</a></li>
            <li><a href="admin-communications.php"><i class="fa fa-envelope"></i> Communications</a></li>
            <li><a href="admin-setting.html"><i class="fa fa-cogs"></i> Settings</a></li>
            <li><a href="admin-login.html"><i class="fa fa-sign-out"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="rd-container">
        <!-- Search Section -->
        <div class="rd-search-section">
            <div class="rd-search-main">
                <input type="text" placeholder="Search tenant or unit">
            </div>
            <button class="rd-btn-add" title="Create New">
                <i class="fa fa-plus"></i>
            </button>
        </div>
        
        <!-- Status Filter Tabs (Mobile) -->
        <div class="rd-status-tabs">
            <div class="rd-status-tab active">Active now</div>
        </div>
        
        <!-- Total Properties -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-building"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Total Properties</div>
                <div class="rd-stat-value"><?php echo $stats['properties']; ?></div>
                <div class="rd-stat-subtext">Active now</div>
            </div>
            <a href="admin-properties.php" class="rd-stat-view-btn">View</a>
        </div>
        
        <!-- Total Units / Occupancy -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-home"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Total Units</div>
                <div class="rd-stat-value"><?php echo $stats['properties']; ?></div>
                <div style="margin-top: 10px;">
                    <div class="rd-stat-label">Occupancy</div>
                    <div class="rd-stat-value" style="font-size: 20px; margin: 5px 0;"><?php echo $occupancy_percent; ?>%</div>
                    <div class="rd-progress-bar">
                        <div class="rd-progress-fill" style="width: <?php echo $occupancy_percent; ?>%"></div>
                    </div>
                    <div class="rd-stat-details" style="margin-top: 10px;">
                        <div class="rd-stat-details-row">
                            <span class="rd-stat-details-label">Units occupied:</span>
                            <span class="rd-stat-details-value"><?php echo $occupied_props; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <a href="admin-properties.php" class="rd-stat-view-btn">View</a>
        </div>
        
        <!-- Total Tenants -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-users"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Total Tenants</div>
                <div class="rd-stat-value"><?php echo $stats['tenants']; ?></div>
                <div class="rd-stat-subtext">Active profiles</div>
            </div>
            <a href="admin-user-all.php" class="rd-stat-view-btn">View</a>
        </div>
        
        <!-- Rent Collection -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-money"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Rent Collected</div>
                <div class="rd-stat-value">KES <?php echo number_format($stats['monthly_revenue'], 2); ?></div>
                <div class="rd-stat-subtext">This month</div>
                <div style="margin-top: 10px;">
                    <span class="rd-badge rd-badge-green">Paid: KES <?php echo number_format($stats['monthly_revenue'], 0); ?></span>
                    <span class="rd-badge rd-badge-yellow">Pending: <?php echo $stats['pending_invoices']; ?></span>
                    <span class="rd-badge rd-badge-red">Outstanding: KES <?php echo number_format($stats['outstanding_rent'], 0); ?></span>
                </div>
            </div>
            <a href="admin-rent-payments.php" class="rd-stat-view-btn">View</a>
        </div>
        
        <!-- Pending Invoices -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-file-text"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Pending Invoices</div>
                <div class="rd-stat-value"><?php echo $stats['pending_invoices']; ?></div>
                <div class="rd-stat-subtext">Unpaid invoices</div>
            </div>
            <a href="admin-rent-payments.php" class="rd-stat-view-btn">View</a>
        </div>
        
        <!-- Maintenance Tasks -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-wrench"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Maintenance Tasks</div>
                <div class="rd-stat-value"><?php echo $stats['maintenance_pending']; ?></div>
                <div class="rd-stat-subtext">Open tasks</div>
            </div>
            <a href="admin-maintenance.php" class="rd-stat-view-btn">View</a>
        </div>
        
        <!-- Scheduled Viewings -->
        <div class="rd-stat-card">
            <div class="rd-stat-icon"><i class="fa fa-calendar"></i></div>
            <div class="rd-stat-content">
                <div class="rd-stat-label">Scheduled Viewings</div>
                <div class="rd-stat-value"><?php echo $stats['viewings']; ?></div>
                <div class="rd-stat-subtext">Upcoming activities</div>
            </div>
            <a href="admin-event-all.html" class="rd-stat-view-btn">View</a>
        </div>
    </div>
    
    <!-- Floating Live Chat Button -->
    <button class="rd-live-chat" title="Live Chat">
        <i class="fa fa-comments"></i>
        <span>Live Chat</span>
    </button>
    
    <!-- Bottom Navigation -->
    <div class="rd-bottom-nav">
        <a href="admin-dashboard-modern.php" class="rd-nav-item active">
            <i class="fa fa-home"></i>
            <span>Home</span>
        </a>
        <a href="admin-properties.php" class="rd-nav-item">
            <i class="fa fa-building"></i>
            <span>Properties</span>
        </a>
        <a href="admin-user-all.php" class="rd-nav-item">
            <i class="fa fa-users"></i>
            <span>Tenants</span>
        </a>
        <a href="admin-rent-payments.php" class="rd-nav-item">
            <i class="fa fa-file-invoice-dollar"></i>
            <span>Invoices</span>
        </a>
        <a href="admin-reports.php" class="rd-nav-item">
            <i class="fa fa-bar-chart"></i>
            <span>Reports</span>
        </a>
        <button class="rd-nav-item" onclick="toggleSidebar()">
            <i class="fa fa-bars"></i>
            <span>Menu</span>
        </button>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.rd-menu-toggle');
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
