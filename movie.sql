-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2026 at 02:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `movie`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `full_details` text DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booked_seats`
--

CREATE TABLE `booked_seats` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type` varchar(20) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booked_seats`
--

INSERT INTO `booked_seats` (`id`, `booking_id`, `seat_number`, `seat_type`, `price`, `created_at`) VALUES
(3, 1, 'B03', 'Standard', 350.00, '2026-04-06 02:26:13'),
(4, 1, 'B04', 'Standard', 350.00, '2026-04-06 02:26:13'),
(5, 2, 'A07', 'Premium', 450.00, '2026-04-06 02:40:12'),
(6, 2, 'A08', 'Premium', 450.00, '2026-04-06 02:40:12');

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity_log`
--

CREATE TABLE `customer_activity_log` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `movie_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manual_payments`
--

CREATE TABLE `manual_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `reference_number` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `screenshot_path` varchar(255) NOT NULL,
  `status` enum('Pending','Verified','Rejected') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `director` varchar(255) DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `rating` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `poster_url` varchar(500) DEFAULT NULL,
  `trailer_url` varchar(500) DEFAULT NULL,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_location` varchar(500) DEFAULT NULL,
  `google_maps_link` varchar(500) DEFAULT NULL,
  `standard_price` decimal(10,2) DEFAULT 350.00,
  `premium_price` decimal(10,2) DEFAULT 450.00,
  `sweet_spot_price` decimal(10,2) DEFAULT 550.00,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `title`, `director`, `genre`, `duration`, `rating`, `description`, `poster_url`, `trailer_url`, `venue_name`, `venue_location`, `google_maps_link`, `standard_price`, `premium_price`, `sweet_spot_price`, `is_active`, `added_by`, `updated_by`, `created_at`, `last_updated`) VALUES
(1, 'Sinner', 'Ryan Coogler', 'historical drama, romance, and musical storytelling', '2hours 15minutes', 'PG-13', 'Sinners is a 2025 horror film directed by Ryan Coogler, featuring Michael B. Jordan in dual roles as twin brothers confronting supernatural evils in the 1930s Mississippi Delta.', 'https://mlpnk72yciwc.i.optimole.com/cqhiHLc.IIZS~2ef73/w:auto/h:auto/q:75/https://bleedingcool.com/wp-content/uploads/2025/03/sinners_ver15_xxlg.jpg', 'https://www.youtube.com/watch?v=bKGxHflevuk', 'SM Cinema', 'Purok 13 Cadulawan minglanilla Cebu', 'https://www.google.com/maps/@10.2701001,123.7749591,3a,75y,53.98h,88.44t/data=!3m7!1e1!3m5!1sevD9mV_wf05akytvLeXazQ!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D1.5615656196106897%26panoid%3DevD9mV_wf05akytvLeXazQ%26yaw%3D53.976932543852776!7i16384!8i8192?entry=ttu&g_ep=EgoyMDI2MDQwMS4wIKXMDSoASAFQAw%3D%3D', 350.00, 450.00, 550.00, 1, 2, 2, '2026-04-06 01:25:38', '2026-04-06 02:21:54'),
(2, 'test', 'tes', 'tes', 'test', 'PG', 'test', '', '', '', '', 'https://www.google.com/maps/@10.2701001,123.7749591,3a,75y,53.98h,88.44t/data=!3m7!1e1!3m5!1sevD9mV_wf05akytvLeXazQ!2e0!6shttps:%2F%2Fstreetviewpixels-pa.googleapis.com%2Fv1%2Fthumbnail%3Fcb_client%3Dmaps_sv.tactile%26w%3D900%26h%3D600%26pitch%3D1.5615656196106897%26panoid%3DevD9mV_wf05akytvLeXazQ%26yaw%3D53.976932543852776!7i16384!8i8192?entry=ttu&g_ep=EgoyMDI2MDQwMS4wIKXMDSoASAFQAw%3D%3D', 350.00, 450.00, 550.00, 1, 2, NULL, '2026-04-06 02:08:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `movie_schedules`
--

CREATE TABLE `movie_schedules` (
  `id` int(11) NOT NULL,
  `movie_id` int(11) NOT NULL,
  `movie_title` varchar(255) NOT NULL,
  `show_date` date NOT NULL,
  `showtime` time NOT NULL,
  `total_seats` int(11) DEFAULT 40,
  `available_seats` int(11) DEFAULT 40,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `movie_schedules`
--

INSERT INTO `movie_schedules` (`id`, `movie_id`, `movie_title`, `show_date`, `showtime`, `total_seats`, `available_seats`, `is_active`, `created_at`) VALUES
(1, 1, 'Sinner', '2026-04-25', '18:01:00', 40, 36, 1, '2026-04-06 02:22:40');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paymongo_payments`
--

CREATE TABLE `paymongo_payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `paymongo_payment_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Paid','Failed') DEFAULT 'Pending',
  `payment_intent_id` varchar(100) DEFAULT NULL,
  `payment_method_id` varchar(100) DEFAULT NULL,
  `client_key` varchar(255) DEFAULT NULL,
  `redirect_url` varchar(255) DEFAULT NULL,
  `webhook_received` tinyint(1) DEFAULT 0,
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seat_availability`
--

CREATE TABLE `seat_availability` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `movie_title` varchar(255) NOT NULL,
  `show_date` date NOT NULL,
  `showtime` time NOT NULL,
  `seat_number` varchar(10) NOT NULL,
  `seat_type` varchar(20) DEFAULT 'Standard',
  `price` decimal(10,2) DEFAULT 350.00,
  `is_available` tinyint(1) DEFAULT 1,
  `booking_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seat_availability`
--

INSERT INTO `seat_availability` (`id`, `schedule_id`, `movie_title`, `show_date`, `showtime`, `seat_number`, `seat_type`, `price`, `is_available`, `booking_id`) VALUES
(1, 1, 'Sinner', '2026-04-25', '18:01:00', 'A01', 'Premium', 450.00, 1, NULL),
(2, 1, 'Sinner', '2026-04-25', '18:01:00', 'A02', 'Premium', 450.00, 1, NULL),
(3, 1, 'Sinner', '2026-04-25', '18:01:00', 'A03', 'Premium', 450.00, 1, NULL),
(4, 1, 'Sinner', '2026-04-25', '18:01:00', 'A04', 'Premium', 450.00, 1, NULL),
(5, 1, 'Sinner', '2026-04-25', '18:01:00', 'A05', 'Premium', 450.00, 1, NULL),
(6, 1, 'Sinner', '2026-04-25', '18:01:00', 'A06', 'Premium', 450.00, 1, NULL),
(7, 1, 'Sinner', '2026-04-25', '18:01:00', 'A07', 'Premium', 450.00, 0, 2),
(8, 1, 'Sinner', '2026-04-25', '18:01:00', 'A08', 'Premium', 450.00, 0, 2),
(9, 1, 'Sinner', '2026-04-25', '18:01:00', 'A09', 'Premium', 450.00, 1, NULL),
(10, 1, 'Sinner', '2026-04-25', '18:01:00', 'A10', 'Premium', 450.00, 1, NULL),
(11, 1, 'Sinner', '2026-04-25', '18:01:00', 'B01', 'Standard', 350.00, 1, NULL),
(12, 1, 'Sinner', '2026-04-25', '18:01:00', 'B02', 'Standard', 350.00, 1, NULL),
(13, 1, 'Sinner', '2026-04-25', '18:01:00', 'B03', 'Standard', 350.00, 0, 1),
(14, 1, 'Sinner', '2026-04-25', '18:01:00', 'B04', 'Standard', 350.00, 0, 1),
(15, 1, 'Sinner', '2026-04-25', '18:01:00', 'B05', 'Standard', 350.00, 1, NULL),
(16, 1, 'Sinner', '2026-04-25', '18:01:00', 'B06', 'Standard', 350.00, 1, NULL),
(17, 1, 'Sinner', '2026-04-25', '18:01:00', 'B07', 'Standard', 350.00, 1, NULL),
(18, 1, 'Sinner', '2026-04-25', '18:01:00', 'B08', 'Standard', 350.00, 1, NULL),
(19, 1, 'Sinner', '2026-04-25', '18:01:00', 'B09', 'Standard', 350.00, 1, NULL),
(20, 1, 'Sinner', '2026-04-25', '18:01:00', 'B10', 'Standard', 350.00, 1, NULL),
(21, 1, 'Sinner', '2026-04-25', '18:01:00', 'C01', 'Standard', 350.00, 1, NULL),
(22, 1, 'Sinner', '2026-04-25', '18:01:00', 'C02', 'Standard', 350.00, 1, NULL),
(23, 1, 'Sinner', '2026-04-25', '18:01:00', 'C03', 'Standard', 350.00, 1, NULL),
(24, 1, 'Sinner', '2026-04-25', '18:01:00', 'C04', 'Standard', 350.00, 1, NULL),
(25, 1, 'Sinner', '2026-04-25', '18:01:00', 'C05', 'Standard', 350.00, 1, NULL),
(26, 1, 'Sinner', '2026-04-25', '18:01:00', 'C06', 'Standard', 350.00, 1, NULL),
(27, 1, 'Sinner', '2026-04-25', '18:01:00', 'C07', 'Standard', 350.00, 1, NULL),
(28, 1, 'Sinner', '2026-04-25', '18:01:00', 'C08', 'Standard', 350.00, 1, NULL),
(29, 1, 'Sinner', '2026-04-25', '18:01:00', 'C09', 'Standard', 350.00, 1, NULL),
(30, 1, 'Sinner', '2026-04-25', '18:01:00', 'C10', 'Standard', 350.00, 1, NULL),
(31, 1, 'Sinner', '2026-04-25', '18:01:00', 'D01', 'Sweet Spot', 550.00, 1, NULL),
(32, 1, 'Sinner', '2026-04-25', '18:01:00', 'D02', 'Sweet Spot', 550.00, 1, NULL),
(33, 1, 'Sinner', '2026-04-25', '18:01:00', 'D03', 'Sweet Spot', 550.00, 1, NULL),
(34, 1, 'Sinner', '2026-04-25', '18:01:00', 'D04', 'Sweet Spot', 550.00, 1, NULL),
(35, 1, 'Sinner', '2026-04-25', '18:01:00', 'D05', 'Sweet Spot', 550.00, 1, NULL),
(36, 1, 'Sinner', '2026-04-25', '18:01:00', 'D06', 'Sweet Spot', 550.00, 1, NULL),
(37, 1, 'Sinner', '2026-04-25', '18:01:00', 'D07', 'Sweet Spot', 550.00, 1, NULL),
(38, 1, 'Sinner', '2026-04-25', '18:01:00', 'D08', 'Sweet Spot', 550.00, 1, NULL),
(39, 1, 'Sinner', '2026-04-25', '18:01:00', 'D09', 'Sweet Spot', 550.00, 1, NULL),
(40, 1, 'Sinner', '2026-04-25', '18:01:00', 'D10', 'Sweet Spot', 550.00, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `suggestions`
--

CREATE TABLE `suggestions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_email` varchar(100) DEFAULT NULL,
  `suggestion` text NOT NULL,
  `status` enum('Pending','Reviewed','Implemented') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_booking`
--

CREATE TABLE `tbl_booking` (
  `b_id` int(11) NOT NULL,
  `u_id` int(11) NOT NULL,
  `movie_name` varchar(255) NOT NULL,
  `show_date` date DEFAULT NULL,
  `showtime` time NOT NULL,
  `booking_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Ongoing','Done','Cancelled') DEFAULT 'Ongoing',
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid','Refunded','Pending Verification') DEFAULT 'Pending',
  `is_visible` tinyint(1) DEFAULT 1,
  `booking_reference` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_booking`
--

INSERT INTO `tbl_booking` (`b_id`, `u_id`, `movie_name`, `show_date`, `showtime`, `booking_fee`, `status`, `booking_date`, `payment_status`, `is_visible`, `booking_reference`) VALUES
(1, 1, 'Sinner', '2026-04-25', '18:01:00', 700.00, 'Ongoing', '2026-04-06 02:23:13', 'Paid', 1, 'BK2026040602231325'),
(2, 1, 'Sinner', '2026-04-25', '18:01:00', 900.00, 'Ongoing', '2026-04-06 02:40:12', 'Pending', 1, 'BK2026040602401202');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `u_id` int(11) NOT NULL,
  `u_name` varchar(100) NOT NULL,
  `u_username` varchar(50) NOT NULL,
  `u_email` varchar(100) NOT NULL,
  `u_pass` varchar(255) NOT NULL,
  `u_role` enum('Admin','Customer') DEFAULT 'Customer',
  `u_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_by_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`u_id`, `u_name`, `u_username`, `u_email`, `u_pass`, `u_role`, `u_status`, `created_by`, `created_by_name`, `created_at`) VALUES
(1, 'jaylord laspuna', 'jaylord', 'jaylord@gmail.com', '$2y$10$Z/c0J8gnmmxH1JFjHa1WmuM3/BaQ19KJz5wBmxT/5Dd8Zwlkn0hxG', 'Customer', 'Active', NULL, NULL, '2026-04-06 01:06:16'),
(2, 'denise', 'denise', 'kethley@gmail.com', '$2y$10$0EYXe8F7PGU6jDUcJR3HtuWHZAr1/ZfALiDsbnYVlCFvHtrJIPm8q', 'Admin', 'Active', NULL, NULL, '2026-04-06 01:07:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `booked_seats`
--
ALTER TABLE `booked_seats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_seat_number` (`seat_number`);

--
-- Indexes for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `movie_id` (`movie_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `movie_id` (`movie_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD PRIMARY KEY (`b_id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `idx_user_id` (`u_id`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_show_date` (`show_date`),
  ADD KEY `idx_is_visible` (`is_visible`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`u_id`),
  ADD UNIQUE KEY `u_username` (`u_username`),
  ADD UNIQUE KEY `u_email` (`u_email`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booked_seats`
--
ALTER TABLE `booked_seats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manual_payments`
--
ALTER TABLE `manual_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seat_availability`
--
ALTER TABLE `seat_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `suggestions`
--
ALTER TABLE `suggestions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `u_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD CONSTRAINT `admin_activity_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `booked_seats`
--
ALTER TABLE `booked_seats`
  ADD CONSTRAINT `booked_seats_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_activity_log`
--
ALTER TABLE `customer_activity_log`
  ADD CONSTRAINT `customer_activity_log_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_activity_log_ibfk_2` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_3` FOREIGN KEY (`schedule_id`) REFERENCES `movie_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_activity_log_ibfk_4` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE SET NULL;

--
-- Constraints for table `manual_payments`
--
ALTER TABLE `manual_payments`
  ADD CONSTRAINT `manual_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_3` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manual_payments_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `movies`
--
ALTER TABLE `movies`
  ADD CONSTRAINT `movies_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `movies_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `movie_schedules`
--
ALTER TABLE `movie_schedules`
  ADD CONSTRAINT `movie_schedules_ibfk_1` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `paymongo_payments`
--
ALTER TABLE `paymongo_payments`
  ADD CONSTRAINT `paymongo_payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `tbl_booking` (`b_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `paymongo_payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `seat_availability`
--
ALTER TABLE `seat_availability`
  ADD CONSTRAINT `seat_availability_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `movie_schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suggestions`
--
ALTER TABLE `suggestions`
  ADD CONSTRAINT `suggestions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_booking`
--
ALTER TABLE `tbl_booking`
  ADD CONSTRAINT `tbl_booking_ibfk_1` FOREIGN KEY (`u_id`) REFERENCES `users` (`u_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`u_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
