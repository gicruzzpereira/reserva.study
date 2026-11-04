<?php
/**
 * Ponto de entrada: leva ao login ou ao painel do perfil logado.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$usuario = usuario_logado();
redirecionar($usuario ? pagina_inicial($usuario) : 'auth/login.php');
