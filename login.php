<?php
// Login page for both tenants and admin users
// Connect to database
require_once 'dbconnect.php';
session_start();

// If already logged in, redirect to the correct dashboard
if (isset($_SESSION['user_id'])) {
    if (in_array($_SESSION['user_role'], array('admin', 'teacher', 'staff', 'landlord'))) {
            header('Location: admin-dashboard-modern.php');
    }
    exit;
}

$login_error = '';
$login_success = false;
$tenant_login_notice = '';
if (isset($_GET['target']) && $_GET['target'] === 'tenant') {
    $tenant_login_notice = 'Tenant portal users: sign in with your tenant ID, email, phone or username.';
}

function verify_password($plain_password, $stored_hash) {
    if (password_verify($plain_password, $stored_hash)) {
        return true;
    }

    // Support legacy MySQL PASSWORD() hashes stored in database
    if (preg_match('/^\*[0-9A-F]{40}$/i', $stored_hash)) {
        $legacy_hash = '*' . strtoupper(sha1(sha1($plain_password, true)));
        return hash_equals($legacy_hash, strtoupper($stored_hash));
    }

    // Support existing plaintext passwords in older installs
    if ($plain_password === $stored_hash) {
        return true;
    }

    return false;
}

function rehash_password_if_needed($conn, $plain_password, $stored_hash, $user_id, $isTenant) {
    $needsRehash = false;

    if (password_verify($plain_password, $stored_hash)) {
        $needsRehash = password_needs_rehash($stored_hash, PASSWORD_DEFAULT);
    } elseif ($plain_password === $stored_hash || preg_match('/^\*[0-9A-F]{40}$/i', $stored_hash)) {
        $needsRehash = true;
    }

    if (!$needsRehash) {
        return;
    }

    $newHash = password_hash($plain_password, PASSWORD_DEFAULT);
    if ($isTenant) {
        $update_stmt = $conn->prepare("UPDATE tenants SET password = ? WHERE id = ?");
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    }

    if ($update_stmt) {
        $update_stmt->bind_param('si', $newHash, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validate inputs
    if (empty($username) || empty($password)) {
        $login_error = 'Username/Email and password are required';
    } else {
        // First try users table for admin/landlord/staff/teacher accounts
        $stmt = $conn->prepare("SELECT id, username, email, phone, password, role, first_name, last_name, status FROM users WHERE (username = ? OR email = ? OR phone = ?) LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('sss', $username, $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = false;
            $login_error = 'Database error: ' . $conn->error;
        }

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $allowedRoles = array('admin', 'landlord', 'teacher', 'staff');
            if (in_array(strtolower($user['role']), $allowedRoles, true)) {
                $isTenant = false;
            } else {
                // If the matched user is not an admin/landlord/staff role, continue to tenant lookup
                if ($stmt) {
                    $stmt->close();
                }
                $result = false;
            }
        }

        if (!$result || $result->num_rows !== 1) {
            if ($stmt) {
                $stmt->close();
            }
            $stmt = $conn->prepare("SELECT id, tenant_id, email, phone, password, first_name, last_name, status FROM tenants WHERE (tenant_id = ? OR email = ? OR phone = ?) LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('sss', $username, $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = false;
                $login_error = 'Database error: ' . $conn->error;
            }
            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $user['role'] = 'tenant';
                $isTenant = true;
            }
        }

        if ($result && $result->num_rows === 1) {
            if ($user['status'] !== 'Active') {
                $login_error = 'Your account is inactive. Please contact administrator.';
            } elseif (verify_password($password, $user['password'])) {
                rehash_password_if_needed($conn, $password, $user['password'], $user['id'], $isTenant);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_type'] = $isTenant ? 'tenant' : 'admin';

                if ($isTenant) {
                    $_SESSION['tenant_id'] = $user['id'];
                    $_SESSION['tenant_name'] = $user['first_name'] . ' ' . $user['last_name'];
                } else {
                    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param('i', $user['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                }

                $login_success = true;
                if ($isTenant) {
                    header('Location: tenant-portal/tenant-dashboard.php');
                } else {
                    header('Location: admin-dashboard-modern.php');
                }
                exit;
            } else {
                $login_error = 'Invalid password';
            }
        } else {
            if (empty($login_error)) {
                $login_error = 'User not found';
            }
        }

        if ($stmt) {
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Property Management System - Sign In</title>
    <!-- META TAGS -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Education master login page">
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
                    <a href="index.html"><img src="images/AGENCY LOGO2.png" alt="Agency Logo"></a>
                </div>
                <div class="ad-log-in-con">
                    <div class="log-in-pop-right">
                        <h4>Sign in</h4>
                        
                        <?php if ($tenant_login_notice): ?>
                            <p style="margin-top: 10px; font-size: 14px; color: #555;">Tenant portal users: sign in with your tenant ID, email, phone or username.</p>
                            <div class="alert alert-info alert-dismissible fade in" role="alert">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <?php echo htmlspecialchars($tenant_login_notice); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($login_error): ?>
                            <div class="alert alert-danger alert-dismissible fade in" role="alert">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <strong>Error!</strong> <?php echo htmlspecialchars($login_error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="s12">
                            <div>
                                <div class="input-field s12">
                                    <input type="text" name="username" class="validate" placeholder="Email or Phone" required>
                                    <label class="">Email or Phone</label>
                                </div>
                            </div>

                            <div>
                                <div class="input-field s12">
                                    <input type="password" name="password" class="validate" placeholder="Password" required>
                                    <label>Password</label>
                                </div>
                            </div>

                            <div>
                                <div class="s12 log-ch-bx">
                                    <p>
                                        <input type="checkbox" id="test5" name="remember_me">
                                        <label for="test5">Remember me</label>
                                    </p>
                                </div>
                            </div>

                            <div>
                                <div class="input-field s4">
                                    <button type="submit" name="login_submit" class="waves-effect waves-light btn" style="width: 100%;">Sign in</button>
                                </div>
                            </div>

                            <div>
                                <div class="input-field s12">
                                    <a href="forgot-password.php">Forgot password?</a> | 
                                    <a href="signup.php">Create a new account</a>
                                </div>
                            </div>
                        </form>

                        <hr>
                        <p style="text-align: center; font-size: 12px; margin-top: 20px;">
                            <strong>Demo Credentials:</strong><br>
                            Tenant: ST17241 / demo123<br>
                            Admin / Landlord: admin / admin123
                        </p>
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

</body>

</html>






