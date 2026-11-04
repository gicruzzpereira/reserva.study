<?php
/**
 * Revisões espaçadas: atrasadas, de hoje e próximas, mais as concluídas recentemente.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'concluir') {
        $pdo->prepare("UPDATE revisoes SET status = 'concluida', concluida_em = NOW() WHERE id = ? AND usuario_id = ? AND status = 'pendente'")
            ->execute([$id, $uid]);
        toast('Revisão concluída.');
    }
    if (($_POST['acao'] ?? '') === 'adiar') {
        $pdo->prepare("UPDATE revisoes SET data_agendada = DATE_ADD(GREATEST(data_agendada, CURDATE()), INTERVAL 1 DAY) WHERE id = ? AND usuario_id = ? AND status = 'pendente'")
            ->execute([$id, $uid]);
        toast('Revisão adiada para amanhã.', 'info');
    }
    redirecionar('aluno/revisoes.php');
}

$stmt = $pdo->prepare(
    "SELECT r.*, m.nome AS materia, m.cor, m.icone FROM revisoes r LEFT JOIN materias m ON m.id = r.materia_id
      WHERE r.usuario_id = ? AND r.status = 'pendente' ORDER BY r.data_agendada, r.id"
);
$stmt->execute([$uid]);
$grupos = ['Atrasadas' => [], 'Hoje' => [], 'Próximos dias' => []];
foreach ($stmt->fetchAll() as $r) {
    $chave = $r['data_agendada'] < date('Y-m-d') ? 'Atrasadas' : ($r['data_agendada'] === date('Y-m-d') ? 'Hoje' : 'Próximos dias');
    $grupos[$chave][] = $r;
}

$stmt = $pdo->prepare(
    "SELECT r.titulo, r.concluida_em, m.nome AS materia, m.cor, m.icone FROM revisoes r LEFT JOIN materias m ON m.id = r.materia_id
      WHERE r.usuario_id = ? AND r.status = 'concluida' ORDER BY r.concluida_em DESC LIMIT 8"
);
$stmt->execute([$uid]);
$concluidas = $stmt->fetchAll();

$origens = ['simulado' => 'Simulado', 'conteudo' => 'Conteúdo estudado', 'flashcard' => 'Flashcard', 'reforco' => 'Reforço'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM revisoes WHERE usuario_id = ? AND status = 'concluida' AND YEARWEEK(concluida_em, 1) = YEARWEEK(CURDATE(), 1)");
$stmt->execute([$uid]);
$concluidas_semana = (int) $stmt->fetchColumn();

$titulo_pagina = 'Revisões';
$pagina_ativa  = 'revisoes';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Revisões</h1>
        <p class="largura-texto">Cada simulado e cada bloco concluído geram revisões em 1, 7 e 30 dias. Quando o desempenho fica abaixo de 60%, entra um reforço extra.</p>
    </div>
</div>

<div class="kpis">
    <article class="kpi <?= $grupos['Atrasadas'] ? 'kpi--alerta' : '' ?>">
        <div class="kpi__topo"><span>Atrasadas</span><?= icone('alerta') ?></div>
        <div class="kpi__valor"><?= count($grupos['Atrasadas']) ?></div>
        <div class="kpi__detalhe"><?= $grupos['Atrasadas'] ? 'Comece por elas' : 'Nenhuma pendência antiga' ?></div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Para hoje</span><?= icone('revisoes') ?></div>
        <div class="kpi__valor"><?= count($grupos['Hoje']) ?></div>
        <div class="kpi__detalhe">Agendadas para <?= e(data_br(date('Y-m-d'))) ?></div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Próximos dias</span><?= icone('cronograma') ?></div>
        <div class="kpi__valor"><?= count($grupos['Próximos dias']) ?></div>
        <div class="kpi__detalhe">Já agendadas</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Feitas na semana</span><?= icone('check-circulo') ?></div>
        <div class="kpi__valor"><?= $concluidas_semana ?></div>
        <div class="kpi__detalhe">Revisões concluídas</div>
    </article>
</div>

<div class="grade-lateral">
    <div class="pilha">
        <?php if (!array_filter($grupos)): ?>
            <div class="card vazio">
                <?= icone('check-circulo') ?>
                <p>Tudo em dia. Novas revisões aparecem depois dos próximos simulados.</p>
                <a class="btn btn--primario" href="<?= e(url('aluno/simulados.php')) ?>">Fazer um simulado</a>
            </div>
        <?php endif; ?>

        <?php foreach ($grupos as $titulo => $lista): if (!$lista) continue; ?>
            <section>
                <div class="secao-titulo">
                    <h2 class="<?= $titulo === 'Atrasadas' ? 'texto-erro' : '' ?>"><?= e($titulo) ?> <span class="texto-secundario numero" style="font-size: var(--fs-sm);">(<?= count($lista) ?>)</span></h2>
                </div>
                <ul class="lista">
                    <?php foreach ($lista as $r): ?>
                        <li class="lista-item faixa-materia" style="--cor: <?= cor_materia($r['cor']) ?>;">
                            <div class="lista-item__corpo">
                                <p class="lista-item__titulo"><?= e($r['titulo']) ?></p>
                                <p class="lista-item__meta" style="flex-wrap: wrap;">
                                    <?= chip_materia($r['materia'], $r['cor'], $r['icone']) ?>
                                    <span><?= e($origens[$r['origem']] ?? '') ?>, revisão de <?= (int) $r['intervalo_dias'] ?> <?= (int) $r['intervalo_dias'] === 1 ? 'dia' : 'dias' ?><?php if ($titulo !== 'Hoje'): ?>, <?= e(mb_strtolower(data_relativa($r['data_agendada']))) ?><?php endif; ?></span>
                                </p>
                            </div>
                            <div class="linha" style="gap: 6px; flex-wrap: nowrap;">
                                <?php if ($r['origem'] === 'flashcard'): ?>
                                    <a class="btn btn--secundario btn--sm" href="<?= e(url('aluno/ia.php')) ?>">Flashcards</a>
                                <?php elseif ($r['materia_id']): ?>
                                    <a class="btn btn--secundario btn--sm" href="<?= e(url('aluno/simulado.php?materia=' . $r['materia_id'] . '&qtd=5')) ?>" title="Simulado rápido de 5 questões">Treinar</a>
                                <?php endif; ?>
                                <form method="post">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="botao-icone" name="acao" value="adiar" title="Adiar para amanhã"><?= icone('relogio', 'icone--sm') ?><span class="visualmente-oculto">Adiar</span></button>
                                    <button class="botao-icone" name="acao" value="concluir" title="Marcar como revisada" style="color: var(--success);"><?= icone('check-circulo', 'icone--sm') ?><span class="visualmente-oculto">Concluir</span></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <aside class="card">
        <div class="card__cabecalho">
            <h2 class="card__titulo">Concluídas recentemente</h2>
        </div>
        <?php if (!$concluidas): ?>
            <p class="texto-pequeno texto-secundario">Nenhuma ainda.</p>
        <?php else: ?>
            <ul class="lista" style="gap: 14px;">
                <?php foreach ($concluidas as $c): ?>
                    <li class="texto-pequeno faixa-materia" style="--cor: <?= cor_materia($c['cor']) ?>; padding-left: 12px;">
                        <?= e($c['titulo']) ?>
                        <div class="texto-xs texto-secundario" style="margin-top: 2px;"><?= e(data_br($c['concluida_em'], true)) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
