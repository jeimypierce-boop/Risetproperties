<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
$errors = [];
$success = '';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: admin-user-all.php');
    exit;
}

    $stmt = $conn->prepare('SELECT id, first_name, last_name, phone, email, city, country, tenant_id AS student_id, status, avatar FROM tenants WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    die('Tenant not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $phone === '' || $email === '' || $student_id === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password !== '' && $password !== $confirm_password) {
        $errors[] = 'Password and confirm password do not match.';
    }

    $avatar_path = $student['avatar'] ?: 'images/user/1.png';
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
        if ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE tenants SET first_name = ?, last_name = ?, phone = ?, email = ?, city = ?, country = ?, tenant_id = ?, password = ?, avatar = ? WHERE id = ?');
            $stmt->bind_param('sssssssssi', $first_name, $last_name, $phone, $email, $city, $country, $student_id, $passwordHash, $avatar_path, $id);
        } else {
            $stmt = $conn->prepare('UPDATE tenants SET first_name = ?, last_name = ?, phone = ?, email = ?, city = ?, country = ?, tenant_id = ?, avatar = ? WHERE id = ?');
            $stmt->bind_param('ssssssssi', $first_name, $last_name, $phone, $email, $city, $country, $student_id, $avatar_path, $id);
        }
        if ($stmt->execute()) {
            $success = 'Tenant updated successfully.';
            $student['first_name'] = $first_name;
            $student['last_name'] = $last_name;
            $student['phone'] = $phone;
            $student['email'] = $email;
            $student['city'] = $city;
            $student['country'] = $country;
            $student['student_id'] = $student_id;
            $student['avatar'] = $avatar_path;
        } else {
            $errors[] = 'Unable to update tenant: ' . $stmt->error;
        }
        $stmt->close();
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
    <meta name="description" content="Riset Property Ltd admin – view and edit tenant records and lease details.">
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
                <a href="admin-dashboard-modern.php" class="logo"><img src="images/logo1.png" alt="" />
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
                                    <li><a href="admin-Property-enquiry.html">Property Enquiry</a>
                                    </li>
                                    <li><a href="admin-admission-enquiry.html">Tenant Enquiry</a>
                                    </li>
                                    <li><a href="admin-seminar-enquiry.html">Maintenance Enquiry</a>
                                    </li>
                                    <li><a href="admin-event-enquiry.html">Viewing Enquiry</a>
                                    </li>
                                    <li><a href="admin-common-enquiry.html">Common Enquiry</a>
                                    </li>
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
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a>
                        </li>
                        <li class="active-bre"><a href="#"> Tenant Details</a>
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
                                    <h4>Tenant Information</h4>
                                    <p>Edit tenant contact and lease information here.</p>
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
                                                <input id="first_name" name="first_name" type="text" value="<?php echo htmlspecialchars($student['first_name']); ?>" class="validate" required>
                                                <label for="first_name" class="active">First name</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="last_name" name="last_name" type="text" value="<?php echo htmlspecialchars($student['last_name']); ?>" class="validate" required>
                                                <label for="last_name" class="active">Last name</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="phone" name="phone" type="text" value="<?php echo htmlspecialchars($student['phone']); ?>" class="validate" required>
                                                <label for="phone" class="active">Phone number</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="validate" required>
                                                <label for="email" class="active">Email</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="city" name="city" type="text" value="<?php echo htmlspecialchars($student['city']); ?>" class="validate">
                                                <label for="city" class="active">City</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="country" name="country" type="text" value="<?php echo htmlspecialchars($student['country']); ?>" class="validate">
                                                <label for="country" class="active">Country</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s6">
                                                <input id="password" name="password" type="password" class="validate">
                                                <label for="password" class="active">Password</label>
                                            </div>
                                            <div class="input-field col s6">
                                                <input id="confirm_password" name="confirm_password" type="password" class="validate">
                                                <label for="confirm_password" class="active">Confirm Password</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s12">
                                                <input id="student_id" name="student_id" type="text" value="<?php echo htmlspecialchars($student['student_id']); ?>" class="validate" required>
                                                <label for="student_id" class="active">Tenant ID</label>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="file-field input-field col s12">
                                                <div class="btn admin-upload-btn">
                                                    <span>File</span>
                                                    <input type="file" name="avatar">
                                                </div>
                                                <div class="file-path-wrapper">
                                                    <input class="file-path validate" type="text" placeholder="Profile image">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="input-field col s12">
                                                <button type="submit" class="waves-effect waves-light btn-large">Save</button>
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











