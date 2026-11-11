<?php
/**
 * Navegação lateral: pílula flutuante com efeito de vidro, somente ícones.
 * O nome de cada página aparece em um tooltip (atributo data-rotulo).
 * Em telas pequenas vira uma barra inferior fixa (ver componentes.css).
 */

$eh_admin = ($usuario['tipo'] ?? '') === 'admin';
$eh_professor = ($usuario['tipo'] ?? '') === 'professor';
$eh_suporte = ($usuario['tipo'] ?? '') === 'suporte';

if ($eh_admin || $eh_professor) {
    $itens_menu = $eh_admin ? [
        'admin-painel' => [$eh_admin ? 'Painel administrativo' : 'Painel do professor', $eh_admin ? 'admin/index.php' : 'professor/index.php', 'painel'],
        'materias'     => ['Matérias', 'admin/materias.php', 'materias'],
        'questoes'     => ['Questões', 'admin/questoes.php', 'questoes'],
        'alunos'       => ['Alunos e resultados', 'admin/alunos.php', 'usuarios'],
        'notas'        => ['Notas', 'admin/notas.php', 'historico'],
    ] : [
        'admin-painel' => ['Painel do professor', 'professor/index.php', 'painel'],
        'questoes' => ['Questoes', 'admin/questoes.php', 'questoes'],
        'alunos' => ['Alunos e resultados', 'admin/alunos.php', 'usuarios'],
        'notas' => ['Notas', 'admin/notas.php', 'historico'],
    ];
} elseif ($eh_suporte) {
    $itens_menu = [
        'suporte' => ['Solicitações de acesso', 'suporte/index.php', 'configuracoes'],
    ];
} else {
    $itens_menu = [
        'painel'     => ['Painel', 'aluno/painel.php', 'painel'],
        'simulados'  => ['Simulados', 'aluno/simulados.php', 'simulado'],
        'redacao'    => ['Redação', 'aluno/redacao.php', 'redacao'],
        'historico'  => ['Histórico', 'aluno/historico.php', 'historico'],
        'notas'      => ['Minhas notas', 'aluno/notas.php', 'historico'],
        'cronograma' => ['Cronograma', 'aluno/cronograma.php', 'cronograma'],
        'pomodoro'   => ['Pomodoro', 'aluno/pomodoro.php', 'pomodoro'],
        'revisoes'   => ['Revisões', 'aluno/revisoes.php', 'revisoes'],
        'caderno'    => ['Caderno de erros', 'aluno/caderno.php', 'caderno'],
    ];
}

/** Classe do item conforme a página ativa. */
$classe_item = fn(string $chave, string $extra = '') =>
    trim('nav-item ' . $extra . ($pagina_ativa === $chave ? ' nav-item--ativo' : ''));
?>
<nav class="sidebar" aria-label="Navegação principal">
    <ul class="sidebar__lista">
        <?php if (!$eh_admin && !$eh_professor && !$eh_suporte): ?>
            <!-- Módulo premium de IA (estrela/brilho, como na referência) -->
            <li class="sidebar__ia">
                <a href="<?= e(url('aluno/ia.php')) ?>"
                   class="<?= $classe_item('ia', 'nav-item--destaque') ?>"
                   data-rotulo="Flashcards e quizzes com IA"
                   <?= $pagina_ativa === 'ia' ? 'aria-current="page"' : '' ?>>
                    <?= icone('brilho') ?>
                    <span class="nav-item__selo">Pro</span>
                    <span class="visualmente-oculto">Flashcards e quizzes com IA (Premium)</span>
                </a>
            </li>
            <li class="sidebar__separador" aria-hidden="true"></li>
        <?php endif; ?>

        <?php foreach ($itens_menu as $chave => [$rotulo, $destino_menu, $nome_icone]): ?>
            <li>
                <a href="<?= e(url($destino_menu)) ?>"
                   class="<?= $classe_item($chave) ?>"
                   data-rotulo="<?= e($rotulo) ?>"
                   <?= $pagina_ativa === $chave ? 'aria-current="page"' : '' ?>>
                    <?= icone($nome_icone) ?>
                    <span class="visualmente-oculto"><?= e($rotulo) ?></span>
                </a>
            </li>
        <?php endforeach; ?>

        <li class="sidebar__separador sidebar__secundario" aria-hidden="true"></li>

        <?php if (!$eh_admin && !$eh_professor && !$eh_suporte): ?>
            <li class="sidebar__secundario">
                <a href="<?= e(url('aluno/planos.php')) ?>"
                   class="<?= $classe_item('planos') ?>"
                   data-rotulo="Planos">
                    <?= icone('coroa') ?>
                    <span class="visualmente-oculto">Planos</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="sidebar__secundario">
            <a href="<?= e(url('conta/perfil.php')) ?>"
               class="<?= $classe_item('perfil') ?>"
               data-rotulo="Meu perfil"
               <?= $pagina_ativa === 'perfil' ? 'aria-current="page"' : '' ?>>
                <?= icone('usuario') ?>
                <span class="visualmente-oculto">Meu perfil</span>
            </a>
        </li>

        <li>
            <form action="<?= e(url('auth/logout.php')) ?>" method="post">
                <?= csrf_campo() ?>
                <button type="submit" class="nav-item" data-rotulo="Sair">
                    <?= icone('sair') ?>
                    <span class="visualmente-oculto">Sair</span>
                </button>
            </form>
        </li>
    </ul>
</nav>
