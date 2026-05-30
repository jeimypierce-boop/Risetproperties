<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

require_admin();

// Ensure only true admins can add landlords (not landlords themselves)
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: admin-dashboard-modern.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $status = $_POST['status'] ?? 'Active';

    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($first_name)) {
        $errors[] = 'First name is required.';
    }

    if (empty($last_name)) {
        $errors[] = 'Last name is required.';
    }

    if (empty($phone)) {
        $errors[] = 'Phone number is required.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Check if username already exists
    if (!empty($username) && empty($errors)) {
        $check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($check_username) {
            $check_username->bind_param('s', $username);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $errors[] = 'Username already exists.';
            }
            $check_username->close();
        }
    }

    // Check if email already exists
    if (!empty($email) && empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if ($check_email) {
            $check_email->bind_param('s', $email);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $errors[] = 'Email already exists.';
            }
            $check_email->close();
        }
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'landlord';
        $avatar = 'images/user/1.png';

        $insert_stmt = $conn->prepare(
            "INSERT INTO users (username, email, first_name, last_name, phone, password, role, status, avatar) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if ($insert_stmt) {
            $insert_stmt->bind_param(
                'sssssssss',
                $username,
                $email,
                $first_name,
                $last_name,
                $phone,
                $password_hash,
                $role,
                $status,
                $avatar
            );

            if ($insert_stmt->execute()) {
                $success = "Landlord account '$username' has been created successfully.";
                // Clear form data
                $username = $email = $first_name = $last_name = $phone = $password = $confirm_password = '';
                $status = 'Active';
            } else {
                $errors[] = 'Error creating landlord account: ' . $insert_stmt->error;
            }
            $insert_stmt->close();
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}

$user_info = get_user_info();
$user_initials = get_user_initials();
$user_role_label = get_user_role_label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Riset Property Ltd - Add Landlord Account</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Riset Property Ltd admin — create new landlord accounts.">
    <meta name="keyword" content="Riset Property Ltd, landlord, create account, admin">
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
                        <li><a href="admin-system-settings.php?section=overview"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a></li>
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
                        <li class="active-bre"><a href="#">Add Landlord</a></li>
                        <li class="page-back"><a href="admin-landlord-all.php"><i class="fa fa-backward" aria-hidden="true"></i> Back</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp">
                                <div class="inn-title">
                                    <h4>Create New Landlord Account</h4>
                                    <p>Add a new independent landlord account to the system.</p>
                                </div>

                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger alert-dismissible fade in" role="alert">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <strong>Errors:</strong>
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($success)): ?>
                                    <div class="alert alert-success alert-dismissible fade in" role="alert">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="tab-inn">
                                    <form method="POST" class="form-horizontal">
                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Username <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="text" name="username" class="form-control" placeholder="Enter unique username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                                <small class="form-text text-muted">Minimum 3 characters. Used for login.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Email <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="email" name="email" class="form-control" placeholder="Enter email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">First Name <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="text" name="first_name" class="form-control" placeholder="Enter first name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Last Name <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="text" name="last_name" class="form-control" placeholder="Enter last name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Phone Number <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="tel" name="phone" class="form-control" placeholder="Enter phone number" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Password <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                                                <small class="form-text text-muted">Minimum 6 characters.</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Confirm Password <span style="color: red;">*</span></label>
                                            <div class="col-md-9">
                                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="col-md-3 control-label">Account Status</label>
                                            <div class="col-md-9">
                                                <select name="status" class="form-control">
                                                    <option value="Active" <?php echo ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="Inactive" <?php echo ($_POST['status'] ?? 'Active') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <div class="col-md-offset-3 col-md-9">
                                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Landlord Account</button>
                                                <a href="admin-landlord-all.php" class="btn btn-secondary"><i class="fa fa-times"></i> Cancel</a>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/materialize.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.collapsible').collapsible();
            $('select').formSelect();
        });
    </script>
</body>
</html>
