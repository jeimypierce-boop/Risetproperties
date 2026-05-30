<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
$errors = [];
$success = '';

$landlord_id = get_landlord_id();
$propertyQuery = "SELECT id, title FROM properties WHERE status IN ('Available', 'active', 'Active')";
if ($landlord_id) {
    $propertyQuery .= " AND landlord_id = " . intval($landlord_id);
}
$propertyQuery .= " ORDER BY title ASC";
$properties = $conn->query($propertyQuery);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $tenant_id = trim($_POST['tenant_id'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $property_id = intval($_POST['property_id'] ?? 0);
    $lease_start_date = trim($_POST['lease_start_date'] ?? '');
    $lease_end_date = trim($_POST['lease_end_date'] ?? '');
    $monthly_rent = trim($_POST['monthly_rent'] ?? '');
    $deposit = trim($_POST['deposit'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $phone === '' || $email === '' || $tenant_id === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Password and confirm password do not match.';
    }
    if ($property_id > 0 && ($lease_start_date === '' || $lease_end_date === '' || $monthly_rent === '')) {
        $errors[] = 'Please provide lease start date, lease end date and monthly rent for property assignment.';
    }

    $landlord_id = get_landlord_id();
    if ($property_id > 0 && $landlord_id) {
        $check = $conn->prepare("SELECT landlord_id FROM properties WHERE id = ? LIMIT 1");
        $check->bind_param('i', $property_id);
        $check->execute();
        $checkResult = $check->get_result();
        $selectedProperty = $checkResult->fetch_assoc();
        $check->close();

        if (!$selectedProperty || intval($selectedProperty['landlord_id']) !== $landlord_id) {
            $errors[] = 'Selected property is invalid or not owned by you.';
        }
    }

    $avatar_path = 'images/user/1.png';
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileExt = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions, true)) {
            $errors[] = 'Only JPG, PNG, and GIF images are allowed for profile image.';
        } else {
            $newName = uniqid('avatar_', true) . '.' . $fileExt;
            $destination = $uploadDir . '/' . $newName;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $avatar_path = 'uploads/' . $newName;
            } else {
                $errors[] = 'Unable to upload the image. Please try again.';
            }
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO tenants (first_name, last_name, phone, email, city, country, tenant_id, date_of_birth, password, status, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $errors[] = 'Database error: ' . $conn->error;
        } else {
            $status = 'Active';
            $stmt->bind_param('sssssssssss', $first_name, $last_name, $phone, $email, $city, $country, $tenant_id, $date_of_birth, $passwordHash, $status, $avatar_path);
            if ($stmt->execute()) {
                $tenantInsertId = $conn->insert_id;
                $leaseCreated = false;

                if ($property_id > 0 && $lease_start_date !== '' && $lease_end_date !== '' && $monthly_rent !== '') {
                    $leaseStmt = $conn->prepare("INSERT INTO leases (tenant_id, property_id, lease_start_date, lease_end_date, monthly_rent, deposit, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'Active', ?)");
                    if ($leaseStmt) {
                        $notes = 'Created from tenant onboarding form.';
                        $depositAmount = $deposit === '' ? 0 : $deposit;
                        $leaseStmt->bind_param('iissdds', $tenantInsertId, $property_id, $lease_start_date, $lease_end_date, $monthly_rent, $depositAmount, $notes);
                        if ($leaseStmt->execute()) {
                            $leaseCreated = true;
                        }
                        $leaseStmt->close();
                    }
                }

                $success = 'Tenant added successfully.';
                if ($leaseCreated) {
                    $success .= ' Lease created successfully.';
                }
                $first_name = $last_name = $phone = $email = $city = $country = $tenant_id = $date_of_birth = $lease_start_date = $lease_end_date = $monthly_rent = $deposit = $password = $confirm_password = '';
            } else {
                $errors[] = 'Unable to save tenant: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Riset Property Ltd - Admin Panel</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Riset Property Ltd admin â€“ add tenant records, lease details and tenant contacts.">
    <meta name="keyword" content="Riset Property Ltd, add tenant, lease, tenant record, Kenya">
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
                                    <li><a href="admin-trash-properties.html">Trash Property</a>
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
                        <li class="active-bre"><a href="#"> Add new tenant</a>
                        </li>
                        <li class="page-back"><a href="admin-user-all.php"><i class="fa fa-backward" aria-hidden="true"></i> Back</a>
                        </li>
                    </ul>
                </div>
                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                        <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4>Add New Tenant Information</h4>
                                    <p>Here you can enter tenant contact details and lease information.</p>
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
                                    <form method="post" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="first_name" name="first_name" type="text" value="<?php echo htmlspecialchars($first_name ?? ''); ?>" class="validate" required>
                                                <label for="first_name" class="active">First name</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars($last_name ?? ''); ?>" class="validate" required>
                                                <label for="last_name" class="active">Last name</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($phone ?? ''); ?>" class="validate" required>
                                                <label for="phone" class="active">Phone number</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" class="validate" required>
                                                <label for="email" class="active">Email</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="tenant_id" name="tenant_id" type="text" value="<?php echo htmlspecialchars($tenant_id ?? ''); ?>" class="validate" required>
                                                <label for="tenant_id" class="active">Tenant ID</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="date_of_birth" name="date_of_birth" type="date" value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>" class="validate">
                                                <label for="date_of_birth" class="active">Date of birth</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="city" name="city" type="text" value="<?php echo htmlspecialchars($city ?? ''); ?>" class="validate">
                                                <label for="city" class="active">City</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="country" name="country" type="text" value="<?php echo htmlspecialchars($country ?? ''); ?>" class="validate">
                                                <label for="country" class="active">Country</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <select id="property_id" name="property_id" class="browser-default">
                                                    <option value="">Select property to assign</option>
                                                    <?php if ($properties && $properties->num_rows): ?>
                                                        <?php while ($property = $properties->fetch_assoc()): ?>
                                                            <option value="<?php echo intval($property['id']); ?>" <?php echo (isset($property_id) && $property_id == $property['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($property['title']); ?></option>
                                                        <?php endwhile; ?>
                                                    <?php endif; ?>
                                                </select>
                                                <label for="property_id" class="active">Assign Property</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="monthly_rent" name="monthly_rent" type="number" step="0.01" value="<?php echo htmlspecialchars($monthly_rent ?? ''); ?>" class="validate">
                                                <label for="monthly_rent" class="active">Monthly Rent (KES)</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="lease_start_date" name="lease_start_date" type="date" value="<?php echo htmlspecialchars($lease_start_date ?? ''); ?>" class="validate">
                                                <label for="lease_start_date" class="active">Lease Start Date</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="lease_end_date" name="lease_end_date" type="date" value="<?php echo htmlspecialchars($lease_end_date ?? ''); ?>" class="validate">
                                                <label for="lease_end_date" class="active">Lease End Date</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="deposit" name="deposit" type="number" step="0.01" value="<?php echo htmlspecialchars($deposit ?? ''); ?>" class="validate">
                                                <label for="deposit" class="active">Deposit (KES)</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="password" name="password" type="password" class="validate" required>
                                                <label for="password" class="active">Password</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="confirm_password" name="confirm_password" type="password" class="validate" required>
                                                <label for="confirm_password" class="active">Confirm Password</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <div class="file-field input-field">
                                                    <div class="btn admin-upload-btn">
                                                        <span>Profile</span>
                                                        <input type="file" name="avatar">
                                                    </div>
                                                    <div class="file-path-wrapper">
                                                        <input class="file-path validate" type="text" placeholder="Profile image">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s12">
                                                <button type="submit" class="waves-effect waves-light btn-large">Submit</button>
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










