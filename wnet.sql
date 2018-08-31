-- phpMyAdmin SQL Dump
-- version 4.0.10.6
-- http://www.phpmyadmin.net
--
-- Хост: 127.0.0.1:3306
-- Время создания: Авг 31 2018 г., 08:27
-- Версия сервера: 5.5.41-log
-- Версия PHP: 5.4.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `printing_doc`
--

-- --------------------------------------------------------

--
-- Структура таблицы `obj_contracts`
--

CREATE TABLE IF NOT EXISTS `obj_contracts` (
  `id_contract` int(11) NOT NULL,
  `id_customer` int(11) NOT NULL,
  `number` varchar(100) NOT NULL,
  `date_sign` date NOT NULL,
  `staff_number` varchar(100) NOT NULL,
  PRIMARY KEY (`id_contract`),
  KEY `id_customer` (`id_customer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `obj_contracts`
--

INSERT INTO `obj_contracts` (`id_contract`, `id_customer`, `number`, `date_sign`, `staff_number`) VALUES
(1, 1, '11111111111111', '2017-12-01', 'qqqqqqqqqqqq'),
(2, 2, '222222222222222', '2017-08-03', 'wwwwwwwwwwwwww'),
(3, 1, '131313131313131', '2017-08-02', 'q3q3q3q3q3q3q3');

-- --------------------------------------------------------

--
-- Структура таблицы `obj_customers`
--

CREATE TABLE IF NOT EXISTS `obj_customers` (
  `id_customer` int(11) NOT NULL AUTO_INCREMENT,
  `name_customer` varchar(250) NOT NULL,
  `company` enum('company_1','company_2','company_3') NOT NULL,
  PRIMARY KEY (`id_customer`),
  KEY `id_customer` (`id_customer`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Дамп данных таблицы `obj_customers`
--

INSERT INTO `obj_customers` (`id_customer`, `name_customer`, `company`) VALUES
(1, 'Customer_Number_1', 'company_1'),
(2, 'Customer_Number_2', 'company_2');

-- --------------------------------------------------------

--
-- Структура таблицы `obj_services`
--

CREATE TABLE IF NOT EXISTS `obj_services` (
  `id_service` int(11) NOT NULL,
  `id_contract` int(11) NOT NULL,
  `title_service` varchar(250) NOT NULL,
  `status` enum('work','connecting','disconnected') DEFAULT NULL,
  PRIMARY KEY (`id_service`),
  KEY `id_contract` (`id_contract`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `obj_services`
--

INSERT INTO `obj_services` (`id_service`, `id_contract`, `title_service`, `status`) VALUES
(1, 1, 'Title Service for Contracts 1', 'work'),
(2, 1, 'Title Service for Contracts 2', 'connecting'),
(3, 1, 'Title Service for Contracts 3', 'disconnected'),
(4, 2, '2 Title Service for Contracts 1', 'work'),
(5, 2, '2 Title Service for Contracts 2', 'connecting'),
(6, 2, '2 Title Service for Contracts 3', 'disconnected'),
(7, 3, '1111111111Title Service for Contracts 111', 'work');

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `obj_contracts`
--
ALTER TABLE `obj_contracts`
  ADD CONSTRAINT `obj_contracts_ibfk_1` FOREIGN KEY (`id_customer`) REFERENCES `obj_customers` (`id_customer`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `obj_services`
--
ALTER TABLE `obj_services`
  ADD CONSTRAINT `obj_services_ibfk_1` FOREIGN KEY (`id_contract`) REFERENCES `obj_contracts` (`id_contract`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
