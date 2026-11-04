<?php
/**
 * Listagem de questões com filtros (matéria, tipo, busca) e exclusão.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    exigir_csrf();
    $pdo->prepare('DELETE FROM questoes WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
    toast('Questão excluída.');
    redirecionar('admin/questoes.php?' . http_build_query(array_intersect_key($_GET, array_flip(['materia', 'tipo', 'busca']))));
}

// Filtros
$filtro_materia = (int) ($_GET['materia'] ?? 0);
$filtro_tipo    = in_array($_GET['tipo'] ?? '', ['multipla', 'dissertativa'], true) ? $_GET['tipo'] : '';
$busca          = trim($_GET['busca'] ?? '');

$where  = [];
$params = [];
if ($filtro_materia) {
    $where[] = 'q.materia_id = ?';
    $params[] = $filtro_materia;
}
if ($filtro_tipo) {
    $where[] = 'q.tipo = ?';
    $params[] = $filtro_tipo;
}
if ($busca !== '') {
    $where[] = 'q.enunciado LIKE ?';
    $params[] = '%' . $busca . '%';
}

$sql = 'SELECT q.id, q.tipo, q.enunciado, q.dificuldade, q.resposta_correta, m.nome AS materia, m.cor, m.icone
          FROM questoes q JOIN materias m ON m.id = q.materia_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY m.nome, q.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questoes = $stmt->fetchAll();

$titulo_pagina = 'Questões';
$pagina_ativa  = 'questoes';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Questões</h1>
        <p><?= count($questoes) ?> <?= count($questoes) === 1 ? 'questão encontrada' : 'questões encontradas' ?>.</p>
    </div>
    <a class="btn btn--primario" href="<?= e(url('admin/questao_form.php' . ($filtro_materia ? '?materia=' . $filtro_materia : ''))) ?>">
        <?= icone('mais', 'icone--sm') ?> Nova questão
    </a>
</div>

<form class="filtros" method="get">
    <div class="campo campo--largo">
        <label class="campo__rotulo" for="f-busca">Buscar no enunciado</label>
        <input class="input" type="search" id="f-busca" name="busca" value="<?= e($busca) ?>" placeholder="Ex.: Newton, crase, mitose">
    </div>
    <div class="campo">
        <label class="campo__rotulo" for="f-materia">Matéria</label>
        <select class="select" id="f-materia" name="materia" data-enviar-ao-mudar>
            <option value="">Todas</option>
            <?php foreach (materias_lista() as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= $filtro_materia === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo">
        <label class="campo__rotulo" for="f-tipo">Tipo</label>
        <select class="select" id="f-tipo" name="tipo" data-enviar-ao-mudar>
            <option value="">Todos</option>
            <option value="multipla" <?= $filtro_tipo === 'multipla' ? 'selected' : '' ?>>Múltipla escolha</option>
            <option value="dissertativa" <?= $filtro_tipo === 'dissertativa' ? 'selected' : '' ?>>Dissertativa</option>
        </select>
    </div>
    <button class="btn btn--secundario" type="submit"><?= icone('filtro', 'icone--sm') ?> Filtrar</button>
    <?php if ($filtro_materia || $filtro_tipo || $busca !== ''): ?>
        <a class="btn btn--contorno" href="<?= e(url('admin/questoes.php')) ?>">Limpar</a>
    <?php endif; ?>
</form>

<section class="card">
    <?php if (!$questoes): ?>
        <div class="vazio">
            <?= icone('questoes') ?>
            <p>Nenhuma questão com esses filtros.</p>
        </div>
    <?php else: ?>
    <div class="tabela-container">
        <table class="tabela">
            <thead>
                <tr><th>Enunciado</th><th>Matéria</th><th>Tipo</th><th>Nível</th><th><span class="visualmente-oculto">Ações</span></th></tr>
            </thead>
            <tbody>
            <?php foreach ($questoes as $q): ?>
                <tr>
                    <td style="max-width: 460px;">
                        <?= e(mb_strimwidth($q['enunciado'], 0, 120, '...')) ?>
                    </td>
                    <td><?= chip_materia($q['materia'], $q['cor'], $q['icone']) ?></td>
                    <td>
                        <?php if ($q['tipo'] === 'multipla'): ?>
                            <span class="selo">Objetiva <?= e($q['resposta_correta']) ?></span>
                        <?php else: ?>
                            <span class="selo selo--accent">Dissertativa</span>
                        <?php endif; ?>
                    </td>
                    <td class="texto-secundario"><?= e(rotulo($q['dificuldade'])) ?></td>
                    <td>
                        <div class="tabela__acoes">
                            <a class="botao-icone" href="<?= e(url('admin/questao_form.php?id=' . $q['id'])) ?>" title="Editar"><?= icone('editar', 'icone--sm') ?><span class="visualmente-oculto">Editar</span></a>
                            <form method="post" data-confirmar="Excluir esta questão?" data-confirmar-texto="Ela também sai do histórico e do caderno de erros dos alunos.">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                                <button type="submit" class="botao-icone" title="Excluir"><?= icone('lixeira', 'icone--sm') ?><span class="visualmente-oculto">Excluir</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
