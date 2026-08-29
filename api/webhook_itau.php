<?php
// api/webhook_itau.php
require_once __DIR__ . '/../config/db.php';

// Configurações de Log (Essencial para debug de Webhook)
ini_set('display_errors', 0); // Não exibir erros na tela (resposta deve ser limpa)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/webhook_itau_errors.log');

// Função para logar eventos
function logWebhook($msg) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/../logs/webhook_itau.log', "[$date] $msg" . PHP_EOL, FILE_APPEND);
}

// 1. Recebe o Payload do Itaú
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

// Loga o recebimento (para você ver se o Itaú chamou)
logWebhook("Recebido: " . $rawInput);

// Validação básica se é um JSON válido
if (!$jsonData || !isset($jsonData['pix'])) {
    logWebhook("Erro: Payload inválido ou vazio.");
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Payload invalido']);
    exit;
}

try {
    // 2. Processa a lista de Pix recebidos
    // O padrão V2 envia um array "pix" com N pagamentos
    foreach ($jsonData['pix'] as $pix) {
        $txid = $pix['txid'] ?? null;
        $e2eid = $pix['endToEndId'] ?? null; // ID único do Bacen
        $valor = $pix['valor'] ?? 0.00;
        $horario = $pix['horario'] ?? date('Y-m-d H:i:s');

        if (!$txid) {
            logWebhook("Alerta: Pix sem TXID recebido (E2E: $e2eid). Ignorando.");
            continue;
        }

        logWebhook("Processando Pix -> TXID: $txid | Valor: $valor");

        // 3. Atualiza no Banco de Dados
        // Verifica se a transação existe e ainda está PENDING
        $stmt = $pdo->prepare("SELECT id, status, student_id FROM transactions WHERE external_reference = ?");
        $stmt->execute([$txid]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            if ($transaction['status'] !== 'COMPLETED') {
                // Atualiza para COMPLETED e salva o E2E ID para rastreio
                $update = $pdo->prepare("UPDATE transactions SET status = 'COMPLETED', updated_at = NOW(), external_reference = ? WHERE id = ?");
                // Nota: Em alguns casos, guardamos o E2E num campo separado, mas aqui atualizaremos a ref ou manteremos o txid.
                // Sugestão: Mantenha o TXID na external_reference e salve o E2E num log ou campo 'metadata' se tiver.
                // Abaixo, vou apenas confirmar o pagamento.
                
                $confirm = $pdo->prepare("UPDATE transactions SET status = 'COMPLETED', updated_at = NOW() WHERE id = ?");
                $confirm->execute([$transaction['id']]);

                // Atualiza saldo na tabela NFC_TAGS (Apenas Tag Ativa)
                $sqlTag = "UPDATE nfc_tags SET balance = balance + ? WHERE current_student_id = ? AND status = 'ACTIVE'";
                $stmtTag = $pdo->prepare($sqlTag);
                $stmtTag->execute([$valor, $transaction['student_id']]);

                if ($stmtTag->rowCount() > 0) {
                    logWebhook("Sucesso: Saldo atualizado na nfc_tags para aluno " . $transaction['student_id']);
                } else {
                    // Fallback: Guarda no pending_balance se não tem tag ativa
                    $sqlPending = "UPDATE students SET pending_balance = pending_balance + ? WHERE id = ?";
                    $pdo->prepare($sqlPending)->execute([$valor, $transaction['student_id']]);
                    logWebhook("Aviso/Fallback: Saldo guardado em pending_balance. Aluno " . $transaction['student_id'] . " não tem tag ATIVA.");
                }

                logWebhook("Sucesso: Transação #{$transaction['id']} atualizada para COMPLETED.");
            } else {
                logWebhook("Aviso: Transação #{$transaction['id']} já estava paga.");
            }
        } else {
            logWebhook("Erro: Nenhuma transação encontrada para o TXID: $txid");
        }
    }

    // 4. Resposta para o Itaú (Obrigatório retornar 200 OK)
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    logWebhook("Exceção Crítica: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
?>