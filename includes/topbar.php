<?php
/**
 * Barra superior: marca, busca, idioma, notificações e avatar.
 *
 * Opcional: $notificacoes_pendentes (int) — acende o ponto no sino.
 */

$eh_admin = ($usuario['tipo'] ?? '') === 'admin';
$eh_equipe = in_array($usuario['tipo'] ?? '', ['admin', 'professor'], true);
$notificacoes_pendentes = $notificacoes_pendentes ?? 0;
$destino_inicio = pagina_inicial($usuario);
?>
<header class="topbar">
    <a class="marca" href="<?= e(url($destino_inicio)) ?>">
        <?= logo_html('horizontal', 'auto', 54) ?>
    </a>

    <?php if ($eh_equipe): ?>
    <form class="busca" action="<?= e(url('admin/questoes.php')) ?>" method="get" role="search">
        <?= icone('busca', 'icone--sm') ?>
        <label for="busca-global" class="visualmente-oculto">Buscar</label>
        <input type="search" id="busca-global" name="busca"
               placeholder="Buscar questões…"
               value="<?= e($_GET['busca'] ?? '') ?>">
    </form>
    <?php elseif ($usuario['tipo'] === 'aluno'): ?>
    <form class="busca" action="<?= e(url('aluno/simulados.php')) ?>" method="get" role="search">
        <?= icone('busca', 'icone--sm') ?>
        <label for="busca-global" class="visualmente-oculto">Buscar</label>
        <input type="search" id="busca-global" name="busca" placeholder="Buscar matérias…"
               value="<?= e($_GET['busca'] ?? '') ?>">
    </form>
    <?php endif; ?>

    <div class="topbar__acoes">
        <span class="idioma" title="Idioma da interface">
            <?= icone('idioma', 'icone--sm') ?> PT-BR
        </span>

        <span class="topbar__divisor" aria-hidden="true"></span>

        <?php require RAIZ_APP . '/includes/botao_tema.php'; ?>

        <?php if ($usuario['tipo'] === 'aluno'): ?>
            <a class="botao-icone" href="<?= e(url('aluno/revisoes.php')) ?>"
               title="<?= $notificacoes_pendentes ? e($notificacoes_pendentes . ' revisão(ões) pendente(s)') : 'Sem notificações' ?>">
                <?= icone('sino') ?>
                <?php if ($notificacoes_pendentes > 0): ?>
                    <span class="ponto-notificacao" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="visualmente-oculto">Notificações</span>
            </a>
        <?php endif; ?>

        <span class="topbar__divisor" aria-hidden="true"></span>

        <a class="perfil" href="<?= e(url('conta/perfil.php')) ?>" title="Meu perfil" style="color: inherit;">
            <?= avatar_html($usuario) ?>
            <span class="perfil__texto">
                <span class="perfil__nome"><?= e($usuario['nome'] ?? '') ?></span><br>
                <span class="perfil__papel">
                <?= $eh_admin || $eh_equipe || $usuario['tipo'] === 'suporte'
                    ? e(rotulo($usuario['tipo']))
                    : 'Plano ' . e(rotulo($usuario['plano'] ?? 'gratuito')) ?>
                </span>
            </span>
        </a>
    </div>
</header>
