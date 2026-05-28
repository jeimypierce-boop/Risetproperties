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

$expense_date = date('Y-m-d');
$category = '';
$vendor_id = '';
$vendor_name = '';
$property_id = '';
$unit_id = '';
$deduction_month = date('F');
$deduction_year = date('Y');
$is_recurring = 0;
$reference = '';
$amount = '';
$taxed_amount = '0.00';
$notes = '';

$units = get_expense_units($conn, $landlord_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $category = $_POST['category'] ?? '';
    $vendor_id = $_POST['vendor_id'] ?? '';
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $property_id = $_POST['property_id'] ?? '';
    $unit_id = $_POST['unit_id'] ?? '';
    $deduction_month = $_POST['deduction_month'] ?? date('F');
    $deduction_year = $_POST['deduction_year'] ?? date('Y');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
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

    if (empty($category) || empty($reference) || $amount === '' || !is_numeric($amount)) {
        $error_msg = 'Please complete the category, reference, and amount fields.';
    } else {
        $expense_data = [
            'landlord_id' => $landlord_id,
            'property_id' => !empty($property_id) ? intval($property_id) : null,
            'unit_id' => !empty($unit_id) ? intval($unit_id) : null,
            'vendor_id' => !empty($vendor_id) ? intval($vendor_id) : null,
            'vendor_name' => $vendor_name,
            'category' => $category,
            'reference' => $reference,
            'amount' => $amount,
            'taxed_amount' => $taxed_amount,
            'notes' => $notes,
            'attachment' => $attachment,
            'expense_date' => $expense_date,
            'deduction_month' => $deduction_month,
            'deduction_year' => $deduction_year,
            'is_recurring' => $is_recurring,
            'created_by' => $_SESSION['user_id'] ?? null,
        ];

        if (add_expense($conn, $expense_data)) {
            $success_msg = 'Expense saved successfully.';
            $category = '';
            $vendor_id = '';
            $vendor_name = '';
            $property_id = '';
            $unit_id = '';
            $deduction_month = date('F');
            $deduction_year = date('Y');
            $is_recurring = 0;
            $reference = '';
            $amount = '';
            $taxed_amount = '0.00';
            $notes = '';
        } else {
            $error_msg = 'Unable to save expense. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Expense - Riset Property Ltd</title>
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
                        <li><h5>Add Expense <span>Create a new expense entry</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-expenses.php"><i class="fa fa-wallet" aria-hidden="true"></i> Expenses</a></li>
                        <li><a href="admin-expense-vendors.php"><i class="fa fa-user-tie" aria-hidden="true"></i> Vendors</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Add Expense</a></li>
                        <li class="page-back"><a href="admin-expenses.php"><i class="fa fa-backward" aria-hidden="true"></i> Back to Expenses</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4>Add Expense</h4>
                                    <p>Use this form to log a new expense. You can also add a receipt or invoice file.</p>
                                </div>
                                <?php if ($success_msg): ?>
                                    <div class="card-panel green lighten-4 green-text text-darken-4"><?php echo htmlspecialchars($success_msg); ?></div>
                                <?php endif; ?>
                                <?php if ($error_msg): ?>
                                    <div class="card-panel red lighten-4 red-text text-darken-4"><?php echo htmlspecialchars($error_msg); ?></div>
                                <?php endif; ?>
                                <div class="tab-inn">
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="add_expense">

                                        <div class="row">
                                            <div class="col s12 m8">
                                                <h5>Timing</h5>
                                                <p>Separate the actual expense date from the month and year used for deduction tracking.</p>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="input-field col s12 m4">
                                                <input id="expense_date" name="expense_date" type="date" value="<?php echo htmlspecialchars($expense_date); ?>" required>
                                                <label for="expense_date" class="active">Expense date</label>
                                            </div>
                                            <div class="input-field col s12 m4">
                                                <select name="deduction_month" required>
                                                    <?php foreach (["January","February","March","April","May","June","July","August","September","October","November","December"] as $month): ?>
                                                        <option value="<?php echo $month; ?>" <?php echo $deduction_month === $month ? 'selected' : ''; ?>><?php echo $month; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Deduction month</label>
                                            </div>
                                            <div class="input-field col s12 m4">
                                                <select name="deduction_year" required>
                                                    <?php for ($year = date('Y') - 3; $year <= date('Y') + 3; $year++): ?>
                                                        <option value="<?php echo $year; ?>" <?php echo $deduction_year == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <label>Deduction year</label>
                                            </div>
                                        </div>

                                        <div class="row" style="margin-bottom: 30px;">
                                            <div class="col s12">
                                                <label>
                                                    <input type="checkbox" name="is_recurring" value="1" <?php echo $is_recurring ? 'checked' : ''; ?> />
                                                    <span>Auto-generate this expense</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col s12 m8">
                                                <h5>Allocation</h5>
                                                <p>Link the expense to a saved vendor, then place it against a property and unit when the cost belongs to a specific location.</p>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="input-field col s12 m6">
                                                <select name="category" required>
                                                    <option value="" disabled <?php echo $category === '' ? 'selected' : ''; ?>>Select category</option>
                                                    <?php foreach ($categories as $categoryOption): ?>
                                                        <option value="<?php echo htmlspecialchars($categoryOption); ?>" <?php echo $category === $categoryOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryOption); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Category</label>
                                            </div>
                                            <div class="input-field col s12 m6">
                                                <select name="vendor_id">
                                                    <option value="" <?php echo $vendor_id === '' ? 'selected' : ''; ?>>Not linked</option>
                                                    <?php foreach ($vendors as $vendor): ?>
                                                        <option value="<?php echo $vendor['id']; ?>" <?php echo $vendor_id == $vendor['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($vendor['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Saved vendor (optional)</label>
                                                <a href="admin-expense-vendors.php" class="waves-effect waves-light btn-flat" style="margin-top: 6px; display: inline-block;">Manage saved vendors</a>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="input-field col s12 m6">
                                                <input id="vendor_name" name="vendor_name" type="text" value="<?php echo htmlspecialchars($vendor_name); ?>" placeholder="Supplier / contractor">
                                                <label for="vendor_name" class="active">Vendor</label>
                                            </div>
                                            <div class="input-field col s12 m6">
                                                <select id="property_id" name="property_id">
                                                    <option value="" <?php echo $property_id === '' ? 'selected' : ''; ?>>Not linked</option>
                                                    <?php foreach ($properties as $property): ?>
                                                        <option value="<?php echo $property['id']; ?>" <?php echo $property_id == $property['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($property['title']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <label>Property (optional)</label>
                                                <span class="helper-text">Leave blank for general expenses.</span>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="input-field col s12 m6">
                                                <select id="unit_id" name="unit_id" <?php echo empty($property_id) ? 'disabled' : ''; ?>>
                                                    <option value="" <?php echo $unit_id === '' ? 'selected' : ''; ?>>Not linked</option>
                                                    <?php if (!empty($property_id) && !empty($units[$property_id])): ?>
                                                        <?php foreach ($units[$property_id] as $unit): ?>
                                                            <option value="<?php echo $unit['id']; ?>" <?php echo $unit_id == $unit['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($unit['label']); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <label>Unit (optional)</label>
                                                <span class="helper-text">Select a property to load the matching units.</span>
                                            </div>
                                            <div class="input-field col s12 m6">
                                                <input id="reference" name="reference" type="text" value="<?php echo htmlspecialchars($reference); ?>" required placeholder="Receipt #, invoice #, or vendor memo">
                                                <label for="reference" class="active">Reference</label>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col s12 m6">
                                                <div class="input-field">
                                                    <input id="amount" name="amount" type="number" step="0.01" value="<?php echo htmlspecialchars($amount); ?>" required>
                                                    <label for="amount" class="active">Amount</label>
                                                </div>
                                            </div>
                                            <div class="col s12 m6">
                                                <div class="input-field">
                                                    <input id="taxed_amount" name="taxed_amount" type="number" step="0.01" value="<?php echo htmlspecialchars($taxed_amount); ?>">
                                                    <label for="taxed_amount" class="active">Taxed amount</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="input-field col s12">
                                                <textarea id="notes" name="notes" class="materialize-textarea"><?php echo htmlspecialchars($notes); ?></textarea>
                                                <label for="notes" class="active">Notes</label>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="file-field input-field col s12">
                                                <div class="btn">
                                                    <span>Attachment (optional)</span>
                                                    <input type="file" name="attachment" accept="image/*,application/pdf">
                                                </div>
                                                <div class="file-path-wrapper">
                                                    <input class="file-path validate" type="text" placeholder="PDF, image, or document. Maximum 5 MB.">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col s12">
                                                <button type="submit" class="waves-effect waves-light btn">Save expense</button>
                                                <a href="admin-expenses.php" class="waves-effect waves-light btn grey">Cancel</a>
                                            </div>
                                        </div>
                                    </form>
                                </div>
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
    <script>
        var unitOptionsByProperty = <?php echo json_encode($units); ?>;

        function refreshUnitSelect() {
            var propertySelect = document.getElementById('property_id');
            var unitSelect = document.getElementById('unit_id');
            var propertyId = propertySelect.value;
            var optionsHtml = '<option value="" ' + (propertyId === '' ? 'selected' : '') + '>Not linked</option>';
            if (propertyId && unitOptionsByProperty[propertyId]) {
                unitOptionsByProperty[propertyId].forEach(function(unit) {
                    var selected = unit.id == '<?php echo htmlspecialchars($unit_id); ?>' ? ' selected' : '';
                    optionsHtml += '<option value="' + unit.id + '"' + selected + '>' + unit.label + '</option>';
                });
                unitSelect.disabled = false;
            } else {
                unitSelect.disabled = true;
            }
            unitSelect.innerHTML = optionsHtml;
            M.FormSelect.init(unitSelect);
        }

        document.addEventListener('DOMContentLoaded', function() {
            var elems = document.querySelectorAll('select');
            M.FormSelect.init(elems);
            var propertySelect = document.getElementById('property_id');
            propertySelect.addEventListener('change', function() {
                refreshUnitSelect();
            });
            refreshUnitSelect();
        });
    </script>
</body>
</html>
