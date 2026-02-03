
USE cantina_db;

-- Settings
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('school_name', 'Escola Estadual Modelo'),
('logo_url', 'https://cdn-icons-png.flaticon.com/512/167/167707.png'),
('allow_new_registrations', '1'),
('payment_provider', 'MERCADO_PAGO');

-- Operators (Password: 123)
INSERT INTO operators (name, email, password_hash, access_level, permissions) VALUES
('Dona Maria', 'admin@cantina.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'ADMIN', '{"canViewDashboard": true, "canManageSettings": true, "canManageFinancial": true, "canManageStudents": true}'),
('João Caixa', 'caixa@cantina.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'CASHIER', '{"canViewDashboard": true, "canManageFinancial": true}');

-- Parents
INSERT INTO parents (name, email, password_hash, cpf, phone) VALUES
('Carlos Silva', 'carlos@pais.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '123.456.789-00', '(11) 99999-8888'),
('Mariana Oliveira', 'mariana@pais.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '987.654.321-00', '(11) 98888-7777');

-- Students
INSERT INTO students (parent_id, name, email, password_hash, cpf, balance, daily_limit, avatar_url) VALUES
(1, 'Rafael Silva', 'rafael@aluno.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '111.222.333-44', 25.50, 30.00, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Rafael'),
(1, 'Ana Silva', 'ana@aluno.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '555.666.777-88', 10.00, 20.00, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Ana'),
(2, 'Pedro Oliveira', 'pedro@aluno.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', '999.888.777-66', 0.00, 15.00, 'https://api.dicebear.com/7.x/avataaars/svg?seed=Pedro');

-- NFC Tags
INSERT INTO nfc_tags (tag_id, status, current_student_id, parent_owner_id) VALUES
('TAG-RAFAEL', 'ACTIVE', 1, NULL),
('TAG-ANA', 'ACTIVE', 2, NULL),
('TAG-SPARE-01', 'SPARE', NULL, 2);

-- Products
INSERT INTO products (name, price, category, image_url) VALUES
('Coxinha de Frango', 6.50, 'Salgados', 'https://images.unsplash.com/photo-1626645738196-c2a7c87a8f58?auto=format&fit=crop&w=200&q=80'),
('Suco Natural Laranja', 8.00, 'Bebidas', 'https://images.unsplash.com/photo-1620916297397-a4a5402a3c6c?auto=format&fit=crop&w=200&q=80'),
('Bolo de Chocolate', 5.50, 'Doces', 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=200&q=80');
