<?php
// Navigation helper - displays login/signup or user profile based on session
// Include in HTML templates to show dynamic navigation

if (!function_exists('display_auth_navbar')) {
    function display_auth_navbar() {
        $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        $user_name = $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : '';
        $user_role = $is_logged_in ? htmlspecialchars($_SESSION['user_role']) : '';
        
        $html = '<div class="ed-com-t1-right">';
        
        if ($is_logged_in) {
            // Show logged-in user menu
            $html .= '<ul>';
            $html .= '<li><a href="#!" class="dropdown-button" data-activates="user-dropdown">';
            $html .= '<i class="fa fa-user"></i> ' . $user_name;
            $html .= '<i class="fa fa-caret-down"></i></a></li>';
            $html .= '</ul>';
            
            // Dropdown menu
            $html .= '<ul id="user-dropdown" class="dropdown-content">';
            if ($user_role === 'student') {
                $html .= '<li><a href="dashboard.html">My Dashboard</a></li>';
                $html .= '<li><a href="db-properties.html">My Properties</a></li>';
                $html .= '<li><a href="db-exams.html">My Exams</a></li>';
                $html .= '<li><a href="db-profile.html">My Profile</a></li>';
            } elseif (in_array($user_role, array('admin', 'teacher', 'staff', 'landlord'))) {
                $html .= '<li><a href="admin-dashboard-modern.php">Admin Dashboard</a></li>';
                $html .= '<li><a href="admin-user-all.php">Manage Users</a></li>';
                $html .= '<li><a href="admin-properties.php">Manage Properties</a></li>';
            }
            $html .= '<li class="divider"></li>';
            $html .= '<li><a href="logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>';
            $html .= '</ul>';
        } else {
            // Show login/signup links
            $html .= '<ul>';
            $html .= '<li><a href="login.php">Sign In</a></li>';
            $html .= '<li><a href="signup.php">Sign Up</a></li>';
            $html .= '</ul>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('display_mobile_auth_menu')) {
    function display_mobile_auth_menu() {
        $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        $user_name = $is_logged_in ? htmlspecialchars($_SESSION['user_name']) : '';
        $user_role = $is_logged_in ? htmlspecialchars($_SESSION['user_role']) : '';
        
        $html = '<h4>User Account</h4>';
        $html .= '<ul>';
        
        if ($is_logged_in) {
            $html .= '<li><strong>Logged in as: ' . $user_name . '</strong></li>';
            if ($user_role === 'student') {
                $html .= '<li><a href="dashboard.html">My Dashboard</a></li>';
                $html .= '<li><a href="db-properties.html">My Properties</a></li>';
                $html .= '<li><a href="db-exams.html">My Exams</a></li>';
                $html .= '<li><a href="db-profile.html">My Profile</a></li>';
            } elseif (in_array($user_role, array('admin', 'teacher', 'staff', 'landlord'))) {
                $html .= '<li><a href="admin-dashboard-modern.php">Admin Dashboard</a></li>';
                $html .= '<li><a href="admin-user-all.php">Manage Users</a></li>';
                $html .= '<li><a href="admin-all-properties.html">Manage Properties</a></li>';
            }
            $html .= '<li><a href="logout.php">Logout</a></li>';
        } else {
            $html .= '<li><a href="login.php">Sign In</a></li>';
            $html .= '<li><a href="signup.php">Register</a></li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }
}
?>




