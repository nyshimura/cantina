
-- Database Creation
CREATE DATABASE IF NOT EXISTS cantina_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cantina_db;

-- System Settings
CREATE TABLE system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT,
    description VARCHAR(255)
);

-- Operators
CREATE TABLE operators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    access_level ENUM('ADMIN', 'CASHIER') DEFAULT 'CASHIER',
    permissions JSON,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Parents
CREATE TABLE parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    phone VARCHAR(20),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL, -- Responsável Financeiro Principal
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    cpf VARCHAR(14),
    balance DECIMAL(10, 2) DEFAULT 0.00,
    daily_limit DECIMAL(10, 2) DEFAULT 0.00,
    can_self_charge BOOLEAN DEFAULT FALSE,
    recharge_config JSON,
    avatar_url TEXT,
    active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- Co-Parents (Many-to-Many relationship for shared custody/access)
CREATE TABLE student_co_parents (
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, parent_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- NFC Tags
CREATE TABLE nfc_tags (
    tag_id VARCHAR(50) PRIMARY KEY,
    status ENUM('ACTIVE', 'SPARE') DEFAULT 'SPARE',
    current_student_id INT NULL,
    parent_owner_id INT NULL,
    last_student_name VARCHAR(255),
    FOREIGN KEY (current_student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_owner_id) REFERENCES parents(id) ON DELETE CASCADE
);

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    image_url TEXT,
    active BOOLEAN DEFAULT TRUE
);

-- Transactions
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('PURCHASE', 'DEPOSIT') NOT NULL,
    status ENUM('PENDING', 'COMPLETED', 'CANCELLED') DEFAULT 'COMPLETED',
    items_summary TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Audit Logs
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    actor_type ENUM('OPERATOR', 'PARENT', 'STUDENT') NOT NULL,
    actor_name VARCHAR(255),
    action VARCHAR(50) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
