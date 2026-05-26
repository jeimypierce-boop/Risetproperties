<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$landlord_id = get_landlord_id();
$tenantQuery = "SELECT t.*, l.id AS lease_id, l.lease_start_date, l.lease_end_date, l.monthly_rent, l.status AS lease_status, p.title AS property_title,
COALESCE(l.monthly_rent - (
    SELECT COALESCE(SUM(rp.amount_paid), 0)
    FROM rent_payments rp
    WHERE rp.lease_id = l.id AND DATE_FORMAT(rp.payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
), 0) AS balance_due
FROM tenants t
LEFT JOIN (
    SELECT l1.*
    FROM leases l1
    JOIN (
        SELECT tenant_id, MAX(lease_end_date) AS max_end
        FROM leases
        GROUP BY tenant_id
    ) l2 ON l1.tenant_id = l2.tenant_id AND l1.lease_end_date = l2.max_end
) l ON l.tenant_id = t.id
LEFT JOIN properties p ON p.id = l.property_id";
if ($landlord_id) {
    $tenantQuery .= " WHERE p.landlord_id = " . intval($landlord_id);
}
$tenantQuery .= " ORDER BY t.last_name, t.first_name";
$result = $conn->query($tenantQuery);
if ($result === false) {
    die('Database query error: ' . $conn->error);
}

$user_info = get_user_info();
$user_initials = get_user_initials();
$user_role_label = get_user_role_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Riset Property Ltd - Admin Panel</title>
    <!-- META TAGS -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Riset Property Ltd admin â€“ manage tenants, leases, and tenant records across Kenya.">
    <meta name="keyword" content="Riset Property Ltd, tenants, tenant management, leases, Kenya">
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
                <a href="index-2.html" class="logo"><img src="images/logo1.png" alt="" />
                </a>
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
                        <li><img src="images/placeholder.jpg" alt="">
                        </li>
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
                        <li><a href="admin-dashboard-modern.php" class="menu-active"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a>
                        </li>
                        <li><a href="admin-setting.html"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-building" aria-hidden="true"></i> Properties</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-properties.php">All Properties</a>
                                    </li>
                                    <li><a href="admin-add-property.php">Add New Property</a>
                                    </li>
                                    <li><a href="admin-trash-properties.html">Trash Properties</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-users" aria-hidden="true"></i> Tenants</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-user-all.php">All Tenants</a>
                                    </li>
                                    <li><a href="admin-user-add.php">Add New Tenant</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-calendar" aria-hidden="true"></i> Viewings</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-event-all.html">All Viewings</a>
                                    </li>
                                    <li><a href="admin-event-add.html">Schedule New Viewing</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-wrench" aria-hidden="true"></i> Maintenance</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-seminar-all.html">All Maintenance Tasks</a>
                                    </li>
                                    <li><a href="admin-seminar-add.html">Create Maintenance Task</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-commenting-o" aria-hidden="true"></i> Enquiry</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-all-enquiry.html">All Enquiries</a></li>
                                    <li><a href="admin-Property-enquiry.html">Property Enquiry</a></li>
                                    <li><a href="admin-admission-enquiry.html">Tenant Enquiry</a></li>
                                    <li><a href="admin-seminar-enquiry.html">Maintenance Enquiry</a></li>
                                    <li><a href="admin-event-enquiry.html">Viewing Enquiry</a></li>
                                    <li><a href="admin-common-enquiry.html">Common Enquiry</a></li>
                                </ul>
                            </div>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-cloud-download" aria-hidden="true"></i> Import & Export</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-export-data.html">Export Data</a>
                                    </li>
                                    <li><a href="admin-import-data.html">Import Data</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="index-2.html"><i class="fa fa-home" aria-hidden="true"></i> Home</a>
                        </li>
                        <li class="active-bre"><a href="#"> Tenants</a>
                        </li>
                        <li class="page-back"><a href="index-2.html"><i class="fa fa-backward" aria-hidden="true"></i> Back</a>
                        </li>
                    </ul>
                </div>
                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp">
                                <div class="inn-title">
                                    <h4>Tenants</h4>
                                    <p>All tenant records, leases and assigned properties in one place.</p>
                                    <a href="admin-user-add.php" class="btn btn-primary" style="float:right; margin-top:-20px;"><i class="fa fa-plus"></i> Add New Tenant</a>
                                </div>
                                <div class="tab-inn">
                                    <div class="table-responsive table-desi">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tenant</th>
                                                    <th>Unit / Property</th>
                                                    <th>Lease</th>
                                                    <th>Rent</th>
                                                    <th>Balance</th>
                                                    <th>Contact</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($row = $result->fetch_assoc()):
                                                    $tenantName = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']));
                                                    $tenantCode = htmlspecialchars(!empty($row['tenant_id']) ? $row['tenant_id'] : $row['id']);
                                                    $leasePeriod = $row['lease_start_date'] && $row['lease_end_date'] ? date('d M Y', strtotime($row['lease_start_date'])) . ' - ' . date('d M Y', strtotime($row['lease_end_date'])) : 'No active lease';
                                                    $propertyTitle = htmlspecialchars($row['property_title'] ?: 'Unassigned');
                                                    $monthlyRent = !empty($row['monthly_rent']) ? 'KES ' . number_format($row['monthly_rent'], 2) : 'N/A';
                                                    $balanceDue = isset($row['balance_due']) ? 'KES ' . number_format(max(0, floatval($row['balance_due'])), 2) : 'KES 0.00';
                                                    $statusLabel = strtolower($row['status']) === 'active' ? 'label-success' : 'label-default';
                                                    $leaseStatusLabel = !empty($row['lease_status']) ? htmlspecialchars($row['lease_status']) : 'No lease';
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <div class="media">
                                                                <div class="media-left">
                                                                    <img src="<?php echo !empty($row['avatar']) ? htmlspecialchars($row['avatar']) : 'images/user/1.png'; ?>" alt="Tenant avatar" style="width:46px; height:46px; border-radius:50%; object-fit:cover;">
                                                                </div>
                                                                <div class="media-body" style="padding-left:12px;">
                                                                    <strong><?php echo $tenantName; ?></strong><br>
                                                                    <small>ID: <?php echo $tenantCode; ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo $propertyTitle; ?></strong><br>
                                                            <small><?php echo htmlspecialchars($row['city'] ?: $row['country'] ?: '-'); ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo $leasePeriod; ?></strong><br>
                                                            <small><?php echo $leaseStatusLabel; ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo $monthlyRent; ?></strong>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo $balanceDue; ?></strong>
                                                        </td>
                                                        <td>
                                                            <div><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></div>
                                                            <div><?php echo htmlspecialchars($row['email'] ?? '-'); ?></div>
                                                        </td>
                                                        <td>
                                                            <span class="label <?php echo $statusLabel; ?>"><?php echo htmlspecialchars($row['status'] ?: 'Unknown'); ?></span>
                                                        </td>
                                                        <td>
                                                            <a href="admin-user-add.php?id=<?php echo urlencode($row['id']); ?>" class="ad-st-view" style="margin-right:8px;">View</a>
                                                            <?php if (!empty($row['lease_id'])): ?>
                                                                <button type="button" class="btn btn-sm btn-success btn-receive-payment" style="margin-right:8px;" data-tenant-id="<?php echo (int)$row['id']; ?>" data-lease-id="<?php echo (int)$row['lease_id']; ?>" data-tenant-name="<?php echo $tenantName; ?>">Receive</button>
                                                            <?php else: ?>
                                                                <a href="#" class="btn btn-sm btn-default" style="margin-right:8px;">No Lease</a>
                                                            <?php endif; ?>
                                                            <a href="#" class="ad-st-del btn-delete" data-type="tenant" data-id="<?php echo (int)$row['id']; ?>" style="color:#d9534f;">Delete</a>
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
    <!-- Receive Payment Modal -->
    <div class="modal fade" id="receivePaymentModal" tabindex="-1" role="dialog" aria-labelledby="receivePaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form method="POST" action="admin-rent-payments.php" id="receivePaymentForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="receivePaymentModalLabel">Receive Payment</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="record_payment">
                        <input type="hidden" name="tenant_id" id="rp_tenant_id" value="">
                        <input type="hidden" name="lease_id" id="rp_lease_id" value="">
                        
                        <div class="form-group">
                            <label for="rp_tenant_name">Tenant Name</label>
                            <input type="text" id="rp_tenant_name" class="form-control" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="amount_paid">Amount Paid (KES) *</label>
                                    <input type="number" id="amount_paid" name="amount_paid" class="form-control" step="50" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="payment_date">Payment Date *</label>
                                    <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="payment_method">Payment Method *</label>
                                    <select id="payment_method" name="payment_method" class="form-control" required>
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
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="paid">Paid</option>
                                        <option value="pending">Pending</option>
                                        <option value="partial">Partial</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID</label>
                            <input type="text" id="transaction_id" name="transaction_id" class="form-control" placeholder="Optional">
                        </div>
                        
                        <div class="form-group">
                            <label for="reference">Reference / Notes</label>
                            <input type="text" id="reference" name="reference" class="form-control" placeholder="e.g., M-PESA code">
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

    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script src="js/admin-delete.js"></script>
    <script>
        (function(){
            // Event listener for Receive Payment button clicks
            document.body.addEventListener('click', function(e) {
                var button = e.target.closest('.btn-receive-payment');
                if (!button) return;
                
                e.preventDefault();
                
                var tenantId = button.getAttribute('data-tenant-id');
                var leaseId = button.getAttribute('data-lease-id');
                var tenantName = button.getAttribute('data-tenant-name');
                
                var form = document.getElementById('receivePaymentForm');
                if (form) {
                    form.reset();
                    document.getElementById('rp_tenant_id').value = tenantId || '';
                    document.getElementById('rp_lease_id').value = leaseId || '';
                    document.getElementById('rp_tenant_name').value = tenantName || '';
                    document.getElementById('payment_date').value = new Date().toISOString().split('T')[0];
                }
                
                // Show modal using Bootstrap API
                if (window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                    jQuery('#receivePaymentModal').modal('show');
                } else {
                    // Fallback for when jQuery is not available
                    var modal = document.getElementById('receivePaymentModal');
                    if (modal) {
                        modal.classList.add('in', 'show');
                        modal.style.display = 'block';
                        modal.setAttribute('aria-modal', 'true');
                        modal.removeAttribute('aria-hidden');
                        document.body.classList.add('modal-open');
                        
                        // Create backdrop if needed
                        if (!document.querySelector('.modal-backdrop')) {
                            var backdrop = document.createElement('div');
                            backdrop.className = 'modal-backdrop fade in show';
                            document.body.appendChild(backdrop);
                        }
                    }
                }
            });

            // Handle modal close button and cancel button
            document.body.addEventListener('click', function(e) {
                // Check if close button was clicked
                if (e.target.closest('[data-dismiss="modal"]')) {
                    var modal = document.getElementById('receivePaymentModal');
                    if (modal && window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                        jQuery('#receivePaymentModal').modal('hide');
                    }
                }
            });

            // Handle Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' || e.key === 'Esc') {
                    var modal = document.getElementById('receivePaymentModal');
                    if (modal && (modal.classList.contains('show') || modal.style.display === 'block')) {
                        if (window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                            jQuery('#receivePaymentModal').modal('hide');
                        }
                    }
                }
            });

            // Handle backdrop click (if using fallback modal)
            document.body.addEventListener('click', function(e) {
                if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
                    var modal = document.getElementById('receivePaymentModal');
                    if (modal && window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                        jQuery('#receivePaymentModal').modal('hide');
                    }
                }
            });

            // Ensure form resets properly when modal is hidden
            if (window.jQuery && jQuery.fn) {
                jQuery('#receivePaymentModal').on('hidden.bs.modal', function() {
                    var form = document.getElementById('receivePaymentForm');
                    if (form) {
                        form.reset();
                        // Clear hidden fields too
                        document.getElementById('rp_tenant_id').value = '';
                        document.getElementById('rp_lease_id').value = '';
                        document.getElementById('rp_tenant_name').value = '';
                    }
                });
            }
        })();
    </script>
</body>
</html>
<?php $conn->close(); ?>









