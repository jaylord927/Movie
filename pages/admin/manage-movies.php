<?php
// pages/admin/manage-movies.php - FIXED VERSION (Same pattern as register.php)

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
$edit_movie = null;

// ADD MOVIE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movie'])) {
    $title = htmlspecialchars(trim($_POST['title']));
    $genre = htmlspecialchars(trim($_POST['genre']));
    $duration = htmlspecialchars(trim($_POST['duration']));
    $rating = htmlspecialchars(trim($_POST['rating']));
    $description = htmlspecialchars(trim($_POST['description']));
    $poster_url = htmlspecialchars(trim($_POST['poster_url'] ?? ''));
    
    // Validation - SAME PATTERN AS REGISTER
    if (empty($title) || empty($genre) || empty($duration) || empty($rating) || empty($description)) {
        $error = "All required fields must be filled!";
    } else {
        // Check if movie already exists
        $check_stmt = $conn->prepare("SELECT id FROM movies WHERE title = ? AND is_active = 1");
        $check_stmt->bind_param("s", $title);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Movie with this title already exists!";
        } else {
            // Insert the movie - SIMPLE INSERT LIKE REGISTER
            $stmt = $conn->prepare("INSERT INTO movies (title, genre, duration, rating, description, poster_url, is_active, added_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
            $stmt->bind_param("ssssssi", $title, $genre, $duration, $rating, $description, $poster_url, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $new_movie_id = $stmt->insert_id;
                $success = "Movie added successfully! ID: " . $new_movie_id;
                // Clear form
                $_POST = array();
            } else {
                $error = "Failed to add movie: " . $conn->error;
            }
            
            $stmt->close();
        }
        
        $check_stmt->close();
    }
}

// UPDATE MOVIE
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_movie'])) {
    $id = intval($_POST['id']);
    $title = htmlspecialchars(trim($_POST['title']));
    $genre = htmlspecialchars(trim($_POST['genre']));
    $duration = htmlspecialchars(trim($_POST['duration']));
    $rating = htmlspecialchars(trim($_POST['rating']));
    $description = htmlspecialchars(trim($_POST['description']));
    $poster_url = htmlspecialchars(trim($_POST['poster_url'] ?? ''));
    
    // Update the movie
    $stmt = $conn->prepare("UPDATE movies SET title = ?, genre = ?, duration = ?, rating = ?, description = ?, poster_url = ?, updated_by = ? WHERE id = ?");
    $stmt->bind_param("ssssssii", $title, $genre, $duration, $rating, $description, $poster_url, $_SESSION['user_id'], $id);
    
    if ($stmt->execute()) {
        $success = "Movie updated successfully!";
    } else {
        $error = "Failed to update movie: " . $stmt->error;
    }
    $stmt->close();
}

// DELETE MOVIE
elseif (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Soft delete
    $stmt = $conn->prepare("UPDATE movies SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success = "Movie deleted successfully!";
    } else {
        $error = "Failed to delete movie: " . $stmt->error;
    }
    $stmt->close();
}

// ============================================
// FETCH DATA FOR DISPLAY
// ============================================

// Get all movies for listing
$movies_result = $conn->query("
    SELECT m.*, 
           a.u_name as added_by_name,
           u.u_name as updated_by_name
    FROM movies m
    LEFT JOIN users a ON m.added_by = a.u_id
    LEFT JOIN users u ON m.updated_by = u.u_id
    WHERE m.is_active = 1 
    ORDER BY m.created_at DESC
");

$movies = [];
if ($movies_result) {
    while ($row = $movies_result->fetch_assoc()) {
        $movies[] = $row;
    }
}

// Check if we're in edit mode
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("
        SELECT m.*, 
               a.u_name as added_by_name,
               u.u_name as updated_by_name
        FROM movies m
        LEFT JOIN users a ON m.added_by = a.u_id
        LEFT JOIN users u ON m.updated_by = u.u_id
        WHERE m.id = ? AND m.is_active = 1
    ");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $edit_movie = $result->fetch_assoc();
        $edit_mode = !empty($edit_movie);
        $stmt->close();
    }
}

// Get movie count
$count_result = $conn->query("SELECT COUNT(*) as total FROM movies WHERE is_active = 1");
$movie_count = $count_result ? $count_result->fetch_assoc()['total'] : 0;

// Close connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Movies - Admin Panel</title>
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
        textarea.form-control { min-height: 100px; resize: vertical; }
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
        .movie-poster-thumb { 
            width: 60px; height: 80px; object-fit: cover; border-radius: 5px;
            border: 2px solid rgba(255,215,0,0.3);
        }
        .action-buttons { display: flex; gap: 8px; }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }
        .empty-state { 
            text-align: center; padding: 40px; color: rgba(255,255,255,0.6);
        }
        @media (max-width: 768px) {
            .admin-container { padding: 10px; }
            .admin-section { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1 class="admin-title">Manage Movies</h1>
            <p class="admin-subtitle">Add, edit, or remove movies from the system</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Movie Form -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="<?php echo $edit_mode ? 'fas fa-edit' : 'fas fa-plus-circle'; ?>"></i>
                <?php echo $edit_mode ? 'Edit Movie' : 'Add New Movie'; ?>
            </h2>
            
            <?php if ($edit_mode): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                Editing: <strong><?php echo htmlspecialchars($edit_movie['title']); ?></strong>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="movieForm">
                <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_movie['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Movie Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                           class="form-control" placeholder="Enter movie title">
                </div>
                
                <div class="form-group">
                    <label for="genre">Genre *</label>
                    <input type="text" id="genre" name="genre" required
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['genre']) : (isset($_POST['genre']) ? htmlspecialchars($_POST['genre']) : ''); ?>"
                           class="form-control" placeholder="e.g., Action, Comedy, Drama">
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration *</label>
                    <input type="text" id="duration" name="duration" required
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['duration']) : (isset($_POST['duration']) ? htmlspecialchars($_POST['duration']) : ''); ?>"
                           class="form-control" placeholder="e.g., 2h 15m">
                </div>
                
                <div class="form-group">
                    <label for="rating">Rating *</label>
                    <select id="rating" name="rating" required class="form-control">
                        <option value="">Select Rating</option>
                        <option value="G" <?php echo ($edit_mode && $edit_movie['rating'] == 'G') || (isset($_POST['rating']) && $_POST['rating'] == 'G') ? 'selected' : ''; ?>>G - General Audiences</option>
                        <option value="PG" <?php echo ($edit_mode && $edit_movie['rating'] == 'PG') || (isset($_POST['rating']) && $_POST['rating'] == 'PG') ? 'selected' : ''; ?>>PG - Parental Guidance</option>
                        <option value="PG-13" <?php echo ($edit_mode && $edit_movie['rating'] == 'PG-13') || (isset($_POST['rating']) && $_POST['rating'] == 'PG-13') ? 'selected' : ''; ?>>PG-13 - Parents Strongly Cautioned</option>
                        <option value="R" <?php echo ($edit_mode && $edit_movie['rating'] == 'R') || (isset($_POST['rating']) && $_POST['rating'] == 'R') ? 'selected' : ''; ?>>R - Restricted</option>
                        <option value="NC-17" <?php echo ($edit_mode && $edit_movie['rating'] == 'NC-17') || (isset($_POST['rating']) && $_POST['rating'] == 'NC-17') ? 'selected' : ''; ?>>NC-17 - Adults Only</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" rows="4" class="form-control" 
                              placeholder="Enter movie description" required><?php echo $edit_mode ? htmlspecialchars($edit_movie['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="poster_url">Poster Image URL</label>
                    <input type="url" id="poster_url" name="poster_url" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_movie['poster_url'] ?? '') : (isset($_POST['poster_url']) ? htmlspecialchars($_POST['poster_url']) : ''); ?>"
                           class="form-control" placeholder="https://example.com/image.jpg">
                </div>
                
                <div class="form-group" style="text-align: center; margin-top: 30px;">
                    <?php if ($edit_mode): ?>
                    <button type="submit" name="update_movie" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-save"></i> Update Movie
                    </button>
                    <a href="index.php?page=admin/manage-movies" class="btn btn-secondary" style="margin-left: 15px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php else: ?>
                    <button type="submit" name="add_movie" class="btn btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                        <i class="fas fa-plus"></i> Add Movie
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Movies List -->
        <div class="admin-section">
            <h2 class="section-title">
                <i class="fas fa-film"></i> All Movies (<?php echo $movie_count; ?>)
            </h2>
            
            <?php if (empty($movies)): ?>
            <div class="empty-state">
                <i class="fas fa-film fa-3x" style="margin-bottom: 20px; opacity: 0.5;"></i>
                <p>No movies found. Add your first movie!</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Poster</th>
                            <th>Movie Details</th>
                            <th>Admin Info</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movies as $movie): ?>
                        <tr>
                            <td><?php echo $movie['id']; ?></td>
                            <td>
                                <?php if (!empty($movie['poster_url'])): ?>
                                <img src="<?php echo $movie['poster_url']; ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                     class="movie-poster-thumb"
                                     onerror="this.src='https://via.placeholder.com/60x80?text=No+Image'">
                                <?php else: ?>
                                <div style="width: 60px; height: 80px; background: rgba(255, 215, 0, 0.1); border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-film" style="color: rgba(255, 215, 0, 0.5); font-size: 1.5rem;"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: white; font-size: 1.1rem;"><?php echo htmlspecialchars($movie['title']); ?></strong>
                                <div style="margin-top: 5px;">
                                    <span style="background: #ffd700; color: #333; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 700; margin-right: 5px;">
                                        <?php echo $movie['rating']; ?>
                                    </span>
                                    <span style="color: rgba(255, 255, 255, 0.8); font-size: 0.9rem;">
                                        <?php echo $movie['genre']; ?> • <?php echo $movie['duration']; ?>
                                    </span>
                                </div>
                                <?php if (!empty($movie['description'])): ?>
                                <p style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.7); margin-top: 8px; max-width: 400px;">
                                    <?php echo substr(htmlspecialchars($movie['description']), 0, 100); ?>...
                                </p>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.7);">
                                    <div><strong>Added by:</strong> <?php echo $movie['added_by_name'] ?? 'Unknown'; ?></div>
                                    <div><strong>Date:</strong> <?php echo date('M d, Y', strtotime($movie['created_at'])); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="index.php?page=admin/manage-movies&edit=<?php echo $movie['id']; ?>" 
                                       class="btn btn-sm" style="background: rgba(66, 153, 225, 0.2); color: #4299e1; border: 1px solid rgba(66, 153, 225, 0.3);">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="index.php?page=admin/manage-movies&delete=<?php echo $movie['id']; ?>" 
                                       class="btn btn-sm" style="background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.3);"
                                       onclick="return confirm('Are you sure you want to delete \'<?php echo addslashes($movie['title']); ?>\'?')">
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
    document.getElementById('movieForm').addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        const genre = document.getElementById('genre').value.trim();
        const duration = document.getElementById('duration').value.trim();
        const rating = document.getElementById('rating').value;
        const description = document.getElementById('description').value.trim();
        
        // Check required fields
        if (!title || !genre || !duration || !rating || !description) {
            e.preventDefault();
            alert('Please fill in all required fields!');
            return false;
        }
        
        return true;
    });
    </script>
</body>
</html>