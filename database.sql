-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: May 21, 2026 at 07:51 AM
-- Server version: 8.4.6-6
-- PHP Version: 8.2.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbgfevitvxajkc`
--

-- --------------------------------------------------------

--
-- Table structure for table `mail_in`
--

CREATE TABLE `moneris_gateway` (
  `id` int NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(255) DEFAULT NULL,
  `apartment` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `zip` varchar(255) DEFAULT NULL,
  `device_model` varchar(255) DEFAULT NULL,
  `issues` text,
  `message` text,
  `device_password` varchar(255) DEFAULT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `paid_amount` varchar(255) DEFAULT NULL,
  `date` timestamp NULL DEFAULT NULL,
  `transaction_id` text,
  `shipping_method` varchar(255) DEFAULT NULL,
  `label_pdf` varchar(255) DEFAULT NULL,
  `clickship_tran_id` varchar(255) DEFAULT NULL,
  `request_id` text,
  `service_id` text,
  `shipment_id` text,
  `shipment_status` varchar(255) DEFAULT NULL,
  `retry` varchar(255) DEFAULT NULL,
  `lang` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `mail_in`
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mail_in`
--
ALTER TABLE `mail_in`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mail_in`
--
ALTER TABLE `mail_in`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
