<?php
// api/purchase.php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$method = $input['paymentMethod'] ?? 'NFC';
$tagIdInput = strtoupper(trim($input['tagId'] ?? ''));
$cart = $input['cart'] ?? [];

try {
    if (empty($cart)) {
        throw new Exception("O carrinho está vazio.");
    }

    $pdo->beginTransaction();

    // --- CORREÇÃO DE TIMEZONE ---
    // Geramos o horário exato aqui no PHP, que já respeita o fuso definido no auth.php
    $currentLocalTime = date('Y-m-d H:i:s');

    // 1. Cálculo do Total da Venda
    $total = 0;
    $itemsSummary = [];
    foreach ($cart as $item) {
        $subtotal = (float)$item['price'] * (int)$item['qty'];
        $total += $subtotal;
        $itemsSummary[] = $item['qty'] . "x " . $item['name'];
    }
    $summaryText = implode(", ", $itemsSummary);

    $studentId = null;
    $finalTagId = null;
    $newBalance = 0;

    // 2. Lógica Baseada no Método de Pagamento
    if ($method === 'NFC') {
        if (empty($tagIdInput)) throw new Exception("Aproxime o cartão para pagar.");

        $stmt = $pdo->prepare("
            SELECT t.*, s.id as student_id, s.active as student_active 
            FROM nfc_tags t 
            LEFT JOIN students s ON t.current_student_id = s.id 
            WHERE t.tag_id = ? AND t.status = 'ACTIVE'
        ");
        $stmt->execute([$tagIdInput]);
        $tag = $stmt->fetch();

        if (!$tag) throw new Exception("Cartão não reconhecido ou inativo.");
        if (!$tag['student_active']) throw new Exception("Cadastro do aluno inativo.");

        if ($tag['balance'] < $total) {
            throw new Exception("Saldo insuficiente: R$ " . number_format($tag['balance'], 2, ',', '.'));
        }

        $pdo->prepare("UPDATE nfc_tags SET balance = balance - ? WHERE tag_id = ?")
            ->execute([$total, $tagIdInput]);

        $studentId = $tag['student_id'];
        $finalTagId = $tagIdInput;
        $newBalance = $tag['balance'] - $total;

    } else {
        $finalTagId = 'DINHEIRO';
    }

    // 3. Registro da Transação (Enviando o timestamp local explicitamente)
    $stmtTx = $pdo->prepare("
        INSERT INTO transactions 
        (student_id, tag_id, amount, type, status, payment_method, items_summary, timestamp) 
        VALUES (?, ?, ?, 'PURCHASE', 'COMPLETED', ?, ?, ?)
    ");
    
    // O último parâmetro é o $currentLocalTime gerado no início deste script
    $stmtTx->execute([
        $studentId, 
        $finalTagId, 
        -$total, 
        $method, 
        $summaryText, 
        $currentLocalTime
    ]);
    
    $transactionId = $pdo->lastInsertId();

    // 4. Registro dos Itens
    $stmtItem = $pdo->prepare("INSERT INTO transaction_items (transaction_id, product_id, product_name, qty, unit_price) VALUES (?, ?, ?, ?, ?)");
    foreach ($cart as $item) {
        $stmtItem->execute([$transactionId, $item['id'], $item['name'], $item['qty'], $item['price']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'newBalance' => ($method === 'NFC') ? number_format($newBalance, 2, ',', '.') : '0,00'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}