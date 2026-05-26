<?php
// Signup/Registration page for students
require_once 'dbconnect.php';
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.html');
    exit;
}

$errors = array();
$success = false;
$form_data = array();

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    // Store form data for repopulation
    $form_data = compact('first_name', 'last_name', 'email', 'student_id', 'phone', 'city', 'country');

    // Validation
    if (empty($first_name)) {
        $errors[] = 'First name is required';
    }
    if (empty($last_name)) {
        $errors[] = 'Last name is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }
    if (empty($student_id)) {
        $errors[] = 'Student ID is required';
    } elseif (!preg_match('/^ST\d{5,}$/', $student_id)) {
        $errors[] = 'Student ID must start with "ST" followed by at least 5 digits (e.g., ST50001)';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    if (empty($city)) {
        $errors[] = 'City is required';
    }
    if (empty($country)) {
        $errors[] = 'Country is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }
    if (empty($confirm_password)) {
        $errors[] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    // Check if email already exists
    if (empty($errors)) {
            $check_stmt = $conn->prepare("SELECT id FROM tenants WHERE email = ? OR tenant_id = ?");
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $errors[] = 'Email or Student ID already exists. Please use different credentials.';
        }
        $check_stmt->close();
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $default_avatar = 'images/user/1.png';
        $status = 'Active';

        $insert_stmt = $conn->prepare("INSERT INTO tenants (first_name, last_name, phone, email, city, country, tenant_id, password, status, avatar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$insert_stmt) {
            $errors[] = 'Database error: ' . $conn->error;
        } else {
            $insert_stmt->bind_param('ssssssssss', $first_name, $last_name, $phone, $email, $city, $country, $student_id, $hashed_password, $status, $default_avatar);
            if ($insert_stmt->execute()) {
                $success = true;
                $user_id = $insert_stmt->insert_id;
                
                // Automatically log in the new user
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role'] = 'student';
                $_SESSION['login_type'] = 'student';
                
                // Redirect to dashboard after 2 seconds
                header('refresh:2; url=dashboard.html');
            } else {
                $errors[] = 'Error creating account: ' . $insert_stmt->error;
            }
            
            $insert_stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Education Master Template - Register</title>
    <!-- META TAGS -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Education master registration page">
    <!-- FAV ICON(BROWSER TAB ICON) -->
    <link rel="shortcut icon" href="images/fav.ico" type="image/x-icon">
    <!-- GOOGLE FONT -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700%7CJosefin+Sans:600,700" rel="stylesheet">
    <!-- FONTAWESOME ICONS -->
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <!-- ALL CSS FILES -->
    <link href="css/materialize.css" rel="stylesheet">
    <link href="css/bootstrap.css" rel="stylesheet" />
    <link href="css/style.css" rel="stylesheet" />
    <!-- RESPONSIVE.CSS ONLY FOR MOBILE AND TABLET VIEWS -->
    <link href="css/style-mob.css" rel="stylesheet" />
</head>

<body>
    <section>
        <div class="ad-log-main">
            <div class="ad-log-in">
                <div class="ad-log-in-logo">
                    <a href="index.html"><img src="images/logo.png" alt=""></a>
                </div>
                <div class="ad-log-in-con">
                    <div class="log-in-pop-right">
                        <h4>Create Your Account</h4>
                        <p>Register now and join our community. It takes less than 5 minutes!</p>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade in" role="alert">
                                <strong>Success!</strong> Your account has been created successfully. Redirecting to dashboard...
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade in" role="alert">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <strong>Errors found:</strong>
                                <ul style="margin: 10px 0 0 20px;">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="s12">
                            <div class="row">
                                <div class="input-field col s6">
                                    <input type="text" name="first_name" class="validate" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                                    <label>First Name</label>
                                </div>
                                <div class="input-field col s6">
                                    <input type="text" name="last_name" class="validate" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                                    <label>Last Name</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s12">
                                    <input type="email" name="email" class="validate" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                                    <label>Email Address</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s6">
                                    <input type="text" name="student_id" class="validate" placeholder="e.g., ST50001" value="<?php echo htmlspecialchars($form_data['student_id'] ?? ''); ?>" required>
                                    <label>Student ID</label>
                                </div>
                                <div class="input-field col s6">
                                    <input type="tel" name="phone" class="validate" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                                    <label>Phone Number</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s6">
                                    <input type="text" name="city" class="validate" value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>" required>
                                    <label>City</label>
                                </div>
                                <div class="input-field col s6">
                                    <input type="text" name="country" class="validate" value="<?php echo htmlspecialchars($form_data['country'] ?? ''); ?>" required>
                                    <label>Country</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s6">
                                    <input type="password" name="password" class="validate" required>
                                    <label>Password</label>
                                </div>
                                <div class="input-field col s6">
                                    <input type="password" name="confirm_password" class="validate" required>
                                    <label>Confirm Password</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col s12">
                                    <p>
                                        <input type="checkbox" id="terms" required>
                                        <label for="terms">I agree to the terms and conditions</label>
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s12">
                                    <button type="submit" name="signup_submit" class="waves-effect waves-light btn" style="width: 100%;">Create Account</button>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s12" style="text-align: center;">
                                    Already have an account? <a href="login.php">Login here</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!--Import jQuery before materialize.js-->
    <script src="js/main.min.js"></script>
    <script src="js/materialize.min.js"></script>`r`n    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
</body>

</html>




