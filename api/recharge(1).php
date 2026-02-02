<?php
// api/recharge.php
// CORREÇÃO: Agora enviamos o external_reference corretamente para o MP
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

try {
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $method = $settings['payment_provider'] ?? 'MANUAL_PIX';
    $copyPaste = ''; $qrCodeBase64 = null; 
    
    // GERA A REFERÊNCIA ANTES DE TUDO
    $externalRef = 'REC-' . $targetStudentId . '-' . time();

    if ($method === 'MERCADO_PAGO') {
        
        // --- PREPARAÇÃO DO TOKEN ---
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
        
        // --- DADOS DO PAGADOR (TABELA PARENTS) ---
        $payerEmail = $_SESSION['email'] ?? "email@escola.com";
        $firstName = "Pagador";
        $lastName = "Escola";
        $docType = "CPF";
        $docNumber = ""; 

        if ($isSandbox) {
            // SANDBOX
            $payerEmail = "test_user_" . mt_rand(100000, 999999) . "@testuser.com"; 
            $docNumber = "19119119100";
            $firstName = "Test";
            $lastName = "User";
        } else {
            // PRODUÇÃO (Busca Pai)
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

        // --- MONTAGEM DO PEDIDO (O PULO DO GATO) ---
        $notificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . str_replace('/recharge.php', '/webhook_mp.php', $_SERVER['SCRIPT_NAME']);
        
        if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            $notificationUrl = null; 
        }

        $mpPayload = [
            "transaction_amount" => (float)$amount,
            "description" => "Recarga ID: " . $targetStudentId,
            "payment_method_id" => "pix",
            // AQUI ESTÁ A CORREÇÃO: Enviamos a referência explicitamente
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

        // ENVIO CURL
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
            // Se o MP devolveu um ID, usamos ele para logar, mas mantemos nosso REF no banco
            // O ID do MP não vai pro banco transactions pq não tem coluna, usamos o REF.
        } else {
            throw new Exception("QR Code não gerado.");
        }

    } else {
        // MODO MANUAL
        $pixKey = $settings['pix_key'] ?? '';
        $copyPaste = montaPix($pixKey, 'Escola', 'Cidade', $amount, $externalRef);
    }

    // SALVA NO BANCO (Status PENDING)
    $stmtTx = $pdo->prepare("INSERT INTO transactions (student_id, type, amount, status, items_summary, external_reference, payment_method, timestamp) VALUES (?, 'DEPOSIT', ?, 'PENDING', 'Recarga Pix', ?, 'PIX', NOW())");
    $stmtTx->execute([$targetStudentId, $amount, $externalRef]);

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

function montaPix($chave, $nome, $cidade, $valor, $txId) {
    $nome = substr(preg_replace("/[^a-zA-Z0-9 ]/", "", $nome), 0, 25);
    $cidade = substr(preg_replace("/[^a-zA-Z0-9 ]/", "", $cidade), 0, 15);
    $valor = number_format((float)$valor, 2, '.', '');
    $payload = "00020126330014BR.GOV.BCB.PIX01" . sprintf("%02d", strlen($chave)) . $chave . "52040000530398654" . sprintf("%02d", strlen($valor)) . $valor . "5802BR59" . sprintf("%02d", strlen($nome)) . $nome . "60" . sprintf("%02d", strlen($cidade)) . $cidade . "62" . sprintf("%02d", strlen($txId) + 4) . "05" . sprintf("%02d", strlen($txId)) . $txId . "6304";
    $payload .= strtoupper(str_pad(dechex(crc16($payload)), 4, '0', STR_PAD_LEFT));
    return $payload;
}
function crc16($str) { $crc = 0xFFFF; for ($c = 0; $c < strlen($str); $c++) { $crc ^= ord($str[$c]) << 8; for ($i = 0; $i < 8; $i++) { if ($crc & 0x8000) $crc = ($crc << 1) ^ 0x1021; else $crc = $crc << 1; } } return $crc & 0xFFFF; }
?>