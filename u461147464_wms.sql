-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 04, 2025 at 01:18 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u461147464_wms`
--

-- --------------------------------------------------------

--
-- Table structure for table `ap_payments`
--

CREATE TABLE `ap_payments` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL COMMENT 'FK to supplier_invoices',
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `paid_by_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'IT', NULL),
(2, 'HR', NULL),
(3, 'Warehouse', NULL),
(4, 'Production', NULL),
(5, 'ควบคุมคุณภาพ', '');

-- --------------------------------------------------------

--
-- Table structure for table `gi_items`
--

CREATE TABLE `gi_items` (
  `id` int(11) NOT NULL,
  `gi_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `quantity_issued` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gi_items`
--

INSERT INTO `gi_items` (`id`, `gi_id`, `material_id`, `location_id`, `batch_number`, `quantity_issued`) VALUES
(10, 13, 9, 6, '0', 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `goods_issuing`
--

CREATE TABLE `goods_issuing` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) DEFAULT NULL,
  `issue_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `issued_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goods_issuing`
--

INSERT INTO `goods_issuing` (`id`, `requisition_id`, `issue_date`, `issued_by_user_id`, `notes`) VALUES
(9, 12, '2025-12-02 16:43:55', 1, NULL),
(10, 16, '2025-12-03 14:56:01', 23, NULL),
(11, 17, '2025-12-03 15:27:12', 23, NULL),
(12, 18, '2025-12-03 15:37:17', 23, NULL),
(13, 19, '2025-12-03 15:56:46', 23, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `goods_receiving`
--

CREATE TABLE `goods_receiving` (
  `id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `receive_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by_user_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `goods_receiving`
--

INSERT INTO `goods_receiving` (`id`, `po_id`, `supplier_id`, `receive_date`, `received_by_user_id`, `notes`) VALUES
(21, 15, 10, '2025-11-19 02:32:13', 23, ''),
(22, NULL, 14, '2025-11-26 03:01:42', 1, 'เดือน ธันวาคม'),
(23, 16, 10, '2025-12-03 13:03:43', 23, 'INV.1234'),
(24, 17, 10, '2025-12-03 13:07:49', 23, 'inv1234'),
(25, 18, 8, '2025-12-03 13:10:11', 23, 'inv1234'),
(26, 19, 10, '2025-12-03 13:19:45', 23, ''),
(27, 20, 10, '2025-12-03 14:53:36', 23, 'INv1234'),
(28, 21, 11, '2025-12-03 14:53:50', 23, 'inv4434');

-- --------------------------------------------------------

--
-- Table structure for table `gr_items`
--

CREATE TABLE `gr_items` (
  `id` int(11) NOT NULL,
  `gr_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_received` decimal(10,2) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `putaway_location_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `gr_items`
--

INSERT INTO `gr_items` (`id`, `gr_id`, `material_id`, `quantity_received`, `batch_number`, `expiry_date`, `putaway_location_id`) VALUES
(28, 21, 13, 500.00, 'SN01', NULL, 7),
(29, 21, 13, 500.00, 'SN02', NULL, 7),
(30, 22, 18, 20.00, 'BCH-20251126', NULL, 10),
(31, 23, 9, 20.00, 'BCH-20251203', NULL, 6),
(32, 24, 9, 100.00, 'SN1234', NULL, 6),
(33, 25, 9, 20.00, 'SN99', NULL, 6),
(34, 26, 13, 100.00, 'SN1', NULL, 7),
(35, 26, 13, 50.00, 'SN2', NULL, 7),
(36, 27, 17, 50.00, 'BCH-20251203', NULL, 5),
(37, 28, 12, 20.00, 'BCH-20251203', NULL, 7);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity_reserved` decimal(10,2) NOT NULL DEFAULT 0.00,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `material_id`, `location_id`, `quantity`, `quantity_reserved`, `batch_number`, `expiry_date`, `last_updated`) VALUES
(28, 13, 7, 1150.00, 0.00, '0', NULL, '2025-12-03 13:19:45'),
(30, 18, 10, 20.00, 10.00, '0', NULL, '2025-12-03 15:35:41'),
(31, 9, 6, 120.00, 30.00, '0', NULL, '2025-12-03 15:56:46'),
(36, 17, 5, 50.00, 0.00, '0', NULL, '2025-12-03 14:53:36'),
(37, 12, 7, 20.00, 0.00, '0', NULL, '2025-12-03 14:53:50');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_code` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_code`, `description`) VALUES
(5, 'A01', ''),
(6, 'A02', ''),
(7, 'A03', ''),
(8, 'A04', ''),
(9, 'A05', ''),
(10, 'CS-PM', 'ST-1807');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL COMMENT 'พาทเก็บรูปภาพสินค้า',
  `drawing_file_path` varchar(255) DEFAULT NULL COMMENT 'พาทเก็บไฟล์ Drawing (PDF, DWG)',
  `min_stock_level` decimal(10,2) DEFAULT 0.00,
  `max_stock_level` decimal(10,2) DEFAULT 0.00,
  `default_location_id` int(11) DEFAULT NULL,
  `status` enum('Active','InActive','Obsolete') NOT NULL DEFAULT 'Active',
  `category_id` int(11) DEFAULT NULL COMMENT 'FK ไปตาราง material_categories',
  `preferred_supplier_id` int(11) DEFAULT NULL COMMENT 'FK ไปตาราง suppliers',
  `lead_time_days` int(3) DEFAULT 0 COMMENT 'ระยะเวลาที่ใช้ในการสั่งซื้อ (วัน)',
  `is_serial_tracking` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=คุม Serial, 0=ไม่คุม'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `item_code`, `name`, `description`, `unit`, `image_path`, `drawing_file_path`, `min_stock_level`, `max_stock_level`, `default_location_id`, `status`, `category_id`, `preferred_supplier_id`, `lead_time_days`, `is_serial_tracking`) VALUES
(6, 'GEN-001', 'เคเบิ้ลไทร์', '', 'ถุง', '', '', 100.00, 10000.00, 5, 'Active', 11, 10, 30, 0),
(7, 'GEN-002', 'เทปพันเกลียว', 'เทปสีขาวบาง (PTFE) ใช้สำหรับพันรอบเกลียวนอกของท่อประปาหรือท่อลม เพื่อป้องกันการรั่วซึม', 'ม้วน', '', '', 100.00, 500.00, 5, 'Active', 11, 10, 30, 0),
(8, 'GEN-003', 'ผ้าเช็ดเครื่อง (เศษผ้า)', 'เศษผ้าสะอาด (มักเป็นผ้าคอตตอน) ใช้สำหรับเช็ดคราบน้ำมัน จาระบี หรือสิ่งสกปรกบนเครื่องจักร', 'กิโลกรัม', NULL, NULL, 100.00, 500.00, 5, 'Active', 11, 10, 30, 0),
(9, 'OFF-001', 'กระดาษ A4 80 แกรม', 'กระดาษถ่ายเอกสารขนาดมาตรฐาน (210x297 มม.) ความหนา 80 แกรม (1 รีม = 500 แผ่น)', 'รีม', NULL, NULL, 5.00, 20.00, 6, 'Active', 12, 9, 30, 0),
(10, 'OFF-002', 'ปากกาลูกลื่น (น้ำเงิน)', 'ปากกาสำหรับเขียนเอกสารทั่วไป หมึกสีน้ำเงิน (ระบุขนาดหัว เช่น 0.5 มม. หรือ 0.7 มม.)', 'ด้าม', NULL, NULL, 100.00, 1000.00, 6, 'Active', 12, 9, 30, 0),
(11, 'OFF-003', 'แฟ้มสันกว้าง 3 นิ้ว', 'แฟ้มเก็บเอกสารขนาด A4 ปกแข็ง มีสันหนา 3 นิ้ว พร้อมคลิปเหล็กสำหรับยึดกระดาษ', 'อัน', NULL, NULL, 5.00, 20.00, 6, 'Active', 12, 9, 30, 0),
(12, 'LUB-001', 'จาระบี (เบอร์ 2)', 'สารหล่อลื่นชนิดข้น (เบอร์ 2 คือค่าความอ่อนแข็ง) ใช้สำหรับลูกปืนและจุดหมุนที่รับแรงกระแทก', 'กระป๋อง', NULL, NULL, 5.00, 20.00, 7, 'Active', 8, 11, 30, 0),
(13, 'LUB-002', 'น้ำมันไฮดรอลิก (เบอร์ 68)', 'น้ำมันสำหรับใช้ในระบบไฮดรอลิก (เบอร์ 68 คือค่าความหนืด) เพื่อส่งกำลังและหล่อลื่น', 'ลิตร', NULL, NULL, 30.00, 1000.00, 7, 'Active', 8, 11, 30, 1),
(14, 'LUB-003', 'น้ำมันเกียร์ (เบอร์ 90)', 'น้ำมันหล่อลื่นสำหรับชุดเกียร์ (เบอร์ 90) ที่ต้องการการรับแรงกดสูง (High Pressure)', 'ลิตร', NULL, NULL, 30.00, 1500.00, 7, 'Active', 8, 11, 30, 0),
(15, 'MECH-001', 'ตลับลูกปืน (Bearing) 6205', 'MECH-001', 'ตลับ', '/uploads/materials/images/file_691d24518a43b8.00983771.png', '', 20.00, 200.00, 8, 'Active', 10, 8, 30, 0),
(16, 'ELE-001', 'แมกเนติก คอนแทคเตอร์', 'สวิตช์ไฟฟ้าที่ควบคุมด้วยแม่เหล็กไฟฟ้า ใช้สำหรับสตาร์ท/หยุด มอเตอร์หรือโหลดกำลังสูง (ระบุพิกัด Amp และ Volt ของคอยล์)', 'ตัว', NULL, NULL, 5.00, 20.00, 7, 'Active', 9, 11, 30, 0),
(17, 'IT-001', 'จอคอม', 'จอคอม 24 นิ้ว ', 'PC', NULL, NULL, 10.00, 50.00, 5, 'Active', 14, 13, 30, 0),
(18, 'T-85A', 'ตลับหมึกโทนเนอร์ Toner Cartridge รุ่น 85A', 'HP ตลับหมึกโทนเนอร์ Toner Cartridge รุ่น 85A (CE285A) สีดำ', 'PC', '/uploads/materials/images/file_69266d224ec850.29739158.png', NULL, 80.00, 200.00, 10, 'Active', 14, 14, 30, 0);

-- --------------------------------------------------------

--
-- Table structure for table `material_categories`
--

CREATE TABLE `material_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_categories`
--

INSERT INTO `material_categories` (`id`, `name`, `description`) VALUES
(7, 'เหล็ก', ''),
(8, 'สารหล่อลื่น', ''),
(9, 'อะไหล่ไฟฟ้า', ''),
(10, 'อะไหล่เครื่องกล', ''),
(11, 'วัสดุทั่วไป', ''),
(12, 'วัสดุสำนักงาน', ''),
(13, 'อุปกรณ์เซฟตี้', ''),
(14, 'อุปกรณ์ IT', 'อุปกรณ์ IT');

-- --------------------------------------------------------

--
-- Table structure for table `po_items`
--

CREATE TABLE `po_items` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_ordered` decimal(10,2) NOT NULL,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `pr_item_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `po_items`
--

INSERT INTO `po_items` (`id`, `po_id`, `material_id`, `quantity_ordered`, `quantity_received`, `unit_price`, `pr_item_id`) VALUES
(22, 15, 13, 1000.00, 1000.00, 1000.00, NULL),
(23, 16, 9, 20.00, 20.00, 900.00, NULL),
(24, 17, 9, 100.00, 100.00, 800.00, NULL),
(25, 18, 9, 20.00, 20.00, 890.00, NULL),
(26, 19, 13, 150.00, 150.00, 5000.00, NULL),
(27, 20, 17, 50.00, 50.00, 1500.00, NULL),
(28, 21, 12, 20.00, 20.00, 700.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_serials`
--

CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `current_location_id` int(11) DEFAULT NULL,
  `status` enum('In Stock','Reserved','Issued','Defective','Lost') DEFAULT 'In Stock',
  `gr_id` int(11) DEFAULT NULL,
  `po_id` int(11) DEFAULT NULL,
  `receive_date` date DEFAULT NULL,
  `warranty_expire_date` date DEFAULT NULL,
  `gi_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pr_items`
--

CREATE TABLE `pr_items` (
  `id` int(11) NOT NULL,
  `pr_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_requested` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pr_items`
--

INSERT INTO `pr_items` (`id`, `pr_id`, `material_id`, `quantity_requested`) VALUES
(35, 15, 13, 1000.00),
(36, 16, 9, 20.00),
(37, 17, 9, 20.00),
(38, 18, 9, 20.00),
(39, 19, 9, 100.00),
(40, 20, 13, 150.00),
(41, 21, 17, 50.00),
(42, 21, 12, 20.00),
(43, 22, 6, 1.00),
(44, 22, 18, 1.00),
(45, 22, 16, 1.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` enum('Pending PO Approval','PO Rejected','Pending','Partial','Completed','Cancelled') NOT NULL DEFAULT 'Pending PO Approval',
  `payment_status` enum('Unpaid','Partial','Paid') NOT NULL DEFAULT 'Unpaid',
  `created_by_user_id` int(11) DEFAULT NULL,
  `approved_by_user_id` int(11) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `pr_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery_date`, `status`, `payment_status`, `created_by_user_id`, `approved_by_user_id`, `approval_date`, `pr_id`) VALUES
(15, 'PO-2025-00001-S10', 10, '2025-11-19', '2025-11-19', 'Completed', 'Unpaid', 23, 24, '2025-11-19', 15),
(16, 'PO-2025-00016-S10', 10, '2025-12-03', '2025-12-03', 'Completed', 'Unpaid', 23, 24, '2025-12-03', 17),
(17, 'PO-2025-00017-S10', 10, '2025-12-03', '2025-12-03', 'Completed', 'Unpaid', 23, 24, '2025-12-03', 19),
(18, 'PO-2025-00018-S8', 8, '2025-12-03', '2025-12-03', 'Completed', 'Unpaid', 23, 24, '2025-12-03', 18),
(19, 'PO-2025-00019-S10', 10, '2025-12-03', '2025-12-03', 'Completed', 'Unpaid', 23, 24, '2025-12-03', 20),
(20, 'PO-2025-00020-S10', 10, '2025-12-03', '2025-12-03', 'Completed', 'Unpaid', 23, 24, '2025-12-03', 21),
(21, 'PO-2025-00021-S11', 11, '2025-12-03', '2025-12-03', 'Completed', 'Unpaid', 23, 24, '2025-12-03', 21);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requisitions`
--

CREATE TABLE `purchase_requisitions` (
  `id` int(11) NOT NULL,
  `pr_number` varchar(50) NOT NULL,
  `requested_by_user_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('Pending WH Approval','WH Rejected','Approved','PO Created','Cancelled') NOT NULL DEFAULT 'Pending WH Approval',
  `approved_by_user_id` int(11) DEFAULT NULL,
  `approval_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requisitions`
--

INSERT INTO `purchase_requisitions` (`id`, `pr_number`, `requested_by_user_id`, `request_date`, `department`, `reason`, `status`, `approved_by_user_id`, `approval_date`) VALUES
(15, 'PR-AUTO-2025-00001', 23, '2025-11-19', 'Warehouse', 'Auto-PR from Low Stock', 'PO Created', 24, '2025-11-19'),
(16, 'PR-AUTO-2025-00016', 1, '2025-12-03', 'Warehouse', 'Auto-PR from Low Stock', 'Cancelled', 1, '2025-12-03'),
(17, 'PR-AUTO-2025-00017', 23, '2025-12-03', 'Warehouse', 'Auto-PR from Low Stock', 'PO Created', 24, '2025-12-03'),
(18, 'PR-AUTO-2025-00018', 23, '2025-12-03', 'Warehouse', 'Auto-PR from Low Stock', 'PO Created', 24, '2025-12-03'),
(19, 'PR-2025-00019', 23, '2025-12-03', 'Warehouse', 'Test', 'PO Created', 24, '2025-12-03'),
(20, 'PR-2025-00020', 23, '2025-12-03', 'Warehouse', 'เตรียมงาน', 'PO Created', 24, '2025-12-03'),
(21, 'PR-AUTO-2025-00021', 23, '2025-12-03', 'Warehouse', 'Auto-PR from Low Stock', 'PO Created', 24, '2025-12-03'),
(22, 'PR-2025-00022', 1, '2025-12-04', 'skc', 'เตรียมสำหรับเตรียมสำหรับโปรเจ็คA', 'Approved', 1, '2025-12-03');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_headers`
--

CREATE TABLE `quotation_headers` (
  `id` int(11) NOT NULL,
  `pr_id` int(11) NOT NULL COMMENT 'FK to purchase_requisitions',
  `supplier_id` int(11) NOT NULL COMMENT 'FK to suppliers',
  `quotation_file_path` varchar(255) DEFAULT NULL COMMENT 'พาทไฟล์ใบเสนอราคาที่แนบ',
  `total_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'ราคารวม (คำนวณแล้ว)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_headers`
--

INSERT INTO `quotation_headers` (`id`, `pr_id`, `supplier_id`, `quotation_file_path`, `total_amount`, `created_at`) VALUES
(30, 15, 10, '/uploads/quotations/PR15_SUP10_691d249c0a299.png', 1000000.00, '2025-11-19 01:59:56'),
(33, 17, 10, '/uploads/quotations/PR17_SUP10_693034bb4b9b1.png', 18000.00, '2025-12-03 13:01:54'),
(34, 17, 9, '/uploads/quotations/PR17_SUP9_693034bb4bfe9.png', 190000.00, '2025-12-03 13:01:54'),
(35, 19, 10, '/uploads/quotations/PR19_SUP10_693035e6a000b.png', 80000.00, '2025-12-03 13:06:46'),
(36, 18, 8, '/uploads/quotations/PR18_SUP8_69303683b4644.png', 17800.00, '2025-12-03 13:09:23'),
(37, 20, 10, '/uploads/quotations/PR20_SUP10_6930384ecfc8d.png', 750000.00, '2025-12-03 13:17:02'),
(38, 21, 10, '/uploads/quotations/PR21_SUP10_69304e7f10241.png', 91000.00, '2025-12-03 14:51:43'),
(39, 21, 9, '/uploads/quotations/PR21_SUP9_69304e7f107de.png', 96300.00, '2025-12-03 14:51:43'),
(40, 21, 11, '/uploads/quotations/PR21_SUP11_69304e7f10af9.png', 91000.00, '2025-12-03 14:51:43');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_header_id` int(11) NOT NULL COMMENT 'FK to quotation_headers',
  `material_id` int(11) NOT NULL COMMENT 'FK to materials',
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`id`, `quotation_header_id`, `material_id`, `quantity`, `unit_price`) VALUES
(87, 30, 13, 1000.00, 1000.00),
(90, 33, 9, 20.00, 900.00),
(91, 34, 9, 20.00, 9500.00),
(92, 35, 9, 100.00, 800.00),
(93, 36, 9, 20.00, 890.00),
(94, 37, 13, 150.00, 5000.00),
(95, 38, 17, 50.00, 1500.00),
(96, 38, 12, 20.00, 800.00),
(97, 39, 17, 50.00, 1570.00),
(98, 39, 12, 20.00, 890.00),
(99, 40, 17, 50.00, 1540.00),
(100, 40, 12, 20.00, 700.00);

-- --------------------------------------------------------

--
-- Table structure for table `requisitions`
--

CREATE TABLE `requisitions` (
  `id` int(11) NOT NULL,
  `mr_number` varchar(50) NOT NULL,
  `requested_by_user_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('Pending Dept Approval','Dept Rejected','Pending Issue','Issued') NOT NULL DEFAULT 'Pending Dept Approval',
  `approved_by_user_id` int(11) DEFAULT NULL,
  `approval_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisitions`
--

INSERT INTO `requisitions` (`id`, `mr_number`, `requested_by_user_id`, `request_date`, `department`, `status`, `approved_by_user_id`, `approval_date`) VALUES
(11, 'MR-20251202-222639', 28, '2025-12-02', 'HR', 'Dept Rejected', 27, '2025-12-02'),
(12, 'MR-20251202-223250', 28, '2025-12-02', 'HR', 'Issued', 1, '2025-12-02'),
(13, 'MR-20251202-234747', 28, '2025-12-02', 'HR', '', 23, '2025-12-03'),
(14, 'MR-20251202-234959', 28, '2025-12-02', 'HR', '', 23, '2025-12-03'),
(15, 'MR-20251203-194618', 28, '2025-12-03', 'HR', 'Dept Rejected', 27, '2025-12-03'),
(16, 'MR-20251203-215423', 28, '2025-12-03', 'HR', 'Issued', 23, '2025-12-03'),
(17, 'MR-20251203-222549', 27, '2025-12-03', 'HR', 'Issued', 23, '2025-12-03'),
(18, 'MR-20251203-223638', 28, '2025-12-03', 'HR', 'Issued', 23, '2025-12-03'),
(19, 'MR-20251203-225435', 27, '2025-12-03', 'HR', 'Issued', 23, '2025-12-03');

-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--

CREATE TABLE `requisition_items` (
  `id` int(11) NOT NULL,
  `requisition_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_requested` decimal(10,2) NOT NULL,
  `quantity_issued` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requisition_items`
--

INSERT INTO `requisition_items` (`id`, `requisition_id`, `material_id`, `quantity_requested`, `quantity_issued`) VALUES
(13, 11, 9, 90.00, 0.00),
(14, 12, 18, 10.00, 0.00),
(15, 13, 18, 5.00, 0.00),
(16, 14, 18, 5.00, 0.00),
(17, 15, 18, 5.00, 0.00),
(18, 16, 9, 10.00, 0.00),
(19, 17, 9, 10.00, 0.00),
(20, 18, 9, 10.00, 0.00),
(21, 19, 9, 10.00, 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustment_log`
--

CREATE TABLE `stock_adjustment_log` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL COMMENT 'FK to inventory table (which lot was adjusted)',
  `user_id` int(11) NOT NULL COMMENT 'Who adjusted the stock',
  `adjustment_type` enum('ADJ-IN','ADJ-OUT') NOT NULL COMMENT 'Adjust In (Gain) or Out (Loss/Damage)',
  `quantity_adjusted` decimal(10,2) NOT NULL COMMENT 'The amount that was changed (+ or -)',
  `reason` varchar(255) NOT NULL COMMENT 'Reason for adjustment (e.g., Stock Count, Damage)',
  `adjustment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_adjustment_log`
--

INSERT INTO `stock_adjustment_log` (`id`, `material_id`, `inventory_id`, `user_id`, `adjustment_type`, `quantity_adjusted`, `reason`, `adjustment_date`) VALUES
(4, 9, 31, 23, 'ADJ-OUT', 10.00, 'ธำหะ', '2025-12-03 15:52:55');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(200) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`) VALUES
(8, 'บ.เอ จำกัด', '', '', ''),
(9, 'บ.บี จำกัด', '', '', ''),
(10, 'บ.ซี จำกัด', '', '', ''),
(11, 'บ.อี จำกัด', '', '', ''),
(12, 'บ.เอฟ จำกัด', '', '', ''),
(13, 'บ.ไอที ซัพพอร์ต เซอร์วิส จำกัด', 'นายไอที ซัพพอร์ต', '0987654321', 'it@mail.com'),
(14, 's2568.co.ltd', 'admin_cs', '1234567891', 'k324165@hotmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_invoices`
--

CREATE TABLE `supplier_invoices` (
  `id` int(11) NOT NULL,
  `po_id` int(11) DEFAULT NULL COMMENT 'FK to purchase_orders (ถ้ามี)',
  `supplier_id` int(11) NOT NULL,
  `invoice_number` varchar(100) NOT NULL COMMENT 'เลขที่ใบแจ้งหนี้ของ Supplier',
  `invoice_date` date NOT NULL,
  `invoice_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Unpaid','Paid') NOT NULL DEFAULT 'Unpaid',
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('ADMIN','WH_MANAGER','WH_STAFF','DEPT_MANAGER','DEPT_STAFF') NOT NULL DEFAULT 'DEPT_STAFF',
  `department_id` int(11) DEFAULT NULL,
  `job_title` enum('Manager','Assistant Manager','Staff') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `department_id`, `job_title`) VALUES
(1, 'admin', '$2y$10$dg4C1EBd5MeJt174J/x48OaGuHuQrsfhCvZda/KqAWU4J580/FfkS', 'admin', NULL, 'ADMIN', NULL, ''),
(23, 'WH01', '$2y$10$pe9jZaVf0x33N4MuFZu7De6rcLKgi0GFzByTnfIx5pZkYt9cCMZyu', 'สมชาย ใจดี', NULL, 'WH_STAFF', 3, 'Staff'),
(24, 'M01', '$2y$10$m1s7lTskah0PUJEH4Wl8TuShE26adrMusIGxYfVYcQY8MgnNPVduW', 'ผู้จัดการ พัสดุ', NULL, 'WH_MANAGER', 3, ''),
(25, 'IT01', '$2y$10$SSVife/Qt0RPVMgG8J4nCuxhIKOyOnkoDDaq/56TQ4Ddizy7UYxpS', 'ผู้จัดการ ไอที', NULL, 'DEPT_MANAGER', 1, 'Manager'),
(26, 'IT02', '$2y$10$oGUxTW3XubRpBNVx8ddC1Ou8bncmgGZSTv70hxcuX1gNRGTt6sCFa', 'พนักงาน ไอที', NULL, 'DEPT_STAFF', 1, 'Staff'),
(27, 'H01', '$2y$10$IvqrH2gALzWl3m35hAGy/uzMn3S770h/mhlopYP3ez67ecSya36N6', 'นางสาวสุดใจ สายสวย', 'nopa.sawa593@gmail.com', 'DEPT_MANAGER', 2, 'Manager'),
(28, 'H02', '$2y$10$5stDAzJHoPUGe8U95UgZne1vCvhCXdsxu1AdsyYGpPub1/fnzm0gW', 'พนักงาน บุคคล', NULL, 'DEPT_STAFF', 2, 'Staff');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ap_payments`
--
ALTER TABLE `ap_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `paid_by_user_id` (`paid_by_user_id`),
  ADD KEY `pay_fk_invoice` (`invoice_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `gi_items`
--
ALTER TABLE `gi_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gi_id` (`gi_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `goods_issuing`
--
ALTER TABLE `goods_issuing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisition_id` (`requisition_id`),
  ADD KEY `issued_by_user_id` (`issued_by_user_id`);

--
-- Indexes for table `goods_receiving`
--
ALTER TABLE `goods_receiving`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `received_by_user_id` (`received_by_user_id`);

--
-- Indexes for table `gr_items`
--
ALTER TABLE `gr_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gr_id` (`gr_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `putaway_location_id` (`putaway_location_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock` (`material_id`,`location_id`,`batch_number`),
  ADD KEY `location_id` (`location_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_code` (`location_code`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `default_location_id` (`default_location_id`);

--
-- Indexes for table `material_categories`
--
ALTER TABLE `material_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `po_items`
--
ALTER TABLE `po_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `pr_item_id` (`pr_item_id`);

--
-- Indexes for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `material_id` (`material_id`,`serial_number`),
  ADD KEY `current_location_id` (`current_location_id`);

--
-- Indexes for table `pr_items`
--
ALTER TABLE `pr_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `po_fk_approver` (`approved_by_user_id`);

--
-- Indexes for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pr_number` (`pr_number`),
  ADD KEY `requested_by_user_id` (`requested_by_user_id`),
  ADD KEY `approved_by_user_id` (`approved_by_user_id`);

--
-- Indexes for table `quotation_headers`
--
ALTER TABLE `quotation_headers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pr_id` (`pr_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_header_id` (`quotation_header_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `requisitions`
--
ALTER TABLE `requisitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mr_number` (`mr_number`),
  ADD KEY `requested_by_user_id` (`requested_by_user_id`),
  ADD KEY `approved_by_user_id` (`approved_by_user_id`);

--
-- Indexes for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requisition_id` (`requisition_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `stock_adjustment_log`
--
ALTER TABLE `stock_adjustment_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `inv_fk_user` (`created_by_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ap_payments`
--
ALTER TABLE `ap_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `gi_items`
--
ALTER TABLE `gi_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `goods_issuing`
--
ALTER TABLE `goods_issuing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `goods_receiving`
--
ALTER TABLE `goods_receiving`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `gr_items`
--
ALTER TABLE `gr_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `material_categories`
--
ALTER TABLE `material_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `po_items`
--
ALTER TABLE `po_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `product_serials`
--
ALTER TABLE `product_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pr_items`
--
ALTER TABLE `pr_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `quotation_headers`
--
ALTER TABLE `quotation_headers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `requisitions`
--
ALTER TABLE `requisitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `requisition_items`
--
ALTER TABLE `requisition_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `stock_adjustment_log`
--
ALTER TABLE `stock_adjustment_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ap_payments`
--
ALTER TABLE `ap_payments`
  ADD CONSTRAINT `ap_payments_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `ap_payments_ibfk_2` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pay_fk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices` (`id`);

--
-- Constraints for table `gi_items`
--
ALTER TABLE `gi_items`
  ADD CONSTRAINT `gi_items_ibfk_1` FOREIGN KEY (`gi_id`) REFERENCES `goods_issuing` (`id`),
  ADD CONSTRAINT `gi_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `gi_items_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `goods_issuing`
--
ALTER TABLE `goods_issuing`
  ADD CONSTRAINT `goods_issuing_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`),
  ADD CONSTRAINT `goods_issuing_ibfk_2` FOREIGN KEY (`issued_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `goods_receiving`
--
ALTER TABLE `goods_receiving`
  ADD CONSTRAINT `goods_receiving_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `goods_receiving_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `goods_receiving_ibfk_3` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `gr_items`
--
ALTER TABLE `gr_items`
  ADD CONSTRAINT `gr_items_ibfk_1` FOREIGN KEY (`gr_id`) REFERENCES `goods_receiving` (`id`),
  ADD CONSTRAINT `gr_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `gr_items_ibfk_3` FOREIGN KEY (`putaway_location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`default_location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `po_items`
--
ALTER TABLE `po_items`
  ADD CONSTRAINT `po_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `po_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `po_items_ibfk_3` FOREIGN KEY (`pr_item_id`) REFERENCES `pr_items` (`id`);

--
-- Constraints for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `product_serials_ibfk_2` FOREIGN KEY (`current_location_id`) REFERENCES `locations` (`id`);

--
-- Constraints for table `pr_items`
--
ALTER TABLE `pr_items`
  ADD CONSTRAINT `pr_items_ibfk_1` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pr_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `po_fk_approver` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`);

--
-- Constraints for table `purchase_requisitions`
--
ALTER TABLE `purchase_requisitions`
  ADD CONSTRAINT `purchase_requisitions_ibfk_1` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_requisitions_ibfk_2` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `quotation_headers`
--
ALTER TABLE `quotation_headers`
  ADD CONSTRAINT `qh_fk_pr` FOREIGN KEY (`pr_id`) REFERENCES `purchase_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qh_fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `qi_fk_header` FOREIGN KEY (`quotation_header_id`) REFERENCES `quotation_headers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qi_fk_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `requisitions`
--
ALTER TABLE `requisitions`
  ADD CONSTRAINT `requisitions_ibfk_1` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `requisitions_ibfk_2` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD CONSTRAINT `requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `requisitions` (`id`),
  ADD CONSTRAINT `requisition_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `stock_adjustment_log`
--
ALTER TABLE `stock_adjustment_log`
  ADD CONSTRAINT `log_fk_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`),
  ADD CONSTRAINT `log_fk_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `log_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `supplier_invoices`
--
ALTER TABLE `supplier_invoices`
  ADD CONSTRAINT `inv_fk_po` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  ADD CONSTRAINT `inv_fk_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `inv_fk_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
