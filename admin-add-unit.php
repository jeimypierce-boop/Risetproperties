<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

require_login();

$success = '';
$errors = [];
$property_id = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;

if (!$property_id) {
    header('Location: admin-properties.php');
    exit;
}

// Get property details
$property_stmt = $conn->prepare("SELECT * FROM properties WHERE id = ?");
$property_stmt->bind_param('i', $property_id);
$property_stmt->execute();
$property_result = $property_stmt->get_result();

if ($property_result->num_rows === 0) {
    header('Location: admin-properties.php');
    exit;
}

$property = $property_result->fetch_assoc();
$property_stmt->close();

// Check if user has access to this property
$landlord_id = get_landlord_id();
if ($landlord_id && $property['landlord_id'] != $landlord_id) {
    header('Location: admin-properties.php');
    exit;
}

// Build relevant select lists for this property
$default_unit_types = ['Shops', 'Stalls', 'Double Rooms', 'Single Rooms', 'Bedsitters', '1 Bedrooms', '2 Bedrooms', '3 Bedrooms', 'Bungalow'];
$unit_type_options = [];
$type_result = $conn->query("SELECT DISTINCT unit_type FROM units WHERE property_id = " . intval($property_id) . " AND unit_type <> '' ORDER BY unit_type");
if ($type_result) {
    while ($row = $type_result->fetch_assoc()) {
        $unit_type_options[] = $row['unit_type'];
    }
}
if (empty($unit_type_options)) {
    $unit_type_options = $default_unit_types;
}

$default_status_options = ['Available', 'Occupied', 'Maintenance', 'Reserved'];
$status_options = [];
$status_result = $conn->query("SELECT DISTINCT status FROM units WHERE property_id = " . intval($property_id) . " AND status <> '' ORDER BY status");
if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $status_options[] = ucfirst(trim($row['status']));
    }
}
if (empty($status_options)) {
    $status_options = $default_status_options;
}

$unit_rows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['unit_name']) && is_array($_POST['unit_name'])) {
        $unit_names = $_POST['unit_name'];
        $unit_numbers = $_POST['unit_number'] ?? [];
        $unit_types = $_POST['unit_type'] ?? [];
        $monthly_rents = $_POST['monthly_rent'] ?? [];
        $deposits = $_POST['deposit'] ?? [];
        $currencies = $_POST['currency'] ?? [];
        $availability_dates = $_POST['availability_date'] ?? [];
        $descriptions = $_POST['description'] ?? [];

        foreach ($unit_names as $index => $name) {
            $unit_name = trim($name);
            $unit_number = trim($unit_numbers[$index] ?? '');
            $unit_type = trim($unit_types[$index] ?? '');
            $monthly_rent = floatval($monthly_rents[$index] ?? 0);
            $deposit = floatval($deposits[$index] ?? 0);
            $currency = trim($currencies[$index] ?? ($_POST['currency'] ?? 'KES')) ?: 'KES';
            $availability_date = trim($availability_dates[$index] ?? '');
            $description = trim($descriptions[$index] ?? '');

            if ($unit_name === '' && $unit_number === '' && $monthly_rent <= 0) {
                continue;
            }

            $unit_rows[] = [
                'unit_name' => $unit_name,
                'unit_number' => $unit_number,
                'unit_type' => $unit_type,
                'monthly_rent' => $monthly_rent,
                'deposit' => $deposit,
                'currency' => $currency,
                'availability_date' => $availability_date,
                'description' => $description,
            ];
        }
    } else {
        $unit_rows[] = [
            'unit_name' => trim($_POST['unit_name'] ?? ''),
            'unit_number' => trim($_POST['unit_number'] ?? ''),
            'unit_type' => trim($_POST['unit_type'] ?? ''),
            'monthly_rent' => floatval($_POST['monthly_rent'] ?? 0),
            'deposit' => floatval($_POST['deposit'] ?? 0),
            'currency' => trim($_POST['currency'] ?? 'KES') ?: 'KES',
            'availability_date' => trim($_POST['availability_date'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
        ];
    }

    if (empty($unit_rows)) {
        $errors[] = 'At least one unit row with a name and rent is required.';
    }

    foreach ($unit_rows as $row_index => $row) {
        if (empty($row['unit_name'])) {
            $errors[] = 'Unit name is required for row ' . ($row_index + 1);
        }
        if ($row['monthly_rent'] <= 0) {
            $errors[] = 'Monthly rent must be greater than 0 for row ' . ($row_index + 1);
        }
        if ($row['unit_number'] !== '') {
            $check_stmt = $conn->prepare("SELECT id FROM units WHERE property_id = ? AND unit_number = ?");
            $check_stmt->bind_param('is', $property_id, $row['unit_number']);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $errors[] = 'Unit number "' . htmlspecialchars($row['unit_number'], ENT_QUOTES) . '" already exists in this property';
            }
            $check_stmt->close();
        }
    }

    if (empty($errors)) {
        $insert_stmt = $conn->prepare(
            "INSERT INTO units 
            (property_id, unit_name, unit_number, unit_type, monthly_rent, deposit, currency, status, description, features, amenities, notes, availability_date, furnished, parking, utilities_included, tenant_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        foreach ($unit_rows as $row) {
            $status = 'Available';
            $features = '';
            $amenities = '';
            $notes = '';
            $availability_date = $row['availability_date'];
            $furnished = 0;
            $parking = 0;
            $utilities_included = '';
            $tenant_id = null;

            $insert_stmt->bind_param(
                'isssddsssssssiisi',
                $property_id,
                $row['unit_name'],
                $row['unit_number'],
                $row['unit_type'],
                $row['monthly_rent'],
                $row['deposit'],
                $row['currency'],
                $status,
                $row['description'],
                $features,
                $amenities,
                $notes,
                $availability_date,
                $furnished,
                $parking,
                $utilities_included,
                $tenant_id
            );

            if (!$insert_stmt->execute()) {
                $errors[] = 'Error adding unit "' . htmlspecialchars($row['unit_name'], ENT_QUOTES) . '": ' . $insert_stmt->error;
                break;
            }
        }

        $insert_stmt->close();

        if (empty($errors)) {
            $success = count($unit_rows) . ' unit' . (count($unit_rows) === 1 ? '' : 's') . ' added successfully!';
            header("refresh:1;url=admin-units.php?property_id=$property_id");
        }
    }
}

if (empty($unit_rows)) {
    $unit_rows[] = [
        'unit_name' => '',
        'unit_number' => '',
        'unit_type' => '',
        'monthly_rent' => 0,
        'deposit' => 0,
        'currency' => 'KES',
        'availability_date' => '',
        'description' => '',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add units - Riset Property Management</title>
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
        .form-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            color: #333;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
            color: #333;
            font-size: 13px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2f80ed;
            box-shadow: 0 0 0 3px rgba(47, 128, 237, 0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #2f80ed;
            color: white;
        }
        .btn-primary:hover {
            background: #1e5cc4;
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .unit-rows-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .unit-rows-table th,
        .unit-rows-table td {
            border: 1px solid #ddd;
            padding: 10px;
            vertical-align: middle;
            text-align: left;
        }
        .unit-rows-table th {
            background: #f7f7f7;
            font-size: 13px;
            font-weight: 700;
        }
        .unit-rows-table input,
        .unit-rows-table select {
            width: 100%;
            box-sizing: border-box;
            margin: 0;
        }
        .floor-cell {
            min-width: 90px;
            color: #555;
            font-size: 13px;
            font-weight: 600;
        }
        .checkbox-cell {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .btn-remove-row {
            background: #f44336;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-remove-row:hover {
            background: #d32f2f;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .hint {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert ul { margin: 0; padding-left: 20px; }
        .breadcrumb { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .breadcrumb a { color: #2f80ed; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .required { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid sb1">
        <div class="row">
            <div class="col-md-2 col-sm-3 col-xs-6 sb1-1">
                <a href="#" class="btn-close-menu"><i class="fa fa-times" aria-hidden="true"></i></a>
                <a href="#" class="atab-menu"><i class="fa fa-bars tab-menu" aria-hidden="true"></i></a>
                <a href="admin-dashboard-modern.php" class="logo"><img src="images/logo1.png" alt="Logo" /></a>
            </div>
            <div class="col-md-6 col-sm-6 mob-hide">
                <form class="app-search">
                    <input type="text" placeholder="Search..." class="form-control">
                    <a href="#"><i class="fa fa-search"></i></a>
                </form>
            </div>
            <div class="col-md-2 tab-hide">
                <div class="top-not-cen">
                    <a class='waves-effect btn-noti' href="admin-communications.php" title="Messages"><i class="fa fa-envelope-o" aria-hidden="true"></i><span>3</span></a>
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
                        <li><h5>Add Unit <span><?php echo htmlspecialchars($property['title']); ?></span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-properties.php" class="menu-active"><i class="fa fa-building" aria-hidden="true"></i> Properties</a></li>
                        <li><a href="admin-user-all.php"><i class="fa fa-users" aria-hidden="true"></i> Tenants</a></li>
                        <li><a href="admin-leases.php"><i class="fa fa-file-contract" aria-hidden="true"></i> Leases</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li><a href="admin-properties.php">Properties</a></li>
                        <li><a href="admin-units.php?property_id=<?php echo $property_id; ?>">Units</a></li>
                        <li class="active-bre"><a href="#">Add units</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <strong>Please fix the following errors:</strong>
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="admin-form" enctype="multipart/form-data">
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fa fa-th-large"></i> Add multiple units at once
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Property</label>
                                            <input type="text" readonly value="<?php echo htmlspecialchars($property['title']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="currency">Currency</label>
                                            <?php $currency = $unit_rows[0]['currency'] ?? 'KES'; ?>
                                            <select id="currency" name="currency" class="browser-default">
                                                <option value="KES" <?php echo ($currency === 'KES') ? 'selected' : ''; ?>>KES</option>
                                                <option value="USD" <?php echo ($currency === 'USD') ? 'selected' : ''; ?>>USD</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="grid-column: 1 / -1;">
                                            <label>Units will be added to Floor 1.</label>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="unit-rows-table">
                                            <thead>
                                                <tr>
                                                    <th>Unit name</th>
                                                    <th>Floor</th>
                                                    <th>Type</th>
                                                    <th>Amount (KES)</th>
                                                    <th>Deposit</th>
                                                    <th>Public</th>
                                                    <th>Remove</th>
                                                </tr>
                                            </thead>
                                            <tbody id="unitRows">
                                                <?php foreach ($unit_rows as $row): ?>
                                                    <tr>
                                                        <td>
                                                            <input type="text" name="unit_name[]" value="<?php echo htmlspecialchars($row['unit_name']); ?>" placeholder="Unit name">
                                                        </td>
                                                        <td class="floor-cell">Floor 1</td>
                                                        <td>
                                                            <select name="unit_type[]" class="browser-default">
                                                                <option value="">Select type</option>
                                                                <?php foreach ($unit_type_options as $unitType): ?>
                                                                    <option value="<?php echo htmlspecialchars($unitType); ?>" <?php echo (($row['unit_type'] ?? '') === $unitType) ? 'selected' : ''; ?>><?php echo htmlspecialchars($unitType); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="number" name="monthly_rent[]" step="0.01" min="0" value="<?php echo htmlspecialchars($row['monthly_rent']); ?>" placeholder="0">
                                                        </td>
                                                        <td>
                                                            <input type="number" name="deposit[]" step="0.01" min="0" value="<?php echo htmlspecialchars($row['deposit']); ?>" placeholder="0">
                                                        </td>
                                                        <td>
                                                            <label class="checkbox-cell"><input type="checkbox" disabled checked><span>Listed</span></label>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-remove-row">Remove row</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <template id="unitRowTemplate">
                                            <tr>
                                                <td>
                                                    <input type="text" name="unit_name[]" value="" placeholder="Unit name">
                                                </td>
                                                <td class="floor-cell">Floor 1</td>
                                                <td>
                                                    <select name="unit_type[]" class="browser-default">
                                                        <option value="">Select type</option>
                                                        <?php foreach ($unit_type_options as $unitType): ?>
                                                            <option value="<?php echo htmlspecialchars($unitType); ?>"><?php echo htmlspecialchars($unitType); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" name="monthly_rent[]" step="0.01" min="0" value="0" placeholder="0">
                                                </td>
                                                <td>
                                                    <input type="number" name="deposit[]" step="0.01" min="0" value="0" placeholder="0">
                                                </td>
                                                <td>
                                                    <label class="checkbox-cell"><input type="checkbox" disabled checked><span>Listed</span></label>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-remove-row">Remove row</button>
                                                </td>
                                            </tr>
                                        </template>
                                        <button type="button" id="addRowBtn" class="btn btn-primary">Add row</button>
                                    </div>
                                    <p class="hint">Amounts set the default lease amount; deposit defaults to 0. Public listings are disabled during the trial period.</p>
                                </div>

                                <!-- Action Buttons -->
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> Save units
                                    </button>
                                    <a href="admin-units.php?property_id=<?php echo $property_id; ?>" class="btn btn-secondary">
                                        <i class="fa fa-arrow-left"></i> Back to Units
                                    </a>
                                </div>
                            </form>
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
        document.addEventListener('DOMContentLoaded', function () {
            var unitRows = document.getElementById('unitRows');
            var addRowBtn = document.getElementById('addRowBtn');
            var rowTemplate = document.getElementById('unitRowTemplate');

            function attachRemove(row) {
                var button = row.querySelector('.btn-remove-row');
                if (!button) return;
                button.addEventListener('click', function () {
                    if (unitRows.children.length > 1) {
                        row.remove();
                    } else {
                        row.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (input) {
                            input.value = '';
                        });
                        var select = row.querySelector('select[name="unit_type[]"]');
                        if (select) select.selectedIndex = 0;
                    }
                });
            }

            Array.from(unitRows.querySelectorAll('tr')).forEach(function (row) {
                attachRemove(row);
            });

            if (addRowBtn && rowTemplate) {
                addRowBtn.addEventListener('click', function () {
                    var clone = rowTemplate.content.firstElementChild.cloneNode(true);
                    attachRemove(clone);
                    unitRows.appendChild(clone);
                });
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
