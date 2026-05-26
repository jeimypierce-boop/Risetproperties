<?php
// Reusable dynamic top user menu for admin pages
$user_info = get_user_info();
$user_role_label = get_user_role_label();
?>
<div class="col-md-2 col-sm-3 col-xs-6">
    <a class='waves-effect dropdown-button top-user-pro' href='#' data-activates='top-menu'>
        <img src="images/user/6.png" alt="" /><?php echo htmlspecialchars($user_info['name'] ?: 'My Account'); ?> <i class="fa fa-angle-down" aria-hidden="true"></i>
    </a>
    <ul id='top-menu' class='dropdown-content top-menu-sty'>
        <li class="user-info">
            <strong><?php echo htmlspecialchars($user_info['name'] ?: 'Account'); ?></strong><br>
            <small><?php echo htmlspecialchars($user_role_label); ?><?php if (!empty($user_info['email'])) { echo ' · ' . htmlspecialchars($user_info['email']); } ?></small>
        </li>
        <li class="divider"></li>
        <li><a href="logout.php" class="waves-effect"><i class="fa fa-sign-in" aria-hidden="true"></i> Logout</a></li>
    </ul>
</div>

