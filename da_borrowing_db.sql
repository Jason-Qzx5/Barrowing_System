-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 02:11 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `da_borrowing_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `borrow_records`
--

CREATE TABLE `borrow_records` (
  `id` int(11) NOT NULL,
  `borrow_code` varchar(50) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `manual_item` varchar(150) DEFAULT NULL,
  `asset_code` varchar(100) DEFAULT NULL,
  `borrower` varchar(150) DEFAULT NULL,
  `office` varchar(150) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `expected_return` date DEFAULT NULL,
  `returned_by` varchar(150) DEFAULT NULL,
  `received_by` varchar(150) DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('BORROWED','RETURNED') DEFAULT 'BORROWED',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `accessories` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_records`
--

INSERT INTO `borrow_records` (`id`, `borrow_code`, `item_id`, `manual_item`, `asset_code`, `borrower`, `office`, `purpose`, `release_date`, `expected_return`, `returned_by`, `received_by`, `return_date`, `status`, `created_at`, `accessories`) VALUES
(1, 'DA-20260304-E04E6', 35, 'laptop', '6574374885-434-343', 'jason', 'FOD HUCDP', 'walang', '2026-03-04', '2026-04-11', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 02:12:34', 'charger'),
(2, 'DA-20260304-4A9D0', 36, 'cellphone', '45367-3432k', 'manoy', 'FOD OA', 'wala lnag', '2026-03-04', '2026-03-28', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 02:36:19', 'cable'),
(3, 'DA-20260304-C158E', 37, 'laptop', '4653-675', 'jason', 'FOD GAD', 'monitoring', '2026-03-04', '2026-04-04', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 05:36:05', 'charger / cable'),
(4, 'DA-20260304-F0936', 36, 'cellphone', '45367-3432k', 'go', 'FOD OA', 'hmmm', '2026-03-04', '2026-03-26', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 06:21:59', 'charger'),
(5, 'DA-20260304-31834', 38, 'laptop', 'MIADP-24-160', 'KENNETH', 'AFD', 'HULAMAN', '2026-03-04', '2030-12-04', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 07:44:15', 'charger'),
(6, 'DA-20260304-91831', 35, 'laptop', '6574374885-434-343', 'JOSH', 'FOD GAD', 'MONITORING', '2026-03-04', '2026-03-27', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 08:10:21', 'charger'),
(7, 'DA-20260304-94C14', 36, 'cellphone', '45367-3432k', 'jason', 'FOD GAD', 'asda', '2026-03-04', '2026-03-27', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 08:17:10', '-'),
(8, 'DA-20260304-BDFE8', 35, 'laptop', '6574374885-434-343', 'bby', 'BAC A2', 'sdad', '2026-03-04', '2026-04-10', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 08:18:15', '-'),
(9, 'DA-20260304-A76C6', 38, 'laptop', 'MIADP-24-160', 'manoy', 'FOD NUPAD', 'walalalng', '2026-03-04', '2026-03-25', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 08:40:27', 'sad'),
(10, 'DA-20260304-35942', 36, 'cellphone', '45367-3432k', 'wawang', 'FOD LIVESTOCK', 'sadasd', '2026-03-04', '2026-04-10', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 08:57:22', '-'),
(11, 'DA-20260304-7B0FE', 35, 'laptop', '6574374885-434-343', 'sadas', 'FOD HUCDP', 'sda', '2026-03-04', '2026-03-27', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 08:58:49', '-'),
(12, 'DA-20260304-0D502', 37, 'laptop', '4653-675', 'tyronne', 'FOD GAD', 'creating program', '2026-03-04', '2026-03-12', NULL, 'onyok babate', '2026-03-04', 'RETURNED', '2026-03-04 09:53:55', 'charger'),
(13, 'DA-20260304-3D670', 36, 'cellphone', '45367-3432k', 'chen2', 'BAC A2', 'testing', '2026-03-04', '2026-03-28', NULL, 'onyok babate', '2026-03-05', 'RETURNED', '2026-03-04 09:59:41', 'charger'),
(14, 'DA-20260304-3D670', 37, 'laptop', '4653-675', 'chen2', 'BAC A2', 'testing', '2026-03-04', '2026-03-28', NULL, 'onyok babate', '2026-03-05', 'RETURNED', '2026-03-04 09:59:41', 'box'),
(15, 'DA-20260304-3D670', 38, 'laptop', 'MIADP-24-160', 'chen2', 'BAC A2', 'testing', '2026-03-04', '2026-03-28', NULL, 'onyok babate', '2026-03-05', 'RETURNED', '2026-03-04 09:59:41', '-'),
(16, 'DA-20260305-4531D', 36, 'cellphone', '45367-3432k', 'manoy', 'FOD NUPAD', 'sadjkasjd', '2026-03-05', '2026-03-27', NULL, NULL, NULL, 'BORROWED', '2026-03-05 00:11:46', 'wdadwda'),
(17, 'DA-20260305-EE551', 35, 'laptop', '6574374885-434-343', 'JASON', 'BAC A2', 'wala lnag', '2026-03-05', '2026-03-20', NULL, NULL, NULL, 'BORROWED', '2026-03-05 00:19:13', 'charger'),
(18, 'DA-20260305-634C0', 37, 'laptop', '4653-675', 'JASON', 'BAC A2', 'jason', '2026-03-05', '2026-03-31', NULL, NULL, NULL, 'BORROWED', '2026-03-05 00:20:43', 'cable');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `asset_code` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `accessories` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `item_name`, `asset_code`, `brand`, `accessories`, `created_at`) VALUES
(35, 'laptop', '6574374885-434-343', 'acer', '', '2026-03-02 11:27:14'),
(36, 'cellphone', '45367-3432k', 'VIVO', 'charger', '2026-03-04 02:34:59'),
(37, 'laptop', '4653-675', 'acer', '', '2026-03-04 05:34:52'),
(38, 'laptop', 'MIADP-24-160', 'hp', '', '2026-03-04 07:43:20');

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `office_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `offices`
--

INSERT INTO `offices` (`id`, `office_name`, `created_at`) VALUES
(1, 'AFD', '2026-02-24 01:39:26'),
(2, 'AFD ACCOUNTING', '2026-02-24 01:39:26'),
(3, 'AFD BUDGET', '2026-02-24 01:39:26'),
(4, 'AFD CASHIER', '2026-02-24 01:39:26'),
(5, 'AFD PSPU', '2026-02-24 01:39:26'),
(6, 'AFD BTU', '2026-02-24 01:39:26'),
(7, 'AFD HRMS', '2026-02-24 01:39:26'),
(8, 'BAC A1', '2026-02-24 01:39:26'),
(9, 'BAC A2', '2026-02-24 01:39:26'),
(10, 'FOD HUCDP', '2026-02-24 01:39:26'),
(11, 'FOD CORN', '2026-02-24 01:39:26'),
(12, 'FOD RICE', '2026-02-24 01:39:26'),
(13, 'FOD OA', '2026-02-24 01:39:26'),
(14, 'FOD LIVESTOCK', '2026-02-24 01:39:26'),
(15, 'FOD NUPAD', '2026-02-24 01:39:26'),
(16, 'FOD GAD', '2026-02-24 01:39:26'),
(17, 'FOD SAAD', '2026-02-24 01:39:26'),
(18, 'FOD CERRMU', '2026-02-24 01:39:26'),
(19, 'RAFIS', '2026-02-24 01:39:26'),
(20, 'ORED', '2026-02-24 01:39:26'),
(21, 'RAED', '2026-02-24 01:39:26'),
(22, 'RESEARCH', '2026-02-24 01:39:26'),
(23, 'ILD-RADDL', '2026-02-24 01:39:26'),
(24, 'ILD-RSL', '2026-02-24 01:39:26'),
(25, 'ILD-RFAL', '2026-02-24 01:39:26'),
(26, 'AMAD', '2026-02-24 01:39:26'),
(27, 'MIADP', '2026-02-24 01:39:26'),
(28, 'PRDP', '2026-02-24 01:39:26'),
(29, 'REGULATORY', '2026-02-24 01:39:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password`, `created_at`) VALUES
(1, 'onyok babate', 'jason', '$2y$10$5SCc9hoi7IcQhd0gj.84feh5ik2LvwHjcnHqV1d1c38AsTKpHNWYq', '2026-02-27 03:05:18'),
(2, 'gogong', 'truy', '$2y$10$9RN8iiGW4v2....FbYsqo.HWX1o1HmwGf550cfpXCsL9m2XnwXmIO', '2026-03-04 02:26:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `borrow_records`
--
ALTER TABLE `borrow_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_borrow_code` (`borrow_code`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `office_name` (`office_name`),
  ADD KEY `idx_office_name` (`office_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `borrow_records`
--
ALTER TABLE `borrow_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
