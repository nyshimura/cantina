<?php
// api/auth/reset_password.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php'; // Para usar a função logAction

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $newPassword = $input['password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        throw new Exception("Dados incompletos.");
    }

    if (strlen($newPassword) < 6) {
        throw new Exception("A senha deve ter pelo menos 6 caracteres.");
    }

    // 1. Valida o Token
    $stmt = $pdo->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch();

    if (!$resetRequest) {
        throw new Exception("Token inválido ou inexistente.");
    }

    if (strtotime($resetRequest['expires_at']) < time()) {
        throw new Exception("Este link expirou. Solicite uma nova recuperação.");
    }

    $email = $resetRequest['email'];

    // 2. Descobre de quem é o email e atualiza a senha
    // A ordem de verificação define a prioridade se houver e-mails duplicados em tabelas diferentes
    $tables = ['operators', 'parents', 'students'];
    $updated = false;
    $userType = '';

    foreach ($tables as $table) {
        // Verifica se o email existe nesta tabela
        $check = $pdo->prepare("SELECT id FROM $table WHERE email = ? LIMIT 1");
        $check->execute([$email]);
        
        if ($check->fetch()) {
            // Atualiza a senha
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE $table SET password_hash = ? WHERE email = ?");
            $update->execute([$newHash, $email]);
            
            $updated = true;
            $userType = $table;
            break; // Para no primeiro usuário encontrado
        }
    }

    if ($updated) {
        // 3. Remove o token usado (para não ser usado novamente)
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

        // 4. Log de Auditoria (Opcional, pois não temos sessão ativa, mas é bom registrar)
        // Como não tem sessão, passamos NULL no operator_id, mas descrevemos a ação
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $pdo->prepare("INSERT INTO audit_logs (action, description, ip_address) VALUES (?, ?, ?)")
                ->execute(['PASSWORD_RESET', "Senha redefinida via token para o e-mail: $email ($userType)", $ip]);
        } catch (Exception $e) {}

        echo json_encode(['success' => true, 'message' => 'Senha atualizada com sucesso!']);
    } else {
        throw new Exception("Usuário não encontrado para este e-mail.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}