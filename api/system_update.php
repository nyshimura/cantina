<?php
/**
 * api/system_update.php
 * Sistema de Atualização Automática via GitHub
 */

// Limpa qualquer output anterior (espaços em branco, etc)
if (ob_get_length()) ob_clean();

set_time_limit(600);
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// --- SEGURANÇA ---
require_once __DIR__ . '/../includes/auth.php';

// VERIFICAÇÃO DE PERMISSÃO CORRIGIDA
// Aceita ADMIN ou quem tem permissão de gerenciar configurações
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
    echo json_encode(['success' => false, 'logs' => [['msg' => 'Acesso negado: Permissão insuficiente.', 'type' => 'error']]]);
    exit;
}

// Fecha sessão para evitar travamento durante download longo
session_write_close();

// --- CONFIGURAÇÕES DO GITHUB ---
define('GITHUB_USER',   'nyshimura');       
define('GITHUB_REPO',   'cantina');  
define('GITHUB_BRANCH', 'main');              
define('GITHUB_TOKEN',  ''); 

// Conexão DB
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

// --- FUNÇÕES DE VERSÃO ---
function getLocalVersion() {
    $path = __DIR__ . '/../package.json';
    clearstatcache(true, $path);
    return file_exists($path) ? (json_decode(file_get_contents($path), true)['version'] ?? '0.0.0') : '0.0.0';
}

function getRemoteVersion() {
    $url = "https://raw.githubusercontent.com/" . GITHUB_USER . "/" . GITHUB_REPO . "/" . GITHUB_BRANCH . "/package.json?t=" . time();
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => ['User-Agent: PHP-Updater-Cantina']
        ]
    ];
    if (!empty(GITHUB_TOKEN)) {
        $opts['http']['header'][] = "Authorization: token " . GITHUB_TOKEN;
    }
    $ctx = stream_context_create($opts);
    $c = @file_get_contents($url, false, $ctx);
    return $c ? (json_decode($c, true)['version'] ?? null) : null;
}

// --- DOWNLOAD E CÓPIA ---
function downloadAndExtractUpdate(&$resp) {
    addLog($resp, "1. Baixando pacote do GitHub...", 'info');
    
    $zipUrl = "https://github.com/" . GITHUB_USER . "/" . GITHUB_REPO . "/archive/refs/heads/" . GITHUB_BRANCH . ".zip";
    $tempZip = __DIR__ . '/update_temp.zip';
    $extractPath = __DIR__ . '/update_temp_folder';
    
    if (file_exists($tempZip)) unlink($tempZip);
    if (is_dir($extractPath)) deleteDirectory($extractPath);

    $opts = ['http' => ['method' => 'GET', 'header' => ['User-Agent: PHP-Updater-Cantina']]];
    if (!empty(GITHUB_TOKEN)) $opts['http']['header'][] = "Authorization: token " . GITHUB_TOKEN;
    
    $fileContent = @file_get_contents($zipUrl, false, stream_context_create($opts));

    if (!$fileContent || strlen($fileContent) < 100) {
        addLog($resp, "Erro Fatal: Falha no download do ZIP.", 'error');
        return false;
    }
    file_put_contents($tempZip, $fileContent);
    addLog($resp, "Download OK (" . round(strlen($fileContent)/1024) . " KB).", 'success');

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
            addLog($resp, "2. Atualizando arquivos...", 'info');
            $count = recursiveCopy($sourceRoot, $systemRoot, $resp);
            
            if ($count > 0) {
                addLog($resp, "Sucesso: $count arquivos atualizados.", 'success');
                if (function_exists('opcache_reset')) opcache_reset();
            } else {
                addLog($resp, "Aviso: Nenhum arquivo copiado. Verifique permissões.", 'warning');
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

// --- CÓPIA SEGURA (NÃO TOCA NO AUTH.PHP) ---
function recursiveCopy($src, $dst, &$resp) {
    $dir = opendir($src);
    @mkdir($dst, 0755, true);
    $copiedCount = 0;

    while (($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            
            // --- LISTA NEGRA: ARQUIVOS QUE NÃO PODEM SER SOBRESCRITOS ---
            if (strpos($dstPath, 'config/db.php') !== false) continue;
            if (strpos($dstPath, 'includes/auth.php') !== false) continue; // <--- CRUCIAL: Protege a sessão
            if (strpos($dstPath, 'includes/sidebar.php') !== false) continue; // Opcional: Protege menu
            if (strpos($dstPath, '/certs/') !== false) continue;
            if (strpos($dstPath, '/uploads/') !== false) continue;
            if (strpos($dstPath, '/install/') !== false) continue;
            // -----------------------------------------------------------
            
            if (is_dir($srcPath)) {
                $copiedCount += recursiveCopy($srcPath, $dstPath, $resp);
            } else {
                if (!@copy($srcPath, $dstPath)) {
                    @chmod($dstPath, 0644);
                    if (!@copy($srcPath, $dstPath)) {
                        // addLog($resp, "Falha: $file", 'error'); 
                    } else {
                        $copiedCount++;
                    }
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
        ['type'=>'col', 't'=>'system_settings', 'c'=>'security_enable_pin', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('security_enable_pin', '0') ON DUPLICATE KEY UPDATE setting_key=setting_key"],
        ['type'=>'col', 't'=>'system_settings', 'c'=>'security_pin_min_amount', 'sql'=>"INSERT INTO system_settings (setting_key, setting_value) VALUES ('security_pin_min_amount', '0.00') ON DUPLICATE KEY UPDATE setting_key=setting_key"]
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
    
    if ($remote && version_compare($remote, $local, '>')) {
        $response['update_available'] = true;
    }

    if ($action == 'check') {
        if ($response['update_available']) {
            addLog($response, "Nova versão v$remote detectada.", 'success');
        } else {
            addLog($response, "Sistema atualizado.", 'info');
        }
    }
    elseif ($action == 'perform_update') {
        if ($remote) {
            if(downloadAndExtractUpdate($response)) {
                if ($conn) runMigrations($conn, $response);
                $response['version_local'] = getLocalVersion();
            }
        } else {
            addLog($resp, "Erro ao conectar ao GitHub.", 'error');
        }
    }
    $response['success'] = true;
} catch (Exception $e) {
    addLog($response, "Erro Fatal: " . $e->getMessage(), 'error');
}

echo json_encode($response);
exit;
?>