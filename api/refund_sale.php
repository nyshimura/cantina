<?php
// api/refund_sale.php
require_once __DIR__ . '/../includes/auth.php';
requireRole('OPERATOR');

header('Content-Type: application/json');

// --- CONFIGURAÇÃO DE FUSO HORÁRIO DINÂMICA ---
try {
    // Busca o timezone salvo nas configurações
    $stmtConfig = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'system_timezone'");
    $stmtConfig->execute();
    $savedTimezone = $stmtConfig->fetchColumn();

    // Define o fuso horário (usa o do banco ou cai para SP como padrão)
    date_default_timezone_set($savedTimezone ?: 'America/Sao_Paulo');
} catch (Exception $e) {
    // Fallback seguro caso o banco falhe
    date_default_timezone_set('America/Sao_Paulo');
}
// ----------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);
$transactionId = $input['id'] ?? 0;

// Captura dados
$operatorId = $_SESSION['user_id'];
$ip = $_SERVER['REMOTE_ADDR'];
$now = date('Y-m-d H:i:s'); // Agora gerado com o timezone correto do banco

try {
    $pdo->beginTransaction();

    // 1. Busca a transação original
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND type = 'PURCHASE' AND status = 'COMPLETED'");
    $stmt->execute([$transactionId]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        throw new Exception("Esta venda não foi encontrada ou já foi cancelada.");
    }

    $refundAmount = abs($transaction['amount']);
    $formattedAmount = number_format($refundAmount, 2, ',', '.');
    $details = "Estorno da venda #$transactionId: R$ " . $formattedAmount;
    
    $impactText = "";

    // 2. Lógica de Estorno (Apenas se for NFC)
    if ($transaction['payment_method'] === 'NFC') {
        if (empty($transaction['tag_id'])) {
             if (empty($transaction['tag_id'])) {
                throw new Exception("Erro: Venda NFC sem Tag registrada para devolução.");
            }
        }

        $tagId = $transaction['tag_id'];

        // Devolve o saldo
        $stmtTag = $pdo->prepare("UPDATE nfc_tags SET balance = balance + ? WHERE tag_id = ?");
        $stmtTag->execute([$refundAmount, $tagId]);

        if ($stmtTag->rowCount() === 0) {
            throw new Exception("Cartão NFC associado ($tagId) não encontrado ou inativo.");
        }
        
        $details .= " devolvido para a Tag $tagId.";
        $impactText = "Saldo devolvido: R$ " . $formattedAmount;
    } else {
        $details .= " (Venda em Dinheiro - Realizar devolução no caixa).";
        $impactText = "Devolucao em Especie: R$ " . $formattedAmount;
    }

    // 3. Atualiza o status e o timestamp para a DATA CORRETA ($now)
    $stmtUpdate = $pdo->prepare("UPDATE transactions SET status = 'CANCELLED', timestamp = ? WHERE id = ?");
    $stmtUpdate->execute([$now, $transactionId]);

    // 4. Log com JSON Garantido
    $impactSafe = mb_convert_encoding($impactText, 'UTF-8', 'auto');
    $impactJson = json_encode(['message' => $impactSafe], JSON_UNESCAPED_UNICODE);

    if ($impactJson === false || empty($impactJson)) {
        $impactJson = '{"message": "Info nao disponivel"}';
    }

    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (operator_id, action, description, ip_address, impact, timestamp) VALUES (?, 'REFUND_SALE', ?, ?, ?, ?)");
    $stmtLog->execute([$operatorId, $details, $ip, $impactJson, $now]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Venda cancelada com sucesso!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}