<?php
// api/recharge.php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

// 1. Verifica Sessão
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['STUDENT', 'PARENT'])) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = $input['amount'] ?? 0;
$targetStudentId = ($_SESSION['role'] === 'STUDENT') ? $_SESSION['user_id'] : ($input['student_id'] ?? null);

if (!$targetStudentId || $amount <= 0) { 
    echo json_encode(['success' => false, 'message' => 'Valor inválido.']); 
    exit; 
}

// --- NOVA VALIDAÇÃO: Verifica se o Pai Logado tem permissão para este aluno ---
if ($_SESSION['role'] === 'PARENT') {
    $parentId = $_SESSION['user_id'];
    if (!isParentAuthorizedForStudent($pdo, $parentId, $targetStudentId)) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para realizar recargas para este aluno.']);
        exit;
    }
}
// ------------------------------------------------------------------------------

try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $method = $settings['payment_provider'] ?? 'MANUAL_PIX';
    $copyPaste = ''; 
    $qrCodeBase64 = null; 
    
    // GERA A REFERÊNCIA
    $externalRef = 'REC-' . $targetStudentId . '-' . time();

    // ---------------------------------------------------------
    // 1. MERCADO PAGO
    // ---------------------------------------------------------
    if ($method === 'MERCADO_PAGO') {
        
        $accessToken = $settings['mp_access_token'] ?? '';
        
        if (strpos($accessToken, 'TEST-') !== 0 && strpos($accessToken, 'APP_USR-') !== 0) {
             if (function_exists('decryptData')) {
                 $cleaned = decryptData($accessToken);
                 if (!empty($cleaned)) $accessToken = $cleaned;
             }
        }
        $accessToken = trim($accessToken);

        if (empty($accessToken)) throw new Exception("Token MP não configurado.");

        $isSandbox = (strpos($accessToken, 'TEST-') === 0);
        
        $payerEmail = $_SESSION['email'] ?? "email@escola.com";
        $firstName = "Pagador";
        $lastName = "Escola";
        $docType = "CPF";
        $docNumber = ""; 

        if ($isSandbox) {
            $payerEmail = "test_user_" . mt_rand(100000, 999999) . "@testuser.com"; 
            $docNumber = "19119119100";
            $firstName = "Test";
            $lastName = "User";
        } else {
            $sql = "SELECT p.cpf, p.email, p.name FROM students s JOIN parents p ON s.parent_id = p.id WHERE s.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$targetStudentId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) throw new Exception("ERRO: Aluno sem responsável financeiro vinculado.");

            $docNumber = preg_replace('/\D/', '', $data['cpf']);
            $payerEmail = $data['email'];
            
            if (!empty($data['name'])) {
                $parts = explode(' ', trim($data['name']));
                $firstName = $parts[0];
                $lastName = end($parts);
            }

            if (empty($docNumber) || strlen($docNumber) < 11) {
                throw new Exception("ERRO: CPF do responsável inválido.");
            }
            
            if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
                 $payerEmail = "pagador_" . $docNumber . "@gmail.com";
            }
        }

        $notificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . str_replace('/recharge.php', '/webhook_mp.php', $_SERVER['SCRIPT_NAME']);
        
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            $notificationUrl = null; 
        }

        $mpPayload = [
            "transaction_amount" => (float)$amount,
            "description" => "Recarga ID: " . $targetStudentId,
            "payment_method_id" => "pix",
            "external_reference" => $externalRef, 
            "date_of_expiration" => date('Y-m-d\TH:i:s.000P', strtotime('+30 minutes')),
            "payer" => [
                "email" => $payerEmail,
                "first_name" => $firstName,
                "last_name" => $lastName,
                "identification" => [
                    "type" => $docType,
                    "number" => $docNumber
                ]
            ]
        ];
        
        if ($notificationUrl) {
            $mpPayload["notification_url"] = $notificationUrl;
        }

        $ch = curl_init('https://api.mercadopago.com/v1/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mpPayload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-Idempotency-Key: ' . uniqid('', true)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $mpData = json_decode($response, true);

        if ($httpCode !== 201) {
            $msg = $mpData['message'] ?? 'Erro MP';
            if (isset($mpData['cause']) && is_array($mpData['cause'])) {
                foreach ($mpData['cause'] as $c) $msg .= " | " . ($c['description'] ?? '');
            }
            throw new Exception("MP Error ($httpCode): $msg");
        }

        if (isset($mpData['point_of_interaction']['transaction_data'])) {
            $qrCodeBase64 = $mpData['point_of_interaction']['transaction_data']['qr_code_base64'];
            $copyPaste = $mpData['point_of_interaction']['transaction_data']['qr_code'];
        } else {
            throw new Exception("QR Code não gerado.");
        }

    // ---------------------------------------------------------
    // 2. ITAÚ EMPRESAS
    // ---------------------------------------------------------
    } elseif ($method === 'ITAU_PIX') {
        
        require_once __DIR__ . '/../lib/ItauPix.php';
        
        // Busca dados do aluno/responsável
        $sql = "SELECT p.cpf, p.name FROM students s JOIN parents p ON s.parent_id = p.id WHERE s.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$targetStudentId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data || empty($data['cpf'])) {
            $stmtUser = $pdo->prepare("SELECT name, cpf FROM students WHERE id = ?");
            $stmtUser->execute([$targetStudentId]);
            $data = $stmtUser->fetch(PDO::FETCH_ASSOC);
        }

        if (empty($data['cpf'])) throw new Exception("CPF obrigatório para Pix Itaú.");

        // Gera TXID compatível com Itaú
        $itauTxid = md5($externalRef);

        $itau = new ItauPix($pdo);
        $pixData = $itau->createCharge($itauTxid, $amount, [
            'name' => $data['name'],
            'cpf'  => $data['cpf']
        ]);

        $copyPaste = $pixData['copy_paste'];
        $qrCodeBase64 = null; 
        $externalRef = $pixData['txid'];

    } else {
        // ---------------------------------------------------------
        // 3. PIX MANUAL (ESTÁTICO) - CORRIGIDO
        // ---------------------------------------------------------
        $pixKey = $settings['pix_key'] ?? '';
        if (empty($pixKey)) throw new Exception("Chave Pix não configurada no painel.");
        
        // Usa função corrigida que calcula o tamanho dinamicamente
        $copyPaste = montaPix($pixKey, 'Escola', 'Cidade', $amount, $externalRef);
    }

    // SALVA NO BANCO
    $parentIdToSave = ($_SESSION['role'] === 'PARENT') ? $_SESSION['user_id'] : null;
    $stmtTx = $pdo->prepare("INSERT INTO transactions (student_id, parent_id, type, amount, status, items_summary, external_reference, payment_method, timestamp) VALUES (?, ?, 'DEPOSIT', ?, 'PENDING', 'Recarga Pix', ?, 'PIX', NOW())");
    $stmtTx->execute([$targetStudentId, $parentIdToSave, $amount, $externalRef]);

    echo json_encode([
        'success' => true, 
        'copy_paste' => $copyPaste, 
        'qr_code_base64' => $qrCodeBase64, 
        'method' => $method,
        'external_reference' => $externalRef
    ]);

} catch (Exception $e) { 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
}

/**
 * Função montaPix CORRIGIDA
 * Calcula automaticamente o tamanho da string da conta (Field 26)
 * para evitar erro "Parâmetros Inválidos".
 */
function montaPix($chave, $nome, $cidade, $valor, $txId) {
    // Limpeza básica
    $nome = substr(preg_replace("/[^a-zA-Z0-9 ]/", "", removeAcentos($nome)), 0, 25);
    $cidade = substr(preg_replace("/[^a-zA-Z0-9 ]/", "", removeAcentos($cidade)), 0, 15);
    $valor = number_format((float)$valor, 2, '.', '');
    
    // Tratamento do TxId (Max 25 chars, sem espaços)
    // Se o $txId for muito longo (REC-...), usamos apenas os últimos 20 chars ou um padrão seguro
    $txIdClean = substr(preg_replace("/[^a-zA-Z0-9]/", "", $txId), -20);
    if (empty($txIdClean)) $txIdClean = "***";

    // --- MONTAGEM DINÂMICA ---
    
    // 1. Merchant Account Information (Campo 26)
    // GUI (00) + Chave (01)
    $gui = "0014BR.GOV.BCB.PIX";
    $keyField = "01" . sprintf("%02d", strlen($chave)) . $chave;
    $merchantAccountInfo = $gui . $keyField;
    
    // Inicia Payload
    $payload = "000201"; 
    $payload .= "26" . sprintf("%02d", strlen($merchantAccountInfo)) . $merchantAccountInfo;
    
    $payload .= "52040000"; // MCC
    $payload .= "5303986";  // Moeda (BRL)
    $payload .= "54" . sprintf("%02d", strlen($valor)) . $valor; // Valor
    $payload .= "5802BR";   // País
    $payload .= "59" . sprintf("%02d", strlen($nome)) . $nome;    // Nome Recebedor
    $payload .= "60" . sprintf("%02d", strlen($cidade)) . $cidade; // Cidade Recebedor
    
    // Campo 62 (Additional Data) - TxID
    $adField = "05" . sprintf("%02d", strlen($txIdClean)) . $txIdClean;
    $payload .= "62" . sprintf("%02d", strlen($adField)) . $adField;
    
    // CRC16
    $payload .= "6304";
    $payload .= strtoupper(str_pad(dechex(crc16Manual($payload)), 4, '0', STR_PAD_LEFT));
    
    return $payload;
}

function crc16Manual($str) { 
    $crc = 0xFFFF; 
    for ($c = 0; $c < strlen($str); $c++) { 
        $crc ^= ord($str[$c]) << 8; 
        for ($i = 0; $i < 8; $i++) { 
            if ($crc & 0x8000) $crc = ($crc << 1) ^ 0x1021; 
            else $crc = $crc << 1; 
        } 
    } 
    return $crc & 0xFFFF; 
}

function removeAcentos($string) {
    return preg_replace(array("/(á|à|ã|â|ä)/","/(Á|À|Ã|Â|Ä)/","/(é|è|ê|ë)/","/(É|È|Ê|Ë)/","/(í|ì|î|ï)/","/(Í|Ì|Î|Ï)/","/(ó|ò|õ|ô|ö)/","/(Ó|Ò|Õ|Ô|Ö)/","/(ú|ù|û|ü)/","/(Ú|Ù|Û|Ü)/","/(ñ)/","/(Ñ)/"),explode(" ","a A e E i I o O u U n N"),$string);
}
?>
