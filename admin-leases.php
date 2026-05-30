<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_lease') {
        $tenant_id = intval($_POST['tenant_id']);
        $property_id = intval($_POST['property_id']);
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $rent_amount = floatval($_POST['rent_amount']);
        $status = $_POST['status'] ?? 'active';

        if ($landlord_id) {
            $check = $conn->prepare("SELECT landlord_id FROM properties WHERE id = ? LIMIT 1");
            $check->bind_param('i', $property_id);
            $check->execute();
            $propResult = $check->get_result();
            $propertyOwner = $propResult->fetch_assoc();
            $check->close();

            if (!$propertyOwner || intval($propertyOwner['landlord_id']) !== $landlord_id) {
                $error_msg = "Invalid property selected or you do not own this property.";
            }
        }

        if (empty($error_msg)) {
            $stmt = $conn->prepare("INSERT INTO leases (tenant_id, property_id, start_date, end_date, rent_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('iissds', $tenant_id, $property_id, $start_date, $end_date, $rent_amount, $status);
            
            if ($stmt->execute()) {
                $success_msg = "Lease created successfully!";
            } else {
                $error_msg = "Error creating lease: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch leases
$sql = "SELECT l.*, t.first_name, t.last_name, t.email, p.title as property_title FROM leases l
        JOIN tenants t ON l.tenant_id = t.id
        JOIN properties p ON l.property_id = p.id";
if ($landlord_id) {
    $sql .= " WHERE p.landlord_id = " . intval($landlord_id);
}
$sql .= " ORDER BY l.created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Lease Management - Riset Property Ltd</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <link href="css/style-mob.css" rel="stylesheet" />
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
                    <a class='waves-effect btn-noti' href="admin-all-enquiry.html" title="All Enquiries"><i class="fa fa-commenting-o" aria-hidden="true"></i><span>5</span></a>
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
                        <li><h5>Lease Management <span>Track & Manage Leases</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-leases.php" class="menu-active"><i class="fa fa-file-contract" aria-hidden="true"></i> Leases</a></li>
                        <li><a href="admin-rent-payments.php"><i class="fa fa-money" aria-hidden="true"></i> Rent Payments</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Lease Management</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp">
                                <div class="inn-title">
                                    <h4>All Leases</h4>
                                    <p>Manage tenant leases, track expiries, and handle renewals.</p>
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#newLeaseModal"><i class="fa fa-plus"></i> New Lease</button>
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
                                                    <th>Tenant</th>
                                                    <th>Property</th>
                                                    <th>Start Date</th>
                                                    <th>End Date</th>
                                                    <th>Rent Amount</th>
                                                    <th>Status</th>
                                                    <th>Days Remaining</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while($row = $result->fetch_assoc()): 
                                                    $end = new DateTime($row['end_date']);
                                                    $now = new DateTime();
                                                    $days_remaining = $end->diff($now)->days;
                                                    $status_class = $days_remaining < 30 ? 'label-warning' : 'label-success';
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['property_title']); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                                    <td><?php echo date('d M Y', strtotime($row['end_date'])); ?></td>
                                                    <td>KES <?php echo number_format($row['rent_amount']); ?></td>
                                                    <td><span class="label <?php echo $status_class; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                                    <td><strong><?php echo $days_remaining; ?> days</strong></td>
                                                    <td>
                                                        <a href="#" class="btn btn-sm btn-info"><i class="fa fa-eye"></i> View</a>
                                                        <a href="#" class="btn btn-sm btn-danger btn-delete" data-type="lease" data-id="<?php echo $row['id']; ?>"><i class="fa fa-trash"></i> Delete</a>
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

    <!-- New Lease Modal -->
    <div class="modal fade" id="newLeaseModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Lease</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_lease">
                        <div class="form-group">
                            <label>Tenant</label>
                            <select name="tenant_id" class="form-control" required>
                                <option value="">Select Tenant</option>
                                <?php 
                                $tenants = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM tenants");
                                while($t = $tenants->fetch_assoc()): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
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
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Monthly Rent Amount (KES)</label>
                            <input type="number" name="rent_amount" class="form-control" step="100" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Lease</button>
                    </div>
                </form>
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





