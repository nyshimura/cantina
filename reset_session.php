<?php
// reset_session.php
// Coloque este arquivo na pasta raiz do seu projeto
require_once __DIR__ . '/config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    try {
        // Busca as permissões mais recentes do banco de dados
        $stmt = $pdo->prepare("SELECT permissions FROM operators WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $permissions = $stmt->fetchColumn();
        
        // Atualiza a variável de sessão que o sidebar.php lê
        $_SESSION['permissions'] = $permissions;
        
        echo "<body style='font-family:sans-serif; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; background:#f0fdf4;'>";
        echo "<h1 style='color:#059669;'>Sucesso!</h1>";
        echo "<p style='color:#334155;'>Sua sessão foi atualizada com as novas permissões do banco.</p>";
        echo "<a href='views/admin/dashboard.php' style='background:#10b981; color:white; padding:12px 24px; border-radius:12px; text-decoration:none; font-weight:bold;'>Voltar ao Dashboard</a>";
        echo "</body>";
    } catch (Exception $e) {
        echo "Erro ao atualizar sessão: " . $e->getMessage();
    }
} else {
    echo "Erro: Você não está logado como operador.";
}
?>