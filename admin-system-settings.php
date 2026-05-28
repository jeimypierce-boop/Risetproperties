<?php
require_once 'dbconnect.php';
require_once 'auth_check.php';

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
            <div class="col-md-2 col-sm-3 col-xs-6">
                <a class='waves-effect dropdown-button top-user-pro' href='#' data-activates='top-menu'><img src="images/user/6.png" alt="" />My Account <i class="fa fa-angle-down" aria-hidden="true"></i>
                </a>
                <ul id='top-menu' class='dropdown-content top-menu-sty'>
                    <li><a href="admin-system-settings.php?section=overview" class="waves-effect"><i class="fa fa-cogs" aria-hidden="true"></i>Admin Setting</a>
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
                        <li><img src="images/placeholder.jpg" alt="">
                        </li>
                        <li>
                            <h5>My Account <span>Guest</span></h5>
                        </li>
                        <li></li>
                    </ul>
                </div>
                <div class="sb2-13">
                    <ul class="collapsible" data-collapsible="accordion">
                        <li><a href="admin-dashboard-modern.php" class="menu-active"><i class="fa fa-bar-chart" aria-hidden="true"></i> Dashboard</a>
                        </li>
                        <li><a href="admin-system-settings.php?section=overview"><i class="fa fa-cogs" aria-hidden="true"></i> Site Setting</a>
                        </li>
                        <li><a href="javascript:void(0)" class="collapsible-header"><i class="fa fa-book" aria-hidden="true"></i> All Properties</a>
                            <div class="collapsible-body left-sub-menu">
                                <ul>
                                    <li><a href="admin-properties.php">All Properties</a>
                                    </li>
                                    <li><a href="admin-add-property.php">Add New Property</a>
                                    </li>
                                    <li><a href="admin-trash-Properties.html">Trash Property</a>
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
                        <li class="active-bre"><a href="#"> Settings</a>
                        </li>
                        <li class="page-back"><a href="index-2.html"><i class="fa fa-backward" aria-hidden="true"></i> Back</a>
                        </li>
                    </ul>
                </div>
                <div class="sb2-2-3">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="box-inn-sp admin-form">
                                <div class="inn-title">
                                    <h4><?php echo htmlspecialchars($sectionTitle); ?></h4>
                                    <p>Use the controls below to manage your system settings, business profile, billing, and billing workflows.</p>
                                </div>
                                <div class="tab-inn">
                                    <?php if ($section === 'overview'): ?>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="box-inn-sp admin-form">
                                                    <h5>Subscription & Billing</h5>
                                                    <p>View plan details, invoices, and upgrade options.</p>
                                                    <a href="admin-subscription-plan.php" class="waves-effect waves-light btn">Plan details</a>
                                                    <a href="admin-billing-invoices.php" class="waves-effect waves-light btn">Invoices</a>
                                                    <a href="admin-subscription-plan.php" class="waves-effect waves-light btn">Upgrade</a>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="box-inn-sp admin-form">
                                                    <h5>Agency Settings</h5>
                                                    <p>Manage business profile, services, and roles.</p>
                                                    <a href="admin-business-profile.php" class="waves-effect waves-light btn">Business Profile</a>
                                                    <a href="admin-services.php" class="waves-effect waves-light btn">Services</a>
                                                    <a href="admin-role-permissions.php" class="waves-effect waves-light btn">Roles & Permissions</a>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="box-inn-sp admin-form">
                                                    <h5>Payments</h5>
                                                    <p>Configure banks, payment modes, and invoice generation.</p>
                                                    <a href="admin-banks.php" class="waves-effect waves-light btn">Banks</a>
                                                    <a href="admin-payment-modes.php" class="waves-effect waves-light btn">Payment Modes</a>
                                                    <a href="admin-invoice-tools.php" class="waves-effect waves-light btn">Invoice tools</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'business-profile'): ?>
                                        <form>
                                            <div class="row">
                                                <div class="input-field col s6">
                                                    <input id="business_name" type="text" class="validate" value="Riset Property Ltd">
                                                    <label for="business_name" class="active">Business name</label>
                                                </div>
                                                <div class="input-field col s6">
                                                    <input id="business_email" type="email" class="validate" value="info@risetproperties.co.ke">
                                                    <label for="business_email" class="active">Business email</label>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="input-field col s6">
                                                    <input id="business_phone" type="text" class="validate" value="+254 700 000 000">
                                                    <label for="business_phone" class="active">Phone number</label>
                                                </div>
                                                <div class="input-field col s6">
                                                    <input id="business_address" type="text" class="validate" value="Nairobi, Kenya">
                                                    <label for="business_address" class="active">Business address</label>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="input-field col s12">
                                                    <textarea id="business_about" class="materialize-textarea">Describe the agency, location and service scope.</textarea>
                                                    <label for="business_about" class="active">About</label>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <button type="button" class="waves-effect waves-light btn">Save business profile</button>
                                                </div>
                                            </div>
                                        </form>
                                    <?php elseif ($section === 'services'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Service Types</h5>
                                                <p>Add or update the service types your agency offers.</p>
                                                <ul class="collection">
                                                    <li class="collection-item">Rent Collection</li>
                                                    <li class="collection-item">Maintenance</li>
                                                    <li class="collection-item">Viewings</li>
                                                    <li class="collection-item">Tenant Onboarding</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="input-field col s8">
                                                        <input id="new_service" type="text" class="validate">
                                                        <label for="new_service">New service name</label>
                                                    </div>
                                                    <div class="input-field col s4">
                                                        <button type="button" class="waves-effect waves-light btn">Add service</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'role-permissions'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Roles & Permissions</h5>
                                                <p>Define which pages and actions each role can access.</p>
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
                                                            <td>Full access</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Property Manager</td>
                                                            <td>Manage properties, tenants, invoices</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Agent</td>
                                                            <td>Viewings, enquiries, reports</td>
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
                                                <p>Manage bank names, account details and default accounts.</p>
                                                <table class="striped responsive-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Bank</th>
                                                            <th>Account Number</th>
                                                            <th>Account Name</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Equity Bank</td>
                                                            <td>01-2345678</td>
                                                            <td>Riset Property Ltd</td>
                                                        </tr>
                                                        <tr>
                                                            <td>Co-operative Bank</td>
                                                            <td>12-3456789</td>
                                                            <td>Riset Property Ltd</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="input-field col s4">
                                                        <input id="bank_name" type="text" class="validate">
                                                        <label for="bank_name">Bank name</label>
                                                    </div>
                                                    <div class="input-field col s4">
                                                        <input id="account_number" type="text" class="validate">
                                                        <label for="account_number">Account number</label>
                                                    </div>
                                                    <div class="input-field col s4">
                                                        <input id="account_name" type="text" class="validate">
                                                        <label for="account_name">Account name</label>
                                                    </div>
                                                </div>
                                                <button type="button" class="waves-effect waves-light btn">Add bank account</button>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'payment-modes'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Payment Methods</h5>
                                                <p>Manage accepted payment modes for tenants.</p>
                                                <ul class="collection">
                                                    <li class="collection-item">M-PESA</li>
                                                    <li class="collection-item">Bank Transfer</li>
                                                    <li class="collection-item">Cash</li>
                                                    <li class="collection-item">Credit Card</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="row">
                                                    <div class="input-field col s8">
                                                        <input id="payment_mode_name" type="text" class="validate">
                                                        <label for="payment_mode_name">New payment mode</label>
                                                    </div>
                                                    <div class="input-field col s4">
                                                        <button type="button" class="waves-effect waves-light btn">Add payment mode</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'subscription-plan'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Subscription Plan</h5>
                                                <p>Review current plan details and update your subscription.</p>
                                                <div class="row">
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Selected Plan</strong>
                                                        <p>Starter Plan</p>
                                                    </div>
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Billed At</strong>
                                                        <p>KES 5,000 / month</p>
                                                    </div>
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Status</strong>
                                                        <p>Trial</p>
                                                    </div>
                                                    <div class="col-md-3 col-sm-6">
                                                        <strong>Valid Until</strong>
                                                        <p>2026-05-31</p>
                                                    </div>
                                                </div>
                                                <button type="button" class="waves-effect waves-light btn">Change plan</button>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'billing-invoices'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Billing Invoices</h5>
                                                <p>Review generated billing invoices and access invoice records.</p>
                                                <table class="striped responsive-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Invoice #</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                            <th>Issued</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>INV-2026-001</td>
                                                            <td>KES 5,000</td>
                                                            <td>Paid</td>
                                                            <td>2026-05-01</td>
                                                        </tr>
                                                        <tr>
                                                            <td>INV-2026-002</td>
                                                            <td>KES 5,000</td>
                                                            <td>Pending</td>
                                                            <td>2026-06-01</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'upgrade'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Upgrade Plan</h5>
                                                <p>Choose a higher plan if you need more features or capacity.</p>
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <div class="box-inn-sp admin-form">
                                                            <h6>Starter</h6>
                                                            <p>KES 5,000 / month</p>
                                                            <button type="button" class="waves-effect waves-light btn">Select Starter</button>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="box-inn-sp admin-form">
                                                            <h6>Growth</h6>
                                                            <p>KES 10,000 / month</p>
                                                            <button type="button" class="waves-effect waves-light btn">Select Growth</button>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="box-inn-sp admin-form">
                                                            <h6>Enterprise</h6>
                                                            <p>Custom pricing</p>
                                                            <button type="button" class="waves-effect waves-light btn">Contact Sales</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'generate-invoices'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Generate Monthly Invoices</h5>
                                                <p>Create rent invoices for a selected month.</p>
                                                <form>
                                                    <div class="row">
                                                        <div class="input-field col s4">
                                                            <input id="invoice_month" type="month" class="validate" value="2026-06">
                                                            <label for="invoice_month" class="active">Select month</label>
                                                        </div>
                                                        <div class="input-field col s4">
                                                            <p>Auto-generate invoices every 5 minutes.</p>
                                                            <label>
                                                                <input type="checkbox" checked>
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                        <div class="input-field col s4">
                                                            <button type="button" class="waves-effect waves-light btn">Generate Invoices</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php elseif ($section === 'regenerate-month'): ?>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h5>Regenerate Month</h5>
                                                <p>Removes invoices for the selected month and rebuilds clean rent invoices.</p>
                                                <form>
                                                    <div class="row">
                                                        <div class="input-field col s6">
                                                            <input id="regenerate_month" type="month" class="validate" value="2026-06">
                                                            <label for="regenerate_month" class="active">Choose month</label>
                                                        </div>
                                                        <div class="input-field col s6">
                                                            <button type="button" class="waves-effect waves-light btn red">Regenerate Month</button>
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
</body>

</html>
