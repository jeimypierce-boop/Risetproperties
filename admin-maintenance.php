<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();

// Ensure maintenance_communications table exists
$conn->query("CREATE TABLE IF NOT EXISTS maintenance_communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    maintenance_id INT NOT NULL,
    sender_id INT,
    sender_type ENUM('user', 'tenant') DEFAULT 'user',
    message LONGTEXT NOT NULL,
    attachment VARCHAR(255),
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_maintenance_id (maintenance_id),
    INDEX idx_sender_id (sender_id),
    FOREIGN KEY (maintenance_id) REFERENCES maintenance_tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Also add comments column to maintenance_tasks if it doesn't exist
$conn->query("ALTER TABLE maintenance_tasks ADD COLUMN IF NOT EXISTS comments TEXT");

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create_maintenance') {
        $property_id = intval($_POST['property_id']);
        $tenant_id = intval($_POST['tenant_id']);
        $title = $_POST['title'];
        $description = $_POST['description'];
        $priority = $_POST['priority'] ?? 'Medium';
        $category = $_POST['category'];
        $status = 'Pending';

        if ($landlord_id) {
            $check = $conn->prepare("SELECT landlord_id FROM properties WHERE id = ? LIMIT 1");
            $check->bind_param('i', $property_id);
            $check->execute();
            $checkResult = $check->get_result();
            $propertyOwner = $checkResult->fetch_assoc();
            $check->close();

            if (!$propertyOwner || intval($propertyOwner['landlord_id']) !== $landlord_id) {
                $error_msg = "Invalid property selected or you do not own this property.";
            }
        }

        if (empty($error_msg)) {
            $stmt = $conn->prepare("INSERT INTO maintenance_tasks (property_id, tenant_id, title, description, priority, category, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param('iiissssi', $property_id, $tenant_id, $title, $description, $priority, $category, $status, $user_id);
            
            if ($stmt->execute()) {
                $maintenance_id = $stmt->insert_id;
                $success_msg = "Maintenance request created successfully!";
                
                // Send notification communication
                $notif_msg = "New maintenance request created: " . $title;
                $notif_stmt = $conn->prepare("INSERT INTO maintenance_communications (maintenance_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
                $notif_stmt->bind_param('iis', $maintenance_id, $user_id, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
            } else {
                $error_msg = "Error creating request: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] == 'update_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $vendor_id = intval($_POST['vendor_id'] ?? 0);
        $assigned_date = $_POST['assigned_date'] ?? NULL;
        $comments = $_POST['comments'] ?? '';

        $stmt = $conn->prepare("UPDATE maintenance_tasks SET status = ?, vendor_id = ?, assigned_date = ?, comments = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sissi', $status, $vendor_id, $assigned_date, $comments, $id);
        
        if ($stmt->execute()) {
            $success_msg = "Status updated successfully!";
            
            // Add communication entry
            $user_id = $_SESSION['user_id'];
            $comm_msg = "Status changed to: " . $status;
            if (!empty($comments)) {
                $comm_msg .= "\n\nNotes: " . $comments;
            }
            $comm_stmt = $conn->prepare("INSERT INTO maintenance_communications (maintenance_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
            $comm_stmt->bind_param('iis', $id, $user_id, $comm_msg);
            $comm_stmt->execute();
            $comm_stmt->close();
        }
        $stmt->close();
    } elseif ($_POST['action'] == 'add_comment') {
        $maintenance_id = intval($_POST['maintenance_id']);
        $message = $_POST['message'];
        $user_id = $_SESSION['user_id'];
        
        $stmt = $conn->prepare("INSERT INTO maintenance_communications (maintenance_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
        $stmt->bind_param('iis', $maintenance_id, $user_id, $message);
        
        if ($stmt->execute()) {
            $success_msg = "Comment added successfully!";
        } else {
            $error_msg = "Error adding comment: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch maintenance tasks
$sql = "SELECT m.*, p.title as property_title, t.first_name, t.last_name, t.email as tenant_email FROM maintenance_tasks m
        LEFT JOIN properties p ON m.property_id = p.id
        LEFT JOIN tenants t ON m.tenant_id = t.id";
if ($landlord_id) {
    $sql .= " WHERE p.landlord_id = " . intval($landlord_id);
}
$sql .= " ORDER BY m.created_at DESC";
$result = $conn->query($sql);

// Summary stats
$whereClause = '';
if ($landlord_id) {
    $whereClause = " JOIN properties p ON maintenance_tasks.property_id = p.id WHERE p.landlord_id = " . intval($landlord_id);
}
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM maintenance_tasks" . $whereClause)->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM maintenance_tasks" . $whereClause . " AND status = 'Pending'")->fetch_assoc()['count'];
$stats['in_progress'] = $conn->query("SELECT COUNT(*) as count FROM maintenance_tasks" . $whereClause . " AND status = 'In Progress'")->fetch_assoc()['count'];
$stats['completed'] = $conn->query("SELECT COUNT(*) as count FROM maintenance_tasks" . $whereClause . " AND status = 'Completed'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Maintenance Management - Riset Property Ltd</title>
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
        .stat-badge { display: inline-block; padding: 10px 15px; margin: 5px; border-radius: 20px; font-weight: bold; color: white; }
        .stat-badge.primary { background: #667eea; }
        .stat-badge.warning { background: #f39c12; }
        .stat-badge.info { background: #3498db; }
        .stat-badge.success { background: #43e97b; }
        .priority-high { color: #e74c3c; font-weight: bold; }
        .priority-medium { color: #f39c12; font-weight: bold; }
        .priority-low { color: #27ae60; font-weight: bold; }
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
                        <li><a href="admin-maintenance.php" class="menu-active"><i class="fa fa-wrench" aria-hidden="true"></i> Maintenance</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Maintenance Management</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3" style="padding: 20px;">
                    <h2>Maintenance Requests</h2>

                    <!-- Status Badges -->
                    <div style="margin-bottom: 20px;">
                        <span class="stat-badge primary"><i class="fa fa-list"></i> Total: <?php echo $stats['total']; ?></span>
                        <span class="stat-badge warning"><i class="fa fa-clock-o"></i> Pending: <?php echo $stats['pending']; ?></span>
                        <span class="stat-badge info"><i class="fa fa-spinner"></i> In Progress: <?php echo $stats['in_progress']; ?></span>
                        <span class="stat-badge success"><i class="fa fa-check"></i> Completed: <?php echo $stats['completed']; ?></span>
                    </div>

                    <div class="box-inn-sp">
                        <div class="inn-title">
                            <button class="btn btn-primary" data-toggle="modal" data-target="#newMaintenanceModal"><i class="fa fa-plus"></i> New Request</button>
                        </div>

                        <?php if (isset($success_msg) && !empty($success_msg)): ?>
                            <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $success_msg; ?></div>
                        <?php endif; ?>
                        <?php if (isset($error_msg) && !empty($error_msg)): ?>
                            <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
                        <?php endif; ?>

                        <div class="tab-inn">
                            <div class="table-responsive table-desi">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Property</th>
                                            <th>Tenant</th>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $maintenance_count = 0;
                                        $temp_result = $result; // Store for iteration
                                        while($row = $temp_result->fetch_assoc()): 
                                            $maintenance_count++;
                                            $priority_class = strtolower($row['priority']) == 'high' ? 'priority-high' : (strtolower($row['priority']) == 'medium' ? 'priority-medium' : 'priority-low');
                                            $status_class = strtolower($row['status']) == 'completed' ? 'label-success' : (strtolower($row['status']) == 'in progress' ? 'label-info' : 'label-warning');
                                        ?>
                                        <tr>
                                            <td>#<?php echo $row['id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['property_title'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
                                            <td><span class="label label-default"><?php echo ucfirst($row['category'] ?? 'General'); ?></span></td>
                                            <td><span class="<?php echo $priority_class; ?>"><?php echo strtoupper($row['priority'] ?? 'MEDIUM'); ?></span></td>
                                            <td><span class="label <?php echo $status_class; ?>"><?php echo ucfirst($row['status'] ?? 'Pending'); ?></span></td>
                                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                            <td style="min-width: 0;">
                                                <button class="btn btn-sm btn-info" onclick="openMaintenanceDetails(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['title']); ?>')"><i class="fa fa-eye"></i> View</button>
                                                <button class="btn btn-sm btn-warning" onclick="openCommunication(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')"><i class="fa fa-comments"></i> Messages</button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if ($maintenance_count == 0): ?>
                                        <tr>
                                            <td colspan="9" style="text-align:center; padding: 40px; color: #999;">
                                                <i class="fa fa-inbox" style="font-size: 48px; opacity: 0.3;"></i>
                                                <p style="margin-top: 10px;">No maintenance requests found</p>
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
    </div>

    <!-- New Maintenance Modal -->
    <div class="modal fade" id="newMaintenanceModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Maintenance Request</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_maintenance">
                        
                        <div class="form-group">
                            <label>Property</label>
                            <select name="property_id" class="form-control" required>
                                <option value="">Select Property</option>
                                <?php 
                                $propertyQuery = "SELECT id, title as name FROM properties";
                                if ($landlord_id) {
                                    $propertyQuery .= " WHERE landlord_id = " . intval($landlord_id);
                                }
                                $props = $conn->query($propertyQuery);
                                while($p = $props->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Tenant</label>
                            <select name="tenant_id" class="form-control" required>
                                <option value="">Select Tenant</option>
                                <?php 
                                $tenantQuery = "SELECT t.id, CONCAT(t.first_name, ' ', t.last_name) as name, GROUP_CONCAT(DISTINCT l.property_id) as property_ids 
                                    FROM tenants t 
                                    JOIN leases l ON t.id = l.tenant_id 
                                    JOIN properties p ON l.property_id = p.id 
                                    WHERE 1=1";
                                if ($landlord_id) {
                                    $tenantQuery .= " AND p.landlord_id = " . intval($landlord_id);
                                }
                                $tenantQuery .= " GROUP BY t.id ORDER BY t.first_name";
                                $tenants = $conn->query($tenantQuery);
                                while($t = $tenants->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>" data-property-ids="<?php echo htmlspecialchars($t['property_ids']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" placeholder="Brief description" required>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Detailed description" required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control" required>
                                <option value="plumbing">Plumbing</option>
                                <option value="electrical">Electrical</option>
                                <option value="painting">Painting</option>
                                <option value="carpentry">Carpentry</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="hvac">HVAC</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Communication Modal -->
    <div id="communicationModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fa fa-comments"></i> Maintenance Communication - <span id="modalTenantName"></span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="communicationThread" style="max-height: 300px; overflow-y: auto; border: 1px solid #e0e0e0; padding: 15px; margin-bottom: 15px; border-radius: 4px; background: #f9f9f9;">
                        <p style="text-align: center; color: #999;">Loading messages...</p>
                    </div>
                    
                    <form method="POST" id="addCommentForm">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="maintenance_id" id="maintenanceIdHidden">
                        
                        <div class="form-group">
                            <label>Add Comment</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Type your message..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-send"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Maintenance Details</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="detailsContent">Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script src="js/admin-delete.js"></script>
    <script>
        function openCommunication(maintenanceId, tenantName) {
            document.getElementById('maintenanceIdHidden').value = maintenanceId;
            document.getElementById('modalTenantName').textContent = tenantName;
            
            // Load communications
            fetch('api/get_maintenance_communications.php?id=' + maintenanceId)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    if (data.length === 0) {
                        html = '<p style="text-align: center; color: #999;">No messages yet</p>';
                    } else {
                        data.forEach(comm => {
                            const senderLabel = comm.sender_type === 'tenant' ? 'Tenant' : 'Admin';
                            const bgColor = comm.sender_type === 'tenant' ? '#e3f2fd' : '#f3e5f5';
                            html += `
                                <div style="background: ${bgColor}; padding: 12px; margin-bottom: 10px; border-radius: 4px; border-left: 3px solid ${comm.sender_type === 'tenant' ? '#1976d2' : '#6a1b9a'};">
                                    <strong>${senderLabel}:</strong> <small style="color: #999;">${comm.created_at}</small>
                                    <p style="margin: 8px 0 0 0; white-space: pre-wrap;">${comm.message}</p>
                                </div>
                            `;
                        });
                    }
                    document.getElementById('communicationThread').innerHTML = html;
                    // Scroll to bottom
                    const thread = document.getElementById('communicationThread');
                    thread.scrollTop = thread.scrollHeight;
                })
                .catch(err => {
                    document.getElementById('communicationThread').innerHTML = '<p style="color: red;">Error loading messages</p>';
                });
            
            $('#communicationModal').modal('show');
        }

        function openMaintenanceDetails(maintenanceId, title) {
            fetch('api/get_maintenance_details.php?id=' + maintenanceId)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Title:</strong> ${data.title}</p>
                                <p><strong>Property:</strong> ${data.property_title}</p>
                                <p><strong>Tenant:</strong> ${data.first_name} ${data.last_name}</p>
                                <p><strong>Category:</strong> <span class="label label-default">${data.category}</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Priority:</strong> <span class="label label-${data.priority === 'High' ? 'danger' : (data.priority === 'Medium' ? 'warning' : 'success')}">${data.priority}</span></p>
                                <p><strong>Status:</strong> <span class="label label-${data.status === 'Completed' ? 'success' : (data.status === 'In Progress' ? 'info' : 'warning')}">${data.status}</span></p>
                                <p><strong>Created:</strong> ${data.created_at}</p>
                            </div>
                        </div>
                        <hr>
                        <h5>Description</h5>
                        <p>${data.description}</p>
                        <hr>
                        <h5>Update Status</h5>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="${maintenanceId}">
                            <div class="form-group">
                                <label>New Status</label>
                                <select name="status" class="form-control">
                                    <option value="Pending" ${data.status === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="In Progress" ${data.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="Completed" ${data.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Comments</label>
                                <textarea name="comments" class="form-control" rows="3" placeholder="Add any comments or updates"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Status</button>
                        </form>
                    `;
                    document.getElementById('detailsContent').innerHTML = html;
                    $('#detailsModal').modal('show');
                })
                .catch(err => {
                    document.getElementById('detailsContent').innerHTML = '<p style="color: red;">Error loading details</p>';
                });
        }

        function refreshMaintenanceTenantOptions() {
            var propertySelect = document.querySelector('select[name="property_id"]');
            var tenantSelect = document.querySelector('select[name="tenant_id"]');
            if (!propertySelect || !tenantSelect) {
                return;
            }

            var originalTenantOptions = Array.from(tenantSelect.options).map(function (opt) {
                return {
                    value: opt.value,
                    text: opt.textContent,
                    propertyIds: opt.dataset.propertyIds || '',
                    defaultSelected: opt.defaultSelected
                };
            });

            function applyTenantFilter() {
                var selectedProperty = propertySelect.value;
                tenantSelect.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select Tenant';
                placeholder.disabled = true;
                placeholder.selected = true;
                tenantSelect.appendChild(placeholder);

                originalTenantOptions.forEach(function (optData) {
                    if (!optData.value) {
                        return;
                    }
                    if (!selectedProperty) {
                        var option = document.createElement('option');
                        option.value = optData.value;
                        option.textContent = optData.text;
                        option.dataset.propertyIds = optData.propertyIds;
                        if (optData.defaultSelected) {
                            option.selected = true;
                        }
                        tenantSelect.appendChild(option);
                        return;
                    }
                    var properties = optData.propertyIds.split(',');
                    if (properties.indexOf(selectedProperty) !== -1) {
                        var option = document.createElement('option');
                        option.value = optData.value;
                        option.textContent = optData.text;
                        option.dataset.propertyIds = optData.propertyIds;
                        if (optData.defaultSelected) {
                            option.selected = true;
                        }
                        tenantSelect.appendChild(option);
                    }
                });
            }

            propertySelect.addEventListener('change', applyTenantFilter);
            applyTenantFilter();
        }

        refreshMaintenanceTenantOptions();

        // Handle comment form submission
        document.getElementById('addCommentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('admin-maintenance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Reload communications
                const maintenanceId = formData.get('maintenance_id');
                openCommunication(maintenanceId, document.getElementById('modalTenantName').textContent);
                this.reset();
            })
            .catch(err => console.error('Error:', err));
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>