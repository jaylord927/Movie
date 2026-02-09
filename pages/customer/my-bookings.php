<?php
// pages/customer/my-bookings.php

// Go up two levels from pages/customer/ to root
$root_dir = dirname(dirname(__DIR__));

// Include config and functions
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';
require_once $root_dir . '/includes/database.php';

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get database connection
$conn = get_db();

// ============================================
// HANDLE ACTIONS
// ============================================
$error = '';
$success = '';

// Cancel booking
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $booking_id = intval($_GET['cancel']);
    
    // Check if booking belongs to user and is ongoing
    $check_stmt = $conn->prepare("
        SELECT b.*, m.title as movie_title 
        FROM tbl_booking b
        LEFT JOIN movies m ON b.movie_name = m.title
        WHERE b.b_id = ? AND b.u_id = ? AND b.status = 'Ongoing'
    ");
    $check_stmt->bind_param("ii", $booking_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "Booking not found or cannot be cancelled!";
    } else {
        $booking = $check_result->fetch_assoc();
        $movie_title = $booking['movie_name'];
        $seat_numbers = $booking['seat_no'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update booking status
            $update_booking = $conn->prepare("
                UPDATE tbl_booking 
                SET status = 'Cancelled', payment_status = 'Refunded' 
                WHERE b_id = ?
            ");
            $update_booking->bind_param("i", $booking_id);
            
            if (!$update_booking->execute()) {
                throw new Exception("Failed to cancel booking!");
            }
            $update_booking->close();
            
            // Get seats from booking
            $seats = explode(', ', $seat_numbers);
            
            // Mark seats as available again for each seat
            foreach ($seats as $seat_number) {
                $seat_update = $conn->prepare("
                    UPDATE seat_availability sa
                    JOIN movie_schedules ms ON sa.schedule_id = ms.id
                    SET sa.is_available = 1, sa.booking_id = NULL
                    WHERE sa.movie_title = ? 
                    AND sa.show_date = ? 
                    AND sa.showtime = ? 
                    AND sa.seat_number = ?
                ");
                $seat_update->bind_param(
                    "ssss",
                    $movie_title,
                    $booking['show_date'],
                    $booking['showtime'],
                    $seat_number
                );
                
                if (!$seat_update->execute()) {
                    throw new Exception("Failed to update seat availability!");
                }
                $seat_update->close();
            }
            
            // Update available seats count
            $update_schedule = $conn->prepare("
                UPDATE movie_schedules 
                SET available_seats = available_seats + ?
                WHERE movie_title = ? 
                AND show_date = ? 
                AND showtime = ?
            ");
            $seat_count = count($seats);
            $update_schedule->bind_param(
                "isss",
                $seat_count,
                $movie_title,
                $booking['show_date'],
                $booking['showtime']
            );
            
            if (!$update_schedule->execute()) {
                throw new Exception("Failed to update schedule!");
            }
            $update_schedule->close();
            
            // Commit transaction
            $conn->commit();
            
            $success = "Booking cancelled successfully! Refund has been processed.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Cancellation failed: " . $e->getMessage();
        }
    }
    $check_stmt->close();
}

// ============================================
// FETCH USER'S BOOKINGS
// ============================================

// Get all bookings for the user with additional movie info
$bookings_stmt = $conn->prepare("
    SELECT 
        b.*,
        m.id as movie_id,
        m.poster_url,
        m.genre,
        m.duration,
        m.rating,
        TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) as hours_until_show,
        CASE 
            WHEN b.status = 'Cancelled' THEN 'cancelled'
            WHEN b.status = 'Done' THEN 'completed'
            WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) <= 0 THEN 'expired'
            WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(b.show_date, ' ', b.showtime)) <= 24 THEN 'upcoming'
            ELSE 'active'
        END as booking_status
    FROM tbl_booking b
    LEFT JOIN movies m ON b.movie_name = m.title
    WHERE b.u_id = ?
    ORDER BY 
        CASE 
            WHEN b.status = 'Ongoing' THEN 1
            WHEN b.status = 'Done' THEN 2
            WHEN b.status = 'Cancelled' THEN 3
            ELSE 4
        END,
        b.show_date DESC,
        b.showtime DESC
");
$bookings_stmt->bind_param("i", $user_id);
$bookings_stmt->execute();
$bookings_result = $bookings_stmt->get_result();

$bookings = [];
$booking_stats = [
    'total' => 0,
    'active' => 0,
    'upcoming' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'expired' => 0
];

while ($row = $bookings_result->fetch_assoc()) {
    $bookings[] = $row;
    $booking_stats['total']++;
    
    if ($row['booking_status'] == 'active') $booking_stats['active']++;
    elseif ($row['booking_status'] == 'upcoming') $booking_stats['upcoming']++;
    elseif ($row['booking_status'] == 'completed') $booking_stats['completed']++;
    elseif ($row['booking_status'] == 'cancelled') $booking_stats['cancelled']++;
    elseif ($row['booking_status'] == 'expired') $booking_stats['expired']++;
}
$bookings_stmt->close();

$conn->close();

// Include header
require_once $root_dir . '/partials/header.php';
?>

<div class="main-container" style="max-width: 1400px; margin: 0 auto; padding: 20px;">
    <!-- Page Header -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1 style="color: white; font-size: 2.2rem; margin-bottom: 10px; font-weight: 800;">
                    <i class="fas fa-receipt"></i> My Bookings
                </h1>
                <p style="color: var(--pale-red); font-size: 1.1rem;">
                    Manage your movie tickets and view booking history
                </p>
            </div>
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" 
                   class="btn btn-primary" style="padding: 12px 25px;">
                    <i class="fas fa-ticket-alt"></i> Book New Movie
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies" 
                   class="btn btn-secondary" style="padding: 12px 25px;">
                    <i class="fas fa-film"></i> Browse Movies
                </a>
            </div>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger" style="background: rgba(226, 48, 32, 0.2); color: #ff9999; 
             padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(226, 48, 32, 0.3);
             display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-circle fa-lg"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success" style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; 
             padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid rgba(46, 204, 113, 0.3);
             display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle fa-lg"></i>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>
    
    <!-- Booking Statistics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.2), rgba(41, 128, 185, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(52, 152, 219, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #3498db; margin-bottom: 5px;">
                <?php echo $booking_stats['total']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Total Bookings</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(46, 204, 113, 0.2), rgba(39, 174, 96, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(46, 204, 113, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #2ecc71; margin-bottom: 5px;">
                <?php echo $booking_stats['active']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Active</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(241, 196, 15, 0.2), rgba(243, 156, 18, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(241, 196, 15, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #f1c40f; margin-bottom: 5px;">
                <?php echo $booking_stats['upcoming']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Upcoming</div>
        </div>
        
        <div style="background: linear-gradient(135deg, rgba(155, 89, 182, 0.2), rgba(142, 68, 173, 0.3)); 
             border-radius: 12px; padding: 20px; border: 1px solid rgba(155, 89, 182, 0.3);">
            <div style="font-size: 2.5rem; font-weight: 800; color: #9b59b6; margin-bottom: 5px;">
                <?php echo $booking_stats['completed']; ?>
            </div>
            <div style="color: white; font-weight: 600; font-size: 1rem;">Completed</div>
        </div>
    </div>
    
    <!-- Bookings List -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
         border-radius: 15px; padding: 25px; margin-bottom: 30px; 
         border: 1px solid rgba(226, 48, 32, 0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h2 style="color: white; font-size: 1.6rem; font-weight: 700;">
                <i class="fas fa-ticket-alt"></i> Booking History
            </h2>
            
            <?php if (!empty($bookings)): ?>
            <div style="color: var(--pale-red); font-size: 0.95rem;">
                Showing <?php echo count($bookings); ?> booking(s)
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 60px 30px;">
            <i class="fas fa-ticket-alt fa-4x" style="color: rgba(255,255,255,0.1); margin-bottom: 20px;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.5rem;">No Bookings Yet</h3>
            <p style="color: var(--pale-red); margin-bottom: 30px; max-width: 500px; margin-left: auto; margin-right: auto;">
                You haven't booked any movies yet. Start your cinematic journey by booking your first movie!
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking" 
                   class="btn btn-primary" style="padding: 15px 30px; font-size: 1.1rem;">
                    <i class="fas fa-ticket-alt"></i> Book Your First Movie
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies" 
                   class="btn btn-secondary" style="padding: 15px 30px; font-size: 1.1rem;">
                    <i class="fas fa-film"></i> Browse Movies
                </a>
            </div>
        </div>
        <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 25px;">
            <?php foreach ($bookings as $booking): 
                $booking_date = date('M d, Y', strtotime($booking['booking_date']));
                $show_date = date('D, M d, Y', strtotime($booking['show_date']));
                $show_time = date('h:i A', strtotime($booking['showtime']));
                $is_cancelled = $booking['status'] == 'Cancelled';
                $is_completed = $booking['status'] == 'Done';
                $is_upcoming = $booking['booking_status'] == 'upcoming';
                $is_expired = $booking['booking_status'] == 'expired';
                
                // Status badge styling
                $status_bg = '';
                $status_color = '';
                $status_icon = '';
                
                if ($is_cancelled) {
                    $status_bg = 'rgba(231, 76, 60, 0.2)';
                    $status_color = '#e74c3c';
                    $status_icon = 'fa-times-circle';
                    $status_text = 'Cancelled';
                } elseif ($is_completed) {
                    $status_bg = 'rgba(46, 204, 113, 0.2)';
                    $status_color = '#2ecc71';
                    $status_icon = 'fa-check-circle';
                    $status_text = 'Completed';
                } elseif ($is_expired) {
                    $status_bg = 'rgba(149, 165, 166, 0.2)';
                    $status_color = '#95a5a6';
                    $status_icon = 'fa-clock';
                    $status_text = 'Expired';
                } elseif ($is_upcoming) {
                    $status_bg = 'rgba(241, 196, 15, 0.2)';
                    $status_color = '#f1c40f';
                    $status_icon = 'fa-clock';
                    $status_text = 'Upcoming';
                } else {
                    $status_bg = 'rgba(52, 152, 219, 0.2)';
                    $status_color = '#3498db';
                    $status_icon = 'fa-ticket-alt';
                    $status_text = 'Active';
                }
                
                // Calculate time remaining
                $time_remaining = '';
                if (!$is_cancelled && !$is_completed && !$is_expired) {
                    $hours = $booking['hours_until_show'];
                    if ($hours > 24) {
                        $days = floor($hours / 24);
                        $time_remaining = "$days day" . ($days > 1 ? 's' : '') . ' left';
                    } elseif ($hours > 0) {
                        $time_remaining = "$hours hour" . ($hours > 1 ? 's' : '') . ' left';
                    }
                }
            ?>
            <div style="background: rgba(255, 255, 255, 0.05); border-radius: 12px; overflow: hidden;
                 border: 1px solid rgba(226, 48, 32, 0.2); transition: all 0.3s ease;">
                <div style="display: flex; gap: 25px; padding: 25px; 
                     <?php echo $is_cancelled ? 'opacity: 0.8;' : ''; ?>
                     <?php echo $is_expired ? 'opacity: 0.7;' : ''; ?>">
                    
                    <!-- Movie Poster -->
                    <div style="flex-shrink: 0;">
                        <?php if (!empty($booking['poster_url'])): ?>
                        <img src="<?php echo $booking['poster_url']; ?>" 
                             alt="<?php echo htmlspecialchars($booking['movie_name']); ?>"
                             style="width: 120px; height: 160px; object-fit: cover; border-radius: 8px;">
                        <?php else: ?>
                        <div style="width: 120px; height: 160px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                             border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-film" style="font-size: 2.5rem; color: rgba(255, 255, 255, 0.3);"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Booking Details -->
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                            <div>
                                <h3 style="color: white; font-size: 1.4rem; margin-bottom: 5px; font-weight: 700;">
                                    <?php echo htmlspecialchars($booking['movie_name']); ?>
                                </h3>
                                
                                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 10px;">
                                    <span style="background: rgba(226, 48, 32, 0.2); color: var(--light-red); 
                                          padding: 4px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 700;">
                                        <?php echo $booking['rating'] ?: 'PG'; ?>
                                    </span>
                                    
                                    <span style="color: var(--pale-red); font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-clock"></i> <?php echo $booking['duration']; ?>
                                    </span>
                                    
                                    <?php if ($booking['genre']): ?>
                                    <span style="color: var(--pale-red); font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-film"></i> <?php echo htmlspecialchars($booking['genre']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Status Badge -->
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                <span style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; 
                                      padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 700;
                                      display: flex; align-items: center; gap: 8px;">
                                    <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                </span>
                                
                                <?php if ($time_remaining && !$is_cancelled && !$is_completed): ?>
                                <span style="color: var(--pale-red); font-size: 0.8rem; font-weight: 600;">
                                    <i class="fas fa-hourglass-half"></i> <?php echo $time_remaining; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Show Details -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Show Date & Time</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem;">
                                    <?php echo $show_time; ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.9rem;"><?php echo $show_date; ?></div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Seats</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem;">
                                    <?php echo htmlspecialchars($booking['seat_no']); ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    <?php echo substr_count($booking['seat_no'], ',') + 1; ?> seat(s)
                                </div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Booking Reference</div>
                                <div style="color: white; font-weight: 600; font-size: 1.1rem; letter-spacing: 1px;">
                                    <?php echo $booking['booking_reference']; ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    Booked on <?php echo $booking_date; ?>
                                </div>
                            </div>
                            
                            <div>
                                <div style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 5px;">Total Amount</div>
                                <div style="color: var(--primary-red); font-weight: 800; font-size: 1.3rem;">
                                    ₱<?php echo number_format($booking['booking_fee'], 2); ?>
                                </div>
                                <div style="color: var(--pale-red); font-size: 0.85rem;">
                                    Payment: 
                                    <span style="color: <?php echo $booking['payment_status'] == 'Paid' ? '#2ecc71' : 
                                                          ($booking['payment_status'] == 'Refunded' ? '#3498db' : '#f39c12'); ?>; 
                                           font-weight: 600;">
                                        <?php echo $booking['payment_status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                            <!-- Print Receipt Button -->
                            <button onclick="printReceipt('<?php echo $booking['b_id']; ?>')" 
                                    class="btn btn-secondary" style="padding: 10px 20px;">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                            
                            <!-- View Movie Button -->
                            <?php if ($booking['movie_id']): ?>
                            <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $booking['movie_id']; ?>" 
                               class="btn btn-secondary" style="padding: 10px 20px;">
                                <i class="fas fa-eye"></i> View Movie
                            </a>
                            <?php endif; ?>
                            
                            <!-- Add Another Ticket Button -->
                            <?php if ($booking['movie_id'] && !$is_cancelled && !$is_completed && !$is_expired): ?>
                            <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $booking['movie_id']; ?>&schedule=<?php echo $booking['b_id']; ?>" 
                               class="btn btn-primary" style="padding: 10px 20px;">
                                <i class="fas fa-plus-circle"></i> Add Another Ticket
                            </a>
                            <?php endif; ?>
                            
                            <!-- Cancel Booking Button -->
                            <?php if (!$is_cancelled && !$is_completed && !$is_expired && $booking['hours_until_show'] > 2): ?>
                            <a href="<?php echo SITE_URL; ?>index.php?page=customer/my-bookings&cancel=<?php echo $booking['b_id']; ?>" 
                               class="btn btn-danger" style="padding: 10px 20px;"
                               onclick="return confirm('Are you sure you want to cancel this booking?\n\nMovie: <?php echo addslashes($booking['movie_name']); ?>\nShow: <?php echo $show_date; ?> <?php echo $show_time; ?>\nSeats: <?php echo addslashes($booking['seat_no']); ?>\n\nA refund will be processed.')">
                                <i class="fas fa-times"></i> Cancel Booking
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- No Bookings Helper -->
    <?php if (empty($bookings)): ?>
    <div style="background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.2)); 
         border-radius: 15px; padding: 40px; text-align: center; margin-top: 30px;
         border: 2px dashed rgba(52, 152, 219, 0.3);">
        <h3 style="color: white; margin-bottom: 20px; font-size: 1.5rem;">
            <i class="fas fa-lightbulb"></i> How to Book a Movie
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-top: 30px;">
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-search"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Browse Movies</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Explore our collection of movies, read synopses, and check ratings
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Select Showtime</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Choose your preferred date and time from available showtimes
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-chair"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Choose Seats</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Select your preferred seats from our interactive seat map
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);">
                <div style="font-size: 2.5rem; color: #3498db; margin-bottom: 15px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">Confirm Booking</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.5;">
                    Review your selection and confirm to complete your booking
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Receipt Modal -->
<div id="receiptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
     background: rgba(0,0,0,0.9); z-index: 9999; padding: 20px; overflow-y: auto;">
    <div id="receiptContent" style="max-width: 800px; margin: 40px auto; background: white; border-radius: 15px; 
         overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <!-- Receipt will be loaded here -->
    </div>
    
    <div style="text-align: center; margin-top: 30px;">
        <button onclick="closeReceipt()" style="background: var(--primary-red); color: white; border: none; 
                padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">
            <i class="fas fa-times"></i> Close
        </button>
        <button onclick="printReceiptContent()" style="background: #3498db; color: white; border: none; 
                padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-left: 15px; font-size: 1rem;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>
</div>

<style>
    /* Global Styles */
    .btn {
        padding: 12px 25px;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        font-size: 1rem;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(226, 48, 32, 0.3);
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, var(--dark-red) 0%, var(--deep-red) 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(226, 48, 32, 0.4);
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 2px solid rgba(226, 48, 32, 0.3);
    }
    
    .btn-secondary:hover {
        background: rgba(226, 48, 32, 0.2);
        border-color: var(--primary-red);
        transform: translateY(-3px);
    }
    
    .btn-danger {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }
    
    .btn-danger:hover {
        background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
    }
    
    /* CSS Variables */
    :root {
        --primary-red: #e23020;
        --dark-red: #c11b18;
        --deep-red: #a80f0f;
        --light-red: #ff6b6b;
        --pale-red: #ff9999;
        --bg-dark: #0f0f23;
        --bg-darker: #1a1a2e;
        --bg-card: #3a0b07;
        --bg-card-light: #6b140e;
    }
    
    /* Hover effect for booking cards */
    .booking-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(226, 48, 32, 0.15);
        border-color: rgba(226, 48, 32, 0.4);
    }
    
    /* Animation */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .alert {
        animation: fadeIn 0.5s ease;
    }
    
    .booking-card {
        animation: slideIn 0.5s ease;
    }
    
    /* Responsive Design */
    @media (max-width: 992px) {
        .main-container {
            padding: 15px;
        }
        
        .booking-card > div {
            flex-direction: column;
            gap: 20px;
        }
        
        .booking-actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        .booking-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .booking-details-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .page-header > div {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .page-header .btn {
            width: 100%;
            justify-content: center;
        }
    }
    
    /* Print styles for receipt */
    @media print {
        body * {
            visibility: hidden;
        }
        
        #receiptContent, #receiptContent * {
            visibility: visible;
        }
        
        #receiptContent {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            box-shadow: none;
            border: none;
        }
        
        .no-print {
            display: none !important;
        }
    }
</style>

<script>
// Print receipt function
function printReceipt(bookingId) {
    // Show loading
    document.getElementById('receiptContent').innerHTML = `
        <div style="padding: 50px; text-align: center; color: #333;">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary-red);"></i>
            <p style="margin-top: 20px; font-size: 1.1rem;">Loading receipt...</p>
        </div>
    `;
    
    document.getElementById('receiptModal').style.display = 'block';
    
    // In a real implementation, this would fetch receipt data via AJAX
    // For demo purposes, we'll simulate with static HTML
    setTimeout(() => {
        const receiptHTML = `
            <div style="padding: 40px; font-family: 'Courier New', monospace; color: #333;">
                <!-- Header -->
                <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px dashed #ddd; padding-bottom: 20px;">
                    <h1 style="color: var(--primary-red); font-size: 2rem; margin-bottom: 10px; font-weight: 800;">MOVIE TICKETING</h1>
                    <p style="color: #666; font-size: 0.9rem;">Book Your Favorite Movies</p>
                    <p style="color: #666; font-size: 0.9rem; margin-top: 5px;">Ward II, Minglanilla, Cebu</p>
                    <p style="color: #666; font-size: 0.9rem;">09267630945 | BSIT@movieticketing.com</p>
                </div>
                
                <!-- Receipt Title -->
                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="font-size: 1.5rem; color: #333; margin-bottom: 10px;">BOOKING RECEIPT</h2>
                    <p style="color: #666; font-size: 0.9rem;">${new Date().toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}</p>
                </div>
                
                <!-- Booking Details -->
                <div style="margin-bottom: 30px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <div>
                            <div style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">Booking Reference</div>
                            <div style="font-size: 1.2rem; font-weight: 700; color: #333;">BK20240115${String(bookingId).padStart(4, '0')}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #666; font-size: 0.9rem; margin-bottom: 5px;">Status</div>
                            <div style="background: #2ecc71; color: white; padding: 5px 15px; border-radius: 15px; font-weight: 700; font-size: 0.9rem; display: inline-block;">
                                CONFIRMED
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: var(--primary-red); font-size: 1.3rem; margin-bottom: 15px; border-bottom: 2px solid var(--primary-red); padding-bottom: 8px;">Movie Details</h3>
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <div style="width: 100px; height: 140px; background: #f5f5f5; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-film" style="font-size: 2rem; color: #ccc;"></i>
                            </div>
                            <div style="flex: 1;">
                                <h4 style="font-size: 1.4rem; color: #333; margin-bottom: 10px; font-weight: 700;">Sample Movie Title</h4>
                                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                    <span style="background: var(--primary-red); color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700;">PG-13</span>
                                    <span style="color: #666; font-size: 0.9rem;"><i class="fas fa-clock"></i> 2h 15m</span>
                                    <span style="color: #666; font-size: 0.9rem;"><i class="fas fa-film"></i> Action, Adventure</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px;">
                        <div>
                            <h3 style="color: var(--primary-red); font-size: 1.1rem; margin-bottom: 10px;">Show Information</h3>
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                <div style="margin-bottom: 8px;">
                                    <div style="color: #666; font-size: 0.9rem;">Date</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">${new Date().toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}</div>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <div style="color: #666; font-size: 0.9rem;">Time</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">6:30 PM</div>
                                </div>
                                <div>
                                    <div style="color: #666; font-size: 0.9rem;">Theater</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">Cinema 3</div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 style="color: var(--primary-red); font-size: 1.1rem; margin-bottom: 10px;">Seat Information</h3>
                            <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                                <div style="margin-bottom: 8px;">
                                    <div style="color: #666; font-size: 0.9rem;">Selected Seats</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">A05, A06, A07</div>
                                </div>
                                <div style="margin-bottom: 8px;">
                                    <div style="color: #666; font-size: 0.9rem;">Seat Type</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">Standard</div>
                                </div>
                                <div>
                                    <div style="color: #666; font-size: 0.9rem;">Total Seats</div>
                                    <div style="font-size: 1.1rem; font-weight: 600; color: #333;">3 seats</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 25px;">
                        <h3 style="color: var(--primary-red); font-size: 1.1rem; margin-bottom: 10px;">Customer Information</h3>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                            <div style="margin-bottom: 8px;">
                                <div style="color: #666; font-size: 0.9rem;">Name</div>
                                <div style="font-size: 1.1rem; font-weight: 600; color: #333;"><?php echo htmlspecialchars($user_name); ?></div>
                            </div>
                            <div>
                                <div style="color: #666; font-size: 0.9rem;">Customer ID</div>
                                <div style="font-size: 1.1rem; font-weight: 600; color: #333;">CUST<?php echo str_pad($user_id, 5, '0', STR_PAD_LEFT); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Summary -->
                <div style="border: 2px solid #eee; border-radius: 10px; padding: 25px; margin-bottom: 30px;">
                    <h3 style="color: var(--primary-red); font-size: 1.3rem; margin-bottom: 20px; text-align: center;">PAYMENT SUMMARY</h3>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ddd;">
                            <div style="color: #666;">Standard Ticket (x3)</div>
                            <div style="font-weight: 600;">₱1,050.00</div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ddd;">
                            <div style="color: #666;">Service Fee</div>
                            <div style="font-weight: 600;">₱50.00</div>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #ddd;">
                            <div style="color: #666;">Tax (12%)</div>
                            <div style="font-weight: 600;">₱126.00</div>
                        </div>
                    </div>
                    
                    <div style="border-top: 3px double #ddd; padding-top: 20px;">
                        <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 800;">
                            <div>TOTAL AMOUNT</div>
                            <div style="color: var(--primary-red);">₱1,226.00</div>
                        </div>
                        <div style="color: #666; font-size: 0.9rem; margin-top: 5px; text-align: right;">
                            Payment Status: <span style="color: #2ecc71; font-weight: 600;">PAID</span>
                        </div>
                    </div>
                </div>
                
                <!-- Terms & Conditions -->
                <div style="border-top: 2px dashed #ddd; padding-top: 20px; margin-bottom: 30px;">
                    <h4 style="color: #666; font-size: 0.9rem; margin-bottom: 10px;">TERMS & CONDITIONS</h4>
                    <ul style="color: #666; font-size: 0.8rem; line-height: 1.5; padding-left: 15px;">
                        <li>This ticket is non-transferable and non-refundable</li>
                        <li>Please arrive at least 30 minutes before the showtime</li>
                        <li>Valid ID required for verification</li>
                        <li>Children under 3 years are free (no seat provided)</li>
                        <li>Outside food and drinks are not allowed</li>
                    </ul>
                </div>
                
                <!-- Footer -->
                <div style="text-align: center; color: #666; font-size: 0.8rem; border-top: 2px solid var(--primary-red); padding-top: 20px;">
                    <p style="margin-bottom: 10px;">Thank you for choosing Movie Ticketing System!</p>
                    <p style="margin-bottom: 5px;">Scan QR code for verification:</p>
                    <div style="width: 100px; height: 100px; background: #f5f5f5; margin: 10px auto; display: flex; align-items: center; justify-content: center;">
                        <div style="text-align: center; font-size: 0.7rem; color: #999;">QR CODE</div>
                    </div>
                    <p style="margin-top: 15px;">Please present this receipt at the counter</p>
                    <p style="font-style: italic;">Enjoy the show! 🎬</p>
                </div>
            </div>
        `;
        
        document.getElementById('receiptContent').innerHTML = receiptHTML;
    }, 1000);
}

// Print receipt content
function printReceiptContent() {
    const printContent = document.getElementById('receiptContent');
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContent.innerHTML;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}

// Close receipt modal
function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('receiptModal');
    if (event.target == modal) {
        closeReceipt();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // ESC to close modal
    if (e.key === 'Escape') {
        closeReceipt();
    }
});

// Add animation to booking cards
document.addEventListener('DOMContentLoaded', function() {
    const bookingCards = document.querySelectorAll('.booking-card');
    bookingCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.animation = 'slideIn 0.5s ease forwards';
        card.style.opacity = '0';
    });
    
    // Add hover class to booking cards
    document.querySelectorAll('div[style*="background: rgba(255, 255, 255, 0.05)"]').forEach(card => {
        card.classList.add('booking-card');
    });
});
</script>

<?php
// Include footer
require_once $root_dir . '/partials/footer.php';
?>