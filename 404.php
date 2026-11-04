<?php
/**
 * Página de erro 404 (página não encontrada).
 * Apache: configurada no .htaccess. Servidor embutido do PHP: router.php.
 */
require_once __DIR__ . '/includes/bootstrap.php';

http_response_code(404);
$usuario = usuario_logado();
$inicio  = $usuario ? pagina_inicial($usuario) : 'auth/login.php';

$titulo_pagina = 'Página não encontrada';
require RAIZ_APP . '/includes/head.php';
?>
<body>
<main class="erro-pagina">
    <a class="erro-pagina__marca" href="<?= e(url($inicio)) ?>"><?= logo_html('horizontal', 'auto', 64) ?></a>
    <div class="erro-pagina__conteudo">
        <div class="erro-pagina__codigo" aria-hidden="true">404</div>
        <div>
            <h1>Essa página não existe.</h1>
            <p>O endereço pode ter sido digitado errado ou a página mudou de lugar. Seu progresso continua salvo.</p>
            <div class="acoes">
                <a class="btn btn--primario" href="<?= e(url($inicio)) ?>">
                    <?= $usuario ? 'Voltar ao painel' : 'Ir para o login' ?>
                </a>
                <a class="btn btn--contorno" href="javascript:history.back()">Página anterior</a>
            </div>
        </div>
    </div>
</main>
<?php require RAIZ_APP . '/includes/scripts_publicos.php'; ?>
</body>
</html>
