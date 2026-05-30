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
    // Student/tenant ID, city and country are optional for the modern signup flow
    if (!empty($student_id) && !preg_match('/^ST\d{5,}$/', $student_id)) {
        $errors[] = 'Student ID must start with "ST" followed by at least 5 digits (e.g., ST50001)';
    }
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
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
        if ($check_stmt) {
            $check_stmt->bind_param('ss', $email, $student_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result && $check_result->num_rows > 0) {
                $errors[] = 'Email or Student ID already exists. Please use different credentials.';
            }
            $check_stmt->close();
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
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
                $_SESSION['user_role'] = 'tenant';
                $_SESSION['login_type'] = 'tenant';
                
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
    <title>Property Management System - Sign Up</title>
    <!-- META TAGS -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Register for access to your property management portal.">
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
    <style>
    /* Signup form tweaks */
    .log-in-pop-right { max-width:520px; margin:0 auto; }
    .input-field .validate { padding:10px 12px; }
    .pwd-meter { margin-top:6px; }
    .pwd-bar { background:#eee; border-radius:6px; height:8px; overflow:hidden; }
    .pwd-fill { height:100%; width:0%; background:#f44336; transition:width .18s ease, background .18s ease; }
    .pwd-weak { background:#f44336; }
    .pwd-fair { background:#ff9800; }
    .pwd-good { background:#4caf50; }
    .pwd-toggle { position:absolute; right:12px; top:12px; background:transparent; border:0; cursor:pointer; color:#666; }
    .pwd-toggle:focus { outline:none; }
    .pwd-checklist { margin-top:8px; font-size:13px; color:#666; }
    .pwd-checklist li { margin-bottom:4px; }
    .pwd-checklist li.valid { color:#4caf50; }
    .waves-effect.waves-light.btn { background:#1976d2; border-radius:4px; font-weight:600; }
    </style>
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
                        <h4>Create account</h4>
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

                        <!-- client-side message container -->
                        <div id="clientMessage"></div>

                        <form id="signupForm" method="POST" action="" class="s12">
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
                                    <input type="text" name="student_id" class="validate" placeholder="e.g., ST50001 (optional)" value="<?php echo htmlspecialchars($form_data['student_id'] ?? ''); ?>">
                                    <label>Student ID (optional)</label>
                                </div>
                                <div class="input-field col s6">
                                    <input type="tel" name="phone" class="validate" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                                    <label>Phone Number</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s6">
                                    <input type="text" name="city" class="validate" value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>">
                                    <label>City (optional)</label>
                                </div>
                                <div class="input-field col s6">
                                    <input type="text" name="country" class="validate" value="<?php echo htmlspecialchars($form_data['country'] ?? ''); ?>">
                                    <label>Country (optional)</label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="input-field col s6" style="position:relative;">
                                    <input type="password" id="password" name="password" class="validate" required>
                                    <label>Password</label>
                                    <button type="button" id="pwdToggle" class="pwd-toggle" aria-label="Show password">👁️</button>
                                    <div class="pwd-meter">
                                        <div class="pwd-bar"><div id="pwdFill" class="pwd-fill"></div></div>
                                        <small id="pwdText" style="display:block;margin-top:6px;color:#666;">Use at least 6 characters including letters and numbers.</small>
                                    </div>
                                    <ul id="pwdChecklist" class="pwd-checklist">
                                        <li id="chkLength">At least 6 characters</li>
                                        <li id="chkLower">Lowercase letter (a–z)</li>
                                        <li id="chkUpper">Uppercase letter (A–Z)</li>
                                        <li id="chkNumber">A number (0–9)</li>
                                        <li id="chkSpecial">A special character (e.g. !@#$%)</li>
                                    </ul>
                                </div>
                                <div class="input-field col s6">
                                    <input type="password" id="confirm_password" name="confirm_password" class="validate" required>
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
                                    <button type="submit" name="signup_submit" class="waves-effect waves-light btn" style="width: 100%;">Create account</button>
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
    <script src="js/materialize.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/custom.js"></script>
    <script>
    (function(){
        function showClientErrors(errors){
            var container = document.getElementById('clientMessage');
            if(!container) return;
            var html = '<div class="alert alert-danger alert-dismissible fade in" role="alert">';
            html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            html += '<strong>Please fix the following:</strong><ul style="margin:8px 0 0 18px;">';
            errors.forEach(function(e){ html += '<li>' + e + '</li>'; });
            html += '</ul></div>';
            container.innerHTML = html;
        }

        function scorePassword(pwd){
            var score = 0;
            if(!pwd) return 0;
            if (pwd.length >= 6) score += 20;
            if (pwd.length >= 8) score += 10;
            if (/[a-z]/.test(pwd)) score += 20;
            if (/[A-Z]/.test(pwd)) score += 20;
            if (/[0-9]/.test(pwd)) score += 15;
            if (/[^A-Za-z0-9]/.test(pwd)) score += 15;
            return Math.min(score, 100);
        }

        function updatePwdMeter(pwd){
            var fill = document.getElementById('pwdFill');
            var text = document.getElementById('pwdText');
            if(!fill || !text) return;
            var s = scorePassword(pwd);
            fill.style.width = s + '%';
            fill.className = 'pwd-fill';
            if(s < 40){ fill.classList.add('pwd-weak'); text.textContent = 'Weak'; }
            else if(s < 70){ fill.classList.add('pwd-fair'); text.textContent = 'Fair'; }
            else { fill.classList.add('pwd-good'); text.textContent = 'Strong'; }
        }

        document.addEventListener('DOMContentLoaded', function(){
            var form = document.getElementById('signupForm');
            var pwdField = document.getElementById('password');
            var chkLength = document.getElementById('chkLength');
            var chkLower = document.getElementById('chkLower');
            var chkUpper = document.getElementById('chkUpper');
            var chkNumber = document.getElementById('chkNumber');
            var chkSpecial = document.getElementById('chkSpecial');
            var pwdToggle = document.getElementById('pwdToggle');
            var pwdFillEl = document.getElementById('pwdFill');

            function updateChecklist(pwd){
                if(!pwd) pwd = '';
                toggleClass(chkLength, pwd.length >= 6);
                toggleClass(chkLower, /[a-z]/.test(pwd));
                toggleClass(chkUpper, /[A-Z]/.test(pwd));
                toggleClass(chkNumber, /[0-9]/.test(pwd));
                toggleClass(chkSpecial, /[^A-Za-z0-9]/.test(pwd));
            }

            function toggleClass(el, isValid){ if(!el) return; if(isValid) el.classList.add('valid'); else el.classList.remove('valid'); }

            if(pwdField){
                pwdField.addEventListener('input', function(){
                    updatePwdMeter(pwdField.value);
                    updateChecklist(pwdField.value);
                });
            }

            if(pwdToggle && pwdField){
                pwdToggle.addEventListener('click', function(){
                    if(pwdField.type === 'password'){ pwdField.type = 'text'; pwdToggle.textContent = '🙈'; }
                    else { pwdField.type = 'password'; pwdToggle.textContent = '👁️'; }
                });
            }

            if(!form) return;
            form.addEventListener('submit', function(e){
                var errors = [];
                var first = form.querySelector('[name="first_name"]').value.trim();
                var last = form.querySelector('[name="last_name"]').value.trim();
                var email = form.querySelector('[name="email"]').value.trim();
                var phone = form.querySelector('[name="phone"]').value.trim();
                var pwd = form.querySelector('[name="password"]').value;
                var cpwd = form.querySelector('[name="confirm_password"]').value;
                var student = form.querySelector('[name="student_id"]').value.trim();
                var terms = document.getElementById('terms');

                if(!first) errors.push('First name is required');
                if(!last) errors.push('Last name is required');
                if(!email) errors.push('Email is required');
                else {
                    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(?:\".+\"))@(([^<>()[\]\\.,;:\s@\"]+\.)+[^<>()[\]\\.,;:\s@\"]{2,})$/i;
                    if(!re.test(email)) errors.push('Please enter a valid email address');
                }
                if(!phone) errors.push('Phone number is required');
                if(pwd.length < 6) errors.push('Password must be at least 6 characters');
                if(pwd !== cpwd) errors.push('Passwords do not match');
                if(student && !/^ST\d{5,}$/.test(student)) errors.push('If provided, Student ID must match format e.g. ST50001');
                if(terms && !terms.checked) errors.push('You must accept the terms and conditions');

                if(errors.length){
                    e.preventDefault();
                    showClientErrors(errors);
                    window.scrollTo(0, document.getElementById('clientMessage').offsetTop - 20);
                }
            });
        });
    })();
    </script>
</body>

</html>




