<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

$success = '';
$errors = [];
$property_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($property_id <= 0) {
    header('Location: admin-properties.php');
    exit;
}

$landlord_id = get_landlord_id();

// Fetch property
if ($landlord_id) {
    $stmt = $conn->prepare('SELECT * FROM properties WHERE id = ? AND landlord_id = ?');
    $stmt->bind_param('ii', $property_id, $landlord_id);
} else {
    $stmt = $conn->prepare('SELECT * FROM properties WHERE id = ?');
    $stmt->bind_param('i', $property_id);
}
$stmt->execute();
$result = $stmt->get_result();
$property_data = $result->fetch_assoc();
$stmt->close();

if (!$property_data) {
    die('Property not found or you do not have permission to edit this property.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_data['title'] = trim($_POST['title'] ?? '');
    $property_data['description'] = trim($_POST['description'] ?? '');
    $property_data['property_type'] = trim($_POST['property_type'] ?? '');
    $property_data['category'] = trim($_POST['category'] ?? '');
    $property_data['bedrooms'] = intval($_POST['bedrooms'] ?? 0);
    $property_data['bathrooms'] = intval($_POST['bathrooms'] ?? 0);
    $property_data['square_feet'] = intval($_POST['square_feet'] ?? 0);
    $property_data['unit_count'] = intval($_POST['unit_count'] ?? 1);
    $property_data['price'] = floatval($_POST['price'] ?? 0);
    $property_data['currency'] = trim($_POST['currency'] ?? 'KES');
    $property_data['location'] = trim($_POST['location'] ?? '');
    $property_data['city'] = trim($_POST['city'] ?? '');
    $property_data['country'] = trim($_POST['country'] ?? 'Kenya');
    $property_data['status'] = trim($_POST['status'] ?? 'Available');
    $property_data['contact_person'] = trim($_POST['contact_person'] ?? '');
    $property_data['contact_phone'] = trim($_POST['contact_phone'] ?? '');
    $property_data['contact_email'] = trim($_POST['contact_email'] ?? '');
    $property_data['availability_date'] = trim($_POST['availability_date'] ?? date('Y-m-d'));

    // Validation
    if (empty($property_data['title'])) $errors[] = 'Property title is required';
    if (empty($property_data['city'])) $errors[] = 'City is required';
    if ($property_data['unit_count'] <= 0) $errors[] = 'Total units must be at least 1';
    if ($property_data['price'] <= 0) $errors[] = 'Price must be greater than 0';

    if (empty($errors)) {
        if ($landlord_id) {
            $stmt = $conn->prepare("UPDATE properties SET title = ?, description = ?, property_type = ?, category = ?, bedrooms = ?, bathrooms = ?, square_feet = ?, unit_count = ?, price = ?, currency = ?, location = ?, city = ?, country = ?, status = ?, contact_person = ?, contact_phone = ?, contact_email = ?, availability_date = ?, updated_at = NOW() WHERE id = ? AND landlord_id = ?");
            $stmt->bind_param('ssssiiiidsssssssssii', 
                $property_data['title'], 
                $property_data['description'], 
                $property_data['property_type'], 
                $property_data['category'], 
                $property_data['bedrooms'], 
                $property_data['bathrooms'], 
                $property_data['square_feet'], 
                $property_data['unit_count'], 
                $property_data['price'], 
                $property_data['currency'], 
                $property_data['location'], 
                $property_data['city'], 
                $property_data['country'], 
                $property_data['status'], 
                $property_data['contact_person'], 
                $property_data['contact_phone'], 
                $property_data['contact_email'], 
                $property_data['availability_date'], 
                $property_id,
                $landlord_id
            );
        } else {
            $stmt = $conn->prepare("UPDATE properties SET title = ?, description = ?, property_type = ?, category = ?, bedrooms = ?, bathrooms = ?, square_feet = ?, unit_count = ?, price = ?, currency = ?, location = ?, city = ?, country = ?, status = ?, contact_person = ?, contact_phone = ?, contact_email = ?, availability_date = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ssssiiiidssssssssi', 
                $property_data['title'], 
                $property_data['description'], 
                $property_data['property_type'], 
                $property_data['category'], 
                $property_data['bedrooms'], 
                $property_data['bathrooms'], 
                $property_data['square_feet'], 
                $property_data['unit_count'], 
                $property_data['price'], 
                $property_data['currency'], 
                $property_data['location'], 
                $property_data['city'], 
                $property_data['country'], 
                $property_data['status'], 
                $property_data['contact_person'], 
                $property_data['contact_phone'], 
                $property_data['contact_email'], 
                $property_data['availability_date'], 
                $property_id
            );
        }

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = 'Property updated successfully!';
            } else {
                $errors[] = 'No changes were made or you do not have permission to edit this property.';
            }
        } else {
            $errors[] = 'Error updating property: ' . $stmt->error;
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Property - Riset Property Management</title>
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
                        <li><h5>Edit Property <span><?php echo htmlspecialchars($property_data['title']); ?></span></h5></li>
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
                        <li class="active-bre"><a href="#">Edit Property</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4>Edit Property Details</h4>
                                    <p>Edit property details, rent and availability.</p>
                                </div>
                                <div class="tab-inn">
                                    <?php if (!empty($success)): ?>
                                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger">
                                            <ul>
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?php echo htmlspecialchars($error); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <form method="post">
                                        <h5 style="margin-bottom: 20px;">Basic Information</h5>
                                        <div class="row">
                                            <div class="input-field col s12">
                                                <input id="title" name="title" type="text" value="<?php echo htmlspecialchars($property_data['title']); ?>" class="validate" required>
                                                <label for="title" class="active">Property Name</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s12">
                                                <textarea id="description" name="description" class="materialize-textarea"><?php echo htmlspecialchars($property_data['description']); ?></textarea>
                                                <label for="description" class="active">Description</label>
                                            </div>
                                        </div>

                                        <h5 style="margin-bottom: 20px; margin-top: 20px;">Property Details</h5>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <select id="property_type" name="property_type" class="browser-default">
                                                    <option value="">Select type</option>
                                                    <option value="Apartment" <?php echo ($property_data['property_type'] === 'Apartment') ? 'selected' : ''; ?>>Apartment</option>
                                                    <option value="House" <?php echo ($property_data['property_type'] === 'House') ? 'selected' : ''; ?>>House</option>
                                                    <option value="Townhouse" <?php echo ($property_data['property_type'] === 'Townhouse') ? 'selected' : ''; ?>>Townhouse</option>
                                                    <option value="Villa" <?php echo ($property_data['property_type'] === 'Villa') ? 'selected' : ''; ?>>Villa</option>
                                                </select>
                                                <label for="property_type">Property Type</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="category" name="category" type="text" value="<?php echo htmlspecialchars($property_data['category']); ?>" class="validate">
                                                <label for="category" class="active">Property Category</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s4">
                                                <input id="square_feet" name="square_feet" type="number" min="0" value="<?php echo $property_data['square_feet']; ?>" class="validate">
                                                <label for="square_feet" class="active">Unit Size (sq ft)</label>
                                            </div>
                                            <div class="input-field col s4">
                                                <input id="unit_count" name="unit_count" type="number" min="1" value="<?php echo $property_data['unit_count']; ?>" class="validate" required>
                                                <label for="unit_count" class="active">Total Units</label>
                                            </div>
                                        </div>

                                        <h5 style="margin-bottom: 20px; margin-top: 20px;">Pricing & Location</h5>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="price" name="price" type="number" step="0.01" value="<?php echo $property_data['price']; ?>" class="validate" required>
                                                <label for="price" class="active">Monthly Rent</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <select id="currency" name="currency" class="browser-default">
                                                    <option value="KES" <?php echo ($property_data['currency'] === 'KES') ? 'selected' : ''; ?>>KES</option>
                                                    <option value="USD" <?php echo ($property_data['currency'] === 'USD') ? 'selected' : ''; ?>>USD</option>
                                                </select>
                                                <label for="currency">Currency</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="city" name="city" type="text" value="<?php echo htmlspecialchars($property_data['city']); ?>" class="validate" required>
                                                <label for="city" class="active">City</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="location" name="location" type="text" value="<?php echo htmlspecialchars($property_data['location']); ?>" class="validate">
                                                <label for="location" class="active">Location</label>
                                            </div>
                                        </div>

                                        <h5 style="margin-bottom: 20px; margin-top: 20px;">Contact Information</h5>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="contact_person" name="contact_person" type="text" value="<?php echo htmlspecialchars($property_data['contact_person']); ?>" class="validate">
                                                <label for="contact_person" class="active">Property Contact</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="contact_phone" name="contact_phone" type="text" value="<?php echo htmlspecialchars($property_data['contact_phone']); ?>" class="validate">
                                                <label for="contact_phone" class="active">Contact Phone</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="contact_email" name="contact_email" type="email" value="<?php echo htmlspecialchars($property_data['contact_email']); ?>" class="validate">
                                                <label for="contact_email" class="active">Contact Email</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="availability_date" name="availability_date" type="date" value="<?php echo htmlspecialchars($property_data['availability_date']); ?>" class="validate">
                                                <label for="availability_date" class="active">Available From</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <select id="status" name="status" class="browser-default">
                                                    <option value="Available" <?php echo ($property_data['status'] === 'Available') ? 'selected' : ''; ?>>Available</option>
                                                    <option value="Occupied" <?php echo ($property_data['status'] === 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                                                    <option value="Maintenance" <?php echo ($property_data['status'] === 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                                </select>
                                                <label for="status">Property Status</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s12">
                                                <button type="submit" class="waves-effect waves-light btn-large">Save Property</button>
                                                <a href="admin-properties.php" class="waves-effect waves-light btn-large" style="background: #888; margin-left: 10px;">Cancel</a>
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
</body>
</html>
<?php $conn->close(); ?>




