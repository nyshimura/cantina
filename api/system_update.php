<?php
/**
 * api/system_update.php
 * Sistema de Atualização Automática via GitHub (Versão Robust cURL)
 */

if (ob_get_length()) ob_clean();

set_time_limit(600);
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// --- SEGURANÇA ---
require_once __DIR__ . '/../includes/auth.php';

$hasPermission = false;
if (isset($_SESSION['access_level']) && $_SESSION['access_level'] === 'ADMIN') {
    $hasPermission = true;
} elseif (isset($_SESSION['permissions'])) {
    $perms = json_decode($_SESSION['permissions'], true);
    if (isset($perms['canManageSettings']) && $perms['canManageSettings'] === true) {
        $hasPermission = true;
    }
}

if (!$hasPermission) {
    echo json_encode(['success' => false, 'logs' => [['msg' => 'Acesso negado.', 'type' => 'error']]]);
    exit;
}

session_write_close();

// --- CONFIGURAÇÕES GITHUB ---
define('GITHUB_USER',   'nyshimura');       
define('GITHUB_REPO',   'cantina');  
define('GITHUB_BRANCH', 'main');              
define('GITHUB_TOKEN',  ''); 

global $pdo;
$conn = $pdo;

$response = [
    'success' => false,
    'update_available' => false,
    'logs' => [],
    'version_local' => '0.0.0',
    'version_remote' => '---'
];

function addLog(&$resp, $msg, $type = 'info') {
    $resp['logs'][] = ['msg' => $msg, 'type' => $type];
}

// --- FUNÇÃO AUXILIAR: REQUISIÇÃO HTTP ROBUSTA (CURL) ---
function fetchUrl($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Evita erro de SSL em servidores antigos
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Updater-Cantina');
        
        if (!empty(GITHUB_TOKEN)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: token " . GITHUB_TOKEN]);
        }
        
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) return false;
        return $data;
    } else {
        // Fallback para file_get_contents se cURL não existir
        $opts = ['http' => ['method' => 'GET', 'header' => ['User-Agent: PHP-Updater-Cantina']]];
        if (!empty(GITHUB_TOKEN)) {
            $opts['http']['header'][] = "Authorization: token " . GITHUB_TOKEN;
        }
        $ctx = stream_context_create($opts);
        return @file_get_contents($url, false, $ctx);
    }
}

// --- VERSÕES ---
function getLocalVersion() {
    $path = __DIR__ . '/../package.json';
    clearstatcache(true, $path);
    if (file_exists($path)) {
        $content = @file_get_contents($path);
        if ($content) {
            $json = json_decode($content, true);
            return $json['version'] ?? '0.0.0';
        }
    }
    return '0.0.0';
}

function getRemoteVersion() {
    $url = "https://raw.githubusercontent.com/" . GITHUB_USER . "/" . GITHUB_REPO . "/" . GITHUB_BRANCH . "/package.json?t=" . time();
    $jsonContent = fetchUrl($url);
    
    if ($jsonContent) {
        $data = json_decode($jsonContent, true);
        return $data['version'] ?? null;
    }
    return null;
}

// --- DOWNLOAD E CÓPIA ---
function downloadAndExtractUpdate(&$resp) {
    addLog($resp, "1. Baixando pacote...", 'info');
    
    $zipUrl = "https://github.com/" . GITHUB_USER . "/" . GITHUB_REPO . "/archive/refs/heads/" . GITHUB_BRANCH . ".zip?nocache=" . time();
    $tempZip = __DIR__ . '/update_temp.zip';
    $extractPath = __DIR__ . '/update_temp_folder';
    
    if (file_exists($tempZip)) unlink($tempZip);
    if (is_dir($extractPath)) deleteDirectory($extractPath);

    $fileContent = fetchUrl($zipUrl);

    if (!$fileContent || strlen($fileContent) < 100) {
        addLog($resp, "Erro Fatal: Download falhou ou arquivo vazio.", 'error');
        return false;
    }
    file_put_contents($tempZip, $fileContent);
    
    $zip = new ZipArchive;
    if ($zip->open($tempZip) === TRUE) {
        if (!is_dir($extractPath)) mkdir($extractPath, 0755, true);
        $zip->extractTo($extractPath);
        $zip->close();
        
        $subFolders = scandir($extractPath);
        $sourceRoot = null;
        foreach ($subFolders as $folder) {
            if ($folder != '.' && $folder != '..' && is_dir($extractPath . '/' . $folder)) {
                $sourceRoot = $extractPath . '/' . $folder;
                break;
            }
        }

        if ($sourceRoot) {
            $systemRoot = dirname(__DIR__); 
            addLog($resp, "2. Aplicando arquivos...", 'info');
            $count = recursiveCopy($sourceRoot, $systemRoot, $resp);
            
            if ($count > 0) {
                addLog($resp, "Sucesso: $count arquivos atualizados.", 'success');
                if (function_exists('opcache_reset')) opcache_reset();
            } else {
                addLog($resp, "Aviso: Nenhum arquivo copiado.", 'warning');
            }
        } else {
            addLog($resp, "Erro: ZIP inválido.", 'error');
        }
        
        @unlink($tempZip);
        deleteDirectory($extractPath);
        return true;
    } else {
        addLog($resp, "Erro ao abrir ZIP.", 'error');
        return false;
    }
}

// --- CÓPIA SEGURA ---
function recursiveCopy($src, $dst, &$resp) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    $copiedCount = 0;

    while (($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            
            // NORMALIZAÇÃO DE CAMINHO PARA WINDOWS/LINUX
            $normalizedDst = str_replace('\\', '/', $dstPath);
            
            // --- PROTEÇÃO CONTRA SOBRESCRITA ---
            if (strpos($normalizedDst, '/config/db.php') !== false) continue;
            if (strpos($normalizedDst, '/certs/') !== false) continue;
            if (strpos($normalizedDst, '/uploads/') !== false) continue;
            if (strpos($normalizedDst, '/install/') !== false) continue;
            // -----------------------------------
            
            if (is_dir($srcPath)) {
                $copiedCount += recursiveCopy($srcPath, $dstPath, $resp);
            } else {
                if (!@copy($srcPath, $dstPath)) {
                    @chmod($dstPath, 0644);
                    if (!@copy($srcPath, $dstPath)) { } else { $copiedCount++; }
                } else {
                    $copiedCount++;
                }
            }
        }
    }
    closedir($dir);
    return $copiedCount;
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// --- MIGRAÇÕES DB ---
function getMigrations() {
    return [
        ['type'=>'col', 't'=>'students', 'c'=>'purchase_pin', 'sql'=>"ALTER TABLE students ADD COLUMN purchase_pin VARCHAR(255) DEFAULT NULL"],
        ['type'=>'col', 't'=>'students', 'c'=>'allow_overdraft', 'sql'=>"ALTER TABLE students ADD COLUMN allow_overdraft TINYINT(1) DEFAULT 1"],
        ['type'=>'col', 't'=>'students', 'c'=>'custom_overdraft_limit', 'sql'=>"ALTER TABLE students ADD COLUMN custom_overdraft_limit DECIMAL(10,2) DEFAULT NULL"],
        ['type'=>'col', 't'=>'students', 'c'=>'pending_balance', 'sql'=>"ALTER TABLE students ADD COLUMN pending_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER daily_limit"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'security_enable_pin', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('security_enable_pin', '0') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'security_pin_min_amount', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('security_pin_min_amount', '0.00') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'global_overdraft_limit', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('global_overdraft_limit', '15.00') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        
        // Novas tabelas e colunas para Reservas (Pre-orders)
        ['type'=>'col', 't'=>'students', 'c'=>'classroom_id', 'sql'=>"ALTER TABLE students ADD COLUMN classroom_id INT DEFAULT NULL"],
        ['type'=>'col', 't'=>'students', 'c'=>'pending_classroom_id', 'sql'=>"ALTER TABLE students ADD COLUMN pending_classroom_id INT DEFAULT NULL"],
        ['type'=>'tbl', 't'=>'meal_schedules', 'sql'=>"CREATE TABLE meal_schedules (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, cutoff_time TIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'tbl', 't'=>'classrooms', 'sql'=>"CREATE TABLE classrooms (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, meal_schedule_id INT DEFAULT NULL, FOREIGN KEY (meal_schedule_id) REFERENCES meal_schedules(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'tbl', 't'=>'pre_orders', 'sql'=>"CREATE TABLE pre_orders (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, operator_id INT DEFAULT NULL, order_date DATE NOT NULL, payment_method ENUM('WALLET', 'CASH') NOT NULL, payment_status ENUM('PENDING', 'PAID', 'REFUNDED') NOT NULL DEFAULT 'PENDING', delivery_status ENUM('PENDING', 'PREPARED', 'DELIVERED', 'CANCELLED') NOT NULL DEFAULT 'PENDING', total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'tbl', 't'=>'pre_order_items', 'sql'=>"CREATE TABLE pre_order_items (id INT AUTO_INCREMENT PRIMARY KEY, pre_order_id INT NOT NULL, product_id INT NOT NULL, qty INT NOT NULL DEFAULT 1, price DECIMAL(10,2) NOT NULL, FOREIGN KEY (pre_order_id) REFERENCES pre_orders(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        
        // Privacidade de Co-Responsáveis
        ['type'=>'col', 't'=>'transactions', 'c'=>'parent_id', 'sql'=>"ALTER TABLE transactions ADD COLUMN parent_id INT DEFAULT NULL AFTER student_id, ADD FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL"],
        
        // Turmas: Ano Letivo
        ['type'=>'col', 't'=>'classrooms', 'c'=>'academic_year', 'sql'=>"ALTER TABLE classrooms ADD COLUMN academic_year YEAR DEFAULT NULL"],

        // Lanche Especial
        ['type'=>'col', 't'=>'products', 'c'=>'is_special_of_day', 'sql'=>"ALTER TABLE products ADD COLUMN is_special_of_day TINYINT(1) DEFAULT 0"],
        
        // Co-Responsáveis
        ['type'=>'tbl', 't'=>'student_co_parents', 'sql'=>"CREATE TABLE student_co_parents (student_id INT NOT NULL, parent_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, active TINYINT(1) DEFAULT 1, PRIMARY KEY (student_id, parent_id), FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE, FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        
        // Self-Recharge Logic (Auto-Recarga)
        ['type'=>'col', 't'=>'students', 'c'=>'can_self_charge', 'sql'=>"ALTER TABLE students ADD COLUMN can_self_charge TINYINT(1) DEFAULT 0"],
        ['type'=>'col', 't'=>'students', 'c'=>'recharge_config', 'sql'=>"ALTER TABLE students ADD COLUMN recharge_config LONGTEXT DEFAULT NULL"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'allow_student_self_recharge', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('allow_student_self_recharge', '1') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        
        // NFC e Melhorias em Transações
        ['type'=>'tbl', 't'=>'nfc_tags', 'sql'=>"CREATE TABLE nfc_tags (tag_id varchar(50) NOT NULL, tag_alias varchar(100) DEFAULT NULL, status enum('ACTIVE','SPARE') DEFAULT 'SPARE', current_student_id int(11) DEFAULT NULL, parent_owner_id int(11) DEFAULT NULL, last_student_name varchar(255) DEFAULT NULL, balance decimal(10,2) DEFAULT 0.00, PRIMARY KEY (tag_id), KEY current_student_id (current_student_id), KEY parent_owner_id (parent_owner_id), CONSTRAINT nfc_tags_ibfk_1 FOREIGN KEY (current_student_id) REFERENCES students (id) ON DELETE SET NULL, CONSTRAINT nfc_tags_ibfk_2 FOREIGN KEY (parent_owner_id) REFERENCES parents (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['type'=>'col', 't'=>'transactions', 'c'=>'items_summary', 'sql'=>"ALTER TABLE transactions ADD COLUMN items_summary text DEFAULT NULL"],
        ['type'=>'col', 't'=>'transactions', 'c'=>'tag_id', 'sql'=>"ALTER TABLE transactions ADD COLUMN tag_id varchar(50) DEFAULT NULL"],
        ['type'=>'col', 't'=>'transactions', 'c'=>'payment_method', 'sql'=>"ALTER TABLE transactions ADD COLUMN payment_method varchar(20) DEFAULT 'NFC'"],
        ['type'=>'col', 't'=>'transactions', 'c'=>'external_reference', 'sql'=>"ALTER TABLE transactions ADD COLUMN external_reference varchar(255) DEFAULT NULL"]
    ];
}

function runMigrations($conn, &$resp) {
    $migrations = getMigrations();
    foreach ($migrations as $mig) {
        $table = $mig['t'];
        $sql   = $mig['sql'];
        $type  = $mig['type'];

        try {
            if ($type === 'col') {
                $col = $mig['c'];
                $stmtT = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmtT->rowCount() == 0) continue; 
                $stmtC = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
                if ($stmtC->rowCount() == 0) {
                    $conn->exec($sql);
                    addLog($resp, "DB: Coluna '$col' criada.", 'success');
                }
            } elseif ($type === 'tbl') {
                $stmtT = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmtT->rowCount() == 0) {
                    $conn->exec($sql);
                    addLog($resp, "DB: Tabela '$table' criada.", 'success');
                }
            } else {
                $conn->exec($sql);
            }
        } catch (Exception $e) { }
    }
}

// --- ROUTER ---
$action = $_GET['action'] ?? 'check';

try {
    $local = getLocalVersion();
    $remote = getRemoteVersion();
    
    $response['version_local'] = $local;
    $response['version_remote'] = $remote ?: 'Falha';
    
    if ($remote && $local && version_compare($remote, $local, '>')) {
        $response['update_available'] = true;
    }

    if ($action == 'check') {
        // Roda migrações durante o check para garantir que tabelas novas sejam criadas
        // logo após o reload da página pós-atualização.
        if ($conn) runMigrations($conn, $response);
        
        if ($response['update_available']) {
            addLog($response, "Nova versão v$remote disponível.", 'success');
        } elseif ($remote === 'Falha' || $remote === null) {
            addLog($response, "Falha ao verificar versão no GitHub.", 'error');
        } else {
            addLog($response, "Sistema atualizado.", 'info');
        }
    }
    elseif ($action == 'perform_update') {
        if ($remote && $remote !== 'Falha') {
            if(downloadAndExtractUpdate($response)) {
                if ($conn) runMigrations($conn, $response);
                $response['version_local'] = getLocalVersion();
            }
        } else {
            addLog($resp, "Erro ao conectar ao GitHub.", 'error');
        }
    }
    elseif ($action == 'migrate') {
        // Roda apenas as migrações (útil para pós-atualização, já que o script antigo em memória
        // não tem as migrações novas no momento do perform_update)
        if ($conn) runMigrations($conn, $response);
        $response['version_local'] = getLocalVersion();
        addLog($response, "Migrações do banco de dados verificadas/aplicadas.", 'success');
    }
    
    $response['success'] = true;
} catch (Exception $e) {
    addLog($response, "Erro Fatal: " . $e->getMessage(), 'error');
}

echo json_encode($response);
exit;
?>
