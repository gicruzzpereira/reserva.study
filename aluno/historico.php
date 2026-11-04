<?php
/**
 * Histórico de simulados: indicadores, desempenho por matéria, evolução e tabela filtrável.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$filtro = (int) ($_GET['materia'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT h.id, h.pontuacao, h.total_questoes, h.origem, h.data_realizacao, COALESCE(m.nome, IF(h.origem = "geral", "Simulado geral", "Refazendo erros")) AS materia, m.cor, m.icone
       FROM historico_simulados h LEFT JOIN materias m ON m.id = h.materia_id
      WHERE h.usuario_id = ? AND (? = 0 OR h.materia_id = ?)
      ORDER BY h.data_realizacao DESC'
);
$stmt->execute([$uid, $filtro, $filtro]);
$simulados = $stmt->fetchAll();

// Resumo por matéria (sempre de todas, para comparar)
$stmt = $pdo->prepare(
    'SELECT m.id, m.nome, m.cor, m.icone, COUNT(h.id) AS simulados,
            ROUND(SUM(h.pontuacao) / NULLIF(SUM(h.total_questoes), 0) * 100) AS acerto,
            MAX(h.data_realizacao) AS ultimo
       FROM materias m LEFT JOIN historico_simulados h ON h.materia_id = m.id AND h.usuario_id = ?
      GROUP BY m.id ORDER BY m.nome'
);
$stmt->execute([$uid]);
$por_materia = $stmt->fetchAll();
// Melhores primeiro; matérias sem simulado vão para o fim
usort($por_materia, fn($a, $b) => [$a['acerto'] === null, -(int) $a['acerto']] <=> [$b['acerto'] === null, -(int) $b['acerto']]);
$com_dados = array_values(array_filter($por_materia, fn($m) => (int) $m['simulados'] > 0));

$total_questoes = array_sum(array_column($simulados, 'total_questoes'));
$total_acertos  = array_sum(array_column($simulados, 'pontuacao'));
$aproveitamento = $total_questoes ? (int) round($total_acertos / $total_questoes * 100) : 0;
$melhor = $com_dados[0] ?? null;
$reforcar = $com_dados ? end($com_dados) : null;

// Evolução: do mais antigo para o mais recente (últimos 15), ponto na cor da matéria
$evolucao = array_reverse(array_slice($simulados, 0, 15));
$rotulos  = array_map(fn($s) => date('d/m', strtotime($s['data_realizacao'])), $evolucao);
$valores  = array_map(fn($s) => (int) round($s['pontuacao'] / max(1, $s['total_questoes']) * 100), $evolucao);
$cores    = array_map(fn($s) => cor_materia($s['cor']), $evolucao);
$legendas = array_map(fn($s) => $s['materia'] ?? 'Refazendo erros', $evolucao);

$materia_filtro = $filtro ? materia_por_id($filtro) : null;

$titulo_pagina  = 'Histórico';
$pagina_ativa   = 'historico';
$usa_chartjs    = true;
$scripts_extras = ['graficos.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Histórico</h1>
        <p><?= $materia_filtro ? 'Simulados de ' . e($materia_filtro['nome']) . '.' : 'Todos os simulados que você fez, do mais recente para o mais antigo.' ?></p>
    </div>
    <form method="get" class="campo" style="min-width: 220px;">
        <label class="campo__rotulo" for="f-materia">Filtrar por matéria</label>
        <select class="select" id="f-materia" name="materia" data-enviar-ao-mudar>
            <option value="0">Todas as matérias</option>
            <?php foreach (materias_lista() as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= $filtro === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn--secundario btn--sm">Filtrar</button></noscript>
    </form>
</div>

<?php if (!$simulados): ?>
    <div class="card vazio">
        <?= icone('historico') ?>
        <p><?= $filtro ? 'Nenhum simulado dessa matéria ainda.' : 'Nenhum simulado por aqui ainda.' ?></p>
        <a class="btn btn--primario" href="<?= e(url('aluno/simulados.php' . ($filtro ? '?materia=' . $filtro : ''))) ?>">Fazer um simulado</a>
    </div>
<?php else: ?>

<div class="kpis">
    <article class="kpi">
        <div class="kpi__topo"><span>Simulados</span><?= icone('simulado') ?></div>
        <div class="kpi__valor"><?= count($simulados) ?></div>
        <div class="kpi__detalhe"><?= $total_questoes ?> questões respondidas</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Aproveitamento</span><?= icone('check-circulo') ?></div>
        <div class="kpi__valor"><?= $aproveitamento ?>%</div>
        <div class="kpi__detalhe"><?= $total_acertos ?> de <?= $total_questoes ?> certas</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Melhor matéria</span><?= icone('coroa') ?></div>
        <div class="kpi__valor"><?= $melhor ? (int) $melhor['acerto'] . '%' : '-' ?></div>
        <div class="kpi__detalhe"><?= $melhor ? chip_materia($melhor['nome'], $melhor['cor'], $melhor['icone']) : 'Sem dados' ?></div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Precisa de reforço</span><?= icone('alerta') ?></div>
        <div class="kpi__valor"><?= $reforcar && $reforcar !== $melhor ? (int) $reforcar['acerto'] . '%' : '-' ?></div>
        <div class="kpi__detalhe"><?= $reforcar && $reforcar !== $melhor ? chip_materia($reforcar['nome'], $reforcar['cor'], $reforcar['icone']) : 'Faça simulados de outras matérias' ?></div>
    </article>
</div>

<div class="grade-lateral">
    <section class="card">
        <div class="card__cabecalho">
            <div>
                <h2 class="card__titulo">Evolução</h2>
                <p class="card__subtitulo">% de acertos nos últimos <?= count($evolucao) ?> simulados. Cada ponto tem a cor da matéria.</p>
            </div>
        </div>
        <div class="grafico">
            <canvas data-grafico="linha"
                    data-rotulos="<?= e(json_encode($rotulos)) ?>"
                    data-valores="<?= e(json_encode($valores)) ?>"
                    data-cores="<?= e(json_encode($cores)) ?>"
                    data-legendas="<?= e(json_encode($legendas, JSON_UNESCAPED_UNICODE)) ?>"
                    aria-label="Gráfico de linha com a evolução do percentual de acertos"></canvas>
        </div>
    </section>

    <section class="card">
        <div class="card__cabecalho">
            <h2 class="card__titulo">Acerto por matéria</h2>
        </div>
        <div class="pilha pilha--sm">
            <?php foreach ($por_materia as $m): $tem = (int) $m['simulados'] > 0; ?>
                <a class="barra-materia <?= $tem ? '' : 'barra-materia--vazia' ?>" style="--cor: <?= cor_materia($m['cor']) ?>; color: inherit;"
                   href="<?= e(url('aluno/historico.php?materia=' . $m['id'])) ?>" title="<?= $tem ? (int) $m['simulados'] . ' simulado(s)' : 'Sem simulados' ?>">
                    <span><?= chip_materia($m['nome'], $m['cor'], $m['icone']) ?></span>
                    <span class="barra-materia__trilha"><span class="barra-materia__valor" style="display: block; width: <?= $tem ? (int) $m['acerto'] : 0 ?>%;"></span></span>
                    <span class="barra-materia__numero"><?= $tem ? (int) $m['acerto'] . '%' : '-' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="card mt-5">
    <div class="card__cabecalho">
        <h2 class="card__titulo">Simulados</h2>
        <span class="texto-xs texto-secundario"><?= count($simulados) ?> no total</span>
    </div>
    <div class="tabela-container">
        <table class="tabela">
            <thead><tr><th>Data</th><th>Matéria</th><th class="tabela__numero">Acertos</th><th class="tabela__numero">Aproveitamento</th><th><span class="visualmente-oculto">Ações</span></th></tr></thead>
            <tbody>
            <?php foreach ($simulados as $s): $pct = (int) round($s['pontuacao'] / max(1, $s['total_questoes']) * 100); ?>
                <tr>
                    <td class="texto-secundario"><?= e(data_br($s['data_realizacao'], true)) ?></td>
                    <td><?= chip_materia($s['materia'] ?? 'Refazendo erros', $s['cor'], $s['icone']) ?></td>
                    <td class="tabela__numero numero"><?= (int) $s['pontuacao'] ?> / <?= (int) $s['total_questoes'] ?></td>
                    <td class="tabela__numero">
                        <span class="selo <?= $pct >= 70 ? 'selo--sucesso' : ($pct >= 50 ? 'selo--aviso' : 'selo--erro') ?>"><?= $pct ?>%</span>
                    </td>
                    <td style="text-align: right;">
                        <a class="btn btn--contorno btn--sm" href="<?= e(url('aluno/resultado.php?id=' . $s['id'])) ?>">Ver revisão</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
