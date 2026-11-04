<?php
/**
 * Painel inicial do aluno (layout baseado em referencias/dashboard.png).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

/* ---------- Agenda de hoje: blocos do cronograma + revisões do dia ---------- */
$stmt = $pdo->prepare(
    "SELECT b.id, b.hora_inicio, b.hora_fim, b.status, m.nome AS materia, m.cor, m.icone
       FROM cronograma_blocos b JOIN materias m ON m.id = b.materia_id
      WHERE b.usuario_id = ? AND b.data = CURDATE() AND b.status <> 'redistribuido'
      ORDER BY b.hora_inicio"
);
$stmt->execute([$uid]);
$blocos_hoje = $stmt->fetchAll();
$blocos_concluidos = count(array_filter($blocos_hoje, fn($b) => $b['status'] === 'concluido'));

$stmt = $pdo->prepare(
    "SELECT r.id, r.titulo, r.data_agendada, r.origem, m.id AS materia_id, m.nome AS materia, m.cor, m.icone
       FROM revisoes r LEFT JOIN materias m ON m.id = r.materia_id
      WHERE r.usuario_id = ? AND r.status = 'pendente'
      ORDER BY r.data_agendada, r.id LIMIT 5"
);
$stmt->execute([$uid]);
$proximas_revisoes = $stmt->fetchAll();
$revisoes_hoje = revisoes_pendentes_hoje($uid);

/* ---------- Desempenho por matéria ---------- */
$stmt = $pdo->prepare(
    'SELECT m.id, m.nome, m.cor, m.icone,
            ROUND(AVG(h.pontuacao / h.total_questoes) * 100) AS media,
            ROUND(SUM(h.pontuacao) / SUM(h.total_questoes) * 100) AS acerto,
            COUNT(h.id) AS simulados
       FROM materias m
       LEFT JOIN historico_simulados h ON h.materia_id = m.id AND h.usuario_id = ?
      GROUP BY m.id ORDER BY m.nome'
);
$stmt->execute([$uid]);
$desempenho = $stmt->fetchAll();
$com_dados = array_values(array_filter($desempenho, fn($d) => (int) $d['simulados'] > 0));

$melhor = null;
foreach ($com_dados as $d) {
    if (!$melhor || $d['media'] > $melhor['media']) {
        $melhor = $d;
    }
}

/* ---------- Horas na semana e sequência ---------- */
$minutos = minutos_semana($uid);
$prefs = cronograma_preferencias($uid);
$meta_horas = $prefs
    ? array_sum(array_map(fn($col) => (float) $prefs[$col], DIAS_SEMANA_COLUNAS))
    : 10;
$meta_horas = $meta_horas > 0 ? $meta_horas : 10;
$pct_meta = min(100, (int) round($minutos / ($meta_horas * 60) * 100));

/* ---------- Indicadores gerais ---------- */
$total_simulados = (int) array_sum(array_column($desempenho, 'simulados'));
$stmt = $pdo->prepare('SELECT COALESCE(SUM(pontuacao), 0), COALESCE(SUM(total_questoes), 0) FROM historico_simulados WHERE usuario_id = ?');
$stmt->execute([$uid]);
[$total_acertos, $total_questoes] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));
$aproveitamento = $total_questoes ? (int) round($total_acertos / $total_questoes * 100) : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM revisoes WHERE usuario_id = ? AND status = 'pendente' AND data_agendada < CURDATE()");
$stmt->execute([$uid]);
$revisoes_atrasadas = (int) $stmt->fetchColumn();

$dias = dias_estudados($uid);
$sequencia = sequencia_atual($dias);
$segunda = new DateTime('monday this week');

/* ---------- Posição do marcador "agora" na agenda ---------- */
$agora = date('H:i:s');
$indice_agora = count($blocos_hoje);
foreach ($blocos_hoje as $i => $b) {
    if ($b['hora_fim'] > $agora) {
        $indice_agora = $i;
        break;
    }
}

$titulo_pagina  = 'Painel';
$pagina_ativa   = 'painel';
$usa_chartjs    = true;
$scripts_extras = ['graficos.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="grade-painel">
    <!-- ===================== COLUNA PRINCIPAL ===================== -->
    <div class="grade-painel__principal">

        <!-- Boas-vindas com o resumo do dia -->
        <section class="banner">
            <div class="banner__texto">
                <p class="texto-pequeno"><?= e(ucfirst(data_extenso())) ?></p>
                <h1>Olá, <?= e(primeiro_nome($usuario['nome'])) ?>!</h1>
                <p>
                    <?php if (!$blocos_hoje && !$revisoes_hoje): ?>
                        Nada agendado para hoje. Que tal um simulado rápido para manter o ritmo?
                    <?php else:
                        $blocos_restantes = count($blocos_hoje) - $blocos_concluidos;
                        $partes = [];
                        if ($revisoes_hoje) {
                            $partes[] = '<strong>' . $revisoes_hoje . ' ' . ($revisoes_hoje === 1 ? 'revisão pendente' : 'revisões pendentes') . '</strong>';
                        }
                        if ($blocos_restantes) {
                            $partes[] = '<strong>' . $blocos_restantes . ' ' . ($blocos_restantes === 1 ? 'bloco' : 'blocos') . '</strong> de estudo pela frente';
                        }
                    ?>
                        <?php if ($partes): ?>
                            Hoje você tem <?= implode(' e ', $partes) ?>.
                        <?php else: ?>
                            Todos os blocos de hoje estão concluídos. Bom trabalho!
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
                <?php if (!$prefs): ?>
                    <a href="<?= e(url('aluno/cronograma.php')) ?>" class="btn btn--secundario">Montar meu cronograma <?= icone('seta-direita', 'icone--sm') ?></a>
                <?php elseif ($revisoes_hoje): ?>
                    <a href="<?= e(url('aluno/revisoes.php')) ?>" class="btn btn--secundario">Começar pelas revisões <?= icone('seta-direita', 'icone--sm') ?></a>
                <?php else: ?>
                    <a href="<?= e(url('aluno/cronograma.php')) ?>" class="btn btn--secundario">Ver meu dia <?= icone('seta-direita', 'icone--sm') ?></a>
                <?php endif; ?>
            </div>

            <?php if ($blocos_hoje): $pct_dia = (int) round($blocos_concluidos / count($blocos_hoje) * 100); ?>
                <div class="banner__progresso" aria-label="Progresso do dia: <?= $pct_dia ?>%">
                    <div class="anel-dia" style="--p: <?= $pct_dia ?>;">
                        <div class="anel-dia__valor"><strong><?= $blocos_concluidos ?>/<?= count($blocos_hoje) ?></strong><span>blocos</span></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="banner__progresso">
                    <div class="anel-dia" style="--p: <?= min(100, $sequencia * 14) ?>;">
                        <div class="anel-dia__valor"><strong><?= $sequencia ?></strong><span><?= $sequencia === 1 ? 'dia seguido' : 'dias seguidos' ?></span></div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Indicadores da semana -->
        <div class="kpis">
            <article class="kpi">
                <div class="kpi__topo"><span>Aproveitamento geral</span><?= icone('check-circulo') ?></div>
                <div class="kpi__valor"><?= $total_questoes ? $aproveitamento . '%' : '-' ?></div>
                <div class="kpi__detalhe"><?= $total_simulados ?> <?= $total_simulados === 1 ? 'simulado feito' : 'simulados feitos' ?></div>
            </article>
            <article class="kpi">
                <div class="kpi__topo"><span>Horas na semana</span><?= icone('relogio') ?></div>
                <div class="kpi__valor"><?= e(formatar_minutos($minutos)) ?></div>
                <div class="progresso" role="progressbar" aria-valuenow="<?= $pct_meta ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Meta semanal">
                    <div class="progresso__barra" style="width: <?= $pct_meta ?>%;"></div>
                </div>
                <div class="kpi__detalhe"><?= $pct_meta ?>% da meta de <?= e(str_replace('.', ',', (string) round($meta_horas, 1))) ?>h</div>
            </article>
            <article class="kpi">
                <div class="kpi__topo"><span>Sequência</span><?= icone('chama') ?></div>
                <div class="kpi__valor"><?= $sequencia ?> <small><?= $sequencia === 1 ? 'dia' : 'dias' ?></small></div>
                <div class="sequencia sequencia--mini" aria-label="Dias estudados nesta semana">
                    <?php
                    $letras = ['S', 'T', 'Q', 'Q', 'S', 'S', 'D'];
                    for ($i = 0; $i < 7; $i++):
                        $data = (clone $segunda)->modify("+$i day")->format('Y-m-d');
                        $classes = 'sequencia__dia' . (isset($dias[$data]) ? ' sequencia__dia--feito' : '') . ($data === date('Y-m-d') ? ' sequencia__dia--hoje' : '');
                    ?>
                        <span class="<?= $classes ?>" title="<?= e(data_br($data)) ?>"><span class="sequencia__marca"></span><?= $letras[$i] ?></span>
                    <?php endfor; ?>
                </div>
            </article>
            <article class="kpi <?= $revisoes_atrasadas ? 'kpi--alerta' : '' ?>">
                <div class="kpi__topo"><span>Revisões para hoje</span><?= icone('revisoes') ?></div>
                <div class="kpi__valor"><?= $revisoes_hoje ?></div>
                <div class="kpi__detalhe"><?= $revisoes_atrasadas ? $revisoes_atrasadas . ' ' . ($revisoes_atrasadas === 1 ? 'atrasada' : 'atrasadas') : 'Nenhuma atrasada' ?></div>
            </article>
        </div>

        <div class="grade-2">
            <!-- Média por matéria (barras na cor de cada matéria) -->
            <section>
                <div class="secao-titulo">
                    <h2>Média por matéria</h2>
                    <a class="link-ver-todos" href="<?= e(url('aluno/historico.php')) ?>">Histórico</a>
                </div>
                <div class="card">
                    <?php if (!$com_dados): ?>
                        <div class="vazio">
                            <?= icone('historico') ?>
                            <p>Faça seu primeiro simulado para ver sua média por matéria.</p>
                            <a class="btn btn--primario btn--sm" href="<?= e(url('aluno/simulados.php')) ?>">Fazer simulado</a>
                        </div>
                    <?php else: ?>
                        <div class="card__cabecalho">
                            <div>
                                <p class="card__subtitulo">Melhor média</p>
                                <div class="numero-destaque">
                                    <strong style="color: <?= cor_materia($melhor['cor']) ?>;"><?= (int) $melhor['media'] ?>%</strong>
                                    <?= chip_materia($melhor['nome'], $melhor['cor'], $melhor['icone']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="grafico">
                            <canvas data-grafico="barras"
                                    data-rotulos="<?= e(json_encode(array_column($com_dados, 'nome'), JSON_UNESCAPED_UNICODE)) ?>"
                                    data-valores="<?= e(json_encode(array_map('intval', array_column($com_dados, 'media')))) ?>"
                                    data-cores="<?= e(json_encode(array_map('cor_materia', array_column($com_dados, 'cor')))) ?>"
                                    aria-label="Gráfico de barras com a média de acertos por matéria"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Percentual de acerto (anéis na cor de cada matéria) -->
            <section>
                <div class="secao-titulo">
                    <h2>Acerto por matéria</h2>
                    <a class="link-ver-todos" href="<?= e(url('aluno/simulados.php')) ?>">Treinar</a>
                </div>
                <div class="card">
                    <div class="donuts">
                        <?php foreach ($desempenho as $d): $tem = (int) $d['simulados'] > 0; ?>
                            <a class="donut" href="<?= e(url('aluno/simulados.php?materia=' . $d['id'])) ?>"
                               style="color: inherit; --cor: <?= cor_materia($d['cor']) ?>;"
                               title="<?= $tem ? e($d['nome'] . ': ' . $d['simulados'] . ' simulado(s)') : e($d['nome'] . ': sem simulados ainda') ?>">
                                <div class="donut__anel" style="--p: <?= $tem ? (int) $d['acerto'] : 0 ?>;">
                                    <span class="donut__valor"><?= $tem ? (int) $d['acerto'] . '%' : '-' ?></span>
                                </div>
                                <span class="donut__rotulo"><span class="chip-materia__ponto"></span><?= e($d['nome']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- ===================== COLUNA DIREITA ===================== -->
    <aside class="grade-painel__lateral">
        <section class="card">
            <div class="card__cabecalho">
                <div>
                    <h2 class="card__titulo">Agenda de hoje</h2>
                    <p class="card__subtitulo"><?= count($blocos_hoje) ?> <?= count($blocos_hoje) === 1 ? 'bloco' : 'blocos' ?> de estudo</p>
                </div>
                <a href="<?= e(url('aluno/cronograma.php')) ?>" class="chip">Cronograma</a>
            </div>

            <?php if (!$blocos_hoje): ?>
                <div class="vazio" style="padding: 24px 8px;">
                    <?= icone('cronograma') ?>
                    <p><?= $prefs ? 'Nenhum bloco para hoje.' : 'Você ainda não tem cronograma.' ?></p>
                    <a class="btn btn--secundario btn--sm" href="<?= e(url('aluno/cronograma.php')) ?>"><?= $prefs ? 'Abrir cronograma' : 'Montar agora' ?></a>
                </div>
            <?php else: ?>
                <div class="agenda">
                    <?php foreach ($blocos_hoje as $i => $b):
                        $ativo = $b['status'] === 'pendente' && $b['hora_inicio'] <= $agora && $b['hora_fim'] > $agora;
                        $classe = $b['status'] === 'concluido' ? 'agenda__bloco--concluido' : ($ativo ? 'agenda__bloco--ativo' : '');
                    ?>
                        <?php if ($i === $indice_agora && !$ativo): ?>
                            <div class="agenda__agora"><span><?= date('H:i') ?></span></div>
                        <?php endif; ?>
                        <div class="agenda__item">
                            <span class="agenda__hora"><?= e(hora_curta($b['hora_inicio'])) ?></span>
                            <a href="<?= e(url('aluno/pomodoro.php?bloco=' . $b['id'])) ?>" class="agenda__bloco <?= $classe ?>"
                               style="--cor: <?= cor_materia($b['cor']) ?>;" title="Estudar com o Pomodoro">
                                <span class="agenda__marcador"><?= icone($b['status'] === 'concluido' ? 'check' : $b['icone'], 'icone--sm') ?></span>
                                <span>
                                    <span class="agenda__titulo"><?= e($b['materia']) ?></span>
                                    <span class="agenda__meta"><?= icone('relogio', 'icone--xs') ?> <?= e(hora_curta($b['hora_inicio'])) ?> às <?= e(hora_curta($b['hora_fim'])) ?></span>
                                </span>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($indice_agora === count($blocos_hoje)): ?>
                        <div class="agenda__agora"><span><?= date('H:i') ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php $tema_hoje = tema_do_dia(); $redacao_hoje = redacao_do_dia($uid, date('Y-m-d')); ?>
        <?php if ($tema_hoje): ?>
            <section class="card redacao-dia">
                <div class="card__cabecalho" style="margin-bottom: 0;">
                    <h2 class="card__titulo">Redação do dia</h2>
                    <span class="selo"><?= e($tema_hoje['eixo']) ?></span>
                </div>
                <p class="redacao-dia__tema"><?= e($tema_hoje['titulo']) ?></p>
                <?php if ($redacao_hoje && $redacao_hoje['status'] === 'enviada'): ?>
                    <a class="btn btn--contorno btn--bloco" href="<?= e(url('aluno/redacao.php')) ?>">
                        <?= icone('check', 'icone--sm') ?> <?= $redacao_hoje['nota'] !== null ? 'Enviada: ' . (int) $redacao_hoje['nota'] . ' pontos' : 'Enviada, falta avaliar' ?>
                    </a>
                <?php else: ?>
                    <a class="btn btn--primario btn--bloco" href="<?= e(url('aluno/redacao.php')) ?>">
                        <?= icone('redacao', 'icone--sm') ?> <?= $redacao_hoje ? 'Continuar rascunho' : 'Escrever agora' ?>
                    </a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <section>
            <div class="secao-titulo">
                <h2>Próximas revisões</h2>
                <a href="<?= e(url('aluno/revisoes.php')) ?>" class="link-ver-todos">Ver todas</a>
            </div>
            <?php if (!$proximas_revisoes): ?>
                <div class="card vazio" style="padding: 24px;">
                    <p class="texto-pequeno">Sem revisões agendadas. Elas aparecem aqui depois de cada simulado.</p>
                </div>
            <?php else: ?>
                <ul class="lista">
                    <?php foreach ($proximas_revisoes as $r): $atrasada = $r['data_agendada'] < date('Y-m-d'); ?>
                        <li>
                            <a class="lista-item faixa-materia" href="<?= e(url('aluno/revisoes.php')) ?>" style="--cor: <?= cor_materia($r['cor']) ?>; color: inherit;">
                                <span class="lista-item__corpo">
                                    <span class="lista-item__titulo" style="display: block;"><?= e($r['titulo']) ?></span>
                                    <span class="lista-item__meta"><?= chip_materia($r['materia'], $r['cor'], $r['icone']) ?> <?= e(data_relativa($r['data_agendada'])) ?></span>
                                </span>
                                <?php if ($atrasada): ?>
                                    <span class="selo selo--erro">Atrasada</span>
                                <?php elseif ($r['data_agendada'] === date('Y-m-d')): ?>
                                    <span class="selo selo--accent">Hoje</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
