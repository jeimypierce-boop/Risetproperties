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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unit_name = trim($_POST['unit_name'] ?? '');
    $unit_number = trim($_POST['unit_number'] ?? '');
    $unit_type = trim($_POST['unit_type'] ?? '');
    $monthly_rent = floatval($_POST['monthly_rent'] ?? 0);
    $deposit = floatval($_POST['deposit'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'KES');
    $availability_date = trim($_POST['availability_date'] ?? null);
    $furnished = isset($_POST['furnished']) ? 1 : 0;
    $parking = isset($_POST['parking']) ? 1 : 0;
    $utilities_included = trim($_POST['utilities_included'] ?? '');
    $tenant_id = !empty($_POST['tenant_id']) ? intval($_POST['tenant_id']) : null;
    $status = trim($_POST['status'] ?? 'Available');
    $description = trim($_POST['description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $amenities = trim($_POST['amenities'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validation
    if (empty($unit_name)) $errors[] = 'Unit name is required';
    if (empty($unit_number)) $errors[] = 'Unit number is required';
    if ($monthly_rent <= 0) $errors[] = 'Monthly rent must be greater than 0';

    // Check if unit number already exists in this property
    if (!empty($errors) === false) {
        $check_stmt = $conn->prepare("SELECT id FROM units WHERE property_id = ? AND unit_number = ?");
        $check_stmt->bind_param('is', $property_id, $unit_number);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = 'Unit number already exists in this property';
        }
        $check_stmt->close();
    }

    if (empty($errors)) {
        $insert_stmt = $conn->prepare(
            "INSERT INTO units 
            (property_id, unit_name, unit_number, unit_type, monthly_rent, deposit, currency, status, description, features, amenities, notes, availability_date, furnished, parking, utilities_included, tenant_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $insert_stmt->bind_param(
            'isssddsssssssiisi',
            $property_id,
            $unit_name,
            $unit_number,
            $unit_type,
            $monthly_rent,
            $deposit,
            $currency,
            $status,
            $description,
            $features,
            $amenities,
            $notes,
            $availability_date,
            $furnished,
            $parking,
            $utilities_included,
            $tenant_id
        );

        if ($insert_stmt->execute()) {
            $new_unit_id = $insert_stmt->insert_id;
            // handle image uploads
            if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $uploadDir = __DIR__ . '/uploads/units/' . $property_id;
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    $tmp = $_FILES['images']['tmp_name'][$i];
                    $name = basename($_FILES['images']['name'][$i]);
                    if ($tmp && is_uploaded_file($tmp)) {
                        $target = $uploadDir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
                        if (move_uploaded_file($tmp, $target)) {
                            $rel = 'uploads/units/' . $property_id . '/' . basename($target);
                            $img_stmt = $conn->prepare("INSERT INTO unit_images (unit_id, image_path, created_at) VALUES (?, ?, NOW())");
                            $img_stmt->bind_param('is', $new_unit_id, $rel);
                            $img_stmt->execute();
                            $img_stmt->close();
                        }
                    }
                }
            }

            $success = 'Unit added successfully!';
            header("refresh:1;url=admin-units.php?property_id=$property_id");
        } else {
            $errors[] = 'Error adding unit: ' . $insert_stmt->error;
        }
        $insert_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add Unit - Riset Property Management</title>
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
                        <li class="active-bre"><a href="#">Add Unit</a></li>
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
                                <!-- Basic Information -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fa fa-info-circle"></i> Basic Information
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="unit_name">Unit Name <span class="required">*</span></label>
                                            <input type="text" id="unit_name" name="unit_name" required 
                                                   value="<?php echo htmlspecialchars($_POST['unit_name'] ?? ''); ?>"
                                                   placeholder="e.g., Unit A, Studio 101">
                                        </div>
                                        <div class="form-group">
                                            <label for="unit_number">Unit Number <span class="required">*</span></label>
                                            <input type="text" id="unit_number" name="unit_number" required 
                                                   value="<?php echo htmlspecialchars($_POST['unit_number'] ?? ''); ?>"
                                                   placeholder="e.g., A101, TH201">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="unit_type">Unit Type</label>
                                            <select id="unit_type" name="unit_type">
                                                <option value="">Select type</option>
                                                <option value="Studio" <?php echo (($_POST['unit_type'] ?? '') === 'Studio') ? 'selected' : ''; ?>>Studio</option>
                                                <option value="1-Bedroom" <?php echo (($_POST['unit_type'] ?? '') === '1-Bedroom') ? 'selected' : ''; ?>>1-Bedroom</option>
                                                <option value="2-Bedroom" <?php echo (($_POST['unit_type'] ?? '') === '2-Bedroom') ? 'selected' : ''; ?>>2-Bedroom</option>
                                                <option value="3-Bedroom" <?php echo (($_POST['unit_type'] ?? '') === '3-Bedroom') ? 'selected' : ''; ?>>3-Bedroom</option>
                                                <option value="4-Bedroom" <?php echo (($_POST['unit_type'] ?? '') === '4-Bedroom') ? 'selected' : ''; ?>>4-Bedroom</option>
                                                <option value="Penthouse" <?php echo (($_POST['unit_type'] ?? '') === 'Penthouse') ? 'selected' : ''; ?>>Penthouse</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select id="status" name="status">
                                                <option value="Available" <?php echo (($_POST['status'] ?? 'Available') === 'Available') ? 'selected' : ''; ?>>Available</option>
                                                <option value="Occupied" <?php echo (($_POST['status'] ?? '') === 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                                                <option value="Maintenance" <?php echo (($_POST['status'] ?? '') === 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                                <option value="Reserved" <?php echo (($_POST['status'] ?? '') === 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea id="description" name="description" 
                                                      placeholder="Describe the unit, features, etc..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Unit Details -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fa fa-th"></i> Unit Details
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="bedrooms">Bedrooms</label>
                                            <input type="number" id="bedrooms" name="bedrooms" min="0" 
                                                   value="<?php echo $_POST['bedrooms'] ?? 0; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="bathrooms">Bathrooms</label>
                                            <input type="number" id="bathrooms" name="bathrooms" min="0" 
                                                   value="<?php echo $_POST['bathrooms'] ?? 0; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="square_feet">Square Feet</label>
                                            <input type="number" id="square_feet" name="square_feet" min="0" 
                                                   value="<?php echo $_POST['square_feet'] ?? 0; ?>"
                                                   placeholder="Total area in sq ft">
                                        </div>
                                    </div>
                                </div>

                                <!-- Pricing -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fa fa-money"></i> Pricing
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="currency">Currency</label>
                                            <select id="currency" name="currency">
                                                <option value="KES" <?php echo (($_POST['currency'] ?? 'KES') === 'KES') ? 'selected' : ''; ?>>KES</option>
                                                <option value="USD" <?php echo (($_POST['currency'] ?? '') === 'USD') ? 'selected' : ''; ?>>USD</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="availability_date">Availability Date</label>
                                            <input type="date" id="availability_date" name="availability_date" value="<?php echo htmlspecialchars($_POST['availability_date'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="furnished">Furnished</label>
                                            <div style="padding-top:8px;"><input type="checkbox" id="furnished" name="furnished" <?php echo isset($_POST['furnished']) ? 'checked' : ''; ?>> Yes</div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="monthly_rent">Monthly Rent <span class="required">*</span></label>
                                            <input type="number" id="monthly_rent" name="monthly_rent" step="0.01" required 
                                                   value="<?php echo $_POST['monthly_rent'] ?? 0; ?>"
                                                   placeholder="Enter monthly rent in KES">
                                        </div>
                                        <div class="form-group">
                                            <label for="deposit">Deposit Amount</label>
                                            <input type="number" id="deposit" name="deposit" step="0.01" 
                                                   value="<?php echo $_POST['deposit'] ?? 0; ?>"
                                                   placeholder="Security deposit amount">
                                        </div>
                                        <div class="form-group">
                                            <label for="parking">Parking</label>
                                            <div style="padding-top:8px;"><input type="checkbox" id="parking" name="parking" <?php echo isset($_POST['parking']) ? 'checked' : ''; ?>> Has parking</div>
                                        </div>
                                        <div class="form-group">
                                            <label for="utilities_included">Utilities Included</label>
                                            <input type="text" id="utilities_included" name="utilities_included" value="<?php echo htmlspecialchars($_POST['utilities_included'] ?? ''); ?>" placeholder="e.g., water, electricity">
                                        </div>
                                    </div>
                                </div>

                                <!-- Additional Information -->
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fa fa-list"></i> Additional Information
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="features">Features</label>
                                            <textarea id="features" name="features" 
                                                      placeholder="e.g., Built-in wardrobes, ceiling fans, modern kitchen..."><?php echo htmlspecialchars($_POST['features'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="tenant_id">Assign Tenant (optional)</label>
                                            <select id="tenant_id" name="tenant_id">
                                                <option value="">-- None --</option>
                                                <?php
                                                $tres = $conn->query("SELECT id, first_name, last_name, email FROM tenants ORDER BY first_name");
                                                while ($t = $tres->fetch_assoc()):
                                                ?>
                                                    <option value="<?php echo $t['id']; ?>" <?php echo (($_POST['tenant_id'] ?? '') == $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name'] . ' (' . $t['email'] . ')'); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="images">Upload Images</label>
                                            <input type="file" id="images" name="images[]" multiple accept="image/*">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="amenities">Amenities</label>
                                            <textarea id="amenities" name="amenities" 
                                                      placeholder="e.g., Swimming pool, gym, parking..."><?php echo htmlspecialchars($_POST['amenities'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="notes">Notes</label>
                                            <textarea id="notes" name="notes" 
                                                      placeholder="Any additional notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> Add Unit
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
</body>
</html>
<?php $conn->close(); ?>
