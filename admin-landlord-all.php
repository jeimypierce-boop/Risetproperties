<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

require_admin();

// Ensure only true admins can view landlords (not landlords themselves)
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: admin-dashboard-modern.php');
    exit;
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $landlord_id = intval($_POST['landlord_id']);
    $new_status = $_POST['status'] === 'Active' ? 'Active' : 'Inactive';
    
    $update_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'landlord'");
    if ($update_stmt) {
        $update_stmt->bind_param('si', $new_status, $landlord_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Return success response for AJAX
        if (!empty($_POST['ajax'])) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            exit;
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $landlord_id = intval($_POST['landlord_id']);
    
    // Check if landlord has any properties first
    $check_properties = $conn->prepare("SELECT COUNT(*) as count FROM properties WHERE landlord_id = ? AND deleted_at IS NULL");
    if ($check_properties) {
        $check_properties->bind_param('i', $landlord_id);
        $check_properties->execute();
        $result = $check_properties->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            if (!empty($_POST['ajax'])) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete landlord with active properties. Please archive or delete properties first.']);
                exit;
            }
        } else {
            // Safe to delete - landlord has no properties
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'landlord'");
            if ($delete_stmt) {
                $delete_stmt->bind_param('i', $landlord_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                if (!empty($_POST['ajax'])) {
                    echo json_encode(['success' => true, 'message' => 'Landlord account deleted successfully']);
                    exit;
                }
            }
        }
        $check_properties->close();
    }
}

// Fetch all landlords
$landlords_query = "SELECT id, username, email, first_name, last_name, phone, status, created_at, 
                    (SELECT COUNT(*) FROM properties WHERE landlord_id = users.id AND deleted_at IS NULL) as property_count,
                    (SELECT COALESCE(SUM(monthly_rent), 0) FROM leases l 
                     JOIN properties p ON l.property_id = p.id 
                     WHERE p.landlord_id = users.id AND l.status = 'Active') as monthly_revenue
                    FROM users WHERE role = 'landlord' ORDER BY created_at DESC";

$landlords_result = $conn->query($landlords_query);
if ($landlords_result === false) {
    die('Database query error: ' . $conn->error);
}

$user_info = get_user_info();
$user_initials = get_user_initials();
$user_role_label = get_user_role_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Riset Property Ltd - Manage Landlords</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Riset Property Ltd admin — manage landlord accounts.">
    <meta name="keyword" content="Riset Property Ltd, landlords, management, admin">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/style-mob.css" rel="stylesheet" />
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        .status-inactive {
            background-color: #f44336;
            color: white;
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
                    <a class='waves-effect btn-noti' href="admin-all-enquiry.html" title="All Enquiries messages"><i class="fa fa-commenting-o" aria-hidden="true"></i><span>5</span></a>
                    <a class='waves-effect btn-noti' href="admin-Property-enquiry.html" title="property enquiry messages"><i class="fa fa-envelope-o" aria-hidden="true"></i><span>5</span></a>
                    <a class='waves-effect btn-noti' href="admin-admission-enquiry.html" title="Tenant Enquiry"><i class="fa fa-tag" aria-hidden="true"></i><span>5</span></a>
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
                            <h5><?php echo htmlspecialchars($user_info['name'] ?: 'My Account'); ?> <span><?php echo htmlspecialchars($user_role_label); ?></span></h5>
                            <?php if (!empty($user_info['email'])): ?>
                                <p><?php echo htmlspecialchars($user_info['email']); ?></p>
                            <?php endif; ?>
                        </li>
                        <li></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php" class="menu-active"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-setting.html"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a></li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-building" aria-hidden="true"></i> Landlords</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-landlord-all.php">All Landlords</a></li>
                                    <li><a href="admin-landlord-add.php">Add New Landlord</a></li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-building" aria-hidden="true"></i> Properties</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-properties.php">All Properties</a></li>
                                    <li><a href="admin-add-property.php">Add New Property</a></li>
                                    <li><a href="admin-trash-properties.html">Trash Properties</a></li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-users" aria-hidden="true"></i> Tenants</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-user-all.php">All Tenants</a></li>
                                    <li><a href="admin-user-add.php">Add New Tenant</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Landlords</a></li>
                        <li class="page-back"><a href="admin-dashboard-modern.php"><i class="fa fa-backward" aria-hidden="true"></i> Back</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp">
                                <div class="inn-title">
                                    <h4>Landlord Accounts</h4>
                                    <p>Manage all independent landlord accounts and their properties.</p>
                                    <a href="admin-landlord-add.php" class="btn btn-primary" style="float:right; margin-top:-20px;"><i class="fa fa-plus"></i> Add New Landlord</a>
                                </div>

                                <div class="tab-inn">
                                    <div class="table-responsive table-desi">
                                        <?php if ($landlords_result->num_rows > 0): ?>
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Username</th>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Phone</th>
                                                        <th>Properties</th>
                                                        <th>Monthly Revenue</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($landlord = $landlords_result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($landlord['username']); ?></strong></td>
                                                            <td><?php echo htmlspecialchars($landlord['first_name'] . ' ' . $landlord['last_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($landlord['email']); ?></td>
                                                            <td><?php echo htmlspecialchars($landlord['phone']); ?></td>
                                                            <td><?php echo intval($landlord['property_count']); ?></td>
                                                            <td>KES <?php echo number_format($landlord['monthly_revenue'], 2); ?></td>
                                                            <td>
                                                                <span class="status-badge status-<?php echo strtolower($landlord['status']); ?>">
                                                                    <?php echo htmlspecialchars($landlord['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($landlord['created_at'])); ?></td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <?php if ($landlord['status'] === 'Active'): ?>
                                                                        <button type="button" class="btn btn-xs btn-warning" onclick="changeStatus(<?php echo $landlord['id']; ?>, 'Inactive')" title="Deactivate">
                                                                            <i class="fa fa-lock"></i>
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-xs btn-success" onclick="changeStatus(<?php echo $landlord['id']; ?>, 'Active')" title="Activate">
                                                                            <i class="fa fa-unlock"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                    <button type="button" class="btn btn-xs btn-danger" onclick="deleteLandlord(<?php echo $landlord['id']; ?>, '<?php echo htmlspecialchars($landlord['username']); ?>')" title="Delete">
                                                                        <i class="fa fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <strong>No landlords found.</strong> <a href="admin-landlord-add.php">Create the first landlord account</a>.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.collapsible').collapsible();
        });

        function changeStatus(landlordId, newStatus) {
            if (confirm('Are you sure you want to ' + (newStatus === 'Active' ? 'activate' : 'deactivate') + ' this landlord account?')) {
                $.ajax({
                    type: 'POST',
                    url: 'admin-landlord-all.php',
                    data: {
                        action: 'change_status',
                        landlord_id: landlordId,
                        status: newStatus,
                        ajax: true
                    },
                    success: function(response) {
                        var result = JSON.parse(response);
                        if (result.success) {
                            alert(result.message);
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating status.');
                    }
                });
            }
        }

        function deleteLandlord(landlordId, username) {
            if (confirm('Are you sure you want to delete the landlord account "' + username + '"?\n\nNote: The landlord must not have any active properties.')) {
                $.ajax({
                    type: 'POST',
                    url: 'admin-landlord-all.php',
                    data: {
                        action: 'delete',
                        landlord_id: landlordId,
                        ajax: true
                    },
                    success: function(response) {
                        var result = JSON.parse(response);
                        if (result.success) {
                            alert(result.message);
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while deleting the account.');
                    }
                });
            }
        }
    </script>
</body>
</html>
