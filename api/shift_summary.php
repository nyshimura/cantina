<?php
// api/shift_summary.php
require_once __DIR__ . '/../includes/auth.php';
requireRole('OPERATOR');

header('Content-Type: application/json; charset=utf-8');

try {
    // Vendas concluídas hoje agrupadas por método de pagamento
    $stmt = $pdo->query("
        SELECT payment_method, SUM(amount) as total 
        FROM transactions 
        WHERE type = 'PURCHASE' AND status = 'COMPLETED' AND DATE(timestamp) = CURRENT_DATE()
        GROUP BY payment_method
    ");
    $sales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $nfcTotal = floatval($sales['NFC'] ?? 0);
    $cashTotal = floatval($sales['CASH'] ?? 0);
    $walletTotal = floatval($sales['WALLET'] ?? 0);
    
    // As vezes a pre-order usa WALLET no DB, NFC é no caixa.

    echo json_encode([
        'success' => true,
        'data' => [
            'nfc' => $nfcTotal + $walletTotal, // Junta NFC e WALLET (saldo da conta)
            'cash' => $cashTotal,
            'total' => $nfcTotal + $cashTotal + $walletTotal
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
