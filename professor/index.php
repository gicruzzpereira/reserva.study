<?php
/**
 * Painel inicial dos professores.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('professor');

$pdo = db();
$materia = materia_do_professor($usuario);
if (!$materia) {
    http_response_code(403);
    exit('Este perfil de professor ainda não está vinculado a uma matéria.');
}
$stmt = $pdo->prepare('SELECT COUNT(*) FROM questoes WHERE materia_id = ?');
$stmt->execute([(int) $materia['id']]);
$totalQuestoes = (int) $stmt->fetchColumn();
$totalAlunos = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno'")->fetchColumn();

$titulo_pagina = 'Painel do professor';
$pagina_ativa = 'admin-painel';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Olá, <?= e(primeiro_nome($usuario['nome'])) ?>.</h1>
        <p>Gerencie questões e conteúdos de <?= e($materia['nome']) ?>.</p>
    </div>
</div>

<div class="kpis">
    <?php if ($usuario['tipo'] === 'admin'): ?>
    <a class="kpi" href="<?= e(url('admin/alunos.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Alunos</span><?= icone('usuarios') ?></div>
        <div class="kpi__valor"><?= (int) $totais['alunos'] ?></div>
        <div class="kpi__detalhe">Consultar alunos e resultados</div>
    </a>
    <a class="kpi" href="<?= e(url('admin/materias.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Matérias</span><?= icone('materias') ?></div>
        <div class="kpi__valor"><?= (int) $totais['materias'] ?></div>
        <div class="kpi__detalhe">Cadastrar e editar matérias</div>
    </a>
    <?php endif; ?>
    <a class="kpi" href="<?= e(url('admin/questoes.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Questões</span><?= icone('questoes') ?></div>
        <div class="kpi__valor"><?= $totalQuestoes ?></div>
        <div class="kpi__detalhe">Questões de <?= e($materia['nome']) ?></div>
    </a>
    <a class="kpi" href="<?= e(url('admin/alunos.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Alunos cadastrados</span><?= icone('usuarios') ?></div>
        <div class="kpi__valor"><?= $totalAlunos ?></div>
        <div class="kpi__detalhe">Consultar alunos e resultados</div>
    </a>
    <?php if ($usuario['tipo'] === 'admin'): ?>
    <a class="kpi" href="<?= e(url('admin/notas.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Notas lançadas</span><?= icone('historico') ?></div>
        <div class="kpi__valor"><?= (int) $totais['notas'] ?></div>
        <div class="kpi__detalhe">Escala de 0 a 1000</div>
    </a>
    <?php endif; ?>
</div>

<section class="card">
    <div class="card__cabecalho">
        <h2 class="card__titulo">Área do professor</h2>
    </div>
    <p class="texto-secundario">Gerencie apenas os dados de <?= e($materia['nome']) ?>.</p>
    <div class="form-acoes">
        <a class="btn btn--primario" href="<?= e(url('admin/alunos.php')) ?>"><?= icone('usuarios', 'icone--sm') ?> Consultar alunos</a>
        <a class="btn btn--contorno" href="<?= e(url('admin/notas.php')) ?>"><?= icone('mais', 'icone--sm') ?> Lançar nota</a>
    </div>
</section>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
