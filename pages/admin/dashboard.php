<?php
// pages/admin/dashboard.php

// Go up two levels from pages/admin/ to root
$root_dir = dirname(dirname(__DIR__));

// Include config and functions
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

// Include admin header
require_once $root_dir . '/partials/admin-header.php';

// Get database connection
$conn = get_db_connection();

// Get counts
$movie_count = $user_count = $booking_count = $schedule_count = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM movies WHERE is_active = 1");
if ($result) $movie_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE u_status = 'Active'");
if ($result) $user_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM tbl_booking WHERE status = 'Ongoing'");
if ($result) $booking_count = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM movie_schedules WHERE is_active = 1");
if ($result) $schedule_count = $result->fetch_assoc()['count'];

$conn->close();
?>

<div class="admin-content">
    <div class="admin-welcome" style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
            Welcome, <?php echo $_SESSION['user_name']; ?>!
        </h1>
        <p style="color: var(--admin-light); font-size: 1.1rem;">
            Admin Dashboard - Movie Ticketing System
        </p>
    </div>
    
    <div class="admin-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); 
                    padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-film"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $movie_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Active Movies</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); 
                    padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-users"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $user_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Active Users</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); 
                    padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $booking_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Ongoing Bookings</div>
        </div>
        
        <div class="stat-card" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%); 
                    padding: 25px; border-radius: 15px; text-align: center; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; color: var(--admin-accent); margin-bottom: 10px;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div style="font-size: 2.5rem; font-weight: bold; color: white; margin-bottom: 5px;">
                <?php echo $schedule_count; ?>
            </div>
            <div style="color: var(--admin-light); font-size: 1rem;">Total Schedules</div>
        </div>
    </div>
    
    <div class="admin-actions" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-movies" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <i class="fas fa-film fa-2x"></i>
            <span>Manage Movies</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-schedules" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <i class="fas fa-calendar-alt fa-2x"></i>
            <span>Manage Schedules</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>index.php?page=admin/manage-users" 
           class="admin-btn admin-btn-primary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <i class="fas fa-users fa-2x"></i>
            <span>Manage Users</span>
        </a>
        
        <a href="<?php echo SITE_URL; ?>" 
           class="admin-btn admin-btn-secondary" style="padding: 20px; text-align: center; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 10px;">
            <i class="fas fa-home fa-2x"></i>
            <span>View Site</span>
        </a>
    </div>
</div>

</div> <!-- Close admin-main-container from admin-header -->
</body>
</html>