-- Movie Ticketing Database Schema - COMPLETE VERSION

CREATE DATABASE IF NOT EXISTS movie;
USE movie;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    u_id INT AUTO_INCREMENT PRIMARY KEY,
    u_name VARCHAR(100) NOT NULL,
    u_username VARCHAR(50) UNIQUE NOT NULL,
    u_email VARCHAR(100) UNIQUE NOT NULL,
    u_pass VARCHAR(255) NOT NULL,
    u_role ENUM('Admin', 'Customer') DEFAULT 'Customer',
    u_status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Movies table 
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    genre VARCHAR(100),
    duration VARCHAR(20),
    rating VARCHAR(10),
    description TEXT,
    poster_url VARCHAR(500),
    is_active BOOLEAN DEFAULT 1,
    added_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Movie schedules table
CREATE TABLE IF NOT EXISTS movie_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    movie_title VARCHAR(255) NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    total_seats INT DEFAULT 40,
    available_seats INT DEFAULT 40,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
);

-- Bookings table
CREATE TABLE IF NOT EXISTS tbl_booking (
    b_id INT AUTO_INCREMENT PRIMARY KEY,
    u_id INT NOT NULL,
    movie_name VARCHAR(255) NOT NULL,
    show_date DATE,
    showtime TIME NOT NULL,
    seat_no TEXT NOT NULL,
    booking_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('Ongoing', 'Done', 'Cancelled') DEFAULT 'Ongoing',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('Pending', 'Paid', 'Refunded') DEFAULT 'Pending',
    booking_reference VARCHAR(20),
    FOREIGN KEY (u_id) REFERENCES users(u_id) ON DELETE CASCADE
);

-- Seat availability table
CREATE TABLE IF NOT EXISTS seat_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    movie_title VARCHAR(255) NOT NULL,
    show_date DATE NOT NULL,
    showtime TIME NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    is_available BOOLEAN DEFAULT 1,
    booking_id INT,
    FOREIGN KEY (schedule_id) REFERENCES movie_schedules(id) ON DELETE CASCADE
);

-- Create admin activity log table
CREATE TABLE IF NOT EXISTS admin_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    movie_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(u_id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE SET NULL
);