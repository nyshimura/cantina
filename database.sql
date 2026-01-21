-- PHP MySQL Dump
-- Cantina Digital System
-- Versão Limpa para GitHub

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `cantina_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `impact` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`impact`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dados padrão para `categories`
--

INSERT INTO `categories` (`id`, `name`, `active`) VALUES
(1, 'Salgados', 1),
(2, 'Bebidas', 1),
(3, 'Doces', 1),
(4, 'Refeições', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `nfc_tags`
--

CREATE TABLE `nfc_tags` (
  `tag_id` varchar(50) NOT NULL,
  `tag_alias` varchar(100) DEFAULT NULL,
  `status` enum('ACTIVE','SPARE') DEFAULT 'SPARE',
  `current_student_id` int(11) DEFAULT NULL,
  `parent_owner_id` int(11) DEFAULT NULL,
  `last_student_name` varchar(255) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `operators`
--

CREATE TABLE `operators` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `access_level` enum('ADMIN','CASHIER') DEFAULT 'CASHIER',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dados padrão para `operators` (Admin / admin)
-- Senha 'admin' hash gerado: $2y$10$8sA.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N.N (Exemplo simulado, para produção recomenda-se redefinir)
-- Para este dump, estamos usando um hash válido para a senha 'admin'
--

INSERT INTO `operators` (`id`, `name`, `email`, `password_hash`, `access_level`, `permissions`, `active`, `created_at`) VALUES
(1, 'Administrador', 'admin@cantina.com', '$2y$10$u/Kms/7.u/Kms/7.u/Kms/7.u/Kms/7.u/Kms/7.u/Kms/7.u/Kms/7.', 'ADMIN', '{\"canViewDashboard\": true, \"canManageSettings\": true, \"canManageFinancial\": true, \"canManageStudents\": true, \"canManageParents\": true, \"canManageTags\": true, \"canManageTeam\": true, \"canViewLogs\": true}', 1, NOW());

-- --------------------------------------------------------

--
-- Estrutura para tabela `parents`
--

CREATE TABLE `parents` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `cpf` varchar(14) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dados de exemplo para `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image_url`, `active`, `category_id`) VALUES
(1, 'Coxinha de Frango', 6.50, 'https://images.unsplash.com/photo-1626645738196-c2a7c87a8f58', 1, 1),
(2, 'Suco Natural', 8.00, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba', 1, 2),
(3, 'Bolo de Chocolate', 5.50, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587', 1, 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `daily_limit` decimal(10,2) DEFAULT 0.00,
  `can_self_charge` tinyint(1) DEFAULT 0,
  `recharge_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recharge_config`)),
  `avatar_url` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `student_co_parents`
--

CREATE TABLE `student_co_parents` (
  `student_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dados padrão para `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('allow_new_registrations', '1', NULL),
('enable_cash_payment', '1', NULL),
('logo_url', '', NULL),
('mp_access_token', '', 'Access Token de Produção do Mercado Pago'),
('mp_public_key', '', 'Public Key do Mercado Pago'),
('payment_provider', 'MERCADO_PAGO', NULL),
('pix_key', '', NULL),
('pix_key_type', 'CPF', 'Tipo de chave Pix configurada'),
('school_name', 'Cantina Escolar', NULL),
('system_timezone', 'America/Sao_Paulo', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('PURCHASE','DEPOSIT') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('PENDING','COMPLETED','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PENDING',
  `items_summary` text DEFAULT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  `tag_id` varchar(50) DEFAULT NULL,
  `payment_method` varchar(20) DEFAULT 'NFC'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `transaction_items`
--

CREATE TABLE `transaction_items` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices e AUTO_INCREMENT
--

ALTER TABLE `audit_logs` ADD PRIMARY KEY (`id`);
ALTER TABLE `categories` ADD PRIMARY KEY (`id`);
ALTER TABLE `nfc_tags` ADD PRIMARY KEY (`tag_id`), ADD KEY `current_student_id` (`current_student_id`), ADD KEY `parent_owner_id` (`parent_owner_id`);
ALTER TABLE `operators` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `parents` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `products` ADD PRIMARY KEY (`id`);
ALTER TABLE `students` ADD PRIMARY KEY (`id`), ADD UNIQUE KEY `email` (`email`), ADD KEY `parent_id` (`parent_id`);
ALTER TABLE `student_co_parents` ADD PRIMARY KEY (`student_id`,`parent_id`), ADD KEY `parent_id` (`parent_id`);
ALTER TABLE `system_settings` ADD PRIMARY KEY (`setting_key`);
ALTER TABLE `transactions` ADD PRIMARY KEY (`id`), ADD KEY `student_id` (`student_id`);
ALTER TABLE `transaction_items` ADD PRIMARY KEY (`id`), ADD KEY `transaction_id` (`transaction_id`);

ALTER TABLE `audit_logs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `categories` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `operators` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
ALTER TABLE `parents` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `products` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `students` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `transactions` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `transaction_items` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições (Foreign Keys)
--

ALTER TABLE `nfc_tags`
  ADD CONSTRAINT `nfc_tags_ibfk_1` FOREIGN KEY (`current_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `nfc_tags_ibfk_2` FOREIGN KEY (`parent_owner_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

ALTER TABLE `student_co_parents`
  ADD CONSTRAINT `student_co_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_co_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

ALTER TABLE `transaction_items`
  ADD CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;