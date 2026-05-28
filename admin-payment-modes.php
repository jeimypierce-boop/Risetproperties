<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
require_once 'settings_model.php';

ensure_settings_tables($conn);

$success_msg = '';
$error_msg = '';
$paymentModes = get_payment_modes($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment_mode') {
    $mode_name = trim($_POST['payment_mode_name'] ?? '');
    if ($mode_name === '') {
        $error_msg = 'Please enter a payment mode name.';
    } else {
        if (add_payment_mode($conn, $mode_name)) {
            $success_msg = 'Payment mode added successfully.';
            $paymentModes = get_payment_modes($conn);
        } else {
            $error_msg = 'Unable to add payment mode. It may already exist.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment Modes - Riset Property Ltd</title>
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
                    <a class='waves-effect btn-noti' href="admin-Property-enquiry.html" title="Property enquiry messages"><i class="fa fa-envelope-o" aria-hidden="true"></i><span>5</span></a>
                    <a class='waves-effect btn-noti' href="admin-admission-enquiry.html" title="Tenant Enquiry"><i class="fa fa-tag" aria-hidden="true"></i><span>5</span></a>
                </div>
            </div>
            <div class="col-md-2 col-sm-3 col-xs-6">
                <a class='waves-effect dropdown-button top-user-pro' href='#' data-activates='top-menu'><img src="images/user/6.png" alt="" />My Account <i class="fa fa-angle-down" aria-hidden="true"></i></a>
                <ul id='top-menu' class='dropdown-content top-menu-sty'>
                    <li><a href="admin-system-settings.php?section=overview" class="waves-effect"><i class="fa fa-cogs" aria-hidden="true"></i>Admin Setting</a></li>
                    <li class="divider"></li>
                    <li><a href="logout.php" class="ho-dr-con-last waves-effect"><i class="fa fa-sign-in" aria-hidden="true"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="container-fluid sb2">
        <div class="row">
            <div class="sb2-1">
                <div class="sb2-12">
                    <ul>
                        <li><img src="images/placeholder.jpg" alt=""></li>
                        <li><h5>Payment Modes <span>Manage accept payments</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-system-settings.php?section=overview"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a></li>
                        <li><a href="admin-payment-modes.php" class="menu-active"><i class="fa fa-credit-card" aria-hidden="true"></i> Payment Modes</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Payment Modes</a></li>
                        <li class="page-back"><a href="admin-system-settings.php?section=overview"><i class="fa fa-backward" aria-hidden="true"></i> Back</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4>Payment Modes</h4>
                                    <p>Manage the payment methods available to your tenants.</p>
                                </div>
                                <div class="tab-inn">
                                    <?php if ($success_msg): ?>
                                        <div class="card-panel green lighten-4 green-text text-darken-4"><?php echo $success_msg; ?></div>
                                    <?php endif; ?>
                                    <?php if ($error_msg): ?>
                                        <div class="card-panel red lighten-4 red-text text-darken-4"><?php echo $error_msg; ?></div>
                                    <?php endif; ?>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <ul class="collection">
                                                <?php if (empty($paymentModes)): ?>
                                                    <li class="collection-item">No payment modes configured yet.</li>
                                                <?php else: ?>
                                                    <?php foreach ($paymentModes as $mode): ?>
                                                        <li class="collection-item"><?php echo htmlspecialchars($mode['name']); ?></li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="add_payment_mode">
                                        <div class="row">
                                            <div class="input-field col s8">
                                                <input id="payment_mode_name" name="payment_mode_name" type="text" class="validate" value="<?php echo isset($_POST['payment_mode_name']) ? htmlspecialchars($_POST['payment_mode_name']) : ''; ?>">
                                                <label for="payment_mode_name" class="active">New payment mode</label>
                                            </div>
                                            <div class="input-field col s4">
                                                <button type="submit" class="waves-effect waves-light btn">Add payment mode</button>
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
    <script src="js/admin-session.js"></script>
</body>
</html>
