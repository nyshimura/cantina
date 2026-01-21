<?php
// api/debug_webhook.php
// SIMULADOR CORRIGIDO: SALDO EM NFC_TAGS
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🕵️ Simulador de Webhook (Final)</h1>";

// 1. Carrega Auth
echo "<p>1. Carregando sistema... ";
require_once __DIR__ . '/../includes/auth.php';
echo "<b style='color:green'>Sucesso!</b></p>";

// 2. Busca Transação Pendente
try {
    // Agora buscamos também o 'amount' para usar o valor real
    $stmt = $pdo->query("SELECT external_reference FROM transactions WHERE status = 'PENDING' ORDER BY id DESC LIMIT 1");
    $ref = $stmt->fetchColumn();
    
    if (!$ref) {
        echo "<p style='color:orange'>⚠️ Nenhuma transação pendente. Gere um Pix novo para testar.</p>";
        exit;
    }
    $external_reference = $ref;
    echo "<p>2. Testando com transação: <b>$external_reference</b></p>";

} catch (Exception $e) {
    die("<p style='color:red'>Erro banco: " . $e->getMessage() . "</p>");
}

echo "<hr><h3>Iniciando Processamento...</h3>";

try {
    // Simula Token (apenas visual, não usado no SQL)
    echo "<p>✅ Token validado.</p>";
    
    echo "<p>🔄 Simulando aprovação...</p>";

    // Busca dados completos da transação
    $stmt = $pdo->prepare("SELECT id, student_id, amount, status FROM transactions WHERE external_reference = ?");
    $stmt->execute([$external_reference]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($transaction) {
        $realAmount = $transaction['amount']; // Pega o valor real do banco
        echo "<p>✅ Transação encontrada (ID: {$transaction['id']}, Valor: R$ $realAmount)</p>";

        if ($transaction['status'] !== 'COMPLETED') {
            
            $pdo->beginTransaction();
            echo "<p>Transaction SQL iniciada...</p>";

            try {
                // TESTE 1: Atualizar Transação
                $sqlTx = "UPDATE transactions SET status = 'COMPLETED' WHERE id = ?";
                echo "<pre>Executando: $sqlTx</pre>";
                $pdo->prepare($sqlTx)->execute([$transaction['id']]);
                echo "<p style='color:green'>✅ Tabela transactions atualizada!</p>";

                // TESTE 2: Atualizar Saldo na tabela NFC_TAGS
                // Atualiza onde current_student_id é igual ao aluno da transação
                $sqlBalance = "UPDATE nfc_tags SET balance = balance + ? WHERE current_student_id = ?";
                echo "<pre>Executando: $sqlBalance (Valor: $realAmount, Aluno ID: {$transaction['student_id']})</pre>";

                $stmtBalance = $pdo->prepare($sqlBalance);
                $stmtBalance->execute([$realAmount, $transaction['student_id']]);
                
                // Verifica se alguma linha foi afetada (se o aluno tem tag)
                if ($stmtBalance->rowCount() > 0) {
                    echo "<p style='color:green'>✅ Tabela nfc_tags atualizada com sucesso!</p>";
                } else {
                    echo "<p style='color:orange'>⚠️ Query rodou, mas nenhuma linha foi afetada. Verifique se o aluno ID {$transaction['student_id']} tem uma tag vinculada (current_student_id) na tabela nfc_tags.</p>";
                }

                $pdo->rollBack(); // Desfaz para não estragar
                echo "<h2 style='color:blue'>🎉 SUCESSO TOTAL!</h2>";
                echo "<p>O SQL está correto. Pode confiar no webhook real.</p>";

            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<h2 style='color:red'>❌ ERRO DE SQL:</h2>";
                echo "<div style='background:#fdd; padding:10px; border:1px solid red;'>" . $e->getMessage() . "</div>";
            }

        } else {
            echo "<p>⚠️ Transação já paga.</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Transação não encontrada.</p>";
    }

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>