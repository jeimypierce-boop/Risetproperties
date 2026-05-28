<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
require_once 'expenses_model.php';

require_login();
$landlord_id = get_landlord_id();
ensure_expense_tables($conn);

$success_msg = '';
$error_msg = '';
$categories = get_expense_categories();
$vendors = get_expense_vendors($conn);
$properties = get_expense_properties($conn, $landlord_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? '';
    $vendor_id = $_POST['vendor_id'] ?? null;
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $property_id = $_POST['property_id'] ?? null;
    $reference = trim($_POST['reference'] ?? '');
    $amount = trim($_POST['amount'] ?? '0');
    $taxed_amount = trim($_POST['taxed_amount'] ?? '0');
    $notes = trim($_POST['notes'] ?? '');
    $attachment = null;

    if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/expenses';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['attachment']['name']));
        $targetPath = $uploadDir . '/' . $fileName;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
            $attachment = 'uploads/expenses/' . $fileName;
        }
    }

    $expense_data = [
        'landlord_id' => $landlord_id,
        'property_id' => !empty($property_id) ? intval($property_id) : null,
        'vendor_id' => !empty($vendor_id) ? intval($vendor_id) : null,
        'vendor_name' => $vendor_name,
        'category' => $category,
        'reference' => $reference,
        'amount' => $amount,
        'taxed_amount' => $taxed_amount,
        'notes' => $notes,
        'attachment' => $attachment,
        'expense_date' => $expense_date,
        'created_by' => $_SESSION['user_id'] ?? null,
    ];

    if (empty($category) || empty($reference) || $amount === '' || !is_numeric($amount)) {
        $error_msg = 'Please complete the category, reference, and amount fields.';
    } else {
        $added = add_expense($conn, $expense_data);
        if ($added) {
            $success_msg = 'Expense logged successfully.';
            $vendors = get_expense_vendors($conn);
        } else {
            $error_msg = 'Unable to save expense. Please try again.';
        }
    }
}

$filters = [
    'category' => $_GET['category'] ?? '',
    'vendor_name' => trim($_GET['vendor_name'] ?? ''),
    'vendor_id' => $_GET['vendor_id'] ?? '',
    'property_id' => $_GET['property_id'] ?? '',
    'reference' => trim($_GET['reference'] ?? ''),
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
];

$expenses = get_expenses($conn, $filters, $landlord_id);
$summary = get_expense_summary($conn, $landlord_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Expenses - Riset Property Ltd</title>
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
        .card-summary { border-radius: 10px; background: #fff; padding: 20px; margin-bottom: 20px; box-shadow: 0 0 18px rgba(0,0,0,0.05); }
        .card-summary h2 { margin: 0; font-size: 28px; }
        .filter-row .form-control { margin-bottom: 10px; }
        .expense-link-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
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
                    <a class='waves-effect btn-noti' href="admin-Property-enquiry.html" title="Property enquiry messages"><i class="fa fa-envelope-o" aria-hidden="true"></i><span>5</span></a>
                    <a class='waves-effect btn-noti' href="admin-admission-enquiry.html" title="Tenant Enquiry"><i class="fa fa-tag" aria-hidden="true"></i><span>5</span></a>
                </div>
            </div>
            <div class="col-md-2 col-sm-3 col-xs-6">
                <a class='waves-effect dropdown-button top-user-pro' href='#' data-activates='top-menu'><img src="images/user/6.png" alt="" />My Account <i class="fa fa-angle-down" aria-hidden="true"></i></a>
                <ul id='top-menu' class='dropdown-content top-menu-sty'>
                    <li><a href="admin-system-settings.php?section=overview" class="waves-effect"><i class="fa fa-cogs" aria-hidden="true"></i>Admin Setting</a></li>
                    <li class="divider"></li>
                    <li><a href="logout.php" class="ho-dr-con-last waves-effect"><i class="fa fa-sign-in" aria-hidden="true"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container-fluid sb2">
        <div class="row">
            <div class="sb2-1">
                <div class="sb2-12">
                    <ul>
                        <li><img src="images/placeholder.jpg" alt=""></li>
                        <li><h5>Expenses <span>Track operating expenses and vendor spend</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-expenses.php" class="menu-active"><i class="fa fa-wallet" aria-hidden="true"></i> Expenses</a></li>
                        <li><a href="admin-expense-vendors.php"><i class="fa fa-user-tie" aria-hidden="true"></i> Vendors</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Expenses</a></li>
                        <li class="page-back"><a href="admin-dashboard-modern.php"><i class="fa fa-backward" aria-hidden="true"></i> Back</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="card-summary">
                        <div class="row">
                            <div class="col-sm-8">
                                <h1>Expenses</h1>
                                <p class="text-muted">Track operating expenses and references with a refined ledger.</p>
                            </div>
                            <div class="col-sm-4 text-sm-right" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <a href="admin-expenses-create.php" class="waves-effect waves-light btn"><i class="fa fa-plus"></i> Add Expense</a>
                                <a href="admin-expense-vendors.php" class="waves-effect waves-light btn grey lighten-2 black-text"><i class="fa fa-user-tie"></i> Manage Vendors</a>
                            </div>
                        </div>
                        <div class="row" style="margin-top: 20px;">
                            <div class="col-md-4">
                                <div><strong>Total spent</strong></div>
                                <div style="font-size: 28px; font-weight: 700;">KES <?php echo number_format($summary['total_amount'], 2); ?></div>
                            </div>
                            <div class="col-md-4">
                                <div><strong>Expense entries</strong></div>
                                <div style="font-size: 28px; font-weight: 700;"><?php echo $summary['count']; ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($success_msg): ?>
                        <div class="card-panel green lighten-4 green-text text-darken-4"><?php echo htmlspecialchars($success_msg); ?></div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="card-panel red lighten-4 red-text text-darken-4"><?php echo htmlspecialchars($error_msg); ?></div>
                    <?php endif; ?>

                    <div class="card-summary">
                        <h4>Filters</h4>
                        <form method="get" class="filter-row">
                            <div class="row">
                                <div class="input-field col s12 m4">
                                    <select name="category">
                                        <option value="">All categories</option>
                                        <?php foreach ($categories as $categoryOption): ?>
                                            <option value="<?php echo htmlspecialchars($categoryOption); ?>" <?php echo $filters['category'] === $categoryOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryOption); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Category</label>
                                </div>
                                <div class="input-field col s12 m4">
                                    <select name="vendor_id">
                                        <option value="">All vendors</option>
                                        <?php foreach ($vendors as $vendor): ?>
                                            <option value="<?php echo $vendor['id']; ?>" <?php echo $filters['vendor_id'] == $vendor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($vendor['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Saved vendor</label>
                                </div>
                                <div class="input-field col s12 m4">
                                    <select name="property_id">
                                        <option value="">All properties</option>
                                        <?php foreach ($properties as $property): ?>
                                            <option value="<?php echo $property['id']; ?>" <?php echo $filters['property_id'] == $property['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($property['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Property</label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="input-field col s12 m4">
                                    <input id="reference" name="reference" type="text" value="<?php echo htmlspecialchars($filters['reference']); ?>">
                                    <label for="reference" class="active">Reference</label>
                                </div>
                                <div class="input-field col s12 m4">
                                    <input id="start_date" name="start_date" type="date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                                    <label for="start_date" class="active">Start date</label>
                                </div>
                                <div class="input-field col s12 m4">
                                    <input id="end_date" name="end_date" type="date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                                    <label for="end_date" class="active">End date</label>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col s12 right-align">
                                    <button type="submit" class="waves-effect waves-light btn">Filter</button>
                                    <a href="admin-expenses.php" class="waves-effect waves-light btn grey">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card-summary">
                        <div class="table-responsive">
                            <table class="table striped responsive-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Vendor</th>
                                        <th>Property</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Taxed amount</th>
                                        <th>Notes</th>
                                        <th>Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($expenses && $expenses->num_rows > 0): ?>
                                        <?php while ($expense = $expenses->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($expense['expense_date']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['vendor_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($expense['property_title'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($expense['reference']); ?></td>
                                                <td>KES <?php echo number_format($expense['amount'], 2); ?></td>
                                                <td>KES <?php echo number_format($expense['taxed_amount'], 2); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($expense['notes'])); ?></td>
                                                <td>
                                                    <?php if (!empty($expense['attachment'])): ?>
                                                        <a href="<?php echo htmlspecialchars($expense['attachment']); ?>" target="_blank">View</a>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" style="text-align: center; color: #999;">
                                                <i class="fa fa-inbox" style="font-size: 42px; opacity: 0.3;"></i>
                                                <p style="margin-top: 10px;">No expenses recorded.</p>
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
        document.addEventListener('DOMContentLoaded', function() {
            var elems = document.querySelectorAll('select');
            M.FormSelect.init(elems);
            var modals = document.querySelectorAll('.modal');
            M.Modal.init(modals);
        });
    </script>
</body>
</html>
