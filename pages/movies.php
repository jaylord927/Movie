<?php
// pages/movies.php

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

$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

// Get all unique genres
$allGenres = [];
foreach ($movies as $movie) {
    if ($movie['genre']) {
        $genres = explode(',', $movie['genre']);
        foreach ($genres as $genre) {
            $genre = trim($genre);
            if ($genre && !in_array($genre, $allGenres)) {
                $allGenres[] = $genre;
            }
        }
    }
}
sort($allGenres);

// Get filter parameters
$searchTerm = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$selectedGenre = isset($_GET['genre']) ? sanitize_input($_GET['genre']) : '';
$selectedRating = isset($_GET['rating']) ? sanitize_input($_GET['rating']) : '';

// Apply filters
$filteredMovies = $movies;

if (!empty($searchTerm)) {
    $filteredMovies = array_filter($filteredMovies, function($movie) use ($searchTerm) {
        return stripos($movie['title'], $searchTerm) !== false || 
               stripos($movie['description'], $searchTerm) !== false;
    });
}

if (!empty($selectedGenre) && $selectedGenre !== 'all') {
    $filteredMovies = array_filter($filteredMovies, function($movie) use ($selectedGenre) {
        if (empty($movie['genre'])) return false;
        $genres = array_map('trim', explode(',', $movie['genre']));
        return in_array($selectedGenre, $genres);
    });
}

if (!empty($selectedRating) && $selectedRating !== 'all') {
    $filteredMovies = array_filter($filteredMovies, function($movie) use ($selectedRating) {
        if (empty($movie['rating'])) return false;
        return $movie['rating'] === $selectedRating;
    });
}

// Reset keys
$filteredMovies = array_values($filteredMovies);

$conn->close();
?>

<div class="main-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <!-- Page Header -->
    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="color: white; font-size: 2.5rem; margin-bottom: 15px; font-weight: 800;">
            All Movies
        </h1>
        <p style="color: var(--pale-red); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
            Browse our complete collection of movies and book your tickets
        </p>
    </div>

    <!-- Search and Filter -->
    <div style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%); 
          border-radius: 15px; padding: 30px; margin-bottom: 40px; 
          border: 1px solid rgba(226, 48, 32, 0.2);">
        
        <!-- Search Box -->
        <div style="margin-bottom: 25px;">
            <form method="GET" action="">
                <input type="hidden" name="page" value="movies">
                <div style="display: flex; gap: 10px; position: relative;">
                    <i class="fas fa-search" style="position: absolute; left: 20px; top: 50%; 
                       transform: translateY(-50%); color: rgba(255,255,255,0.6); font-size: 1.2rem;"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="Search movies by title or description..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                           style="flex: 1; padding: 15px 20px 15px 50px; background: rgba(255,255,255,0.08);
                                  border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 10px;
                                  color: white; font-size: 1rem;"
                           autocomplete="off">
                    <button type="submit" style="padding: 15px 30px; background: linear-gradient(135deg, var(--primary-red) 0%, var(--dark-red) 100%);
                            color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if ($searchTerm || $selectedGenre || $selectedRating): ?>
                    <a href="?page=movies" style="padding: 15px 20px; background: rgba(255,255,255,0.1); 
                       color: white; text-decoration: none; border-radius: 10px; border: 2px solid rgba(226, 48, 32, 0.3);
                       display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <!-- Genre Filter -->
            <div>
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                    <i class="fas fa-film"></i> Filter by Genre
                </label>
                <select onchange="window.location.href='?page=movies&genre='+this.value"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.08); 
                               border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 8px; color: white;">
                    <option value="all" <?php echo !$selectedGenre ? 'selected' : ''; ?>>All Genres</option>
                    <?php foreach ($allGenres as $genre): ?>
                    <option value="<?php echo urlencode($genre); ?>" 
                        <?php echo $selectedGenre === $genre ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($genre); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Rating Filter -->
            <div>
                <label style="display: block; color: white; font-weight: 600; margin-bottom: 10px;">
                    <i class="fas fa-star"></i> Filter by Rating
                </label>
                <select onchange="window.location.href='?page=movies&rating='+this.value"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.08); 
                               border: 2px solid rgba(226, 48, 32, 0.3); border-radius: 8px; color: white;">
                    <option value="all" <?php echo !$selectedRating ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="G" <?php echo $selectedRating === 'G' ? 'selected' : ''; ?>>G - General</option>
                    <option value="PG" <?php echo $selectedRating === 'PG' ? 'selected' : ''; ?>>PG</option>
                    <option value="PG-13" <?php echo $selectedRating === 'PG-13' ? 'selected' : ''; ?>>PG-13</option>
                    <option value="R" <?php echo $selectedRating === 'R' ? 'selected' : ''; ?>>R</option>
                    <option value="NC-17" <?php echo $selectedRating === 'NC-17' ? 'selected' : ''; ?>>NC-17</option>
                </select>
            </div>
        </div>

        <!-- Active Filters -->
        <?php if ($searchTerm || $selectedGenre || $selectedRating): ?>
        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid rgba(226, 48, 32, 0.2);">
            <div style="color: var(--pale-red); font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-filter"></i> Active Filters:
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                <?php if ($searchTerm): ?>
                <span style="background: rgba(226, 48, 32, 0.15); color: white; padding: 6px 12px; border-radius: 20px;
                      font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    Search: "<?php echo htmlspecialchars($searchTerm); ?>"
                    <a href="?page=movies<?php echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; 
                                 echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>"
                       style="color: inherit; text-decoration: none;">×</a>
                </span>
                <?php endif; ?>
                
                <?php if ($selectedGenre): ?>
                <span style="background: rgba(226, 48, 32, 0.15); color: white; padding: 6px 12px; border-radius: 20px;
                      font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    Genre: <?php echo htmlspecialchars($selectedGenre); ?>
                    <a href="?page=movies<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; 
                                 echo $selectedRating ? '&rating=' . urlencode($selectedRating) : ''; ?>"
                       style="color: inherit; text-decoration: none;">×</a>
                </span>
                <?php endif; ?>
                
                <?php if ($selectedRating): ?>
                <span style="background: rgba(226, 48, 32, 0.15); color: white; padding: 6px 12px; border-radius: 20px;
                      font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    Rating: <?php echo htmlspecialchars($selectedRating); ?>
                    <a href="?page=movies<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; 
                                 echo $selectedGenre ? '&genre=' . urlencode($selectedGenre) : ''; ?>"
                       style="color: inherit; text-decoration: none;">×</a>
                </span>
                <?php endif; ?>
                
                <span style="margin-left: auto; color: var(--light-red); font-weight: 700;">
                    <?php echo count($filteredMovies); ?> movies found
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Movies Grid -->
    <?php if (empty($filteredMovies)): ?>
        <div style="text-align: center; padding: 60px; background: rgba(226, 48, 32, 0.05); 
              border-radius: 15px; border: 2px dashed rgba(226, 48, 32, 0.3);">
            <i class="fas fa-search fa-3x" style="color: var(--primary-red); margin-bottom: 20px; opacity: 0.8;"></i>
            <h3 style="color: white; margin-bottom: 15px; font-size: 1.8rem;">No Movies Found</h3>
            <p style="color: var(--pale-red); margin-bottom: 25px; max-width: 400px; margin-left: auto; margin-right: auto;">
                We couldn't find any movies matching your criteria.
            </p>
            <a href="?page=movies" class="btn btn-primary" style="padding: 12px 30px;">
                <i class="fas fa-times"></i> Clear All Filters
            </a>
        </div>
    <?php else: ?>
        <div class="movies-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px;">
            <?php foreach ($filteredMovies as $movie): ?>
            <div class="movie-card" style="background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-light) 100%);
                  border-radius: 15px; overflow: hidden; transition: all 0.3s ease; 
                  border: 1px solid rgba(226, 48, 32, 0.2); position: relative;">
                
                <?php if (!empty($movie['poster_url'])): ?>
                    <img src="<?php echo $movie['poster_url']; ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                         style="width: 100%; height: 320px; object-fit: cover;"
                         onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 300 400\"><rect width=\"300\" height=\"400\" fill=\"%233a0b07\"/><text x=\"50%\" y=\"50%\" font-family=\"Arial\" font-size=\"24\" fill=\"%23ff9999\" text-anchor=\"middle\" dy=\".3em\">No Poster</text></svg>'">
                <?php else: ?>
                    <div style="width: 100%; height: 320px; background: linear-gradient(135deg, rgba(226, 48, 32, 0.1), rgba(193, 27, 24, 0.2)); 
                          display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-film" style="font-size: 3rem; color: rgba(255, 255, 255, 0.3);"></i>
                    </div>
                <?php endif; ?>
                
                <!-- Movie Badges -->
                <div style="position: absolute; top: 15px; right: 15px; display: flex; flex-direction: column; gap: 8px;">
                    <span style="background: var(--primary-red); color: white; font-weight: 700; font-size: 0.8rem;
                          padding: 6px 12px; border-radius: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); text-align: center; min-width: 40px;">
                        <?php echo $movie['rating'] ?: 'PG'; ?>
                    </span>
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
                            <a href="<?php echo SITE_URL; ?>index.php?page=booking&movie=<?php echo $movie['id']; ?>" 
                               class="btn btn-primary" style="padding: 12px; text-align: center; font-size: 0.9rem;">
                                <i class="fas fa-ticket-alt"></i> Book This Movie
                            </a>
                        <?php elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin'): ?>
                            <a href="<?php echo SITE_URL; ?>index.php?page=admin/dashboard" 
                               class="btn btn-primary" style="padding: 12px; text-align: center; font-size: 0.9rem;">
                                <i class="fas fa-shield-alt"></i> Admin
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>index.php?page=login" 
                               class="btn btn-primary" style="padding: 12px; text-align: center; font-size: 0.9rem;">
                                <i class="fas fa-sign-in-alt"></i> Login to Book
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Total Movies Count -->
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: rgba(226, 48, 32, 0.05); 
              border-radius: 10px; border: 1px solid rgba(226, 48, 32, 0.2);">
            <p style="color: var(--pale-red); font-size: 1.1rem;">
                Showing <strong style="color: white;"><?php echo count($filteredMovies); ?></strong> movies
                <?php if ($searchTerm || $selectedGenre || $selectedRating): ?>
                    (filtered from <?php echo count($movies); ?> total movies)
                <?php else: ?>
                    in our collection
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<style>
    .movie-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(226, 48, 32, 0.2);
        border-color: #e23020;
    }
    
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
    
    @media (max-width: 768px) {
        .movies-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .movies-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const movieCards = document.querySelectorAll('.movie-card');
        
        movieCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'fadeInUp 0.5s ease forwards';
            card.style.opacity = '0';
            
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
            });
        });
        
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
        `;
        document.head.appendChild(style);
    });
</script>

<?php
// Include footer
require_once $root_dir . '/partials/footer.php';
?>