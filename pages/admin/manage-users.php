<?php
// pages/admin/manage-users.php

// Go up TWO levels from pages/admin/ to root
$root_dir = dirname(dirname(__DIR__));

// Check if files exist
require_once $root_dir . '/includes/config.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Admin') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$current_admin_id = $_SESSION['user_id'];
$current_admin_name = $_SESSION['user_name'];

// Get database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$error = '';
$success = '';
$edit_mode = false;
$edit_user = null;

// Function to log admin actions
function log_admin_action($conn, $action, $details, $target_user_id = null) {
    global $current_admin_id;
    
    $stmt = $conn->prepare("INSERT INTO admin_activity_log (admin_id, action, details, movie_id) VALUES (?, ?, ?, ?)");
    // We'll use NULL for movie_id since this is user management
    $stmt->bind_param("issi", $current_admin_id, $action, $details, $target_user_id);
    $stmt->execute();
    $stmt->close();
}

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

// ADD ADMIN ONLY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = htmlspecialchars(trim($_POST['password']));
    $confirm_password = htmlspecialchars(trim($_POST['confirm_password']));
    $role = 'Admin'; // Only Admin can be added here
    
    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required!";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if email exists
        $check_stmt = $conn->prepare("SELECT u_id FROM users WHERE u_email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user as Admin
            $stmt = $conn->prepare("INSERT INTO users (u_name, u_email, u_pass, u_role, u_status) VALUES (?, ?, ?, ?, 'Active')");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;
                $success = "New Admin added successfully! ID: " . $new_user_id;
                
                // Log the action
                log_admin_action($conn, 'ADD_ADMIN', "Added new admin: $name ($email)", $new_user_id);
                
                $_POST = array(); // Clear form
            } else {
                $error = "Failed to add admin: " . $conn->error;
            }
            
            $stmt->close();
        }
        
        $check_stmt->close();
    }
}

// UPDATE ADMIN (Only admins can be edited here)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $id = intval($_POST['id']);
    $status = htmlspecialchars(trim($_POST['status']));
    
    // Get user info before update
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    // Only allow editing if user is an Admin
    if ($user_data['u_role'] !== 'Admin') {
        $error = "You can only edit other administrators!";
    } 
    // Prevent self-deactivation
    elseif ($id == $current_admin_id && $status == 'Inactive') {
        $error = "You cannot deactivate your own account!";
    } 
    // Prevent editing of customer accounts
    else {
        $stmt = $conn->prepare("UPDATE users SET u_status = ? WHERE u_id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            $success = "Admin updated successfully!";
            
            // Log the action
            log_admin_action($conn, 'UPDATE_ADMIN', "Updated admin: {$user_data['u_name']} ({$user_data['u_email']}) - Status: $status", $id);
        } else {
            $error = "Failed to update admin: " . $stmt->error;
        }
        $stmt->close();
    }
}

// RESET ADMIN PASSWORD (Only for admins)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_admin_password'])) {
    $id = intval($_POST['id']);
    $new_password = htmlspecialchars(trim($_POST['new_password']));
    $confirm_password = htmlspecialchars(trim($_POST['confirm_password']));
    
    // Get user info
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (empty($new_password)) {
        $error = "Password cannot be empty!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters!";
    } 
    // Only allow reset for Admins
    elseif ($user_data['u_role'] !== 'Admin') {
        $error = "You can only reset passwords for other administrators!";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET u_pass = ? WHERE u_id = ?");
        $stmt->bind_param("si", $hashed_password, $id);
        
        if ($stmt->execute()) {
            $success = "Admin password reset successfully!";
            
            // Log the action
            log_admin_action($conn, 'RESET_ADMIN_PASSWORD', "Reset password for admin: {$user_data['u_name']} ({$user_data['u_email']})", $id);
        } else {
            $error = "Failed to reset password: " . $stmt->error;
        }
        $stmt->close();
    }
}

// DELETE USER (Only admins can delete other admins)
elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Get user info before deletion
    $user_stmt = $conn->prepare("SELECT u_name, u_email, u_role FROM users WHERE u_id = ?");
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data) {
        $error = "User not found!";
    } 
    // Prevent self-deletion
    elseif ($id == $current_admin_id) {
        $error = "You cannot delete your own account!";
    } 
    // Only allow deletion of Admins (by Admins)
    elseif ($user_data['u_role'] !== 'Admin') {
        $error = "You can only delete other administrators! Customer accounts cannot be deleted.";
    } else {
        // Soft delete - set status to Inactive
        $stmt = $conn->prepare("UPDATE users SET u_status = 'Inactive' WHERE u_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Admin deleted successfully!";
            
            // Log the action
            log_admin_action($conn, 'DELETE_ADMIN', "Deleted admin: {$user_data['u_name']} ({$user_data['u_email']})", $id);
        } else {
            $error = "Failed to delete admin: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

// Get all users for listing
$users_result = $conn->query("
    SELECT u_id, u_name, u_email, u_role, u_status, created_at
    FROM users 
    ORDER BY 
        CASE u_role 
            WHEN 'Admin' THEN 1 
            WHEN 'Customer' THEN 2 
            ELSE 3 
        END,
        created_at DESC
");

$users = [];
$admins = [];
$customers = [];

if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
        if ($row['u_role'] === 'Admin') {
            $admins[] = $row;
        } else {
            $customers[] = $row;
        }
    }
}

// Get admin activity log
$activity_log = [];
$log_result = $conn->query("
    SELECT al.*, u.u_name as admin_name
    FROM admin_activity_log al
    LEFT JOIN users u ON al.admin_id = u.u_id
    WHERE al.action LIKE '%ADMIN%' OR al.action LIKE '%USER%'
    ORDER BY al.created_at DESC
    LIMIT 20
");

if ($log_result) {
    while ($row = $log_result->fetch_assoc()) {
        $activity_log[] = $row;
    }
}

// Get user counts
$admin_count = count($admins);
$customer_count = count($customers);
$active_admin_count = 0;
$active_customer_count = 0;

foreach ($admins as $admin) {
    if ($admin['u_status'] == 'Active') $active_admin_count++;
}

foreach ($customers as $customer) {
    if ($customer['u_status'] == 'Active') $active_customer_count++;
}

$total_users = count($users);

// Check if we're in edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT u_id, u_name, u_email, u_role, u_status, created_at
        FROM users 
        WHERE u_id = ? AND u_role = 'Admin'
    ");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_user = $result->fetch_assoc();
        $edit_mode = !empty($edit_user);
        $stmt->close();
    }
}

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 100%);
            color: white; min-height: 100vh; padding: 20px;
        }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .admin-header { 
            text-align: center; margin-bottom: 40px; padding: 20px;
            background: rgba(26,26,46,0.8); border-radius: 15px;
            border: 1px solid rgba(255,215,0,0.3);
        }
        .admin-title { color: #ffd700; font-size: 2.5rem; margin-bottom: 10px; }
        .admin-subtitle { color: rgba(255,255,255,0.7); font-size: 1.1rem; }
        .admin-section { 
            background: rgba(26,26,46,0.8); border-radius: 15px; padding: 30px;
            border: 1px solid rgba(255,215,0,0.3); margin-bottom: 30px;
        }
        .section-title { 
            color: white; font-size: 1.5rem; margin-bottom: 25px; padding-bottom: 15px;
            border-bottom: 2px solid #ffd700; display: flex; align-items: center; gap: 10px;
        }
        .stats-container {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px; margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255,255,255,0.05); border-radius: 10px; padding: 20px;
            text-align: center; border: 1px solid rgba(255,215,0,0.1);
        }
        .stat-number { font-size: 2rem; font-weight: bold; color: #ffd700; }
        .stat-label { color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-top: 5px; }
        .alert {
            padding: 15px 20px; border-radius: 8px; margin-bottom: 20px;
            font-weight: 600; text-align: center;
        }
        .alert-success { 
            background: rgba(40,167,69,0.2); color: #d4edda;
            border: 1px solid rgba(40,167,69,0.3);
        }
        .alert-danger { 
            background: rgba(220,53,69,0.2); color: #f8d7da;
            border: 1px solid rgba(220,53,69,0.3);
        }
        .alert-info { 
            background: rgba(23,162,184,0.2); color: #d1ecf1;
            border: 1px solid rgba(23,162,184,0.3);
        }
        .alert-warning { 
            background: rgba(255,193,7,0.2); color: #fff3cd;
            border: 1px solid rgba(255,193,7,0.3);
        }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: white; }
        .form-control { 
            width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,215,0,0.3); border-radius: 8px;
            color: white; font-size: 1rem;
        }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23ffd700' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 15px center; background-size: 16px; }
        .btn { 
            padding: 12px 25px; border-radius: 8px; font-weight: 600;
            cursor: pointer; text-decoration: none; display: inline-flex;
            align-items: center; justify-content: center; gap: 8px;
            transition: all 0.3s ease; border: none;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%);
            color: #333;
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #ffaa00 0%, #ff8800 100%);
            transform: translateY(-2px);
        }
        .btn-secondary { 
            background: rgba(255,255,255,0.1); color: white;
            border: 1px solid rgba(255,215,0,0.3);
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.2); }
        .table-responsive { overflow-x: auto; border-radius: 10px; border: 1px solid rgba(255,215,0,0.2); margin-bottom: 20px; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .data-table th { 
            background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%); color: #333;
            padding: 14px; text-align: left; font-weight: 700;
        }
        .data-table td { 
            padding: 14px; border-bottom: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
        }
        .status-badge { 
            padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .status-active { background: rgba(40,167,69,0.2); color: #28a745; }
        .status-inactive { background: rgba(108,117,125,0.2); color: #6c757d; }
        .role-badge { 
            padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .role-admin { background: rgba(255,215,0,0.2); color: #ffd700; border: 1px solid rgba(255,215,0,0.3); }
        .role-customer { background: rgba(0,123,255,0.2); color: #007bff; border: 1px solid rgba(0,123,255,0.3); }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
        .empty-state { 
            text-align: center; padding: 40px; color: rgba(255,255,255,0.6);
        }
        .modal { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center;
            align-items: center; padding: 20px;
        }
        .modal-content {
            background: #1a1a2e; border-radius: 15px; padding: 30px;
            max-width: 500px; width: 100%; border: 1px solid rgba(255,215,0,0.3);
            max-height: 80vh; overflow-y: auto;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(255,215,0,0.3);
        }
        .modal-title { color: #ffd700; font-size: 1.3rem; }
        .close-modal { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .activity-log {
            margin-top: 30px;
        }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table th { 
            background: rgba(255,215,0,0.2); color: #ffd700; padding: 10px; text-align: left;
            font-weight: 600; border-bottom: 2px solid rgba(255,215,0,0.3);
        }
        .log-table td { 
            padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.8); font-size: 0.9rem;
        }
        .log-action {
            background: rgba(255,215,0,0.1); padding: 4px 8px; border-radius: 4px;
            font-size: 0.8rem; font-weight: 600;
        }
        @media (max-width: 768px) {
            .admin-container { padding: 10px; }
            .admin-section { padding: 20px; }
            .action-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">Manage Users</h1>
            <p class="admin-subtitle">Administrator Management Panel</p>
            <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem; margin-top: 10px;">
                Logged in as: <strong style="color: #ffd700;"><?php echo $current_admin_name; ?></strong>
            </p>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $admin_count; ?></div>
                <div class="stat-label">Total Admins</div>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                    <?php echo $active_admin_count; ?> active
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $customer_count; ?></div>
                <div class="stat-label">Total Customers</div>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.5);">
                    <?php echo $active_customer_count; ?> active
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Admin Form -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="fas fa-user-plus"></i> Add New Administrator
            </h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 20px; font-size: 0.95rem;">
                <i class="fas fa-info-circle"></i> Note: Only administrators can be added through this panel. 
                Customers must register through the public registration page.
            </p>
            
            <form method="POST" action="" id="adminForm">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               class="form-control" placeholder="Enter admin's full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               class="form-control" placeholder="Enter admin's email">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required 
                               class="form-control" placeholder="At least 6 characters">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               class="form-control" placeholder="Confirm password">
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 30px;">
                    <button type="submit" name="add_admin" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-plus"></i> Add Administrator
                    </button>
                </div>
            </form>
        </div>

        <!-- Administrators List -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="fas fa-crown"></i> Administrators (<?php echo $admin_count; ?>)
            </h2>
            
            <?php if (empty($admins)): ?>
            <div class="empty-state">
                <i class="fas fa-crown fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p>No administrators found. Add your first admin!</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Admin Details</th>
                            <th>Account Info</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): 
                            $is_current_user = $admin['u_id'] == $current_admin_id;
                        ?>
                        <tr style="<?php echo $is_current_user ? 'background: rgba(255,215,0,0.05);' : ''; ?>">
                            <td><?php echo $admin['u_id']; ?></td>
                            <td>
                                <strong style="color: white; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($admin['u_name']); ?>
                                    <?php if ($is_current_user): ?>
                                    <span style="color: #ffd700; font-size: 0.8rem;">(You)</span>
                                    <?php endif; ?>
                                </strong>
                                <div style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-top: 5px;">
                                    <?php echo htmlspecialchars($admin['u_email']); ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="role-badge role-admin">
                                        <i class="fas fa-crown"></i>
                                        Administrator
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-top: 8px;">
                                    <i class="far fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($admin['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $admin['u_status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fas <?php echo $admin['u_status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $admin['u_status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm" 
                                            style="background: rgba(66, 153, 225, 0.2); color: #4299e1; border: 1px solid rgba(66, 153, 225, 0.3);"
                                            onclick="openEditModal(<?php echo $admin['u_id']; ?>, '<?php echo addslashes($admin['u_name']); ?>', '<?php echo $admin['u_status']; ?>', <?php echo $is_current_user ? 'true' : 'false'; ?>)"
                                            <?php echo $is_current_user ? 'disabled' : ''; ?>>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm" 
                                            style="background: rgba(23, 162, 184, 0.2); color: #17a2b8; border: 1px solid rgba(23, 162, 184, 0.3);"
                                            onclick="openResetPasswordModal(<?php echo $admin['u_id']; ?>, '<?php echo addslashes($admin['u_name']); ?>')"
                                            <?php echo $is_current_user ? 'disabled' : ''; ?>>
                                        <i class="fas fa-key"></i> Reset Password
                                    </button>
                                    
                                    <a href="index.php?page=admin/manage-users&delete=<?php echo $admin['u_id']; ?>" 
                                       class="btn btn-sm" style="background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3);"
                                       onclick="return confirm('Are you sure you want to delete admin \'<?php echo addslashes($admin['u_name']); ?>\'?\nThis will deactivate their account.')"
                                       <?php echo $is_current_user ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Customers List (Read-only) -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="fas fa-users"></i> Customers (<?php echo $customer_count; ?>)
            </h2>
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 20px; font-size: 0.95rem;">
                <i class="fas fa-info-circle"></i> Note: Customer accounts are view-only. They cannot be edited or deleted by administrators.
            </p>
            
            <?php if (empty($customers)): ?>
            <div class="empty-state">
                <i class="fas fa-users fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p>No customers found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Details</th>
                            <th>Account Info</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo $customer['u_id']; ?></td>
                            <td>
                                <strong style="color: white; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($customer['u_name']); ?>
                                </strong>
                                <div style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-top: 5px;">
                                    <?php echo htmlspecialchars($customer['u_email']); ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <span class="role-badge role-customer">
                                        <i class="fas fa-user"></i>
                                        Customer
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-top: 8px;">
                                    <i class="far fa-calendar"></i> Joined: <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $customer['u_status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fas <?php echo $customer['u_status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $customer['u_status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Admin Activity Log -->
        <div class="admin-section activity-log">
            <h2 class="section-title">
                <i class="fas fa-history"></i> Recent Admin Actions
            </h2>
            
            <?php if (empty($activity_log)): ?>
            <div class="empty-state" style="padding: 20px;">
                <i class="fas fa-history fa-2x" style="margin-bottom: 10px; opacity: 0.5;"></i>
                <p>No recent admin actions found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity_log as $log): ?>
                        <tr>
                            <td>
                                <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                            </td>
                            <td>
                                <span class="log-action">
                                    <?php 
                                    $action_map = [
                                        'ADD_ADMIN' => 'Added Admin',
                                        'UPDATE_ADMIN' => 'Updated Admin',
                                        'RESET_ADMIN_PASSWORD' => 'Reset Password',
                                        'DELETE_ADMIN' => 'Deleted Admin'
                                    ];
                                    echo $action_map[$log['action']] ?? $log['action'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($log['details']); ?>
                            </td>
                            <td>
                                <strong style="color: #ffd700;"><?php echo $log['admin_name']; ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Administrator</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="" id="editForm">
                <input type="hidden" name="id" id="editUserId">
                <input type="hidden" name="update_admin" value="1">
                
                <div class="form-group">
                    <label>Admin Name</label>
                    <input type="text" id="editUserName" class="form-control" readonly style="background: rgba(255,255,255,0.05);">
                </div>
                
                <div class="form-group">
                    <label for="editUserStatus">Status *</label>
                    <select id="editUserStatus" name="status" required class="form-control">
                        <option value="">Select Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                
                <div id="selfEditWarning" class="alert alert-warning" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i> You cannot edit your own account!
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reset Admin Password</h3>
                <button class="close-modal" onclick="closeResetPasswordModal()">&times;</button>
            </div>
            <form method="POST" action="" id="resetPasswordForm">
                <input type="hidden" name="id" id="resetUserId">
                <input type="hidden" name="reset_admin_password" value="1">
                
                <div class="form-group">
                    <label id="resetUserNameLabel"></label>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required 
                           class="form-control" placeholder="Enter new password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password *</label>
                    <input type="password" id="confirm_new_password" name="confirm_password" required 
                           class="form-control" placeholder="Confirm new password">
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Modal Functions
    function openEditModal(userId, userName, userStatus, isCurrentUser) {
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUserName').value = userName;
        document.getElementById('editUserStatus').value = userStatus;
        
        const warningDiv = document.getElementById('selfEditWarning');
        if (isCurrentUser) {
            warningDiv.style.display = 'block';
            document.getElementById('editUserStatus').disabled = true;
        } else {
            warningDiv.style.display = 'none';
            document.getElementById('editUserStatus').disabled = false;
        }
        
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    function openResetPasswordModal(userId, userName) {
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetUserNameLabel').innerHTML = 
            `<strong>Admin:</strong> ${userName}<br>
             <small style="color: rgba(255,255,255,0.6);">Enter new password for this administrator</small>`;
        
        document.getElementById('resetPasswordModal').style.display = 'flex';
    }
    
    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').style.display = 'none';
    }
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const editModal = document.getElementById('editModal');
        const resetModal = document.getElementById('resetPasswordModal');
        
        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == resetModal) {
            closeResetPasswordModal();
        }
    }
    
    // Form Validation
    document.getElementById('adminForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const email = document.getElementById('email').value;
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters!');
            return false;
        }
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        // Basic email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            alert('Please enter a valid email address!');
            return false;
        }
        
        return true;
    });
    </script>
</body>
</html>