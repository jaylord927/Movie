<?php
// pages/customer/browse-movies.php

// Go up two levels from pages/customer/ to root
$root_dir = dirname(dirname(__DIR__));

// Include config and functions
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

// Check if customer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Customer') {
    header("Location: " . SITE_URL . "index.php?page=login");
    exit();
}

// Include header
require_once $root_dir . '/partials/header.php';

// Get movies from database
$conn = get_db_connection();
$movies_result = $conn->query("
    SELECT m.* 
    FROM movies m
    WHERE m.is_active = 1 
    ORDER BY m.created_at DESC
");

$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

$conn->close();
?>

<div class="main-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <h1 style="color: white; margin-bottom: 30px; text-align: center;">Browse Movies</h1>
    
    <?php if (empty($movies)): ?>
        <div style="text-align: center; padding: 50px;">
            <i class="fas fa-film fa-3x" style="color: var(--primary-red); margin-bottom: 20px;"></i>
            <h3 style="color: white; margin-bottom: 10px;">No Movies Available</h3>
            <p style="color: var(--pale-red);">Check back soon for new releases!</p>
        </div>
    <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($movies as $movie): ?>
            <div class="movie-card">
                <?php if (!empty($movie['poster_url'])): ?>
                    <img src="<?php echo $movie['poster_url']; ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                         style="width: 100%; height: 250px; object-fit: cover; border-radius: 10px 10px 0 0;">
                <?php else: ?>
                    <div style="width: 100%; height: 250px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                              border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
                    </div>
                <?php endif; ?>
                
                <div style="padding: 20px;">
                    <h3 style="color: white; margin-bottom: 10px; font-size: 1.2rem;">
                        <?php echo htmlspecialchars($movie['title']); ?>
                    </h3>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                        <span style="background: var(--primary-red); color: white; padding: 4px 10px; 
                                     border-radius: 15px; font-size: 0.8rem; font-weight: 700;">
                            <?php echo $movie['rating'] ?: 'PG'; ?>
                        </span>
                        <span style="color: var(--pale-red); font-size: 0.9rem;">
                            <i class="fas fa-clock"></i> <?php echo $movie['duration']; ?>
                        </span>
                    </div>
                    
                    <?php if ($movie['genre']): ?>
                    <p style="color: var(--pale-red); font-size: 0.9rem; margin-bottom: 15px;">
                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($movie['genre']); ?>
                    </p>
                    <?php endif; ?>
                    
                    <?php if ($movie['description']): ?>
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-bottom: 20px; line-height: 1.5;">
                        <?php echo substr(htmlspecialchars($movie['description']), 0, 100); ?>...
                    </p>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 10px;">
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/movie-details&id=<?php echo $movie['id']; ?>" 
                           class="btn btn-secondary" style="flex: 1; text-align: center;">
                            <i class="fas fa-info-circle"></i> Details
                        </a>
                        <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>" 
                           class="btn btn-primary" style="flex: 1; text-align: center;">
                            <i class="fas fa-ticket-alt"></i> Book Now
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <style>
            .movies-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 30px;
            }
            
            .movie-card {
                background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%);
                border-radius: 10px;
                overflow: hidden;
                transition: all 0.3s ease;
                border: 1px solid rgba(226, 48, 32, 0.2);
            }
            
            .movie-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(226, 48, 32, 0.2);
                border-color: var(--primary-red);
            }
        </style>
    <?php endif; ?>
</div>

<?php
// Include footer
require_once $root_dir . '/partials/footer.php';
?>