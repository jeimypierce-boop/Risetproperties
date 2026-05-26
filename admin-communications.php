<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

// Ensure communications table exists
$conn->query("CREATE TABLE IF NOT EXISTS communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    recipient_id INT,
    recipient_type ENUM('user','tenant') DEFAULT 'user',
    channel ENUM('email', 'sms', 'whatsapp', 'internal') DEFAULT 'internal',
    message_type VARCHAR(100),
    subject VARCHAR(255),
    message LONGTEXT,
    template_key VARCHAR(100),
    template_params JSON,
    status ENUM('pending', 'sent', 'delivered', 'failed', 'read') DEFAULT 'pending',
    delivery_status VARCHAR(100),
    delivery_timestamp DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sender_id (sender_id),
    INDEX idx_recipient_id (recipient_id),
    INDEX idx_recipient_type (recipient_type),
    INDEX idx_status (status),
    INDEX idx_channel (channel),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure communication_templates table exists
$conn->query("CREATE TABLE IF NOT EXISTS communication_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    channel ENUM('email', 'sms', 'whatsapp') NOT NULL,
    subject VARCHAR(255),
    body LONGTEXT NOT NULL,
    parameters JSON,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_channel (channel),
    INDEX idx_template_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Handle POST actions
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'send_message') {
        $channel = $_POST['channel'] ?? 'internal';
        $recipient_raw = $_POST['recipient_id'] ?? '';
        $recipient_name = $_POST['recipient_name'] ?? '';
        $recipient_type = $_POST['recipient_type'] ?? 'user';
        $template_key = $_POST['template_key'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        $template_params = $_POST['template_params'] ?? '';
        $sender_id = $_SESSION['user_id'];

        if (strpos($recipient_raw, ':') !== false) {
            list($raw_type, $raw_id) = explode(':', $recipient_raw, 2);
            $recipient_type = in_array($raw_type, ['user', 'tenant']) ? $raw_type : 'user';
            $recipient_id = intval($raw_id);
        } else {
            $recipient_id = intval($recipient_raw);
        }
        if (!in_array($recipient_type, ['user', 'tenant'])) {
            $recipient_type = 'user';
        }

        // Verify the recipient exists before inserting.
        if ($recipient_type === 'tenant') {
            $recipient_check = $conn->prepare("SELECT id FROM tenants WHERE id = ? LIMIT 1");
        } else {
            $recipient_check = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        }
        $recipient_check->bind_param('i', $recipient_id);
        $recipient_check->execute();
        $recipient_check->store_result();

        if ($recipient_check->num_rows === 0) {
            $error_msg = "Selected recipient is invalid. Please choose a valid user or tenant.";
        } else {
            $stmt = $conn->prepare("INSERT INTO communications (sender_id, recipient_id, recipient_type, channel, subject, message, template_key, template_params, status, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent', NOW())");
            $template_params_json = !empty($template_params) ? json_encode(json_decode($template_params)) : NULL;
            $stmt->bind_param('iissssss', $sender_id, $recipient_id, $recipient_type, $channel, $subject, $message, $template_key, $template_params_json);
            
            if ($stmt->execute()) {
                $success_msg = "Message sent successfully to " . htmlspecialchars($recipient_name) . " via " . strtoupper($channel);
            } else {
                $error_msg = "Error sending message: " . $stmt->error;
            }
            $stmt->close();
        }

        $recipient_check->close();
    } elseif ($_POST['action'] == 'resend_message') {
        $message_id = intval($_POST['message_id']);
        $stmt = $conn->prepare("UPDATE communications SET status = 'sent', delivery_timestamp = NOW() WHERE id = ?");
        $stmt->bind_param('i', $message_id);
        if ($stmt->execute()) {
            $success_msg = "Message resent successfully!";
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'mark_read') {
        $message_id = intval($_POST['message_id']);
        $stmt = $conn->prepare("UPDATE communications SET status = 'read' WHERE id = ?");
        $stmt->bind_param('i', $message_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Get filter from query parameter
$filter = $_GET['filter'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query based on filter
$whereClause = "1=1";
if ($filter !== 'all') {
    switch ($filter) {
        case 'failed':
            $whereClause = "status = 'failed'";
            break;
        case 'pending':
            $whereClause = "status = 'pending'";
            break;
        case 'rent-reminders':
            $whereClause = "message_type = 'rent_reminder' OR template_key LIKE '%rent%'";
            break;
        case 'triggers':
            $whereClause = "message_type = 'automated' OR template_key LIKE '%trigger%'";
            break;
        case 'needs-attention':
            $whereClause = "status IN ('failed', 'pending')";
            break;
        case 'lead-alerts':
            $whereClause = "message_type = 'lead_alert' OR template_key = 'sales_lead_alert'";
            break;
        case 'subscriptions':
            $whereClause = "message_type = 'subscription' OR template_key LIKE '%subscription%'";
            break;
    }
}

if (!empty($search_query)) {
    $search_term = $conn->real_escape_string($search_query);
    $whereClause .= " AND (subject LIKE '%$search_term%' OR message LIKE '%$search_term%' OR (recipient_type = 'user' AND recipient_id IN (
        SELECT id FROM users WHERE CONCAT(first_name, ' ', last_name) LIKE '%$search_term%' OR email LIKE '%$search_term%'
    )) OR (recipient_type = 'tenant' AND recipient_id IN (
        SELECT id FROM tenants WHERE CONCAT(first_name, ' ', last_name) LIKE '%$search_term%' OR email LIKE '%$search_term%'
    )))";
}

// Fetch all communications with pagination
$per_page = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$sql = "SELECT c.id, c.sender_id, c.recipient_id, c.recipient_type, c.channel, c.subject, c.message, c.status, c.delivery_status, 
               c.created_at, c.updated_at, 
               CONCAT(u.first_name, ' ', u.last_name) as sender_name,
               u.email as sender_email,
               CASE WHEN c.recipient_type = 'tenant' THEN (
                   SELECT CONCAT(first_name, ' ', last_name) FROM tenants WHERE id = c.recipient_id
               ) ELSE (
                   SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = c.recipient_id
               ) END as recipient_name,
               CASE WHEN c.recipient_type = 'tenant' THEN (
                   SELECT email FROM tenants WHERE id = c.recipient_id
               ) ELSE (
                   SELECT email FROM users WHERE id = c.recipient_id
               ) END as recipient_email
        FROM communications c
        LEFT JOIN users u ON c.sender_id = u.id
        WHERE $whereClause
        ORDER BY c.created_at DESC
        LIMIT $offset, $per_page";
$result = $conn->query($sql);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM communications WHERE $whereClause";
$count_result = $conn->query($count_sql)->fetch_assoc();
$total_count = $count_result['total'];
$total_pages = ceil($total_count / $per_page);

// Get stats
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM communications")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM communications WHERE status = 'pending'")->fetch_assoc()['count'];
$stats['sent'] = $conn->query("SELECT COUNT(*) as count FROM communications WHERE status = 'sent'")->fetch_assoc()['count'];
$stats['failed'] = $conn->query("SELECT COUNT(*) as count FROM communications WHERE status = 'failed'")->fetch_assoc()['count'];
$stats['read'] = $conn->query("SELECT COUNT(*) as count FROM communications WHERE status = 'read'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Communications - Riset Property Ltd</title>
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
        .comm-nav { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; border-bottom: 2px solid #e0e0e0; padding-bottom: 15px; }
        .comm-nav-item { padding: 8px 12px; border: none; background: none; color: #666; cursor: pointer; text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .comm-nav-item:hover, .comm-nav-item.active { color: #667eea; border-bottom-color: #667eea; }
        .filter-section { background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-btn { padding: 8px 12px; border: 1px solid #ddd; background: white; border-radius: 20px; cursor: pointer; transition: all 0.3s; font-size: 13px; }
        .filter-btn.active { background: #667eea; color: white; border-color: #667eea; }
        .filter-btn:hover { border-color: #667eea; }
        .stat-badge { display: inline-block; padding: 8px 12px; margin: 5px; border-radius: 20px; font-weight: bold; color: white; font-size: 13px; }
        .stat-badge.primary { background: #667eea; }
        .stat-badge.warning { background: #f39c12; }
        .stat-badge.danger { background: #e74c3c; }
        .stat-badge.success { background: #27ae60; }
        .stat-badge.info { background: #3498db; }
        .send-form-section { background: white; padding: 20px; border-radius: 6px; border: 1px solid #e0e0e0; margin-bottom: 30px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-row.full { grid-template-columns: 1fr; }
        .comm-table { width: 100%; border-collapse: collapse; }
        .comm-table th { background: #f5f5f5; padding: 12px; text-align: left; font-weight: 600; border-bottom: 2px solid #e0e0e0; font-size: 13px; }
        .comm-table td { padding: 12px; border-bottom: 1px solid #e0e0e0; }
        .comm-table tr:hover { background: #f9f9f9; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-delivered { background: #d1ecf1; color: #0c5460; }
        .status-read { background: #cfe2ff; color: #084298; }
        .channel-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; margin-right: 5px; }
        .channel-email { background: #e3f2fd; color: #1976d2; }
        .channel-sms { background: #e8f5e9; color: #388e3c; }
        .channel-whatsapp { background: #e0f2f1; color: #00796b; }
        .channel-internal { background: #f3e5f5; color: #6a1b9a; }
        .action-btn { padding: 4px 8px; margin: 2px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .action-btn-resend { background: #3498db; color: white; }
        .action-btn-resend:hover { background: #2980b9; }
        .action-btn-view { background: #667eea; color: white; }
        .action-btn-view:hover { background: #5568d3; }
        .message-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .search-box { padding: 10px 15px; border: 1px solid #ddd; border-radius: 4px; flex-grow: 1; max-width: 300px; }
        .pagination { margin-top: 20px; display: flex; gap: 5px; justify-content: center; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; }
        .pagination a:hover { background: #667eea; color: white; border-color: #667eea; }
        .pagination .active { background: #667eea; color: white; border-color: #667eea; }
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
                <form class="app-search" method="GET">
                    <input type="text" name="search" placeholder="Search user or email" class="form-control" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit"><i class="fa fa-search"></i></button>
                </form>
            </div>
            <div class="col-md-2 tab-hide">
                <div class="top-not-cen">
                    <a class='waves-effect btn-noti' href="#"><i class="fa fa-bell-o" aria-hidden="true"></i><span><?php echo $stats['pending']; ?></span></a>
                </div>
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
                        <li><a href="admin-communications.php" class="menu-active"><i class="fa fa-envelope" aria-hidden="true"></i> Communications</a></li>
                        <li><a href="admin-maintenance.php"><i class="fa fa-wrench" aria-hidden="true"></i> Maintenance</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Communications</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3" style="padding: 20px;">
                    <h1 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-envelope"></i> Communications
                    </h1>

                    <!-- Navigation Tabs -->
                    <div class="comm-nav">
                        <a href="admin-communications.php" class="comm-nav-item <?php echo !isset($_GET['section']) || $_GET['section'] == 'inbox' ? 'active' : ''; ?>">
                            <i class="fa fa-inbox"></i> Internal Inbox
                        </a>
                        <a href="admin-communications.php?section=announcements" class="comm-nav-item <?php echo isset($_GET['section']) && $_GET['section'] == 'announcements' ? 'active' : ''; ?>">
                            <i class="fa fa-bullhorn"></i> Announcements
                        </a>
                        <a href="admin-communications.php?section=sms-templates" class="comm-nav-item <?php echo isset($_GET['section']) && $_GET['section'] == 'sms-templates' ? 'active' : ''; ?>">
                            <i class="fa fa-mobile"></i> SMS Templates
                        </a>
                        <a href="admin-communications.php?section=email-templates" class="comm-nav-item <?php echo isset($_GET['section']) && $_GET['section'] == 'email-templates' ? 'active' : ''; ?>">
                            <i class="fa fa-file-text"></i> Email Templates
                        </a>
                        <a href="admin-communications.php?section=whatsapp" class="comm-nav-item <?php echo isset($_GET['section']) && $_GET['section'] == 'whatsapp' ? 'active' : ''; ?>">
                            <i class="fa fa-comments"></i> WhatsApp Templates
                        </a>
                    </div>

                    <!-- Stats -->
                    <div style="margin: 20px 0;">
                        <span class="stat-badge primary"><i class="fa fa-envelope"></i> Total: <?php echo $stats['total']; ?></span>
                        <span class="stat-badge warning"><i class="fa fa-clock"></i> Pending: <?php echo $stats['pending']; ?></span>
                        <span class="stat-badge danger"><i class="fa fa-times"></i> Failed: <?php echo $stats['failed']; ?></span>
                        <span class="stat-badge info"><i class="fa fa-check"></i> Sent: <?php echo $stats['sent']; ?></span>
                        <span class="stat-badge success"><i class="fa fa-eye"></i> Read: <?php echo $stats['read']; ?></span>
                    </div>

                    <?php if (!empty($success_msg)): ?>
                        <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $success_msg; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <!-- Send Message Form -->
                    <div class="send-form-section">
                        <h3 style="margin-top: 0;">
                            <i class="fa fa-paper-plane"></i> Send Message
                        </h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="send_message">
                            
                            <div class="form-row">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Channel</label>
                                    <select name="channel" class="form-control" id="channelSelect">
                                        <option value="email">Email</option>
                                        <option value="sms" disabled>SMS (disabled)</option>
                                        <option value="whatsapp" disabled>WhatsApp (disabled)</option>
                                        <option value="internal">Internal Message</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Recipient</label>
                                    <input type="text" id="recipientSearch" class="form-control" placeholder="Search user or tenant..." autocomplete="off">
                                    <input type="hidden" name="recipient_id" id="recipientId" required>
                                    <input type="hidden" name="recipient_name" id="recipientName">
                                    <input type="hidden" name="recipient_type" id="recipientType" value="user">
                                    <div id="recipientList" style="position: absolute; background: white; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; width: 280px; display: none; z-index: 1000;"></div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Template key/name</label>
                                    <input type="text" name="template_key" class="form-control" placeholder="custom_message" value="custom_message">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Subject</label>
                                    <input type="text" name="subject" class="form-control" placeholder="Message subject">
                                </div>
                            </div>

                            <div class="form-row full">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Template parameters (optional)</label>
                                    <input type="text" class="form-control" placeholder='{"name":"John","order":"#12345"}' id="templateParams">
                                    <small style="color: #999;">JSON format: {"key":"value"}</small>
                                </div>
                            </div>

                            <div class="form-row full">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Message</label>
                                    <textarea name="message" class="form-control" rows="5" placeholder="Enter your message..." required></textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
                                <i class="fa fa-send"></i> Send
                            </button>
                        </form>
                    </div>

                    <!-- Filter Section -->
                    <div class="filter-section">
                        <strong style="display: flex; align-items: center; gap: 5px;">
                            <i class="fa fa-filter"></i> Recent Logs:
                        </strong>
                        <a href="admin-communications.php" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All Logs</a>
                        <a href="admin-communications.php?filter=rent-reminders" class="filter-btn <?php echo $filter === 'rent-reminders' ? 'active' : ''; ?>">Rent Reminders</a>
                        <a href="admin-communications.php?filter=triggers" class="filter-btn <?php echo $filter === 'triggers' ? 'active' : ''; ?>">Automated Triggers</a>
                        <a href="admin-communications.php?filter=needs-attention" class="filter-btn <?php echo $filter === 'needs-attention' ? 'active' : ''; ?>">Needs Attention</a>
                        <a href="admin-communications.php?filter=failed" class="filter-btn <?php echo $filter === 'failed' ? 'active' : ''; ?>">Failed</a>
                        <a href="admin-communications.php?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="admin-communications.php?filter=lead-alerts" class="filter-btn <?php echo $filter === 'lead-alerts' ? 'active' : ''; ?>">Lead Alerts</a>
                        <a href="admin-communications.php?filter=subscriptions" class="filter-btn <?php echo $filter === 'subscriptions' ? 'active' : ''; ?>">Subscriptions</a>
                    </div>

                    <!-- Communications Table -->
                    <div style="overflow-x: auto;">
                        <table class="comm-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Channel</th>
                                    <th>Type</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Delivery</th>
                                    <th>Message</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 0;
                                while($row = $result->fetch_assoc()):
                                    $count++;
                                    $message_id = $row['id'];
                                    $channel_upper = strtoupper($row['channel']);
                                    $message_preview = substr(strip_tags($row['message']), 0, 80) . '...';
                                ?>
                                <tr>
                                    <td><?php echo $message_id; ?></td>
                                    <td>
                                        <span class="channel-badge channel-<?php echo strtolower($row['channel']); ?>">
                                            <?php echo $channel_upper; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['subject'] ?? 'General'); ?></td>
                                    <td><?php echo htmlspecialchars($row['recipient_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo ucfirst($row['delivery_status'] ?? $row['status']); ?><br>
                                            <small><?php echo date('Y-m-d H:i', strtotime($row['updated_at'])); ?></small>
                                        </span>
                                    </td>
                                    <td><span class="message-preview"><?php echo htmlspecialchars($message_preview); ?></span></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'failed'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="resend_message">
                                                <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
                                                <button type="submit" class="action-btn action-btn-resend">Resend</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="#" class="action-btn action-btn-view" onclick="viewMessage(<?php echo $message_id; ?>)">View</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if ($count == 0): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding: 40px; color: #999;">
                                        <i class="fa fa-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                                        <p style="margin-top: 10px;">No communications found</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php 
                        $query_params = (strpos($_SERVER['QUERY_STRING'], 'page=') === false ? $_SERVER['QUERY_STRING'] . '&' : preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING'] . '&'));
                        
                        if ($page > 1): ?>
                            <a href="?<?php echo $query_params; ?>page=1">First</a>
                            <a href="?<?php echo $query_params; ?>page=<?php echo $page - 1; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo $query_params; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo $query_params; ?>page=<?php echo $page + 1; ?>">Next</a>
                            <a href="?<?php echo $query_params; ?>page=<?php echo $total_pages; ?>">Last</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Message Detail Modal -->
    <div id="messageModal" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000;">
        <div style="background: white; margin: 50px auto; padding: 30px; width: 90%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalTitle" style="margin: 0;">Message Details</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script>
        // Recipient search functionality
        document.getElementById('recipientSearch')?.addEventListener('input', function() {
            const query = this.value.trim();
            if (query.length < 2) {
                document.getElementById('recipientList').style.display = 'none';
                return;
            }

            fetch('api/search_recipients.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    const listDiv = document.getElementById('recipientList');
                    listDiv.innerHTML = '';
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.style.padding = '8px 12px';
                        div.style.cursor = 'pointer';
                        div.style.borderBottom = '1px solid #eee';
                        div.textContent = item.name + ' (' + item.email + ') [' + item.type + ']';
                        div.onclick = () => {
                            document.getElementById('recipientId').value = item.type + ':' + item.id;
                            document.getElementById('recipientType').value = item.type;
                            document.getElementById('recipientName').value = item.name;
                            document.getElementById('recipientSearch').value = item.name;
                            listDiv.style.display = 'none';
                        };
                        listDiv.appendChild(div);
                    });
                    listDiv.style.display = data.length > 0 ? 'block' : 'none';
                });
        });

        function viewMessage(msgId) {
            fetch('api/get_message.php?id=' + msgId)
                .then(response => response.json())
                .then(data => {
                    const modal = document.getElementById('messageModal');
                    const content = document.getElementById('modalContent');
                    content.innerHTML = `
                        <p><strong>From:</strong> ${data.sender_name} (${data.sender_email})</p>
                        <p><strong>To:</strong> ${data.recipient_name}</p>
                        <p><strong>Channel:</strong> <span class="channel-badge channel-${data.channel}">${data.channel.toUpperCase()}</span></p>
                        <p><strong>Status:</strong> <span class="status-badge status-${data.status}">${data.status.toUpperCase()}</span></p>
                        <p><strong>Subject:</strong> ${data.subject}</p>
                        <hr>
                        <p><strong>Message:</strong></p>
                        <p>${data.message.replace(/\n/g, '<br>')}</p>
                        <p><strong>Sent:</strong> ${data.created_at}</p>
                    `;
                    modal.style.display = 'block';
                });
            return false;
        }

        function closeModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('messageModal')?.addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Send Message</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_message">

                        <div class="form-group">
                            <label>Send To</label>
                            <select name="recipient_id" class="form-control" required>
                                <option value="">Select Recipient</option>
                                <?php 
                                $recipients = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name, ' - ', email) as info, 'user' as type FROM users WHERE id != " . intval($_SESSION['user_id']) . " UNION ALL SELECT id, CONCAT(first_name, ' ', last_name, ' - ', email) as info, 'tenant' as type FROM tenants ORDER BY info");
                                while($r = $recipients->fetch_assoc()): ?>
                                <option value="<?php echo $r['type'] . ':' . $r['id']; ?>"><?php echo htmlspecialchars(ucfirst($r['type']) . ': ' . $r['info']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Message Type</label>
                            <select name="message_type" class="form-control" required>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="notification">In-App Notification</option>
                                <option value="chat">Chat Message</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="Message subject" required>
                        </div>

                        <div class="form-group">
                            <label>Message</label>
                            <textarea name="message" class="form-control" rows="6" placeholder="Enter your message..." required></textarea>
                        </div>

                        <div style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                            <p style="margin: 0; font-size: 12px; color: #999;">
                                <i class="fa fa-info-circle"></i> 
                                <strong>Note:</strong> SMS messages are limited to 160 characters. Email and notifications support full text.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-send"></i> Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>`r`n    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
</body>
</html>
<?php $conn->close(); ?>




