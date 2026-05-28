<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
require_once 'settings_model.php';

ensure_settings_tables($conn);

$success_msg = '';
$error_msg = '';
$invoiceMonth = date('Y-m');
$invoiceJobs = get_latest_invoice_jobs($conn, 5);
$createdBy = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_month'])) {
    $invoiceMonth = trim($_POST['invoice_month']);
    $action = $_POST['action'] ?? '';
    if ($invoiceMonth === '') {
        $error_msg = 'Please select a month before submitting.';
    } elseif ($action === 'generate_invoices' || $action === 'regenerate_month') {
        $jobType = $action === 'generate_invoices' ? 'generate' : 'regenerate';
        if (record_invoice_job($conn, $jobType, $invoiceMonth, $createdBy)) {
            $success_msg = $jobType === 'generate' ? 'Invoice generation requested successfully.' : 'Invoice regeneration requested successfully.';
            $invoiceJobs = get_latest_invoice_jobs($conn, 5);
        } else {
            $error_msg = 'Unable to process invoice request. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Invoice Tools - Riset Property Ltd</title>
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
                        <li><h5>Invoice Tools <span>Generate and reissue</span></h5></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-system-settings.php?section=overview"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a></li>
                        <li><a href="admin-invoice-tools.php" class="menu-active"><i class="fa fa-file" aria-hidden="true"></i> Invoice Tools</a></li>
                    </ul>
                </div>
            </div>

            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#">Invoice Tools</a></li>
                        <li class="page-back"><a href="admin-system-settings.php?section=overview"><i class="fa fa-backward" aria-hidden="true"></i> Back</a></li>
                    </ul>
                </div>

                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4>Invoice Tools</h4>
                                    <p>Generate invoices for the selected month or regenerate existing batches.</p>
                                </div>
                                <div class="tab-inn">
                                    <?php if ($success_msg): ?>
                                        <div class="card-panel green lighten-4 green-text text-darken-4"><?php echo $success_msg; ?></div>
                                    <?php endif; ?>
                                    <?php if ($error_msg): ?>
                                        <div class="card-panel red lighten-4 red-text text-darken-4"><?php echo $error_msg; ?></div>
                                    <?php endif; ?>
                                    <form method="post">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="input-field">
                                                    <input id="invoice_month" name="invoice_month" type="month" class="validate" value="<?php echo htmlspecialchars($invoiceMonth); ?>">
                                                    <label for="invoice_month" class="active">Invoice month</label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" name="action" value="generate_invoices" class="waves-effect waves-light btn">Generate invoices</button>
                                            </div>
                                            <div class="col-md-4">
                                                <button type="submit" name="action" value="regenerate_month" class="waves-effect waves-light btn">Regenerate month</button>
                                            </div>
                                        </div>
                                    </form>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <p>Use this tool to produce invoice batches for tenant billing cycles. Regenerating will replace the selected month's invoices.</p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5>Recent invoice jobs</h5>
                                            <ul class="collection">
                                                <?php if (empty($invoiceJobs)): ?>
                                                    <li class="collection-item">No invoice jobs have been recorded yet.</li>
                                                <?php else: ?>
                                                    <?php foreach ($invoiceJobs as $job): ?>
                                                        <li class="collection-item">
                                                            <?php echo ucfirst($job['job_type']); ?> <?php echo htmlspecialchars($job['target_month']); ?> — <?php echo htmlspecialchars($job['status']); ?>
                                                            <span class="secondary-content"><?php echo htmlspecialchars($job['created_at']); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
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
