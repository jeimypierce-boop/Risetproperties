<?php
// Forgot password page
require_once 'dbconnect.php';
session_start();

$message = '';
$message_type = ''; // success or error

// Handle forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_submit'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : 'tenant';

    if (empty($email)) {
        $message = 'Please enter your email address';
        $message_type = 'error';
    } else {
        // Check if email exists
        if ($user_type === 'admin') {
            $check_stmt = $conn->prepare("SELECT id, email, first_name FROM users WHERE email = ? AND role IN ('admin', 'teacher', 'staff', 'landlord')");
        } else {
            $check_stmt = $conn->prepare("SELECT id, email, first_name FROM tenants WHERE email = ?");
        }
        
        $check_stmt->bind_param('s', $email);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $reset_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // In a production system, you would:
            // 1. Store this token in database
            // 2. Send email with reset link
            // For demo purposes, we'll show a message
            
            $message = 'Password reset instructions have been sent to your email address. Check your inbox.';
            $message_type = 'success';
            
            // Note: In production, you would store token and send email
            // For now, we'll show demo info
            $message .= '<br><br><small style="color: #999;">Demo: Password reset token would be sent to ' . htmlspecialchars($email) . '</small>';
        } else {
            $message = 'Email address not found in our records';
            $message_type = 'error';
        }
        
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Property Management System - Forgot Password</title>
    <!-- META TAGS -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                        <h4>Forgot password</h4>
                        <p>Enter your email and we will send you a password reset link.</p>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade in" role="alert">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="s12">
                            <div>
                                <div class="input-field s12">
                                    <select name="user_type" id="user_type" class="browser-default" style="display: block; padding: 10px 0;">
                                        <option value="tenant">I'm a Tenant</option>
                                        <option value="admin">I'm an Admin / Landlord</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="input-field s12">
                                    <input type="email" name="email" class="validate" placeholder="Enter your email address" required>
                                    <label>Email Address</label>
                                </div>
                            </div>

                            <div>
                                <div class="input-field s12">
                                    <button type="submit" name="forgot_submit" class="waves-effect waves-light btn" style="width: 100%;">Send Reset Link</button>
                                </div>
                            </div>

                            <div>
                                <div class="input-field s12" style="text-align: center;">
                                    <a href="login.php">Back to Login</a> | 
                                    <a href="signup.php">Create Account</a>
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
</body>

</html>




