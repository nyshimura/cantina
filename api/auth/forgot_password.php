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
    $tables = ['parents', 'students', 'operators'];
    $userFound = false;
    $userName = '';
    $userTable = '';

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT name FROM $table WHERE email = ? AND active = 1 LIMIT 1");
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            $userFound = true;
            $userName = $row['name'];
            $userTable = $table;
            break; 
        }
    }

    // Se não achar ninguém, fingimos que enviou para segurança
    if (!$userFound) {
        echo json_encode(['success' => true, 'message' => 'Se o e-mail existir, o link foi enviado.']); 
        exit;
    }

    // 2. Configura Fuso Horário e Datas (CORREÇÃO TIMEZONE)
    // Força o PHP a usar o horário de São Paulo para garantir sincronia
    date_default_timezone_set('America/Sao_Paulo'); 
    
    $agora = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Gera Token Único
    $token = bin2hex(random_bytes(32));

    // 3. Salva na tabela password_resets
    // Inserimos created_at e expires_at manualmente para garantir coerência
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $token, $agora, $expira]);

    // 4. Monta o Link Dinâmico
    // Detecta protocolo e host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Detecta o diretório raiz do sistema automaticamente
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']); // .../api/auth
    $systemRoot = dirname(dirname($scriptPath));    // .../ (raiz do sistema)

    // Normaliza barras e remove barra final se for raiz
    $systemRoot = str_replace('\\', '/', $systemRoot);
    if ($systemRoot === '/' || $systemRoot === '.') {
        $systemRoot = '';
    }
    
    // O link aponta para a VIEW de reset
    $link = "$protocol://$host$systemRoot/views/reset_password.php?token=$token";

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

    // 6. Envia
    $mailer = new Mailer($pdo);
    if ($mailer->send($email, $subject, $body)) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Erro ao enviar e-mail. Verifique as configurações SMTP.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
