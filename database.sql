-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 03/02/2026 às 15:30
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

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
-- Despejando dados para a tabela `categories`
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
-- Despejando dados para a tabela `operators`
--

INSERT INTO `operators` (`id`, `name`, `email`, `password_hash`, `access_level`, `permissions`, `active`, `created_at`) VALUES
(1, 'Administrador', 'admin@cantina.com', '$2y$10$Zp9qRlSfZKhjkDzaBxDV4Oy0LbWpIzNrcxLynSKokdx12X8TFwkKS', 'ADMIN', '{\"canViewDashboard\":true,\"canManageSettings\":true,\"canManageFinancial\":true,\"canManageStudents\":true,\"canManageParents\":true,\"canManageTags\":true,\"canManageTeam\":true,\"canViewLogs\":true}', 1, '2026-01-16 23:22:24');

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
-- Estrutura para tabela `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
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
-- Despejando dados para a tabela `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `image_url`, `active`, `category_id`) VALUES
(1, 'Coxinha de Frango', 6.50, 'https://guiadacozinha.com.br/wp-content/uploads/2018/08/coxinhadefrangocremosa.webp', 1, 1),
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
  `purchase_pin` varchar(255) DEFAULT NULL,
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
-- Despejando dados para a tabela `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('allow_new_registrations', '1', NULL),
('enable_cash_payment', '1', NULL),
('security_enable_pin', '0', NULL),
('security_pin_min_amount', '0.00', NULL),
('itau_api_key', '', 'Chave de API do Itaú (X-Itau-ApiKey)'),
('itau_client_id', '', 'ID do Cliente Itaú'),
('itau_client_secret', '', 'Secret do Cliente Itaú'),
('itau_env', 'sandbox', 'Ambiente da API Itaú (sandbox/production)'),
('logo_url', '', NULL),
('mp_access_token', '', 'Access Token de Produção do Mercado Pago'),
('mp_client_id', '', NULL),
('mp_client_secret', '', NULL),
('mp_public_key', '', 'Public Key do Mercado Pago'),
('mp_sandbox_mode', '0', NULL),
('payment_provider', 'MANUAL_PIX', NULL),
('pix_key', '', NULL),
('pix_key_type', 'CPF', 'Tipo de chave Pix configurada'),
('school_address', 'Endereço da Escola, 123', NULL),
('school_cnpj', '', NULL),
('school_name', 'Cantina Escolar', NULL),
('smtp_email', 'nao-responda@exemplo.com', 'E-mail remetente do sistema'),
('smtp_host', 'smtp.exemplo.com', 'Servidor SMTP para envios'),
('smtp_password', '', 'Senha de aplicativo ou do e-mail'),
('smtp_port', '465', 'Porta de conexão SMTP'),
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
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `nfc_tags`
--
ALTER TABLE `nfc_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD KEY `current_student_id` (`current_student_id`),
  ADD KEY `parent_owner_id` (`parent_owner_id`);

--
-- Índices de tabela `operators`
--
ALTER TABLE `operators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Índices de tabela `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Índices de tabela `student_co_parents`
--
ALTER TABLE `student_co_parents`
  ADD PRIMARY KEY (`student_id`,`parent_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Índices de tabela `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Índices de tabela `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Índices de tabela `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `operators`
--
ALTER TABLE `operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `parents`
--
ALTER TABLE `parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `transaction_items`
--
ALTER TABLE `transaction_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `nfc_tags`
--
ALTER TABLE `nfc_tags`
  ADD CONSTRAINT `nfc_tags_ibfk_1` FOREIGN KEY (`current_student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `nfc_tags_ibfk_2` FOREIGN KEY (`parent_owner_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `student_co_parents`
--
ALTER TABLE `student_co_parents`
  ADD CONSTRAINT `student_co_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_co_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Restrições para tabelas `transaction_items`
--
ALTER TABLE `transaction_items`
  ADD CONSTRAINT `transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

