-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 10-06-2026 a las 11:19:51
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `galeria_cumple`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `albums`
--

CREATE TABLE `albums` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `name` varchar(120) NOT NULL,
  `emoji` varchar(10) NOT NULL DEFAULT '',
  `color` varchar(7) NOT NULL DEFAULT '#0071e3',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `albums`
--

INSERT INTO `albums` (`id`, `user_id`, `name`, `emoji`, `color`, `created_at`, `cover_url`, `sort_order`) VALUES
(3, 2, 'Leo', '', '#5da269', '2026-05-01 12:50:52', 'uploads/cover_69f4c1b23c0667.16816713.jpg', 5),
(4, 2, 'Chaplin', '', '#690ac7', '2026-05-01 12:51:13', 'uploads/cover_69f4c14abae5c9.52416517.jpg', 3),
(5, 2, 'Nidalee', '', '#f04799', '2026-05-01 12:51:24', 'uploads/cover_69f4c1c9014a98.46601209.jpg', 4),
(7, 2, 'Familia', '', '#30b0c7', '2026-05-01 12:52:17', 'uploads/cover_69f4c838c816f2.89882570.jpg', 1),
(8, 2, 'Recuerdos', '', '#ee4c17', '2026-05-02 19:45:28', 'uploads/cover_69f6559419d525.79701229.jpg', 2),
(10, 2, 'Rosquilleta', '', '#e65bac', '2026-05-07 14:15:57', 'uploads/cover_6a01a3a8c5f0c3.20629954.jpg', 0),
(11, 3, 'aves', '', '#8e5dea', '2026-06-10 08:59:30', 'uploads/user_3/cover_6a2928b2ea8f86.81803253.jpg', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `photos`
--

CREATE TABLE `photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `album_id` int(10) UNSIGNED NOT NULL,
  `url` varchar(500) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `tags` varchar(500) NOT NULL DEFAULT '',
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `storage_limit_mb` int(10) UNSIGNED NOT NULL DEFAULT 500,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `storage_limit_mb`, `created_at`) VALUES
(1, 'warlock2484', '$2y$10$j0qjYQGdbf7V3ecDhz.Ug.imOCglnR3aSo8H0EpHoWzfbZXvZDXje', 'admin', 0, '2026-06-10 07:30:04'),
(2, 'dayane', '$2y$10$P6x0TcOPo9SaQ9t.bm4vPuAStf6e5Y/MaIH8jEJ/jTlYM3aRYTB2y', 'user', 0, '2026-06-10 07:30:04'),
(3, 'prueba1', '$2y$10$RG0YenWmwXdPAC7zId0GhO8oECfhWAw0L88BZZLDlU6bc4zFyfTiq', 'user', 10, '2026-06-10 07:36:53');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `albums`
--
ALTER TABLE `albums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_albums_user` (`user_id`);

--
-- Indices de la tabla `photos`
--
ALTER TABLE `photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_photos_album` (`album_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `albums`
--
ALTER TABLE `albums`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `photos`
--
ALTER TABLE `photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=373;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `albums`
--
ALTER TABLE `albums`
  ADD CONSTRAINT `fk_albums_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `photos`
--
ALTER TABLE `photos`
  ADD CONSTRAINT `fk_photos_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
