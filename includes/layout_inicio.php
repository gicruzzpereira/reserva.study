<?php
/**
 * Abertura do layout das páginas internas (aluno e admin).
 *
 * Variáveis esperadas antes do include:
 *   $titulo_pagina  string  título exibido na aba do navegador
 *   $pagina_ativa   string  chave do item ativo na sidebar (ex.: 'painel')
 *   $usuario        array   usuário logado (exigir_login())
 * Opcionais:
 *   $scripts_extras array   arquivos JS de /assets/js a carregar no fim da página
 *   $usa_chartjs    bool    carrega o Chart.js via CDN
 */

$titulo_pagina = $titulo_pagina ?? APP_NOME;
$pagina_ativa  = $pagina_ativa ?? '';

// Ponto de notificação no sino: revisões pendentes até hoje
if (!isset($notificacoes_pendentes)) {
    $notificacoes_pendentes = $usuario['tipo'] === 'aluno' ? revisoes_pendentes_hoje((int) $usuario['id']) : 0;
}

require RAIZ_APP . '/includes/head.php';
?>
<body>
<a class="visualmente-oculto" href="#conteudo">Pular para o conteúdo</a>
<div class="app">
    <?php require RAIZ_APP . '/includes/sidebar.php'; ?>

    <main class="principal">
        <?php require RAIZ_APP . '/includes/topbar.php'; ?>
        <div class="conteudo" id="conteudo">
