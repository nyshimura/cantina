<?php
// api/purchase.php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

// --- 1. AÇÃO ESPECIAL: VERIFICAR SE A SENHA É NECESSÁRIA ---
// O PDV chama isso antes de finalizar a venda
if (isset($input['action']) && $input['action'] === 'check_pin_requirement') {
    try {
        $tagId = strtoupper(trim($input['tagId'] ?? ''));
        $amount = floatval($input['amount'] ?? 0);

        // Busca configurações de segurança
        $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('security_enable_pin', 'security_pin_min_amount')")->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $enablePin = ($settings['security_enable_pin'] ?? '0') === '1';
        $minAmount = floatval($settings['security_pin_min_amount'] ?? 0);

        // Se desligado ou valor baixo, não pede senha
        if (!$enablePin || $amount <= $minAmount) {
            echo json_encode(['required' => false]);
            exit;
        }

        // Se ligado, verifica se o aluno DONO DA TAG tem senha cadastrada
        $stmt = $pdo->prepare("
            SELECT s.purchase_pin 
            FROM nfc_tags t
            JOIN students s ON t.current_student_id = s.id
            WHERE t.tag_id = ? AND t.status = 'ACTIVE'
        ");
        $stmt->execute([$tagId]);
        $student = $stmt->fetch();

        // Só exige se o aluno tiver senha definida no banco
        if ($student && !empty($student['purchase_pin'])) {
            echo json_encode(['required' => true]);
        } else {
            echo json_encode(['required' => false]);
        }
    } catch (Exception $e) {
        // Em caso de erro na verificação, por segurança, não exige (ou trate como preferir)
        echo json_encode(['required' => false]);
    }
    exit;
}

// --- 2. FLUXO NORMAL DE VENDA ---

$method = $input['paymentMethod'] ?? 'NFC';
$tagIdInput = strtoupper(trim($input['tagId'] ?? ''));
$cart = $input['cart'] ?? [];
$pin = $input['pin'] ?? null; // <--- Recebe a senha digitada

try {
    if (empty($cart)) {
        throw new Exception("O carrinho está vazio.");
    }

    $pdo->beginTransaction();

    // --- CORREÇÃO DE TIMEZONE ---
    $currentLocalTime = date('Y-m-d H:i:s');

    // Cálculo do Total
    $total = 0;
    $itemsSummary = [];
    foreach ($cart as $item) {
        $subtotal = (float)$item['price'] * (int)$item['qty'];
        $total += $subtotal;
        $itemsSummary[] = $item['qty'] . "x " . $item['name'];
    }
    $summaryText = implode(", ", $itemsSummary);

    $studentId = null;
    $studentName = null; // Para retornar ao PDV
    $finalTagId = null;
    $newBalance = 0;

    // Lógica por Método
    if ($method === 'NFC') {
        if (empty($tagIdInput)) throw new Exception("Aproxime o cartão para pagar.");

        // Busca dados completos (Tag + Aluno + Senha + Limite)
        $stmt = $pdo->prepare("
            SELECT t.*, s.id as student_id, s.name as student_name, s.active as student_active, s.purchase_pin, s.daily_limit
            FROM nfc_tags t 
            LEFT JOIN students s ON t.current_student_id = s.id 
            WHERE t.tag_id = ?
        ");
        $stmt->execute([$tagIdInput]);
        $tag = $stmt->fetch();

        if (!$tag) throw new Exception("Cartão não reconhecido.");
        if ($tag['status'] !== 'ACTIVE') throw new Exception("Esta tag não está ativa.");
        if (!$tag['student_active']) throw new Exception("Cadastro do aluno inativo.");

        // --- VALIDAÇÃO DE SEGURANÇA (PIN) ---
        $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('security_enable_pin', 'security_pin_min_amount')")->fetchAll(PDO::FETCH_KEY_PAIR);
        $enablePin = ($settings['security_enable_pin'] ?? '0') === '1';
        $minAmount = floatval($settings['security_pin_min_amount'] ?? 0);

        // Se (Ligado) E (Valor > Mínimo) E (Aluno tem senha)
        if ($enablePin && $total > $minAmount && !empty($tag['purchase_pin'])) {
            if (empty($pin)) {
                throw new Exception("Senha de compra é obrigatória.");
            }
            if (!password_verify($pin, $tag['purchase_pin'])) {
                throw new Exception("Senha incorreta.");
            }
        }
        // -------------------------------------

        // Verifica Limite Diário (Se houver)
        if ($tag['daily_limit'] > 0) {
            $stmtLimit = $pdo->prepare("
                SELECT SUM(amount) FROM transactions 
                WHERE student_id = ? AND type = 'PURCHASE' AND status = 'COMPLETED' 
                AND DATE(timestamp) = CURRENT_DATE()
            ");
            $stmtLimit->execute([$tag['student_id']]);
            $spentToday = abs($stmtLimit->fetchColumn());
            
            if (($spentToday + $total) > $tag['daily_limit']) {
                throw new Exception("Limite diário excedido.");
            }
        }

        // Verifica Saldo
        if ($tag['balance'] < $total) {
            throw new Exception("Saldo insuficiente: R$ " . number_format($tag['balance'], 2, ',', '.'));
        }

        // Debita
        $pdo->prepare("UPDATE nfc_tags SET balance = balance - ? WHERE tag_id = ?")
            ->execute([$total, $tagIdInput]);

        $studentId = $tag['student_id'];
        $studentName = $tag['student_name'];
        $finalTagId = $tagIdInput;
        $newBalance = $tag['balance'] - $total;

    } else {
        $finalTagId = 'DINHEIRO';
    }

    // Registro da Transação
    $stmtTx = $pdo->prepare("
        INSERT INTO transactions 
        (student_id, tag_id, amount, type, status, payment_method, items_summary, timestamp) 
        VALUES (?, ?, ?, 'PURCHASE', 'COMPLETED', ?, ?, ?)
    ");
    
    $stmtTx->execute([
        $studentId, 
        $finalTagId, 
        -$total, 
        $method, 
        $summaryText, 
        $currentLocalTime
    ]);
    
    $transactionId = $pdo->lastInsertId();

    // Registro dos Itens
    $stmtItem = $pdo->prepare("INSERT INTO transaction_items (transaction_id, product_id, product_name, qty, unit_price) VALUES (?, ?, ?, ?, ?)");
    foreach ($cart as $item) {
        $stmtItem->execute([$transactionId, $item['id'], $item['name'], $item['qty'], $item['price']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'student_name' => $studentName,
        'newBalance' => ($method === 'NFC') ? number_format($newBalance, 2, ',', '.') : '0,00'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}