<?php
/**
 * Inicialização comum a todas as páginas e endpoints.
 * Carrega configuração, conexão, funções auxiliares e inicia a sessão.
 */

define('RAIZ_APP', dirname(__DIR__));

if (!file_exists(RAIZ_APP . '/config/config.php')) {
    http_response_code(500);
    exit('Arquivo config/config.php não encontrado. Copie config/config.example.php para config/config.php.');
}

require_once RAIZ_APP . '/config/config.php';
require_once RAIZ_APP . '/config/conexao.php';
require_once RAIZ_APP . '/includes/funcoes.php';
require_once RAIZ_APP . '/includes/icones.php';
require_once RAIZ_APP . '/includes/auth.php';
require_once RAIZ_APP . '/includes/estudos.php';
require_once RAIZ_APP . '/includes/ia.php';
require_once RAIZ_APP . '/includes/redacao.php';

date_default_timezone_set('America/Sao_Paulo');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Sessão com cookie protegido contra acesso via JavaScript e envio entre sites
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('RESERVASTUDY');
    session_start();
}
