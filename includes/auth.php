<?php
// includes/auth.php

// --- DEBUG DE ERROS (REMOVER EM PRODUÇÃO DEPOIS QUE FUNCIONAR) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ------------------------------------------------------------------

// Verifica se o arquivo de banco existe antes de incluir
$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    die("Erro Crítico: Arquivo de banco de dados não encontrado em: $dbPath");
}
require_once $dbPath;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- SINCRONIZAÇÃO DE TIMEZONE ---
try {
    // Verifica se a tabela existe antes de consultar (evita erro em instalação nova)
    $stmtTz = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_timezone'");
    if ($stmtTz) {
        $systemTz = $stmtTz->fetchColumn();
        if (!$systemTz) $systemTz = 'America/Sao_Paulo';
        
        date_default_timezone_set($systemTz);
        
        $dt = new DateTime('now', new DateTimeZone($systemTz));
        $offset = $dt->format('P'); 
        $pdo->exec("SET time_zone = '$offset'");
    }
} catch (Exception $e) {
    date_default_timezone_set('America/Sao_Paulo');
}

// --- FUNÇÕES DE LOGIN ---

function login($email, $password) {
    global $pdo;
    try {
        // OPERADOR
        $stmt = $pdo->prepare("SELECT * FROM operators WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = 'OPERATOR';
            $_SESSION['access_level'] = $user['access_level'];
            $_SESSION['permissions'] = $user['permissions'];
            return "admin/dashboard.php"; 
        }
        // ALUNO
        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $student = $stmt->fetch();
        if ($student && password_verify($password, $student['password_hash'])) {
            $_SESSION['user_id'] = $student['id'];
            $_SESSION['name'] = $student['name'];
            $_SESSION['role'] = 'STUDENT';
            return "student/dashboard.php"; 
        }
        // PAI
        $stmt = $pdo->prepare("SELECT * FROM parents WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $parent = $stmt->fetch();
        if ($parent && password_verify($password, $parent['password_hash'])) {
            $_SESSION['user_id'] = $parent['id'];
            $_SESSION['name'] = $parent['name'];
            $_SESSION['role'] = 'PARENT';
            return "parent/dashboard.php"; 
        }
    } catch (PDOException $e) {
        // Loga erro mas não para execução se possível
        error_log("Erro Login: " . $e->getMessage());
    }
    return false;
}

function requireRole($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        // Tenta achar o caminho relativo para login
        $path = '../../login.php';
        if (file_exists('../login.php')) $path = '../login.php';
        if (file_exists('login.php')) $path = 'login.php';
        
        header("Location: $path");
        exit;
    }
}

function requirePermission($key) {
    if (!isset($_SESSION['access_level'])) {
        header("Location: ../../login.php");
        exit;
    }
    if ($_SESSION['access_level'] === 'ADMIN') return true;
    
    $perms = json_decode($_SESSION['permissions'] ?? '{}', true);
    if (!isset($perms[$key]) || $perms[$key] !== true) {
        die("Acesso Negado.");
    }
}

function formatBRL($value) {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function logAction($action, $description, $impact = null) {
    global $pdo;
    $opId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $now = date('Y-m-d H:i:s'); 
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (operator_id, action, description, impact, ip_address, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$opId, $action, $description, $impact ? json_encode($impact) : null, $ip, $now]);
    } catch (Exception $e) {}
}

// --- CRIPTOGRAFIA BLINDADA ---

// Definições de constantes
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', '74447b834e37ab57a6f79d30b6db25decc1990299fb6138205459df60b63ffc1'); 
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

function encryptData($data) {
    if (empty($data)) return '';
    
    // VERIFICA SE O SERVIDOR TEM OPENSSL
    if (!extension_loaded('openssl')) {
        return $data; // Retorna sem criptografar para não quebrar o site
    }

    try {
        $key = hash('sha256', ENCRYPTION_KEY);
        $ivLen = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    } catch (Exception $e) {
        return $data; // Falha segura
    }
}

function decryptData($data) {
    if (empty($data)) return '';
    if (!is_string($data)) return $data;

    // Se não tem separador, não está criptografado
    if (strpos($data, '::') === false) {
        return $data; 
    }

    // VERIFICA SE O SERVIDOR TEM OPENSSL
    if (!extension_loaded('openssl')) {
        return $data; // Não consegue descriptografar, retorna o original
    }

    try {
        $key = hash('sha256', ENCRYPTION_KEY);
        $decoded = base64_decode($data);
        if ($decoded === false) return $data;

        $parts = explode('::', $decoded, 2);
        if (count($parts) < 2) return $data;

        list($encrypted_data, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted_data, ENCRYPTION_METHOD, $key, 0, $iv);

        return $decrypted !== false ? $decrypted : $data;
    } catch (Exception $e) {
        return $data;
    }
}