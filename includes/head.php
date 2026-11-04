<?php
/**
 * <head> comum a todas as páginas (internas, login e erro).
 * Espera $titulo_pagina definido.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR" data-tema="escuro">
<head>
    <meta charset="UTF-8">
    <script>
        // Aplica o tema salvo antes de desenhar a página (evita piscar o tema errado)
        try {
            if (localStorage.getItem('tema') === 'claro') document.documentElement.dataset.tema = 'claro';
        } catch (e) { /* armazenamento indisponível: fica o tema escuro padrão */ }
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <meta name="theme-color" content="#01273A">
    <title><?= e($titulo_pagina ?? APP_NOME) ?> | <?= e(APP_NOME) ?></title>

    <link rel="icon" href="<?= e(asset('img/marca/favicon-32.png')) ?>" type="image/png" sizes="32x32">
    <link rel="icon" href="<?= e(asset('img/marca/favicon-192.png')) ?>" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?= e(asset('img/marca/apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/componentes.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/paginas.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/tema-claro.css')) ?>">
</head>
