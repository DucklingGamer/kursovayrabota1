-- phpMyAdmin SQL Dump
-- version 5.1.3-3.red80
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Окт 28 2026 г., 04:48
-- Версия сервера: 10.11.11-MariaDB
-- Версия PHP: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `belov_bd1.2`
--

-- --------------------------------------------------------

--
-- Структура таблицы `action_log`
--

CREATE TABLE `action_log` (
  `log_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `login` varchar(30) NOT NULL,
  `action` varchar(255) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `action_log`
--

INSERT INTO `action_log` (`log_id`, `user_id`, `login`, `action`, `ip`, `created_at`) VALUES
(1, 1, 'admin', 'Неудачная попытка входа', '::1', '2026-10-09 13:10:30'),
(2, 1, 'admin', 'Неудачная попытка входа', '::1', '2026-10-09 13:10:41'),
(3, NULL, 'chesnok', 'Создана новая учётная запись', '::1', '2026-10-09 13:11:35'),
(4, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:11:47'),
(5, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 13:11:54'),
(6, 1, 'admin', 'Неудачная попытка входа', '::1', '2026-10-09 13:13:33'),
(7, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:15:29'),
(8, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 13:20:26'),
(9, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:21:05'),
(10, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 13:22:15'),
(11, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 13:25:23'),
(12, 1, 'admin', 'Блокировка user_id=2', '::1', '2026-10-09 13:25:28'),
(13, 1, 'admin', 'Разблокировка user_id=2', '::1', '2026-10-09 13:25:33'),
(14, 1, 'admin', 'Блокировка user_id=2', '::1', '2026-10-09 13:26:23'),
(15, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 13:26:32'),
(16, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 13:27:01'),
(17, 1, 'admin', 'Разблокировка user_id=2', '::1', '2026-10-09 13:27:03'),
(18, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 13:27:05'),
(19, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:27:19'),
(20, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 13:27:26'),
(21, 1, 'admin', 'Неудачная попытка входа', '::1', '2026-10-09 13:36:18'),
(22, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 13:36:29'),
(23, 1, 'admin', 'Блокировка user_id=2', '::1', '2026-10-09 13:36:35'),
(24, 1, 'admin', 'Разблокировка user_id=2', '::1', '2026-10-09 13:36:39'),
(25, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 13:39:02'),
(26, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:39:39'),
(27, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 13:44:09'),
(28, 1, 'admin', 'Неудачная попытка входа', '::1', '2026-10-09 13:47:15'),
(29, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 13:47:28'),
(30, 1, 'admin', 'Обновлён профиль', '::1', '2026-10-09 13:48:18'),
(31, 1, 'admin', 'Блокировка user_id=2', '::1', '2026-10-09 13:49:36'),
(32, 1, 'admin', 'Разблокировка user_id=2', '::1', '2026-10-09 13:49:43'),
(33, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 13:49:58'),
(34, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:50:18'),
(35, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 13:52:30'),
(36, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 13:52:43'),
(37, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 13:58:08'),
(38, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 13:58:33'),
(39, 1, 'admin', 'Выдана роль администратора для chesnok (id=2)', '::1', '2026-10-09 13:58:45'),
(40, 1, 'admin', 'Забрана роль администратора у chesnok', '::1', '2026-10-09 13:58:52'),
(41, 1, 'admin', 'Выдана роль администратора для chesnok (id=2)', '::1', '2026-10-09 13:58:53'),
(42, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 13:58:54'),
(43, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 13:59:19'),
(44, 2, 'chesnok', 'Выход из системы', '::1', '2026-10-09 14:01:50'),
(45, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 14:02:02'),
(46, 1, 'admin', 'Забрана роль администратора у chesnok', '::1', '2026-10-09 14:02:04'),
(47, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 14:06:49'),
(48, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 14:07:17'),
(49, 1, 'admin', 'Изменён авто #1 (BMW 5 Series)', '::1', '2026-10-09 14:09:46'),
(50, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 14:10:01'),
(51, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 14:10:15'),
(52, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 14:14:51'),
(53, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 14:15:10'),
(54, 1, 'admin', 'Выдана роль администратора для chesnok (id=2)', '::1', '2026-10-09 14:16:40'),
(55, 1, 'admin', 'Забрана роль администратора у chesnok', '::1', '2026-10-09 14:16:41'),
(56, 1, 'admin', 'Блокировка user_id=2', '::1', '2026-10-09 14:16:44'),
(57, 1, 'admin', 'Разблокировка user_id=2', '::1', '2026-10-09 14:16:48'),
(58, 1, 'admin', 'Блокировка user_id=2', '::1', '2026-10-09 14:16:59'),
(59, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 14:17:03'),
(60, 1, 'admin', 'Успешный вход', '::1', '2026-10-09 14:17:34'),
(61, 1, 'admin', 'Разблокировка user_id=2', '::1', '2026-10-09 14:17:37'),
(62, 1, 'admin', 'Выход из системы', '::1', '2026-10-09 14:18:49'),
(63, 2, 'chesnok', 'Успешный вход', '::1', '2026-10-09 14:19:10'),
(64, 2, 'chesnok', 'Обновлён профиль', '::1', '2026-10-09 14:19:40'),
(65, 1, 'admin', 'Успешный вход', '::1', '2026-10-13 13:31:32'),
(66, 1, 'admin', 'Выход из системы', '::1', '2026-10-13 13:31:38'),
(67, 1, 'admin', 'Успешный вход', '::1', '2026-10-13 13:57:59'),
(68, 1, 'admin', 'Выход из системы', '::1', '2026-10-13 14:11:26'),
(69, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 10:54:28'),
(70, 1, 'admin', 'Изменён авто #1 (BMW 5 Series)', '::1', '2026-10-14 10:57:03'),
(71, 1, 'admin', 'Выход из системы', '::1', '2026-10-14 11:09:42'),
(72, NULL, 'belovgleb', 'Создана новая учётная запись', '::1', '2026-10-14 11:10:10'),
(73, 3, 'belovgleb', 'Успешный вход', '::1', '2026-10-14 11:10:22'),
(74, 3, 'belovgleb', 'Выход из системы', '::1', '2026-10-14 11:10:25'),
(75, 3, 'belovgleb', 'Неудачная попытка входа', '::1', '2026-10-14 11:10:34'),
(76, 3, 'belovgleb', 'Неудачная попытка входа', '::1', '2026-10-14 11:10:40'),
(77, 3, 'belovgleb', 'Неудачная попытка входа', '::1', '2026-10-14 11:10:46'),
(78, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 11:11:15'),
(79, 1, 'admin', 'Обновлён профиль', '::1', '2026-10-14 11:11:45'),
(80, 1, 'admin', 'Выход из системы', '::1', '2026-10-14 11:15:25'),
(81, 3, 'belovgleb', 'Аккаунт заблокирован на 15 минут (3 неудачных попытки)', '::1', '2026-10-14 11:15:45'),
(82, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 11:16:02'),
(83, 1, 'admin', 'Выход из системы', '::1', '2026-10-14 11:19:28'),
(84, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 11:20:21'),
(85, 1, 'admin', 'Выход из системы', '::1', '2026-10-14 11:35:47'),
(86, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 11:37:00'),
(87, 1, 'admin', 'Разблокировка user_id=3', '::1', '2026-10-14 11:37:02'),
(88, 1, 'admin', 'Выход из системы', '::1', '2026-10-14 11:40:09'),
(89, 3, 'belovgleb', 'Успешный вход', '::1', '2026-10-14 11:40:24'),
(90, 3, 'belovgleb', 'Выход из системы', '::1', '2026-10-14 11:40:27'),
(91, 3, 'belovgleb', 'Неудачная попытка входа (1/3)', '::1', '2026-10-14 11:40:34'),
(92, 3, 'belovgleb', 'Неудачная попытка входа (2/3)', '::1', '2026-10-14 11:40:43'),
(93, 3, 'belovgleb', 'Аккаунт заблокирован на 15 минут (3 неудачных попытки)', '::1', '2026-10-14 11:40:49'),
(94, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 11:41:16'),
(95, 1, 'admin', 'Изменён авто #1 (BMW 5 Series)', '::1', '2026-10-14 11:45:22'),
(96, 3, 'belovgleb', 'Успешный вход', '::1', '2026-10-14 11:56:00'),
(97, 3, 'belovgleb', 'Выход из системы', '::1', '2026-10-14 11:56:09'),
(98, 1, 'admin', 'Успешный вход', '::1', '2026-10-14 11:56:19'),
(99, 1, 'admin', 'Выдана роль администратора для belovgleb (id=3)', '::1', '2026-10-14 11:57:56'),
(100, 1, 'admin', 'Забрана роль администратора у belovgleb', '::1', '2026-10-14 11:58:01'),
(101, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 11:03:05'),
(102, 1, 'admin', 'Изменён авто #2 (Mercedes E-Class)', '::1', '2026-10-16 11:27:44'),
(103, 1, 'admin', 'Изменён авто #1 (BMW 5 Series)', '::1', '2026-10-16 11:27:47'),
(104, 1, 'admin', 'Изменён авто #5 (Lexus RX 350)', '::1', '2026-10-16 11:27:51'),
(105, 1, 'admin', 'Изменён авто #8 (Tesla Model S)', '::1', '2026-10-16 11:27:53'),
(106, 1, 'admin', 'Изменён авто #7 (Land Rover Defender)', '::1', '2026-10-16 11:27:55'),
(107, 1, 'admin', 'Изменён авто #2 (Mercedes E-Class)', '::1', '2026-10-16 11:30:21'),
(108, 1, 'admin', 'Изменён авто #8 (Tesla Model S)', '::1', '2026-10-16 11:32:59'),
(109, 1, 'admin', 'Изменён авто #8 (Tesla Model S)', '::1', '2026-10-16 11:34:25'),
(110, 1, 'admin', 'Изменён авто #4 (Porsche Cayenne)', '::1', '2026-10-16 11:36:54'),
(111, 1, 'admin', 'Изменён авто #8 (Tesla Model S)', '::1', '2026-10-16 11:37:25'),
(112, 1, 'admin', 'Изменён авто #4 (Porsche Cayenne)', '::1', '2026-10-16 11:37:29'),
(113, 1, 'admin', 'Изменён авто #4 (Porsche Cayenne)', '::1', '2026-10-16 11:37:54'),
(114, 1, 'admin', 'Изменён авто #8 (Tesla Model S)', '::1', '2026-10-16 11:37:59'),
(115, 1, 'admin', 'Добавлен авто ChesnoCar Z', '::1', '2026-10-16 11:45:52'),
(116, 1, 'admin', 'Изменён авто #9 (ChesnoCar Z)', '::1', '2026-10-16 11:46:21'),
(117, 1, 'admin', 'Изменён авто #9 (ChesnoCar Z)', '::1', '2026-10-16 11:47:01'),
(118, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 11:52:27'),
(119, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 11:52:38'),
(120, 1, 'admin', 'Смена пароля', '::1', '2026-10-16 11:53:26'),
(121, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 11:53:27'),
(122, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 11:53:48'),
(123, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 11:54:00'),
(124, 1, 'admin', 'Неудачная попытка входа (1/3)', '::1', '2026-10-16 11:54:10'),
(125, 1, 'admin', 'Неудачная попытка входа (2/3)', '::1', '2026-10-16 11:54:58'),
(126, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 11:55:14'),
(127, 1, 'admin', 'Смена пароля', '::1', '2026-10-16 11:55:36'),
(128, 1, 'admin', 'Смена пароля', '::1', '2026-10-16 12:01:19'),
(129, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 12:01:20'),
(130, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 12:01:36'),
(131, 1, 'admin', 'Изменён авто #9 (ChesnoCar Z)', '::1', '2026-10-16 12:12:14'),
(132, 1, 'admin', 'Изменён авто #4 (Porsche Cayenne)', '::1', '2026-10-16 12:15:43'),
(133, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 12:44:38'),
(134, 3, 'belovgleb', 'Неудачная попытка входа (1/3)', '::1', '2026-10-16 12:45:23'),
(135, 3, 'belovgleb', 'Успешный вход', '::1', '2026-10-16 12:45:31'),
(136, NULL, 'Белов Глеб', 'Заявка на покупку: car_id=9, тел=79515867717', '::1', '2026-10-16 12:46:12'),
(137, 3, 'belovgleb', 'Выход из системы', '::1', '2026-10-16 12:46:28'),
(138, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 12:46:39'),
(139, 1, 'admin', 'Заявка #1 → статус in_progress', '::1', '2026-10-16 12:47:01'),
(140, 1, 'admin', 'Заявка #1 → статус done', '::1', '2026-10-16 12:47:04'),
(141, 1, 'admin', 'Заявка #1 → статус rejected', '::1', '2026-10-16 12:47:05'),
(142, 1, 'admin', 'Заявка #1 → статус new', '::1', '2026-10-16 12:47:07'),
(143, 1, 'admin', 'Заявка #1 → статус in_progress', '::1', '2026-10-16 12:47:10'),
(144, 1, 'admin', 'Заявка #1 → статус rejected', '::1', '2026-10-16 12:47:11'),
(145, 1, 'admin', 'Заявка #1 → статус done', '::1', '2026-10-16 12:47:12'),
(146, 1, 'admin', 'Заявка #1 → статус new', '::1', '2026-10-16 12:47:13'),
(147, 1, 'admin', 'Заявка #1 → статус in_progress', '::1', '2026-10-16 12:48:10'),
(148, 1, 'admin', 'Заявка #1 → статус done', '::1', '2026-10-16 12:48:11'),
(149, 1, 'admin', 'Заявка #1 → статус rejected', '::1', '2026-10-16 12:48:12'),
(150, 1, 'admin', 'Заявка #1 → статус in_progress', '::1', '2026-10-16 12:48:13'),
(151, 1, 'admin', 'Заявка #1 → статус new', '::1', '2026-10-16 12:48:14'),
(152, 1, 'admin', 'Заявка #1 → статус done', '::1', '2026-10-16 12:48:20'),
(153, 1, 'admin', 'Заявка #1 → статус in_progress', '::1', '2026-10-16 12:48:21'),
(154, 1, 'admin', 'Заявка #1 → статус new', '::1', '2026-10-16 12:48:24'),
(155, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 12:48:24'),
(156, 3, 'belovgleb', 'Успешный вход', '::1', '2026-10-16 12:48:43'),
(157, 3, 'belovgleb', 'Выход из системы', '::1', '2026-10-16 12:50:09'),
(158, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 12:50:23'),
(159, 1, 'admin', 'Выход из системы', '::1', '2026-10-16 12:50:29'),
(160, 1, 'admin', 'Успешный вход', '::1', '2026-10-16 12:50:40'),
(161, 1, 'admin', 'Успешный вход', '::1', '2026-10-20 09:07:27'),
(162, 1, 'admin', 'Заявка #1 → статус rejected', '::1', '2026-10-20 09:29:26'),
(163, 1, 'admin', 'Заявка #1 → статус new', '::1', '2026-10-20 09:29:45'),
(164, 1, 'admin', 'Изменён авто #3 (Audi Q7)', '::1', '2026-10-20 10:15:18'),
(165, 1, 'admin', 'Изменён авто #5 (Lexus RX 350)', '::1', '2026-10-20 10:15:49'),
(166, 1, 'admin', 'Изменён авто #6 (Volvo XC90)', '::1', '2026-10-20 10:16:16'),
(167, 1, 'admin', 'Изменён авто #7 (Land Rover Defender)', '::1', '2026-10-20 10:16:43'),
(168, 1, 'admin', 'Добавлен авто Kia Ceed 3', '::1', '2026-10-20 10:20:29'),
(169, 1, 'admin', 'Изменён авто #10 (Kia Ceed 3)', '::1', '2026-10-20 10:20:34'),
(170, 1, 'admin', 'Добавлен авто Toyota Auris Wagon', '::1', '2026-10-20 10:24:11'),
(171, 1, 'admin', 'Выход из системы', '::1', '2026-10-20 10:24:24'),
(172, 1, 'admin', 'Успешный вход', '::1', '2026-10-21 10:45:48'),
(173, 1, 'admin', 'Успешный вход', '::1', '2026-10-28 11:25:58'),
(174, 1, 'admin', 'Выход из системы', '::1', '2026-10-28 11:31:44'),
(175, 1, 'admin', 'Успешный вход', '::1', '2026-10-28 11:32:37'),
(176, 1, 'admin', 'Заявка #1 → статус in_progress', '::1', '2026-10-28 11:34:29'),
(177, 1, 'admin', 'Заявка #1 → статус rejected', '::1', '2026-10-28 11:34:30'),
(178, 1, 'admin', 'Заявка #1 → статус done', '::1', '2026-10-28 11:34:31');

-- --------------------------------------------------------

--
-- Структура таблицы `car`
--

CREATE TABLE `car` (
  `car_id` bigint(20) NOT NULL,
  `vin` varchar(17) NOT NULL,
  `brand` varchar(60) NOT NULL,
  `model` varchar(60) NOT NULL,
  `year` smallint(6) NOT NULL,
  `color` varchar(40) DEFAULT NULL,
  `power_hp` smallint(6) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `body_type` varchar(40) DEFAULT NULL,
  `fuel` varchar(40) DEFAULT NULL,
  `status` enum('active','sold','service','archived') NOT NULL DEFAULT 'active',
  `current_owner_id` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `car`
--

INSERT INTO `car` (`car_id`, `vin`, `brand`, `model`, `year`, `color`, `power_hp`, `price`, `body_type`, `fuel`, `status`, `current_owner_id`, `created_at`, `updated_at`, `deleted_at`, `image_path`) VALUES
(1, 'WBA5E51000G123456', 'BMW', '5 Series', 2024, 'Чёрный', 276, '5490000.00', 'Седан', 'Бензин', 'active', NULL, '2026-10-09 13:17:31', '2026-10-14 10:57:03', NULL, 'uploads/car_20261009_070946_4c1352e8.jpg'),
(2, 'WDD2130421A987654', 'Mercedes', 'E-Class', 2023, 'Серебро', 200, '6200000.00', 'Седан', 'Дизель', 'active', NULL, '2026-10-09 13:17:31', '2026-10-16 11:30:21', NULL, 'uploads/car_20261016_043021_4edfb281.webp'),
(3, 'WAUZZZ4M0JD654321', 'Audi', 'Q7', 2024, 'Белый', 340, '7100000.00', 'Кроссовер', 'Бензин', 'active', NULL, '2026-10-09 13:17:31', '2026-10-20 10:15:18', NULL, 'uploads/car_20261020_031518_524dd21e.jpg'),
(4, 'WP1ZZZ92ZGLA12345', 'Porsche', 'Cayenne', 2026, 'Серый', 470, '9800000.00', 'Внедорожник', 'Гибрид', 'active', NULL, '2026-10-09 13:17:31', '2026-10-16 12:15:43', NULL, 'uploads/car_20261016_043754_71546b0b.jpg'),
(5, 'JTJHY00W504123456', 'Lexus', 'RX 350', 2023, 'Белый', 275, '6850000.00', 'Кроссовер', 'Бензин', 'active', NULL, '2026-10-09 13:17:31', '2026-10-20 10:15:49', NULL, 'uploads/car_20261020_031549_eb5f349f.jpg'),
(6, 'YV1LFA2ACK1076543', 'Volvo', 'XC90', 2024, 'Синий', 235, '7400000.00', 'Внедорожник', 'Дизель', 'active', NULL, '2026-10-09 13:17:31', '2026-10-20 10:16:16', NULL, 'uploads/car_20261020_031616_9555ed9f.jpg'),
(7, 'SALGA2EF1EA123456', 'Land Rover', 'Defender', 2023, 'Хаки', 300, '8300000.00', 'Внедорожник', 'Дизель', 'active', NULL, '2026-10-09 13:17:31', '2026-10-20 10:16:43', NULL, 'uploads/car_20261020_031643_9d3ad34c.jpg'),
(8, '5YJSA1E26HF000123', 'Tesla', 'Model S', 2013, 'Красный', 670, '8900000.00', 'Седан', 'Электро', 'active', NULL, '2026-10-09 13:17:31', '2026-10-16 11:37:59', NULL, 'uploads/car_20261016_043759_3b41c048.webp'),
(9, 'WBA5A5C50ED512345', 'ChesnoCar', 'Z', 2027, 'Красный', 1150, '25000000.00', 'Купе', 'Бензин', 'active', NULL, '2026-10-16 11:45:52', '2026-10-16 11:47:01', NULL, 'uploads/car_20261016_044552_15208a4e.jpg'),
(10, 'WBAJC51060B123456', 'Kia', 'Ceed 3', 2026, 'Синий', 250, '2000000.00', 'Хэтчбек', 'Бензин', 'active', NULL, '2026-10-20 10:20:29', '2026-10-20 10:20:34', NULL, 'uploads/car_20261020_032029_f3e9ba8b.webp'),
(11, 'WVWZZZ1KZAW123456', 'Toyota', 'Auris Wagon', 2026, 'Белый', 260, '2500000.00', 'Универсал', 'Бензин', 'active', NULL, '2026-10-20 10:24:11', '2026-10-20 10:24:11', NULL, 'uploads/car_20261020_032411_8edbfec0.webp');

-- --------------------------------------------------------

--
-- Структура таблицы `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` varchar(1000) DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  `created_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `notes`
--

INSERT INTO `notes` (`id`, `title`, `content`, `id_user`, `created_at`) VALUES
(1, 'Конференция ИТ', 'Обсуждение новых технологий', 25, '2027-03-15'),
(2, 'Планёрка отдела', 'Задачи на неделю', 12, '2027-03-16'),
(3, 'Идеи для проекта', 'Список гипотез для MVP', 25, '2027-03-17'),
(4, 'Напоминание', 'Позвонить клиенту в 15:00', 7, '2027-03-18'),
(5, 'Отчёт за квартал', 'Черновик финансового отчёта', 3, '2027-03-19');

-- --------------------------------------------------------

--
-- Структура таблицы `purchase_request`
--

CREATE TABLE `purchase_request` (
  `request_id` bigint(20) NOT NULL,
  `car_id` bigint(20) NOT NULL,
  `client_name` varchar(120) NOT NULL,
  `client_phone` varchar(30) NOT NULL,
  `comment` text DEFAULT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `status` enum('new','in_progress','done','rejected') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `purchase_request`
--

INSERT INTO `purchase_request` (`request_id`, `car_id`, `client_name`, `client_phone`, `comment`, `user_id`, `status`, `created_at`) VALUES
(1, 9, 'Белов Глеб', '79515867717', 'хочу данный автомобиль', 3, 'done', '2026-10-16 12:46:12');

-- --------------------------------------------------------

--
-- Структура таблицы `user`
--

CREATE TABLE `user` (
  `user_id` bigint(20) NOT NULL,
  `login` varchar(30) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `first_name` varchar(60) NOT NULL,
  `middle_name` varchar(60) DEFAULT NULL,
  `fio` varchar(180) GENERATED ALWAYS AS (concat_ws(' ',`last_name`,`first_name`,`middle_name`)) VIRTUAL,
  `email` varchar(120) NOT NULL,
  `phone_encrypted` varchar(255) DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `totp_secret` varchar(64) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `failed_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ;

--
-- Дамп данных таблицы `user`
--

INSERT INTO `user` (`user_id`, `login`, `password_hash`, `last_name`, `first_name`, `middle_name`, `email`, `phone_encrypted`, `role_id`, `status_id`, `totp_secret`, `totp_enabled`, `failed_attempts`, `locked_until`, `password_changed_at`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'admin', '$2y$10$UzkVmPuaKhIDey9Ht7lM7.fTZ4lSBKkZPKKFfBU.iovZYtl5XlpZ6', 'Ад', 'ми', 'н', 'admin@autocatalog.ru', NULL, 3, 2, NULL, 0, 0, NULL, '2026-10-16 12:01:19', '2026-10-28 11:32:37', '2026-10-09 13:09:43', '2026-10-28 11:32:37', NULL),
(2, 'chesnok', '$2y$10$S2VlTNT/r1.UIils9pJH9OhDrGCNHLa83nUquBIjdOITIq2p1e86u', 'Чесноков', 'ДД', NULL, 'chesnok@autocatalog.ru', NULL, 1, 2, NULL, 0, 0, NULL, NULL, '2026-10-09 14:19:10', '2026-10-09 13:11:35', '2026-10-09 14:19:10', NULL),
(3, 'belovgleb', '$2y$10$Lx4Ck071dssT4ACGChkHRemwFJM3qBxN6IFjjdpKUO4.4o2DE6qea', 'Глеб', 'Белов', NULL, 'belovgleb@autocatalog.ru', NULL, 1, 2, NULL, 0, 0, NULL, NULL, '2026-10-16 12:48:43', '2026-10-14 11:10:10', '2026-10-16 12:48:43', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `login`) VALUES
(3, 'user03'),
(7, 'user07'),
(12, 'user12'),
(25, 'user25');

-- --------------------------------------------------------

--
-- Структура таблицы `user_audit`
--

CREATE TABLE `user_audit` (
  `audit_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `action` varchar(20) NOT NULL,
  `field_name` varchar(60) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` bigint(20) DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `user_role`
--

CREATE TABLE `user_role` (
  `role_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `level` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_role`
--

INSERT INTO `user_role` (`role_id`, `code`, `name`, `level`) VALUES
(1, 'user', 'Пользователь', 1),
(2, 'manager', 'Менеджер', 5),
(3, 'admin', 'Администратор', 9);

-- --------------------------------------------------------

--
-- Структура таблицы `user_status`
--

CREATE TABLE `user_status` (
  `status_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_status`
--

INSERT INTO `user_status` (`status_id`, `code`, `name`) VALUES
(1, 'pending', 'Ожидает'),
(2, 'active', 'Активен'),
(3, 'blocked', 'Заблокирован'),
(4, 'deleted', 'Удалён');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `action_log`
--
ALTER TABLE `action_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Индексы таблицы `car`
--
ALTER TABLE `car`
  ADD PRIMARY KEY (`car_id`),
  ADD UNIQUE KEY `uq_car_vin` (`vin`),
  ADD KEY `idx_car_brand` (`brand`);

--
-- Индексы таблицы `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notes_user` (`id_user`);

--
-- Индексы таблицы `purchase_request`
--
ALTER TABLE `purchase_request`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_req_car` (`car_id`);

--
-- Индексы таблицы `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_user_login` (`login`),
  ADD UNIQUE KEY `uq_user_email` (`email`),
  ADD KEY `idx_user_role` (`role_id`),
  ADD KEY `idx_user_status` (`status_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login` (`login`);

--
-- Индексы таблицы `user_audit`
--
ALTER TABLE `user_audit`
  ADD PRIMARY KEY (`audit_id`);

--
-- Индексы таблицы `user_role`
--
ALTER TABLE `user_role`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `uq_role_code` (`code`);

--
-- Индексы таблицы `user_status`
--
ALTER TABLE `user_status`
  ADD PRIMARY KEY (`status_id`),
  ADD UNIQUE KEY `uq_status_code` (`code`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `action_log`
--
ALTER TABLE `action_log`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=179;

--
-- AUTO_INCREMENT для таблицы `car`
--
ALTER TABLE `car`
  MODIFY `car_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `purchase_request`
--
ALTER TABLE `purchase_request`
  MODIFY `request_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `user`
--
ALTER TABLE `user`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_audit`
--
ALTER TABLE `user_audit`
  MODIFY `audit_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `user_role`
--
ALTER TABLE `user_role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `user_status`
--
ALTER TABLE `user_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `fk_notes_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `purchase_request`
--
ALTER TABLE `purchase_request`
  ADD CONSTRAINT `fk_req_car` FOREIGN KEY (`car_id`) REFERENCES `car` (`car_id`);

--
-- Ограничения внешнего ключа таблицы `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `user_role` (`role_id`),
  ADD CONSTRAINT `fk_user_status` FOREIGN KEY (`status_id`) REFERENCES `user_status` (`status_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
