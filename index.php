<?php
// index.php - Main Router

// Define ROOT_DIR first
define('ROOT_DIR', dirname(__FILE__));

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config
require_once ROOT_DIR . '/includes/config.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

// Get requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// ============================================
// NEW: REDIRECT ADMINS FROM HOME TO ADMIN-HOME
// ============================================
if ($page === 'home' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin') {
    $page = 'admin-home';
}
// ============================================

// Security: Prevent directory traversal
$page = str_replace(['..', '/\\', '../'], '', $page);

// Define allowed pages
$allowed_pages = [
    'home' => 'pages/home.php',
    'admin-home' => 'pages/admin-home.php',
    'login' => 'pages/login.php',
    'register' => 'pages/register.php',
    'movies' => 'pages/movies.php',
    'logout' => 'pages/logout.php',
    'aboutus' => 'pages/aboutus.php',
    'contactus' => 'pages/contactus.php',
    'privacypolicy_termsservice' => 'pages/privacypolicy_termsservice.php',
    
    // Customer pages
    'customer/browse-movies' => 'pages/customer/browse-movies.php',
    'customer/movie-details' => 'pages/customer/movie-details.php',
    'customer/booking' => 'pages/customer/booking.php',
    'customer/my-bookings' => 'pages/customer/my-bookings.php',
    'customer/payment' => 'pages/customer/payment.php',
    'customer/payment-manual' => 'pages/customer/payment-manual.php',
    'customer/paymongo' => 'pages/customer/paymongo.php',
    'customer/paymongo-success' => 'pages/customer/paymongo-success.php',
    'customer/profile' => 'pages/customer/profile.php',
    'customer/rebook' => 'pages/customer/rebook.php',
    'customer/receipt' => 'pages/customer/receipt.php',
    
    // Admin pages
    'admin/dashboard' => 'pages/admin/dashboard.php',
    'admin/manage-movies' => 'pages/admin/manage-movies.php',
    'admin/manage-schedules' => 'pages/admin/manage-schedules.php',
    'admin/manage-users' => 'pages/admin/manage-users.php',
    'admin/manage-payments' => 'pages/admin/manage-payments.php',
    'admin/manage-suggestions' => 'pages/admin/manage-suggestions.php',
    'admin/manage-seats' => 'pages/admin/manage-seats.php',
    'admin/payment-methods' => 'pages/admin/payment-methods.php',
];          

// Get the actual file path
if (isset($allowed_pages[$page])) {
    $page_file = $allowed_pages[$page];
} else {
    // Try to construct the path based on pattern
    if (strpos($page, 'customer/') === 0) {
        $customer_page = str_replace('customer/', '', $page);
        $page_file = 'pages/customer/' . $customer_page . '.php';
    } elseif (strpos($page, 'admin/') === 0) {
        $admin_page = str_replace('admin/', '', $page);
        $page_file = 'pages/admin/' . $admin_page . '.php';
    } else {
        $page_file = 'pages/' . $page . '.php';
    }
}

// Check if file exists
if (file_exists(ROOT_DIR . '/' . $page_file)) {
    include ROOT_DIR . '/' . $page_file;
} else {
    // Show 404 page
    include ROOT_DIR . '/pages/404.php';
}
?>