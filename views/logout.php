<?php
// logout.php

// 1. Inicia a sessão para poder manipulá-la
session_start();

// 2. Limpa todas as variáveis de sessão
$_SESSION = array();

// 3. Se desejar matar a sessão completamente, apague também o cookie da sessão.
// Nota: Isso garante que o ID da sessão seja invalidado no navegador.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destrói a sessão no servidor
session_destroy();

// 5. REDIRECIONAMENTO (O que estava faltando)
// Ajuste o caminho abaixo para o seu arquivo de login real
header("Location: login.php");
exit;
?>