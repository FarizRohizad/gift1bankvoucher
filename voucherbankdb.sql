-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 19, 2025 at 09:23 AM
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
-- Database: `voucherbankdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cartId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `voucherID` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `history`
--

CREATE TABLE `history` (
  `historyId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `voucherID` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `pointCost` int(11) NOT NULL,
  `dateBuy` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `history`
--

INSERT INTO `history` (`historyId`, `userId`, `voucherID`, `quantity`, `pointCost`, `dateBuy`) VALUES
(1, 1, 4, 2, 1000, '2025-09-19 14:50:00'),
(2, 1, 15, 1, 600, '2025-09-19 14:54:23'),
(3, 1, 9, 1, 500, '2025-09-19 15:20:08');

-- --------------------------------------------------------

--
-- Table structure for table `points`
--

CREATE TABLE `points` (
  `pointsId` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `credit` int(11) DEFAULT 0,
  `debit` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `points`
--

INSERT INTO `points` (`pointsId`, `userId`, `credit`, `debit`) VALUES
(1, 1, 0, 600),
(2, 1, 0, 500);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `User_ID` int(11) NOT NULL,
  `User_Name` varchar(100) NOT NULL,
  `User_Email` varchar(100) NOT NULL,
  `User_Password` varchar(255) NOT NULL,
  `User_Role` enum('customer','admin','staff') DEFAULT 'customer',
  `User_Points` int(11) DEFAULT 0,
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`User_ID`, `User_Name`, `User_Email`, `User_Password`, `User_Role`, `User_Points`, `Created_At`) VALUES
(1, 'fariz', 'fariz@gmail.com', '$2y$10$MG1ka8uk8CV0pU3HiekxGOsQO7W4DirFMOOpEszv.7x4laa4wxC6e', 'customer', 0, '2025-09-19 06:22:04');

-- --------------------------------------------------------

--
-- Table structure for table `voucher`
--

CREATE TABLE `voucher` (
  `voucherID` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `expiredDate` date NOT NULL,
  `cost` int(11) NOT NULL,
  `categoryName` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voucher`
--

INSERT INTO `voucher` (`voucherID`, `name`, `expiredDate`, `cost`, `categoryName`) VALUES
(1, 'RM50 Shopping Voucher', '2024-12-31', 1000, 'Shopping'),
(2, 'Dining Discount', '2024-12-31', 700, 'Food & Beverage'),
(3, 'Petrol Cashback', '2024-12-31', 900, 'Automotive'),
(4, 'Online Store Voucher', '2024-12-31', 500, 'E-commerce'),
(5, 'RM50 AEON Voucher', '2025-10-19', 1000, 'Shopping'),
(6, 'RM25 Zalora Voucher', '2025-11-03', 800, 'Shopping'),
(7, 'RM100 Harvey Norman Voucher', '2025-11-18', 1800, 'Shopping'),
(8, 'RM30 GrabFood Credit', '2025-10-04', 700, 'Dining'),
(9, 'Starbucks Buy 1 Get 1 Free', '2025-10-09', 500, 'Dining'),
(10, 'RM50 Dining Voucher (TGIF)', '2025-10-19', 1200, 'Dining'),
(11, 'RM50 KLOOK Voucher', '2025-12-18', 1100, 'Travel'),
(12, 'GSC Cinema Ticket (2 pax)', '2025-10-03', 950, 'Entertainment'),
(13, 'RM100 AirAsia Flight Voucher', '2026-01-17', 2500, 'Travel'),
(14, 'RM30 Petrol Cashback', '2025-10-19', 900, 'Cashback'),
(15, 'RM20 Phone Bill Rebate', '2025-10-19', 600, 'Cashback'),
(16, 'Fitness Class Pass', '2025-11-18', 750, 'Entertainment');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cartId`),
  ADD KEY `userId` (`userId`),
  ADD KEY `voucherID` (`voucherID`);

--
-- Indexes for table `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`historyId`),
  ADD KEY `userId` (`userId`),
  ADD KEY `voucherID` (`voucherID`);

--
-- Indexes for table `points`
--
ALTER TABLE `points`
  ADD PRIMARY KEY (`pointsId`),
  ADD KEY `userId` (`userId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_ID`),
  ADD UNIQUE KEY `User_Email` (`User_Email`);

--
-- Indexes for table `voucher`
--
ALTER TABLE `voucher`
  ADD PRIMARY KEY (`voucherID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cartId` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `history`
--
ALTER TABLE `history`
  MODIFY `historyId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `points`
--
ALTER TABLE `points`
  MODIFY `pointsId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `voucher`
--
ALTER TABLE `voucher`
  MODIFY `voucherID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`voucherID`) REFERENCES `voucher` (`voucherID`) ON DELETE CASCADE;

--
-- Constraints for table `history`
--
ALTER TABLE `history`
  ADD CONSTRAINT `history_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `history_ibfk_2` FOREIGN KEY (`voucherID`) REFERENCES `voucher` (`voucherID`) ON DELETE CASCADE;

--
-- Constraints for table `points`
--
ALTER TABLE `points`
  ADD CONSTRAINT `points_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`User_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
