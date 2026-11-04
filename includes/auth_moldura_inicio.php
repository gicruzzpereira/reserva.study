<?php
/**
 * Moldura das telas de entrada (login e cadastro):
 * fundo azul com brilhos amarelos em corte diagonal e um card grande com a
 * foto emoldurada à esquerda (logo, atalhos e carrossel de recursos) e o
 * formulário à direita.
 *
 * Espera: $aba_ativa = 'entrar' | 'cadastro'
 */
$aba_ativa = $aba_ativa ?? 'entrar';

// Imagens do fundo do painel (assets/img): passam sozinhas, em loop
$imagens = ['img/foto2-gaby.jpeg', 'img/foto3-vitoria.jpeg', 'img/foto1-homem.jpeg', 'img/matriculas-abertas.jpeg'];

// Recursos apresentados no carrossel da foto
$slides = [
    ['simulado',   'Simulados no formato ENEM', 'Questões de 9 matérias com correção na hora'],
    ['cronograma', 'Cronograma automático',     'Blocos de estudo montados pela sua rotina'],
    ['redacao',    'Redação do dia',            'Um tema novo todos os dias, com autoavaliação'],
    ['brilho',     'Flashcards com IA',         'Revise mais rápido a partir dos seus resumos'],
];
?>
<body class="pagina-entrada">
<div class="entrada">
    <div class="entrada__card">
        <!-- ===================== Painel da foto ===================== -->
        <aside class="entrada__foto">
            <div class="entrada__galeria" aria-hidden="true">
                <?php foreach ($imagens as $i => $imagem): ?>
                    <img class="<?= $i === 0 ? 'ativo' : '' ?>" data-imagem src="<?= e(asset($imagem)) ?>" alt=""
                         <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                <?php endforeach; ?>
            </div>

            <div class="entrada__foto-topo">
                <a href="<?= e(url('auth/login.php')) ?>" aria-label="Reserva Study, página inicial">
                    <?= logo_html('horizontal', 'escuro', 52) ?>
                </a>
                <nav class="entrada__atalhos" aria-label="Acesso">
                    <a href="<?= e(url('auth/login.php')) ?>" class="<?= $aba_ativa === 'entrar' ? 'ativo' : '' ?>">Entrar</a>
                    <a href="<?= e(url('auth/cadastro.php')) ?>" class="entrada__pilula <?= $aba_ativa === 'cadastro' ? 'ativo' : '' ?>">Criar conta</a>
                </nav>
            </div>

            <div class="entrada__carrossel" data-carrossel aria-roledescription="carrossel" aria-label="Recursos do Reserva Study">
                <div class="entrada__slides">
                    <?php foreach ($slides as $i => [$icone_slide, $titulo_slide, $texto_slide]): ?>
                        <div class="entrada__slide <?= $i === 0 ? 'ativo' : '' ?>" data-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                            <span class="entrada__slide-icone"><?= icone($icone_slide) ?></span>
                            <span>
                                <b><?= e($titulo_slide) ?></b>
                                <span><?= e($texto_slide) ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="entrada__setas">
                    <button type="button" data-anterior aria-label="Recurso anterior"><?= icone('seta-direita', 'icone--sm') ?></button>
                    <button type="button" data-proximo aria-label="Próximo recurso"><?= icone('seta-direita', 'icone--sm') ?></button>
                </div>
            </div>
        </aside>

        <!-- ===================== Formulário ===================== -->
        <main class="entrada__form-area">
            <div class="entrada__form-topo">
                <div class="linha" style="gap: 8px; margin-left: auto;">
                    <span class="entrada__idioma"><?= icone('idioma', 'icone--xs') ?> PT-BR</span>
                    <?php $classe_botao_tema = ''; require RAIZ_APP . '/includes/botao_tema.php'; ?>
                </div>
            </div>

            <div class="entrada__form">
