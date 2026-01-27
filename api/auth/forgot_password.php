<?php
// api/auth/forgot_password.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/Mailer.php';

header('Content-Type: application/json');

try {
    // Recebe o JSON do frontend
    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        throw new Exception("E-mail inválido.");
    }

    // 1. Verifica se o usuário existe nas tabelas especificadas
    // A ordem importa: primeiro Pais, depois Alunos, depois Operadores
    $tables = ['parents', 'students', 'operators'];
    $userFound = false;
    $userName = '';
    $userTable = '';

    foreach ($tables as $table) {
        // Verifica se o e-mail existe e está ativo (active = 1)
        $stmt = $pdo->prepare("SELECT name FROM $table WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            $userFound = true;
            $userName = $row['name'];
            $userTable = $table;
            break; // Parar assim que encontrar (evita conflitos se houver e-mail igual)
        }
    }

    // Se não achar ninguém, fingimos que enviou para segurança (evita descoberta de e-mails)
    if (!$userFound) {
        echo json_encode(['success' => true, 'message' => 'Se o e-mail existir, o link foi enviado.']); 
        exit;
    }

    // 2. Gera Token Único
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expira em 1 hora

    // 3. Salva na tabela password_resets
    // (Certifique-se de ter rodado o SQL CREATE TABLE que mandei anteriormente)
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$email, $token, $expires]);

    // 4. Monta o Link
    // Detecta se é HTTP ou HTTPS
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // O usuário será redirecionado para esta página para criar a nova senha
    // Certifique-se que o caminho 'views/reset_password.php' existirá
    $link = "$protocol://$host/views/reset_password.php?token=$token";

    // 5. Prepara o E-mail
    $subject = "Recuperação de Senha - Cantina";
    $body = "
        <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 10px; overflow: hidden;'>
            <div style='background-color: #10b981; padding: 20px; text-align: center;'>
                <h1 style='color: white; margin: 0;'>Recuperação de Senha</h1>
            </div>
            <div style='padding: 30px;'>
                <p style='font-size: 16px;'>Olá, <strong>$userName</strong>!</p>
                <p>Recebemos uma solicitação para redefinir a senha da sua conta ($userTable).</p>
                <p>Se foi você, clique no botão abaixo para criar uma nova senha:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='background-color: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;'>Redefinir Minha Senha</a>
                </div>
                <p style='font-size: 12px; color: #666;'>Ou copie e cole este link no seu navegador:<br>$link</p>
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999;'>Este link é válido por 1 hora. Se você não solicitou isso, apenas ignore este e-mail.</p>
            </div>
        </div>
    ";

    // 6. Envia usando a classe Mailer
    $mailer = new Mailer($pdo);
    if ($mailer->send($email, $subject, $body)) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Erro ao enviar e-mail. Verifique as configurações SMTP.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}