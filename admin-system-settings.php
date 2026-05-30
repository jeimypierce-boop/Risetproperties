<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';
require_once 'settings_model.php';

ensure_settings_tables($conn);

$section = $_GET['section'] ?? 'overview';
$sections = [
    'overview' => 'System Settings Overview',
    'business-profile' => 'Business Profile',
    'services' => 'Services',
    'role-permissions' => 'Role & Permissions',
    'banks' => 'Banks',
    'payment-modes' => 'Payment Modes',
    'subscription-plan' => 'Subscription Plan',
    'billing-invoices' => 'Billing Invoices',
    'upgrade' => 'Upgrade Plan',
    'generate-invoices' => 'Generate Invoices',
    'regenerate-month' => 'Regenerate Month',
];

$sectionTitle = isset($sections[$section]) ? $sections[$section] : 'System Settings';

$success_msg = '';
$error_msg = '';

$businessProfile = get_business_profile($conn);
$serviceTypes = get_service_types($conn);
$paymentModes = get_payment_modes($conn);
$bankAccounts = get_bank_accounts($conn);
$subscription = get_subscription_plan($conn);
$invoiceJobs = get_latest_invoice_jobs($conn, 5);
$createdBy = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'save_business_profile':
            $business_name = trim($_POST['business_name'] ?? '');
            $business_email = trim($_POST['business_email'] ?? '');
            $business_phone = trim($_POST['business_phone'] ?? '');
            $business_address = trim($_POST['business_address'] ?? '');
            $business_about = trim($_POST['business_about'] ?? '');

            if ($business_name === '' || $business_email === '' || $business_phone === '' || $business_address === '') {
                $error_msg = 'Please complete all business profile fields.';
            } elseif (!filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = 'Please enter a valid business email address.';
            } else {
                if (save_business_profile($conn, [
                    'business_name' => $business_name,
                    'business_email' => $business_email,
                    'business_phone' => $business_phone,
                    'business_address' => $business_address,
                    'business_about' => $business_about,
                ])) {
                    $success_msg = 'Business profile saved successfully.';
                    $businessProfile = get_business_profile($conn);
                } else {
                    $error_msg = 'Unable to save business profile. Please try again.';
                }
            }
            break;

        case 'add_service_type':
            $service_name = trim($_POST['service_name'] ?? '');
            if ($service_name === '') {
                $error_msg = 'Please enter a service name.';
            } elseif (add_service_type($conn, $service_name)) {
                $success_msg = 'Service type added successfully.';
                $serviceTypes = get_service_types($conn);
            } else {
                $error_msg = 'Unable to add service type. It may already exist.';
            }
            break;

        case 'add_payment_mode':
            $payment_mode_name = trim($_POST['payment_mode_name'] ?? '');
            if ($payment_mode_name === '') {
                $error_msg = 'Please enter a payment mode name.';
            } elseif (add_payment_mode($conn, $payment_mode_name)) {
                $success_msg = 'Payment mode added successfully.';
                $paymentModes = get_payment_modes($conn);
            } else {
                $error_msg = 'Unable to add payment mode. It may already exist.';
            }
            break;

        case 'add_bank_account':
            $bank_name = trim($_POST['bank_name'] ?? '');
            $account_number = trim($_POST['account_number'] ?? '');
            $account_name = trim($_POST['account_name'] ?? '');
            if ($bank_name === '' || $account_number === '' || $account_name === '') {
                $error_msg = 'Please provide all bank account details.';
            } elseif (add_bank_account($conn, $bank_name, $account_number, $account_name)) {
                $success_msg = 'Bank account added successfully.';
                $bankAccounts = get_bank_accounts($conn);
            } else {
                $error_msg = 'Unable to add bank account. Please try again.';
            }
            break;

        case 'save_subscription_plan':
            $plan_name = trim($_POST['plan_name'] ?? '');
            $amount = trim($_POST['plan_amount'] ?? '');
            $status = trim($_POST['plan_status'] ?? '');
            $valid_until = trim($_POST['plan_valid_until'] ?? '');

            if ($plan_name === '' || $amount === '' || $status === '' || $valid_until === '') {
                $error_msg = 'Please complete all subscription plan fields.';
            } elseif (!is_numeric($amount) || $amount < 0) {
                $error_msg = 'Please enter a valid monthly amount.';
            } else {
                if (save_subscription_plan($conn, $plan_name, $amount, $status, $valid_until)) {
                    $success_msg = 'Subscription plan saved successfully.';
                    $subscription = get_subscription_plan($conn);
                } else {
                    $error_msg = 'Unable to save subscription plan. Please try again.';
                }
            }
            break;

        case 'generate_invoices':
        case 'regenerate_month':
            $invoice_month = trim($_POST['invoice_month'] ?? '');
            if ($invoice_month === '') {
                $error_msg = 'Please select a month first.';
            } else {
                $jobType = $action === 'generate_invoices' ? 'generate' : 'regenerate';
                if (record_invoice_job($conn, $jobType, $invoice_month, $createdBy)) {
                    $success_msg = $jobType === 'generate' ? 'Invoice generation requested successfully.' : 'Invoice regeneration requested successfully.';
                    $invoiceJobs = get_latest_invoice_jobs($conn, 5);
                } else {
                    $error_msg = 'Unable to record invoice job. Please try again.';
                }
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>System Settings - Riset Property Ltd</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
                    <a class='waves-effect btn-noti' href="admin-Property-enquiry.html" title="Property enquiry messages"><i class="fa fa-envelope-o" aria-hidden="true"></i><span>5</span></a>
                    <a class='waves-effect btn-noti' href="admin-admission-enquiry.html" title="Tenant Enquiry"><i class="fa fa-tag" aria-hidden="true"></i><span>5</span></a>
                </div>
            </div>
            <div class="col-md-2 col-sm-3 col-xs-6">
                <a class='waves-effect dropdown-button top-user-pro' href='#' data-activates='top-menu'><img src="images/user/6.png" alt="" /> My Account <i class="fa fa-angle-down" aria-hidden="true"></i>
                </a>
                <ul id='top-menu' class='dropdown-content top-menu-sty'>
                    <li><a href="admin-system-settings.php?section=overview" class="waves-effect"><i class="fa fa-cogs" aria-hidden="true"></i> Admin Settings</a>
                    </li>
                    <li class="divider"></li>
                    <li><a href="logout.php" class="ho-dr-con-last waves-effect"><i class="fa fa-sign-in" aria-hidden="true"></i> Logout</a>
                    </li>
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
                        <li>
                            <h5>System Settings <span>Manage business and billing settings</span></h5>
                        </li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a></li>
                        <li><a href="admin-system-settings.php?section=overview" class="<?php echo $section === 'overview' ? 'menu-active' : ''; ?>"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a></li>
                        <li><a href="admin-system-settings.php?section=business-profile" class="<?php echo $section === 'business-profile' ? 'menu-active' : ''; ?>"><i class="fa fa-building" aria-hidden="true"></i> Business Profile</a></li>
                        <li><a href="admin-system-settings.php?section=services" class="<?php echo $section === 'services' ? 'menu-active' : ''; ?>"><i class="fa fa-wrench" aria-hidden="true"></i> Services</a></li>
                        <li><a href="admin-system-settings.php?section=role-permissions" class="<?php echo $section === 'role-permissions' ? 'menu-active' : ''; ?>"><i class="fa fa-users" aria-hidden="true"></i> Role & Permissions</a></li>
                        <li><a href="admin-system-settings.php?section=banks" class="<?php echo $section === 'banks' ? 'menu-active' : ''; ?>"><i class="fa fa-bank" aria-hidden="true"></i> Banks</a></li>
                        <li><a href="admin-system-settings.php?section=payment-modes" class="<?php echo $section === 'payment-modes' ? 'menu-active' : ''; ?>"><i class="fa fa-credit-card" aria-hidden="true"></i> Payment Modes</a></li>
                        <li><a href="admin-system-settings.php?section=subscription-plan" class="<?php echo $section === 'subscription-plan' ? 'menu-active' : ''; ?>"><i class="fa fa-star" aria-hidden="true"></i> Subscription Plan</a></li>
                        <li><a href="admin-system-settings.php?section=billing-invoices" class="<?php echo $section === 'billing-invoices' ? 'menu-active' : ''; ?>"><i class="fa fa-file-text" aria-hidden="true"></i> Billing Invoices</a></li>
                        <li><a href="admin-system-settings.php?section=upgrade" class="<?php echo $section === 'upgrade' ? 'menu-active' : ''; ?>"><i class="fa fa-arrow-up" aria-hidden="true"></i> Upgrade Plan</a></li>
                        <li><a href="admin-system-settings.php?section=generate-invoices" class="<?php echo $section === 'generate-invoices' ? 'menu-active' : ''; ?>"><i class="fa fa-file" aria-hidden="true"></i> Generate Invoices</a></li>
                        <li><a href="admin-system-settings.php?section=regenerate-month" class="<?php echo $section === 'regenerate-month' ? 'menu-active' : ''; ?>"><i class="fa fa-refresh" aria-hidden="true"></i> Regenerate Month</a></li>
                    </ul>
                </div>
            </div>
            <div class="sb2-2">
                <div class="sb2-2-2">
                    <ul>
                        <li><a href="admin-dashboard-modern.php"><i class="fa fa-home" aria-hidden="true"></i> Home</a></li>
                        <li class="active-bre"><a href="#"><?php echo htmlspecialchars($sectionTitle); ?></a></li>
                        <li class="page-back"><a href="admin-dashboard-modern.php"><i class="fa fa-backward" aria-hidden="true"></i> Back</a></li>
                    </ul>
                </div>
                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4><?php echo htmlspecialchars($sectionTitle); ?></h4>
                                    <p>Use this area to manage your agency settings, subscription, banks, and billing workflows.</p>
                                </div>
                                <div class="tab-inn">
                                    <?php if ($success_msg): ?>
                                        <div class="card-panel green lighten-4 green-text text-darken-4"><?php echo htmlspecialchars($success_msg); ?></div>
                                    <?php endif; ?>
                                    <?php if ($error_msg): ?>
                                        <div class="card-panel red lighten-4 red-text text-darken-4"><?php echo htmlspecialchars($error_msg); ?></div>
                                    <?php endif; ?>

                                    <?php if ($section === 'overview'): ?>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="box-inn-sp admin-form">
                                                    <h5>Subscription & Billing</h5>
                                                    <p><strong>Plan:</strong> <?php echo htmlspecialchars($subscription['plan_name']); ?></p>
                                                    <p><strong>Amount:</strong> KES <?php echo htmlspecialchars($subscription['amount']); ?> / month</p>
                                                    <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($subscription['status'])); ?></p>
                                                    <p><strong>Valid Until:</strong> <?php echo htmlspecialchars($subscription['valid_until']); ?></p>
                                                    <a href="admin-subscription-plan.php" class="waves-effect waves-light btn">Manage subscription</a>
                                                    <a href="admin-invoice-tools.php" class="waves-effect waves-light btn">Invoice tools</a>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="box-inn-sp admin-form">
                                                    <h5>Agency Settings</h5>
                                                    <p><strong>Services:</strong> <?php echo count($serviceTypes); ?></p>
                                                    <p><strong>Payment modes:</strong> <?php echo count($paymentModes); ?></p>
                                                    <p><strong>Banks:</strong> <?php echo count($bankAccounts); ?></p>
                                                    <a href="admin-system-settings.php?section=business-profile" class="waves-effect waves-light btn">Business profile</a>
                                                    <a href="admin-system-settings.php?section=services" class="waves-effect waves-light btn">Services</a>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="box-inn-sp admin-form">
                                                    <h5>Payments</h5>
                                                    <p>Keep payment setup updated for tenant collections.</p>
                                                    <a href="admin-system-settings.php?section=banks" class="waves-effect waves-light btn">Banks</a>
                                                    <a href="admin-system-settings.php?section=payment-modes" class="waves-effect waves-light btn">Payment modes</a>
                                                    <a href="admin-system-settings.php?section=generate-invoices" class="waves-effect waves-light btn">Generate invoices</a>
                                                </div>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'business-profile'): ?>
                                        <form method="post">
                                            <input type="hidden" name="action" value="save_business_profile">
                                            <div class="row">
                                                <div class="input-field col s6">
                                                    <input id="business_name" name="business_name" type="text" class="validate" value="<?php echo htmlspecialchars($businessProfile['business_name']); ?>">
                                                    <label for="business_name" class="active">Business name</label>
                                                </div>
                                                <div class="input-field col s6">
                                                    <input id="business_email" name="business_email" type="email" class="validate" value="<?php echo htmlspecialchars($businessProfile['business_email']); ?>">
                                                    <label for="business_email" class="active">Business email</label>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="input-field col s6">
                                                    <input id="business_phone" name="business_phone" type="text" class="validate" value="<?php echo htmlspecialchars($businessProfile['business_phone']); ?>">
                                                    <label for="business_phone" class="active">Phone number</label>
                                                </div>
                                                <div class="input-field col s6">
                                                    <input id="business_address" name="business_address" type="text" class="validate" value="<?php echo htmlspecialchars($businessProfile['business_address']); ?>">
                                                    <label for="business_address" class="active">Business address</label>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="input-field col s12">
                                                    <textarea id="business_about" name="business_about" class="materialize-textarea"><?php echo htmlspecialchars($businessProfile['business_about']); ?></textarea>
                                                    <label for="business_about" class="active">About the agency</label>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <button type="submit" class="waves-effect waves-light btn">Save business profile</button>
                                                </div>
                                            </div>
                                        </form>

                                    <?php elseif ($section === 'services'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Service Types</h5>
                                                <p>Add or update the service types your agency offers.</p>
                                                <ul class="collection">
                                                    <?php if (empty($serviceTypes)): ?>
                                                        <li class="collection-item">No service types configured yet.</li>
                                                    <?php else: ?>
                                                        <?php foreach ($serviceTypes as $service): ?>
                                                            <li class="collection-item"><?php echo htmlspecialchars($service['name']); ?></li>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                            <div class="col-md-12">
                                                <form method="post">
                                                    <input type="hidden" name="action" value="add_service_type">
                                                    <div class="row">
                                                        <div class="input-field col s8">
                                                            <input id="service_name" name="service_name" type="text" class="validate" value="<?php echo isset($_POST['service_name']) ? htmlspecialchars($_POST['service_name']) : ''; ?>">
                                                            <label for="service_name" class="active">New service type</label>
                                                        </div>
                                                        <div class="input-field col s4">
                                                            <button type="submit" class="waves-effect waves-light btn">Add service</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'role-permissions'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Roles & Permissions</h5>
                                                <p>Define what each user role can access in the system.</p>
                                                <table class="striped responsive-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Role</th>
                                                            <th>Access</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Owner</td>
                                                            <td>Full access to all modules and settings</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Property Manager</td>
                                                            <td>Manage properties, tenants, invoices, and reports</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Agent</td>
                                                            <td>Manage viewings, enquiries, and tenant communications</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <button type="button" class="waves-effect waves-light btn">Edit role permissions</button>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'banks'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Bank Accounts</h5>
                                                <p>Manage the accounts used for tenant collections and payments.</p>
                                                <table class="striped responsive-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Bank</th>
                                                            <th>Account Number</th>
                                                            <th>Account Name</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($bankAccounts)): ?>
                                                            <tr>
                                                                <td colspan="3">No bank accounts configured yet.</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($bankAccounts as $bank): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($bank['bank_name']); ?></td>
                                                                    <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
                                                                    <td><?php echo htmlspecialchars($bank['account_name']); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="col-md-12">
                                                <form method="post">
                                                    <input type="hidden" name="action" value="add_bank_account">
                                                    <div class="row">
                                                        <div class="input-field col s4">
                                                            <input id="bank_name" name="bank_name" type="text" class="validate" value="<?php echo isset($_POST['bank_name']) ? htmlspecialchars($_POST['bank_name']) : ''; ?>">
                                                            <label for="bank_name" class="active">Bank name</label>
                                                        </div>
                                                        <div class="input-field col s4">
                                                            <input id="account_number" name="account_number" type="text" class="validate" value="<?php echo isset($_POST['account_number']) ? htmlspecialchars($_POST['account_number']) : ''; ?>">
                                                            <label for="account_number" class="active">Account number</label>
                                                        </div>
                                                        <div class="input-field col s4">
                                                            <input id="account_name" name="account_name" type="text" class="validate" value="<?php echo isset($_POST['account_name']) ? htmlspecialchars($_POST['account_name']) : ''; ?>">
                                                            <label for="account_name" class="active">Account name</label>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="waves-effect waves-light btn">Add bank account</button>
                                                </form>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'payment-modes'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Payment Modes</h5>
                                                <p>Manage accepted payment methods for tenants.</p>
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
                                            <div class="col-md-12">
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

                                    <?php elseif ($section === 'subscription-plan'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Subscription Plan</h5>
                                                <p>Review and update your current subscription details.</p>
                                                <div class="row">
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Plan</strong>
                                                        <p><?php echo htmlspecialchars($subscription['plan_name']); ?></p>
                                                    </div>
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Amount</strong>
                                                        <p>KES <?php echo htmlspecialchars($subscription['amount']); ?> / month</p>
                                                    </div>
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Status</strong>
                                                        <p><?php echo htmlspecialchars(ucfirst($subscription['status'])); ?></p>
                                                    </div>
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Valid Until</strong>
                                                        <p><?php echo htmlspecialchars($subscription['valid_until']); ?></p>
                                                    </div>
                                                </div>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="save_subscription_plan">
                                                    <div class="row">
                                                        <div class="input-field col s6">
                                                            <input id="plan_name" name="plan_name" type="text" class="validate" value="<?php echo htmlspecialchars($subscription['plan_name']); ?>">
                                                            <label for="plan_name" class="active">Plan Name</label>
                                                        </div>
                                                        <div class="input-field col s6">
                                                            <input id="plan_amount" name="plan_amount" type="number" min="0" class="validate" value="<?php echo htmlspecialchars($subscription['amount']); ?>">
                                                            <label for="plan_amount" class="active">Monthly Amount (KES)</label>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="input-field col s6">
                                                            <select id="plan_status" name="plan_status">
                                                                <option value="active" <?php echo $subscription['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="trial" <?php echo $subscription['status'] === 'trial' ? 'selected' : ''; ?>>Trial</option>
                                                                <option value="paused" <?php echo $subscription['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                                            </select>
                                                            <label for="plan_status">Subscription Status</label>
                                                        </div>
                                                        <div class="input-field col s6">
                                                            <input id="plan_valid_until" name="plan_valid_until" type="date" class="validate" value="<?php echo htmlspecialchars($subscription['valid_until']); ?>">
                                                            <label for="plan_valid_until" class="active">Valid Until</label>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <button type="submit" class="waves-effect waves-light btn">Save subscription plan</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'billing-invoices'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Billing Invoices</h5>
                                                <p>Review recent invoice jobs and access billing history.</p>
                                                <table class="striped responsive-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Type</th>
                                                            <th>Month</th>
                                                            <th>Status</th>
                                                            <th>Created</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (empty($invoiceJobs)): ?>
                                                            <tr>
                                                                <td colspan="4">No invoice jobs have been recorded yet.</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php foreach ($invoiceJobs as $job): ?>
                                                                <tr>
                                                                    <td><?php echo ucfirst(htmlspecialchars($job['job_type'])); ?></td>
                                                                    <td><?php echo htmlspecialchars($job['target_month']); ?></td>
                                                                    <td><?php echo htmlspecialchars($job['status']); ?></td>
                                                                    <td><?php echo htmlspecialchars($job['created_at']); ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                                <a href="admin-invoice-tools.php" class="waves-effect waves-light btn">Open invoice tools</a>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'upgrade'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Upgrade Plan</h5>
                                                <p>Choose a higher plan if you need more capacity or premium features.</p>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="box-inn-sp admin-form">
                                                            <h6>Starter</h6>
                                                            <p>KES 5,000 / month</p>
                                                            <a href="admin-subscription-plan.php" class="waves-effect waves-light btn">Select Starter</a>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="box-inn-sp admin-form">
                                                            <h6>Growth</h6>
                                                            <p>KES 10,000 / month</p>
                                                            <a href="admin-subscription-plan.php" class="waves-effect waves-light btn">Select Growth</a>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="box-inn-sp admin-form">
                                                            <h6>Enterprise</h6>
                                                            <p>Custom pricing</p>
                                                            <a href="admin-subscription-plan.php" class="waves-effect waves-light btn">Contact Sales</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'generate-invoices'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Generate Monthly Invoices</h5>
                                                <p>Create rent billing for the selected month.</p>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="generate_invoices">
                                                    <div class="row">
                                                        <div class="input-field col s4">
                                                            <input id="invoice_month" name="invoice_month" type="month" class="validate" value="<?php echo date('Y-m'); ?>">
                                                            <label for="invoice_month" class="active">Invoice month</label>
                                                        </div>
                                                        <div class="input-field col s8">
                                                            <button type="submit" class="waves-effect waves-light btn">Generate invoices</button>
                                                            <a href="admin-invoice-tools.php" class="waves-effect waves-light btn grey">Invoice tools</a>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                    <?php elseif ($section === 'regenerate-month'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Regenerate Month</h5>
                                                <p>Reset invoices for a selected month and rebuild them cleanly.</p>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="regenerate_month">
                                                    <div class="row">
                                                        <div class="input-field col s6">
                                                            <input id="invoice_month" name="invoice_month" type="month" class="validate" value="<?php echo date('Y-m'); ?>">
                                                            <label for="invoice_month" class="active">Select month</label>
                                                        </div>
                                                        <div class="input-field col s6">
                                                            <button type="submit" class="waves-effect waves-light btn red">Regenerate month</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                    <?php else: ?>
                                        <p>This settings section does not exist.</p>
                                    <?php endif; ?>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var elems = document.querySelectorAll('select');
            if (typeof M !== 'undefined' && elems.length) {
                M.FormSelect.init(elems);
            }
        });
    </script>
</body>

</html>

