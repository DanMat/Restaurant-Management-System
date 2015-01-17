-- phpMyAdmin SQL Dump
-- version 4.0.9
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Dec 11, 2014 at 10:25 PM
-- Server version: 5.6.14
-- PHP Version: 5.5.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `oose`
--

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE IF NOT EXISTS `category` (
  `catId` int(11) NOT NULL AUTO_INCREMENT,
  `catDesc` varchar(20) NOT NULL,
  PRIMARY KEY (`catId`),
  UNIQUE KEY `catId` (`catId`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`catId`, `catDesc`) VALUES
(1, 'Soup'),
(2, 'Appetizer'),
(3, 'Main Course'),
(4, 'Dessert');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE IF NOT EXISTS `employee` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `pwd` varchar(32) NOT NULL,
  `role` varchar(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`id`, `username`, `pwd`, `role`) VALUES
(1, 'waiter', '202cb962ac59075b964b07152d234b70', 'waiter'),
(2, 'cook', '202cb962ac59075b964b07152d234b70', 'cook'),
(3, 'busboy', '202cb962ac59075b964b07152d234b70', 'busboy'),
(4, 'host', '202cb962ac59075b964b07152d234b70', 'host');

-- --------------------------------------------------------

--
-- Table structure for table `floorplan`
--

CREATE TABLE IF NOT EXISTS `floorplan` (
  `tableid` int(11) NOT NULL AUTO_INCREMENT,
  `status` varchar(10) NOT NULL,
  `waiterid` int(11) NOT NULL,
  PRIMARY KEY (`tableid`),
  UNIQUE KEY `tableid` (`tableid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=7 ;

--
-- Dumping data for table `floorplan`
--

INSERT INTO `floorplan` (`tableid`, `status`, `waiterid`) VALUES
(1, 'open', 1),
(2, 'occupied', 1),
(3, 'open', 1),
(4, 'dirty', 1),
(5, 'occupied', 1),
(6, 'occupied', 1);

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

CREATE TABLE IF NOT EXISTS `item` (
  `itemid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL,
  `desc` varchar(30) NOT NULL,
  `price` float(10,2) NOT NULL,
  PRIMARY KEY (`itemid`),
  UNIQUE KEY `itemid` (`itemid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`itemid`, `catid`, `desc`, `price`) VALUES
(1, 1, 'Miso soup', 3.50),
(2, 1, 'Chicken soup', 4.99),
(3, 1, 'Goulash soup', 4.00),
(4, 1, 'Lentil soup', 3.49),
(5, 2, 'Artichoke Spinach Dip', 4.00),
(6, 2, 'Chicken Spread', 4.99),
(7, 2, 'Guacamole', 5.50),
(8, 2, 'Pepperoni Bread', 6.25),
(9, 3, 'Baked Teriyaki Chick', 7.49),
(10, 3, 'Chicken Marsala', 8.21),
(11, 3, 'Grilled Salmon', 9.99),
(12, 3, 'Salsa Chicken', 10.99),
(13, 4, 'Apple crisp', 4.25),
(14, 4, 'Bread pudding', 4.49),
(15, 4, 'Fudge', 4.99),
(16, 4, 'Parfait', 3.99);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `orderid` int(11) NOT NULL AUTO_INCREMENT,
  `orderref` varchar(20) NOT NULL,
  `tableid` int(11) NOT NULL,
  `orderlist` text NOT NULL,
  `done` tinyint(1) NOT NULL DEFAULT '0',
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`orderid`),
  UNIQUE KEY `orderid` (`orderid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=12 ;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`orderid`, `orderref`, `tableid`, `orderlist`, `done`, `paid`) VALUES
(1, '4order20141211152000', 4, 'Chicken Marsala-1-8.21;Artichoke Spinach Dip-1-4.00;Fudge-2-4.99;Baked Teriyaki Chick-1-7.49;Pepperoni Bread-1-6.25;Apple crisp-1-4.25;Salsa Chicken-2-10.99;Baked Teriyaki Chick-1-7.49;Chicken Spread-1-4.99;Pepperoni Bread-1-6.25;Chicken Marsala-1-8.21;Goulash soup-1-4.00;Goulash soup-1-4.00;Chicken Spread-1-4.99;', 1, 1),
(3, '3order20141211152339', 3, 'Fudge-2-4.99;', 1, 1),
(4, '2order20141211164145', 2, 'Chicken soup-1-4.99;Grilled Salmon-1-9.99;Parfait-1-3.99;', 1, 1),
(5, '2order20141211193729', 2, 'Miso soup-4-3.50;Goulash soup-4-4.00;', 1, 1),
(6, '4order20141211220226', 4, 'Goulash soup-1-4.00;Salsa Chicken-3-10.99;', 1, 1),
(7, '3order20141211220301', 3, 'Artichoke Spinach Dip-1-4.00;Fudge-1-4.99;', 1, 1),
(8, '5order20141211221114', 5, '', 1, 1),
(9, '5order20141211221642', 5, '', 1, 1),
(10, '5order20141211222357', 5, 'Goulash soup-1-4.00;Grilled Salmon-1-9.99;', 1, 1),
(11, '6order20141211222432', 6, 'Grilled Salmon-2-9.99;Guacamole-5-5.50;', 1, 1);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
