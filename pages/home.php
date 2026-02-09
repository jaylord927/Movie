<?php
// pages/home.php

// Go up one level from pages/ to root
$root_dir = dirname(__DIR__);

// Include config and functions
require_once $root_dir . '/includes/config.php';
require_once $root_dir . '/includes/functions.php';

// Include header
require_once $root_dir . '/partials/header.php';

// Get database connection
$conn = get_db_connection();

// Get all active movies
$movies_result = $conn->query("
    SELECT m.* 
    FROM movies m
    WHERE m.is_active = 1 
    ORDER BY m.created_at DESC
");

$all_movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $all_movies[] = $row;
    }
}

// Determine how many movies to show
$total_movies = count($all_movies);
$movies_to_show = [];

if ($total_movies <= 5) {
    $movies_to_show = $all_movies;
} elseif ($total_movies <= 10) {
    $movies_to_show = array_slice($all_movies, 0, 4);
} else {
    $movies_to_show = array_slice($all_movies, 0, 5);
}

$conn->close();
?>

<div class="main-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Hero Section -->
    <div class="hero-section" style="text-align: center; padding: 40px 20px; margin-bottom: 50px; 
          background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2));
          border-radius: 20px; border: 2px solid rgba(226, 48, 32, 0.2);">
        <h1 style="font-size: 3rem; color: white; margin-bottom: 20px; font-weight: 800;">
            Welcome to Movie Ticketing
        </h1>
        <p
            style="font-size: 1.2rem; color: var(--pale-red); margin-bottom: 30px; max-width: 600px; margin: 0 auto 30px;">
            Book tickets for the latest blockbusters in cinemas near you
        </p>

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-primary"
                    style="padding: 15px 30px; font-size: 1.1rem;">
                    <i class="fas fa-sign-in-alt"></i> Login to Book Tickets
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Featured Movies Section -->
    <div style="margin-bottom: 60px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2 style="color: white; font-size: 2rem; font-weight: 800;">
                <?php if (empty($all_movies)): ?>
                    No Movies Available
                <?php else: ?>
                    <?php if ($total_movies <= 5): ?>
                        All Movies
                    <?php else: ?>
                        Featured Movies
                    <?php endif; ?>
                <?php endif; ?>
            </h2>

            <?php if (!empty($all_movies) && $total_movies > count($movies_to_show)): ?>
                <a href="<?php echo SITE_URL; ?>index.php?page=movies" class="btn btn-secondary"
                    style="padding: 12px 25px; font-size: 1rem;">
                    <i class="fas fa-film"></i> View All Movies
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($all_movies)): ?>
            <div style="text-align: center; padding: 50px; background: rgba(226, 48, 32, 0.05); 
                  border-radius: 15px; border: 2px dashed rgba(226, 48, 32, 0.3);">
                <i class="fas fa-film fa-3x" style="color: var(--primary-red); margin-bottom: 20px; opacity: 0.8;"></i>
                <h3 style="color: white; margin-bottom: 10px; font-size: 1.5rem;">No Movies Available</h3>
                <p style="color: var(--pale-red); max-width: 400px; margin: 0 auto;">
                    We're preparing something special for you! New releases coming soon.
                </p>
            </div>
        <?php else: ?>
            <div class="movies-grid"
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px;">
                <?php foreach ($movies_to_show as $movie): ?>
                    <div class="movie-card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%);
                      border-radius: 15px; overflow: hidden; transition: all 0.3s ease; 
                      border: 1px solid rgba(226, 48, 32, 0.2); position: relative;">

                        <?php if (!empty($movie['poster_url'])): ?>
                            <img src="<?php echo $movie['poster_url']; ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                style="width: 100%; height: 320px; object-fit: cover;"
                                onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=\" http://www.w3.org/2000/svg\"
                                viewBox=\"0 0 300 400\">
                            <rect width=\"300\" height=\"400\" fill=\"%233a0b07\" /><text x=\"50%\" y=\"50%\" font-family=\"Arial\"
                                font-size=\"24\" fill=\"%23ff9999\" text-anchor=\"middle\" dy=\".3em\">No Poster</text></svg>'">
                        <?php else: ?>
                            <div style="width: 100%; height: 320px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                              display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Movie Badges -->
                        <div
                            style="position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 8px;">
                            <span
                                style="background: var(--primary-red); color: white; font-weight: 700; font-size: 0.8rem;
                              padding: 6px 12px; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-align: center; min-width: 40px;">
                                <?php echo $movie['rating'] ?: 'PG'; ?>
                            </span>

                            <?php if ($movie['genre']): ?>
                                <span style="background: rgba(0,0,0,0.7); color: white; font-weight: 600; font-size: 0.75rem;
                              padding: 5px 10px; border-radius: 15px; display: flex; align-items: center; gap: 5px;">
                                    <i class="fas fa-tag"></i> <?php echo explode(',', $movie['genre'])[0]; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Movie Content -->
                        <div style="padding: 25px;">
                            <h3 style="color: white; font-size: 1.3rem; font-weight: 800; margin-bottom: 15px; 
                              line-height: 1.4; min-height: 3.2em;">
                                <?php echo htmlspecialchars($movie['title']); ?>
                            </h3>

                            <div style="margin-bottom: 15px;">
                                <?php if ($movie['duration']): ?>
                                    <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); 
                                  font-size: 0.9rem; margin-bottom: 8px;">
                                        <i class="fas fa-clock"></i> <?php echo $movie['duration']; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($movie['genre']): ?>
                                    <div style="display: flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.8); 
                                  font-size: 0.9rem;">
                                        <i class="fas fa-film"></i> <?php echo htmlspecialchars($movie['genre']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($movie['description']): ?>
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.95rem; line-height: 1.6; 
                              margin-bottom: 20px; min-height: 4.5em;">
                                    <?php echo substr(htmlspecialchars($movie['description']), 0, 100); ?>
                                    <?php if (strlen($movie['description']) > 100): ?>...<?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <a href="<?php echo SITE_URL; ?>index.php?page=movie-details&id=<?php echo $movie['id']; ?>"
                                    class="btn btn-secondary" style="padding: 12px; text-align: center; font-size: 0.9rem;">
                                    <i class="fas fa-info-circle"></i> Details
                                </a>

                                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'Customer'): ?>
                                    <a href="<?php echo SITE_URL; ?>index.php?page=customer/booking&movie=<?php echo $movie['id']; ?>"
                                        class="btn btn-primary">
                                        <i class="fas fa-ticket-alt"></i> Book Now
                                    </a>
                                <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                                    <a href="<?php echo SITE_URL; ?>index.php?page=admin/dashboard" class="btn btn-primary"
                                        style="padding: 12px; text-align: center; font-size: 0.9rem;">
                                        <i class="fas fa-shield-alt"></i> Admin
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-primary"
                                        style="padding: 12px; text-align: center; font-size: 0.9rem;">
                                        <i class="fas fa-sign-in-alt"></i> Login to Book
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Movie Count Info -->
            <div style="text-align: center; margin-top: 30px; padding: 15px; background: rgba(226, 48, 32, 0.05); 
                  border-radius: 10px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <p style="color: var(--pale-red); margin-bottom: 5px;">
                    Showing <?php echo count($movies_to_show); ?> of <?php echo $total_movies; ?> movies
                </p>
                <?php if ($total_movies > count($movies_to_show)): ?>
                    <a href="<?php echo SITE_URL; ?>index.php?page=movies" style="color: var(--light-red); text-decoration: none; font-weight: 600; display: inline-flex; 
                          align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-right"></i> View all <?php echo $total_movies; ?> movies
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Features Section -->
    <div style="margin-top: 60px; text-align: center;">
        <h2 style="color: white; margin-bottom: 40px; font-size: 2rem; font-weight: 800;">Why Choose Us?</h2>
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-top: 30px;">
            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                  padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">
                    <i class="fas fa-film"></i>
                </div>
                <h3 style="color: white; margin-bottom: 10px; font-size: 1.3rem;">Latest Movies</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Get access to the newest releases and blockbuster
                    hits</p>
            </div>

            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                  padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3 style="color: white; margin-bottom: 10px; font-size: 1.3rem;">Easy Booking</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Simple and fast ticket booking process</p>
            </div>

            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                  padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">
                    <i class="fas fa-chair"></i>
                </div>
                <h3 style="color: white; margin-bottom: 10px; font-size: 1.3rem;">Seat Selection</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Choose your preferred seats with our interactive
                    seat map</p>
            </div>

            <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
                  padding: 30px; border-radius: 15px; border: 1px solid rgba(226, 48, 32, 0.2);">
                <div style="font-size: 2.5rem; color: var(--primary-red); margin-bottom: 15px;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 style="color: white; margin-bottom: 10px; font-size: 1.3rem;">Secure Payment</h3>
                <p style="color: var(--pale-red); line-height: 1.6;">Safe and secure payment processing</p>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div style="text-align: center; margin-top: 80px; padding: 50px; 
          background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2));
          border-radius: 20px; border: 2px solid rgba(226, 48, 32, 0.3);">
            <h2 style="color: white; margin-bottom: 20px; font-size: 2.2rem; font-weight: 800;">
                Ready to Book Your Movie?
            </h2>
            <p
                style="color: var(--pale-red); font-size: 1.1rem; margin-bottom: 30px; max-width: 600px; margin-left: auto; margin-right: auto;">
                Join thousands of movie lovers who book their tickets with us. Create an account and start your cinematic
                journey today!
            </p>
            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>index.php?page=register" class="btn btn-primary"
                    style="padding: 15px 35px; font-size: 1.1rem;">
                    <i class="fas fa-user-plus"></i> Sign Up Now
                </a>
                <a href="<?php echo SITE_URL; ?>index.php?page=login" class="btn btn-secondary"
                    style="padding: 15px 35px; font-size: 1.1rem;">
                    <i class="fas fa-sign-in-alt"></i> Login to Account
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Movie card hover effect */
    .movie-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(226, 48, 32, 0.2);
        border-color: #e23020;
    }

    /* Button styles */
    .btn {
        padding: 12px 25px;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
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

    /* Responsive design */
    @media (max-width: 768px) {
        .movies-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .hero-section h1 {
            font-size: 2.3rem;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .movies-grid {
            grid-template-columns: 1fr;
        }

        .movie-card .btn {
            padding: 10px;
            font-size: 0.85rem;
        }

        .hero-section h1 {
            font-size: 2rem;
        }
    }
</style>

<script>
    // Add hover effects to movie cards
    document.addEventListener('DOMContentLoaded', function () {
        const movieCards = document.querySelectorAll('.movie-card');

        movieCards.forEach((card, index) => {
            // Add animation delay
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'fadeInUp 0.5s ease forwards';
            card.style.opacity = '0';

            // Enhanced hover effect
            card.addEventListener('mouseenter', function () {
                this.style.zIndex = '10';
            });

            card.addEventListener('mouseleave', function () {
                this.style.zIndex = '1';
            });
        });

        // Animate feature cards
        const featureCards = document.querySelectorAll('.features-grid > div');
        featureCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'fadeInUp 0.5s ease forwards';
            card.style.opacity = '0';
        });

        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .movie-card {
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            
            .features-grid > div {
                transition: all 0.3s ease;
            }
            
            .features-grid > div:hover {
                transform: translateY(-5px);
            }
        `;
        document.head.appendChild(style);

        // Smooth scroll for "View All" link
        document.querySelectorAll('a[href*="movies.php"]').forEach(link => {
            link.addEventListener('click', function (e) {
                // Only if we're going to the same page
                if (this.href.includes('index.php?page=movies')) {
                    // Let the normal navigation happen
                    return true;
                }
            });
        });
    });
</script>

<?php
// Include footer
require_once $root_dir . '/partials/footer.php';
?>