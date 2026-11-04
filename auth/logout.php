<?php
/**
 * Logout: destrói a sessão (somente via POST com token CSRF).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    redirecionar('index.php');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Nova sessão só para exibir o aviso de saída
session_start();
toast('Você saiu da sua conta.', 'info');
redirecionar('auth/login.php');
