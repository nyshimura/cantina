<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('OPERATOR');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$transactionId = $input['id'] ?? null;
$action = $input['action'] ?? null; // 'APPROVE', 'REJECT', 'REFUND'

// 1. Captura dados para auditoria (IP e Operador)
$operatorId = $_SESSION['user_id'];
$ip = $_SERVER['REMOTE_ADDR'];

try {
    if (!$transactionId || !$action) throw new Exception("Dados insuficientes.");

    $pdo->beginTransaction();

    // Busca transação
    $stmt = $pdo->prepare("
        SELECT t.*, n.tag_id as current_active_tag 
        FROM transactions t 
        LEFT JOIN nfc_tags n ON (n.current_student_id = t.student_id AND n.status = 'ACTIVE')
        WHERE t.id = ? AND t.type IN ('DEPOSIT', 'RECHARGE')
    ");
    $stmt->execute([$transactionId]);
    $tx = $stmt->fetch();

    if (!$tx) throw new Exception("Transação não encontrada.");

    $formattedAmount = number_format($tx['amount'], 2, ',', '.');
    $now = date('Y-m-d H:i:s');
    
    // Variáveis para montar o Log
    $logAction = '';
    $logDesc = '';
    $impactText = '';

    // === APROVAÇÃO ===
    if ($action === 'APPROVE') {
        if ($tx['status'] !== 'PENDING') throw new Exception("Esta transação não está pendente.");
        if (!$tx['current_active_tag']) throw new Exception("O aluno não tem uma tag ativa para receber saldo.");

        // Credita
        $stmtBalance = $pdo->prepare("UPDATE nfc_tags SET balance = balance + ? WHERE tag_id = ?");
        $stmtBalance->execute([$tx['amount'], $tx['current_active_tag']]);

        // Atualiza Transação (Define Tag, Status e Data Atual)
        $stmtUpdate = $pdo->prepare("UPDATE transactions SET status = 'COMPLETED', tag_id = ?, timestamp = ? WHERE id = ?");
        $stmtUpdate->execute([$tx['current_active_tag'], $now, $transactionId]);

        $logAction = 'FINANCIAL_APPROVE';
        $logDesc = "Crédito de R$ $formattedAmount aprovado (ID: $transactionId)";
        $impactText = "Saldo adicionado: R$ " . $formattedAmount;

    // === REJEIÇÃO ===
    } elseif ($action === 'REJECT') {
        if ($tx['status'] !== 'PENDING') throw new Exception("Esta transação não está pendente.");

        $stmtUpdate = $pdo->prepare("UPDATE transactions SET status = 'CANCELLED', timestamp = ? WHERE id = ?");
        $stmtUpdate->execute([$now, $transactionId]);

        $logAction = 'FINANCIAL_REJECT';
        $logDesc = "Crédito de R$ $formattedAmount rejeitado (ID: $transactionId)";
        $impactText = "Solicitacao recusada";

    // === ESTORNO (DEVOLUÇÃO) ===
    } elseif ($action === 'REFUND') {
        if ($tx['status'] !== 'COMPLETED') throw new Exception("Apenas transações concluídas podem ser estornadas.");
        
        // Define qual tag sofrerá o débito (Prioridade: Tag original da transação)
        $targetTag = $tx['tag_id'] ? $tx['tag_id'] : $tx['current_active_tag'];
        
        if (!$targetTag) throw new Exception("Não foi encontrada uma tag válida para realizar o estorno.");

        // Debita
        $stmtBalance = $pdo->prepare("UPDATE nfc_tags SET balance = balance - ? WHERE tag_id = ?");
        $stmtBalance->execute([$tx['amount'], $targetTag]);

        // Atualiza status para REFUNDED
        $stmtUpdate = $pdo->prepare("UPDATE transactions SET status = 'REFUNDED', timestamp = ? WHERE id = ?");
        $stmtUpdate->execute([$now, $transactionId]);

        $logAction = 'FINANCIAL_REFUND';
        $logDesc = "Estorno de R$ $formattedAmount realizado (ID: $transactionId)";
        $impactText = "Saldo removido: R$ " . $formattedAmount;
    }

    // === REGISTRA LOG DE AUDITORIA (Com IP e JSON seguro) ===
    // Garante conversão UTF-8 para evitar falha no json_encode e erro SQL 4025
    $impactSafe = mb_convert_encoding($impactText, 'UTF-8', 'auto');
    $impactJson = json_encode(['message' => $impactSafe], JSON_UNESCAPED_UNICODE);

    // Fallback caso o JSON falhe
    if ($impactJson === false || empty($impactJson)) {
        $impactJson = '{"message": "Info nao disponivel"}';
    }

    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (operator_id, action, description, ip_address, impact, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmtLog->execute([$operatorId, $logAction, $logDesc, $ip, $impactJson]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}