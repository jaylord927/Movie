<?php
// pages/admin/manage-schedules.php

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

// Get database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$error = '';
$success = '';
$edit_mode = false;
$edit_schedule = null;

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

// ADD SCHEDULE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $movie_id = intval($_POST['movie_id']);
    $show_date = htmlspecialchars(trim($_POST['show_date']));
    $showtime = htmlspecialchars(trim($_POST['showtime']));
    $total_seats = intval($_POST['total_seats']);
    
    // Get movie title for reference
    $movie_stmt = $conn->prepare("SELECT title FROM movies WHERE id = ? AND is_active = 1");
    $movie_stmt->bind_param("i", $movie_id);
    $movie_stmt->execute();
    $movie_result = $movie_stmt->get_result();
    
    if ($movie_result->num_rows === 0) {
        $error = "Selected movie not found or inactive!";
        $movie_stmt->close();
    } else {
        $movie = $movie_result->fetch_assoc();
        $movie_title = $movie['title'];
        $movie_stmt->close();
        
        // Check for duplicate schedule
        $check_stmt = $conn->prepare("SELECT id FROM movie_schedules WHERE movie_id = ? AND show_date = ? AND showtime = ? AND is_active = 1");
        $check_stmt->bind_param("iss", $movie_id, $show_date, $showtime);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Schedule already exists for this movie at the same date and time!";
        } else {
            // Insert the schedule
            $stmt = $conn->prepare("INSERT INTO movie_schedules (movie_id, movie_title, show_date, showtime, total_seats, available_seats, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $available_seats = $total_seats;
            $stmt->bind_param("isssii", $movie_id, $movie_title, $show_date, $showtime, $total_seats, $available_seats);
            
            if ($stmt->execute()) {
                $new_schedule_id = $stmt->insert_id;
                
                // Create seat availability records
                $seat_stmt = $conn->prepare("INSERT INTO seat_availability (schedule_id, movie_title, show_date, showtime, seat_number, is_available) VALUES (?, ?, ?, ?, ?, 1)");
                
                for ($i = 1; $i <= $total_seats; $i++) {
                    $seat_number = "A" . str_pad($i, 2, '0', STR_PAD_LEFT);
                    $seat_stmt->bind_param("issss", $new_schedule_id, $movie_title, $show_date, $showtime, $seat_number);
                    $seat_stmt->execute();
                }
                
                $seat_stmt->close();
                $success = "Schedule added successfully! " . $total_seats . " seats created.";
                $_POST = array(); // Clear form
            } else {
                $error = "Failed to add schedule: " . $conn->error;
            }
            
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// UPDATE SCHEDULE
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedule'])) {
    $id = intval($_POST['id']);
    $movie_id = intval($_POST['movie_id']);
    $show_date = htmlspecialchars(trim($_POST['show_date']));
    $showtime = htmlspecialchars(trim($_POST['showtime']));
    $total_seats = intval($_POST['total_seats']);
    
    // Get current schedule and movie title
    $current_stmt = $conn->prepare("SELECT movie_title FROM movie_schedules WHERE id = ?");
    $current_stmt->bind_param("i", $id);
    $current_stmt->execute();
    $current_result = $current_stmt->get_result();
    $current_schedule = $current_result->fetch_assoc();
    $current_stmt->close();
    
    // Get new movie title if changed
    $movie_stmt = $conn->prepare("SELECT title FROM movies WHERE id = ?");
    $movie_stmt->bind_param("i", $movie_id);
    $movie_stmt->execute();
    $movie_result = $movie_stmt->get_result();
    $movie = $movie_result->fetch_assoc();
    $movie_title = $movie['title'];
    $movie_stmt->close();
    
    // Update the schedule
    $stmt = $conn->prepare("UPDATE movie_schedules SET movie_id = ?, movie_title = ?, show_date = ?, showtime = ?, total_seats = ? WHERE id = ?");
    $stmt->bind_param("isssii", $movie_id, $movie_title, $show_date, $showtime, $total_seats, $id);
    
    if ($stmt->execute()) {
        // Update seat availability if movie title changed
        if ($current_schedule['movie_title'] !== $movie_title) {
            $update_seat_stmt = $conn->prepare("UPDATE seat_availability SET movie_title = ? WHERE schedule_id = ?");
            $update_seat_stmt->bind_param("si", $movie_title, $id);
            $update_seat_stmt->execute();
            $update_seat_stmt->close();
        }
        
        $success = "Schedule updated successfully!";
    } else {
        $error = "Failed to update schedule: " . $stmt->error;
    }
    $stmt->close();
}

// DELETE SCHEDULE
elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if there are bookings for this schedule
    $booking_check = $conn->prepare("
        SELECT COUNT(*) as booking_count 
        FROM tbl_booking b
        WHERE b.movie_name = (SELECT movie_title FROM movie_schedules WHERE id = ?)
        AND b.show_date = (SELECT show_date FROM movie_schedules WHERE id = ?)
        AND b.showtime = (SELECT showtime FROM movie_schedules WHERE id = ?)
        AND b.status != 'Cancelled'
    ");
    $booking_check->bind_param("iii", $id, $id, $id);
    $booking_check->execute();
    $booking_result = $booking_check->get_result();
    $booking_data = $booking_result->fetch_assoc();
    $booking_check->close();
    
    if ($booking_data['booking_count'] > 0) {
        $error = "Cannot delete schedule. There are active bookings for this schedule.";
    } else {
        // Soft delete
        $stmt = $conn->prepare("UPDATE movie_schedules SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Schedule deleted successfully!";
        } else {
            $error = "Failed to delete schedule: " . $stmt->error;
        }
        $stmt->close();
    }
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

// Get all active movies for dropdown
$movies_result = $conn->query("SELECT id, title FROM movies WHERE is_active = 1 ORDER BY title");
$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

// Get all schedules for listing
$schedules_result = $conn->query("
    SELECT s.*, m.title as movie_title_full, 
           (SELECT COUNT(*) FROM tbl_booking b 
            WHERE b.movie_name = s.movie_title 
            AND b.show_date = s.show_date 
            AND b.showtime = s.showtime 
            AND b.status != 'Cancelled') as booking_count
    FROM movie_schedules s
    LEFT JOIN movies m ON s.movie_id = m.id
    WHERE s.is_active = 1 
    ORDER BY s.show_date DESC, s.showtime
");

$schedules = [];
if ($schedules_result) {
    while ($row = $schedules_result->fetch_assoc()) {
        $schedules[] = $row;
    }
}

// Check if we're in edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT s.*, m.title as movie_title_full
        FROM movie_schedules s
        LEFT JOIN movies m ON s.movie_id = m.id
        WHERE s.id = ? AND s.is_active = 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_schedule = $result->fetch_assoc();
        $edit_mode = !empty($edit_schedule);
        $stmt->close();
    }
}

// Get schedule count
$count_result = $conn->query("SELECT COUNT(*) as total FROM movie_schedules WHERE is_active = 1");
$schedule_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Admin Panel</title>
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
        .table-responsive { overflow-x: auto; border-radius: 10px; border: 1px solid rgba(255,215,0,0.2); }
        .data-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .data-table th { 
            background: linear-gradient(135deg, #ffd700 0%, #ffaa00 100%); color: #333;
            padding: 14px; text-align: left; font-weight: 700;
        }
        .data-table td { 
            padding: 14px; border-bottom: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.9);
        }
        .status-badge { 
            padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;
            display: inline-block;
        }
        .status-active { background: rgba(40,167,69,0.2); color: #28a745; }
        .status-full { background: rgba(220,53,69,0.2); color: #dc3545; }
        .status-available { background: rgba(23,162,184,0.2); color: #17a2b8; }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
        .empty-state { 
            text-align: center; padding: 40px; color: rgba(255,255,255,0.6);
        }
        .seat-info { 
            background: rgba(255,215,0,0.1); padding: 10px; border-radius: 5px;
            margin-top: 5px; font-size: 0.9rem;
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
            <h1 class="admin-title">Manage Movie Schedules</h1>
            <p class="admin-subtitle">Add, edit, or remove movie showtimes</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Schedule Form -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
                <?php echo $edit_mode ? 'Edit Schedule' : 'Add New Schedule'; ?>
            </h2>
            
            <?php if ($edit_mode): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Editing schedule for: <strong><?php echo htmlspecialchars($edit_schedule['movie_title_full']); ?></strong>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="scheduleForm">
                <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_schedule['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="movie_id">Movie *</label>
                    <select id="movie_id" name="movie_id" required class="form-control">
                        <option value="">Select Movie</option>
                        <?php foreach ($movies as $movie): ?>
                        <option value="<?php echo $movie['id']; ?>" 
                            <?php echo ($edit_mode && $edit_schedule['movie_id'] == $movie['id']) || (isset($_POST['movie_id']) && $_POST['movie_id'] == $movie['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($movie['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="show_date">Show Date *</label>
                        <input type="date" id="show_date" name="show_date" required 
                               value="<?php echo $edit_mode ? htmlspecialchars($edit_schedule['show_date']) : (isset($_POST['show_date']) ? htmlspecialchars($_POST['show_date']) : ''); ?>"
                               class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="showtime">Show Time *</label>
                        <input type="time" id="showtime" name="showtime" required
                               value="<?php echo $edit_mode ? htmlspecialchars($edit_schedule['showtime']) : (isset($_POST['showtime']) ? htmlspecialchars($_POST['showtime']) : ''); ?>"
                               class="form-control" 
                               min="09:00" max="23:00">
                    </div>
                    
                    <div class="form-group">
                        <label for="total_seats">Total Seats *</label>
                        <input type="number" id="total_seats" name="total_seats" required
                               value="<?php echo $edit_mode ? $edit_schedule['total_seats'] : (isset($_POST['total_seats']) ? $_POST['total_seats'] : '40'); ?>"
                               class="form-control" min="1" max="100" placeholder="Maximum seats">
                        <small style="color: rgba(255,255,255,0.6);">Standard: 40 seats (A01-A40)</small>
                    </div>
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 30px;">
                    <?php if ($edit_mode): ?>
                    <button type="submit" name="update_schedule" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-save"></i> Update Schedule
                    </button>
                    <a href="index.php?page=admin/manage-schedules" class="btn btn-secondary" style="margin-left: 15px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php else: ?>
                    <button type="submit" name="add_schedule" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-plus"></i> Add Schedule
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Schedules List -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="fas fa-calendar-alt"></i> All Schedules (<?php echo $schedule_count; ?>)
            </h2>
            
            <?php if (empty($schedules)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-alt fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p>No schedules found. Add your first schedule!</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Movie Details</th>
                            <th>Show Time</th>
                            <th>Seats</th>
                            <th>Bookings</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): 
                            $available_percentage = ($schedule['available_seats'] / $schedule['total_seats']) * 100;
                            $is_today = date('Y-m-d') == $schedule['show_date'];
                            $is_past = strtotime($schedule['show_date'] . ' ' . $schedule['showtime']) < time();
                        ?>
                        <tr style="<?php echo $is_today ? 'background: rgba(255,215,0,0.05);' : ''; ?>">
                            <td><?php echo $schedule['id']; ?></td>
                            <td>
                                <strong style="color: white; font-size: 1.1rem;"><?php echo htmlspecialchars($schedule['movie_title_full']); ?></strong>
                                <div style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-top: 5px;">
                                    Movie ID: <?php echo $schedule['movie_id']; ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 1.1rem; color: #ffd700; font-weight: 600;">
                                    <?php echo date('h:i A', strtotime($schedule['showtime'])); ?>
                                </div>
                                <div style="color: rgba(255,255,255,0.8); margin-top: 5px;">
                                    <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($schedule['show_date'])); ?>
                                    <?php if ($is_today): ?>
                                    <span style="background: #ffd700; color: #333; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; margin-left: 5px;">TODAY</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_past): ?>
                                <div style="color: #dc3545; font-size: 0.8rem; margin-top: 3px;">
                                    <i class="fas fa-exclamation-triangle"></i> Past show
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="seat-info">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span>Available:</span>
                                        <span style="font-weight: 600;"><?php echo $schedule['available_seats']; ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span>Total:</span>
                                        <span><?php echo $schedule['total_seats']; ?></span>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.1); height: 6px; border-radius: 3px; margin-top: 8px; overflow: hidden;">
                                        <div style="background: <?php echo $available_percentage > 50 ? '#28a745' : ($available_percentage > 20 ? '#ffc107' : '#dc3545'); ?>; 
                                             height: 100%; width: <?php echo $available_percentage; ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; color: <?php echo $schedule['booking_count'] > 0 ? '#ffd700' : 'rgba(255,255,255,0.5)'; ?>;">
                                        <?php echo $schedule['booking_count']; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: rgba(255,255,255,0.6);">Bookings</div>
                                </div>
                            </td>
                            <td>
                                <?php if ($is_past): ?>
                                <span class="status-badge status-full">
                                    <i class="fas fa-clock"></i> Expired
                                </span>
                                <?php elseif ($schedule['available_seats'] == 0): ?>
                                <span class="status-badge status-full">
                                    <i class="fas fa-times-circle"></i> Sold Out
                                </span>
                                <?php elseif ($schedule['available_seats'] < 10): ?>
                                <span class="status-badge status-full" style="background: rgba(220,53,69,0.2); color: #dc3545;">
                                    <i class="fas fa-exclamation"></i> Few Seats
                                </span>
                                <?php else: ?>
                                <span class="status-badge status-active">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?page=admin/manage-schedules&edit=<?php echo $schedule['id']; ?>" 
                                       class="btn btn-sm" style="background: rgba(66, 153, 225, 0.2); color: #4299e1; border: 1px solid rgba(66, 153, 225, 0.3);">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="index.php?page=admin/manage-schedules&delete=<?php echo $schedule['id']; ?>" 
                                       class="btn btn-sm" style="background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3);"
                                       onclick="return confirm('Are you sure you want to delete this schedule?\nMovie: <?php echo addslashes($schedule['movie_title_full']); ?>\nDate: <?php echo date('M d, Y', strtotime($schedule['show_date'])); ?> <?php echo date('h:i A', strtotime($schedule['showtime'])); ?>')">
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
    </div>

    <script>
    // Simple form validation
    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        const movieId = document.getElementById('movie_id').value;
        const showDate = document.getElementById('show_date').value;
        const showtime = document.getElementById('showtime').value;
        const totalSeats = document.getElementById('total_seats').value;
        
        // Check required fields
        if (!movieId || !showDate || !showtime || !totalSeats || parseInt(totalSeats) < 1) {
            e.preventDefault();
            alert('Please fill in all required fields correctly!');
            return false;
        }
        
        // Check if date is in the past
        const selectedDate = new Date(showDate + 'T' + showtime);
        if (selectedDate < new Date()) {
            e.preventDefault();
            if (!confirm('Warning: You are scheduling a show in the past. Continue anyway?')) {
                return false;
            }
        }
        
        return true;
    });
    
    // Set minimum date to today
    document.getElementById('show_date').min = new Date().toISOString().split('T')[0];
    
    // Auto-fill showtime suggestions
    document.getElementById('showtime').addEventListener('focus', function() {
        if (!this.value) {
            this.value = '18:00'; // Default showtime
        }
    });
    </script>
</body>
</html>