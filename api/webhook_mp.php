<?php
// api/webhook_mp.php
// CORRIGIDO: Consulta API, pega REF e atualiza NFC_TAGS
require_once __DIR__ . '/../includes/auth.php';
http_response_code(200);

function mpLog($msg) {
    $logFile = __DIR__ . '/webhook_log.txt';
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

try {
    // 1. Captura o ID da notificação (vem no JSON ou na URL)
    $paymentId = $_GET['id'] ?? $_GET['data_id'] ?? null;
    if (!$paymentId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $paymentId = $input['data']['id'] ?? $input['id'] ?? null;
    }

    if (!$paymentId) {
        // As vezes o MP manda um teste sem ID, ignoramos
        exit;
    }

    mpLog("Notificação Recebida. Payment ID: $paymentId");

    // 2. Prepara Token
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $accessToken = $settings['mp_access_token'] ?? '';
    
    if (strpos($accessToken, 'TEST-') !== 0 && strpos($accessToken, 'APP_USR-') !== 0 && function_exists('decryptData')) {
         $accessToken = decryptData($accessToken);
    }
    $accessToken = trim($accessToken);

    if (empty($accessToken)) {
        mpLog("Erro: Token não configurado.");
        exit;
    }

    // 3. CONSULTA OFICIAL NA API DO MERCADO PAGO
    // É aqui que transformamos o ID "1422..." no External Reference "REC-..."
    $ch = curl_init("https://api.mercadopago.com/v1/payments/$paymentId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        mpLog("Erro ao consultar API do MP. HTTP: $httpCode");
        exit;
    }

    $paymentData = json_decode($response, true);
    $status = $paymentData['status'] ?? 'unknown';
    // O external_reference vem daqui agora, pois garantimos o envio no recharge.php
    $externalRef = $paymentData['external_reference'] ?? ''; 
    $transactionAmount = $paymentData['transaction_amount'] ?? 0;

    mpLog("Status MP: $status | Ref: $externalRef | Valor: $transactionAmount");

    // 4. ATUALIZAÇÃO DO BANCO
    if ($status === 'approved') {
        
        // Busca a transação no nosso banco pelo External Reference
        $stmt = $pdo->prepare("SELECT id, student_id, status FROM transactions WHERE external_reference = ?");
        $stmt->execute([$externalRef]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            if ($transaction['status'] !== 'COMPLETED') {
                
                $pdo->beginTransaction();
                try {
                    // A. Marca transação como Paga
                    $sqlTx = "UPDATE transactions SET status = 'COMPLETED' WHERE id = ?";
                    $pdo->prepare($sqlTx)->execute([$transaction['id']]);

                    // B. Adiciona saldo na tabela NFC_TAGS
                    $sqlTag = "UPDATE nfc_tags SET balance = balance + ? WHERE current_student_id = ?";
                    $stmtTag = $pdo->prepare($sqlTag);
                    $stmtTag->execute([$transactionAmount, $transaction['student_id']]);

                    if ($stmtTag->rowCount() > 0) {
                        mpLog("SUCESSO: Saldo atualizado na nfc_tags para aluno " . $transaction['student_id']);
                    } else {
                        mpLog("AVISO: Transação paga, mas aluno " . $transaction['student_id'] . " não tem tag vinculada na tabela nfc_tags.");
                    }

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    mpLog("ERRO SQL: " . $e->getMessage());
                }
            } else {
                mpLog("Ignorado: Transação já estava paga.");
            }
        } else {
            mpLog("ERRO CRÍTICO: Transação não encontrada no banco local (Ref: $externalRef). Verifique se o recharge.php salvou corretamente.");
        }
    }

} catch (Exception $e) {
    mpLog("ERRO FATAL: " . $e->getMessage());
}
?>