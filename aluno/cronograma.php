<?php
/**
 * Cronograma automático.
 *
 * Tela principal (uma só, sem abas):
 *   1. Resumo da semana em números
 *   2. Quadro da semana (clique em um dia para ver o detalhe)
 *   3. Detalhe do dia escolhido, com as ações de cada bloco
 *   4. Lateral: matérias da semana e revisões do dia
 * Ajuste da rotina: ?vista=ajustar (3 passos com prévia ao vivo).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$prefs = cronograma_preferencias($uid);
$pesos = cronograma_materias($uid);
$hoje  = date('Y-m-d');

/* ===================== Ações (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';
    $voltar = 'aluno/cronograma.php' . (isset($_GET['data']) ? '?data=' . urlencode($_GET['data']) : '');

    if ($acao === 'salvar') {
        // Horas por dia (0 a 12, passo de meia hora)
        $horas = [];
        foreach (DIAS_SEMANA_COLUNAS as $coluna) {
            $valor = (float) str_replace(',', '.', $_POST[$coluna] ?? '0');
            $horas[$coluna] = max(0, min(12, round($valor * 2) / 2));
        }
        $hora_inicio = preg_match('/^\d{2}:\d{2}$/', $_POST['hora_inicio'] ?? '') ? $_POST['hora_inicio'] . ':00' : '14:00:00';
        $data_prova = $_POST['data_prova'] ?? '';
        $data_prova = ($data_prova && strtotime($data_prova) && $data_prova > $hoje) ? $data_prova : null;

        $escolhidas = [];
        foreach ((array) ($_POST['materias'] ?? []) as $materia_id) {
            $materia_id = (int) $materia_id;
            if (materia_por_id($materia_id)) {
                $escolhidas[$materia_id] = max(1, min(5, (int) ($_POST['peso'][$materia_id] ?? 3)));
            }
        }

        if (!$escolhidas) {
            toast('Escolha pelo menos uma matéria.', 'erro');
            redirecionar('aluno/cronograma.php?vista=ajustar');
        }
        if (array_sum($horas) <= 0) {
            toast('Informe quantas horas você tem em pelo menos um dia.', 'erro');
            redirecionar('aluno/cronograma.php?vista=ajustar');
        }

        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO cronograma_preferencias (usuario_id, horas_seg, horas_ter, horas_qua, horas_qui, horas_sex, horas_sab, horas_dom, hora_inicio, data_prova)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE horas_seg = VALUES(horas_seg), horas_ter = VALUES(horas_ter), horas_qua = VALUES(horas_qua),
                horas_qui = VALUES(horas_qui), horas_sex = VALUES(horas_sex), horas_sab = VALUES(horas_sab),
                horas_dom = VALUES(horas_dom), hora_inicio = VALUES(hora_inicio), data_prova = VALUES(data_prova)'
        )->execute([$uid, ...array_values($horas), $hora_inicio, $data_prova]);

        $pdo->prepare('DELETE FROM cronograma_materias WHERE usuario_id = ?')->execute([$uid]);
        $inserir = $pdo->prepare('INSERT INTO cronograma_materias (usuario_id, materia_id, peso) VALUES (?, ?, ?)');
        foreach ($escolhidas as $materia_id => $peso) {
            $inserir->execute([$uid, $materia_id, $peso]);
        }
        $pdo->commit();

        $criados = gerar_cronograma($uid);
        toast($criados ? "Pronto! $criados blocos de estudo distribuídos nos próximos " . DIAS_GERADOS . ' dias.' : 'Rotina salva, mas não sobrou horário livre nos próximos dias.', $criados ? 'sucesso' : 'aviso');
        redirecionar('aluno/cronograma.php');
    }

    if ($acao === 'gerar') {
        $criados = gerar_cronograma($uid);
        toast($criados ? "Cronograma refeito: $criados blocos nos próximos dias." : 'Não há horários livres para gerar blocos.', $criados ? 'sucesso' : 'aviso');
        redirecionar($voltar);
    }

    $bloco_id = (int) ($_POST['bloco_id'] ?? 0);

    if ($acao === 'concluir') {
        if ($materia = concluir_bloco($uid, $bloco_id)) {
            toast('Bloco concluído. Revisões de ' . $materia . ' agendadas.');
        }
        redirecionar($voltar);
    }

    if ($acao === 'redistribuir') {
        $nova = redistribuir_bloco($uid, $bloco_id);
        toast($nova ? 'Bloco remarcado para ' . data_extenso($nova) . '.' : 'Não encontrei espaço livre nos próximos dias.', $nova ? 'sucesso' : 'aviso');
        redirecionar($voltar);
    }

    redirecionar($voltar);
}

/* ===================== Dados ===================== */
$vista = (($_GET['vista'] ?? '') === 'ajustar' || !$prefs) ? 'ajustar' : 'quadro';

$nomes_dias  = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
$nomes_longos = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];

/** Situação de um bloco: concluido, agora, perdido (passou sem concluir) ou pendente. */
function situacao_bloco(array $b): string
{
    if ($b['status'] === 'concluido') {
        return 'concluido';
    }
    $inicio = $b['data'] . ' ' . $b['hora_inicio'];
    $fim = $b['data'] . ' ' . $b['hora_fim'];
    $agora = date('Y-m-d H:i:s');
    if ($fim < $agora) {
        return 'perdido';
    }
    return $inicio <= $agora ? 'agora' : 'pendente';
}

const ROTULOS_SITUACAO = [
    'concluido' => ['Feito', 'selo--sucesso', 'check'],
    'agora'     => ['Agora', 'selo--premium', 'play'],
    'perdido'   => ['Não feito', 'selo--erro', 'alerta'],
    'pendente'  => ['A fazer', '', 'relogio'],
];

if ($vista === 'quadro') {
    $data = $_GET['data'] ?? null;
    $data = ($data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && strtotime($data)) ? $data : null;
    if ($data === null) {
        // Sem dia escolhido: abre hoje, ou o próximo dia com estudo se hoje não tiver mais nada
        $stmt = $pdo->prepare(
            "SELECT MIN(data) FROM cronograma_blocos WHERE usuario_id = ? AND status = 'pendente' AND data >= CURDATE()
               AND CONCAT(data, ' ', hora_fim) > NOW()"
        );
        $stmt->execute([$uid]);
        $tem_hoje = $pdo->prepare("SELECT 1 FROM cronograma_blocos WHERE usuario_id = ? AND data = CURDATE() AND status <> 'redistribuido'");
        $tem_hoje->execute([$uid]);
        $data = $tem_hoje->fetchColumn() ? $hoje : ($stmt->fetchColumn() ?: $hoje);
    }

    $segunda = (new DateTime($data))->modify('monday this week');
    $domingo = (clone $segunda)->modify('+6 days');

    $stmt = $pdo->prepare(
        "SELECT b.*, m.nome AS materia, m.cor, m.icone FROM cronograma_blocos b JOIN materias m ON m.id = b.materia_id
          WHERE b.usuario_id = ? AND b.data BETWEEN ? AND ? AND b.status <> 'redistribuido' ORDER BY b.data, b.hora_inicio"
    );
    $stmt->execute([$uid, $segunda->format('Y-m-d'), $domingo->format('Y-m-d')]);
    $blocos_semana = $stmt->fetchAll();

    $por_dia = [];
    $resumo_materias = [];
    $min_planejados = 0;
    $feitos = 0;
    foreach ($blocos_semana as $b) {
        $b['situacao'] = situacao_bloco($b);
        $por_dia[$b['data']][] = $b;
        $resumo_materias[$b['materia']] ??= ['cor' => $b['cor'], 'icone' => $b['icone'], 'id' => $b['materia_id'], 'total' => 0, 'feitos' => 0];
        $resumo_materias[$b['materia']]['total']++;
        $min_planejados += (strtotime($b['hora_fim']) - strtotime($b['hora_inicio'])) / 60;
        if ($b['situacao'] === 'concluido') {
            $resumo_materias[$b['materia']]['feitos']++;
            $feitos++;
        }
    }
    uasort($resumo_materias, fn($a, $b) => $b['total'] <=> $a['total']);
    $total_semana = count($blocos_semana);
    $pct_semana = $total_semana ? (int) round($feitos / $total_semana * 100) : 0;

    $blocos_dia = $por_dia[$data] ?? [];

    // Próximo bloco a fazer (a partir de agora)
    $stmt = $pdo->prepare(
        "SELECT b.*, m.nome AS materia, m.cor, m.icone FROM cronograma_blocos b JOIN materias m ON m.id = b.materia_id
          WHERE b.usuario_id = ? AND b.status = 'pendente' AND CONCAT(b.data, ' ', b.hora_fim) > NOW()
          ORDER BY b.data, b.hora_inicio LIMIT 1"
    );
    $stmt->execute([$uid]);
    $proximo = $stmt->fetch() ?: null;

    // Revisões do dia escolhido (as atrasadas aparecem em "hoje")
    $stmt = $pdo->prepare(
        "SELECT r.id, r.titulo, r.data_agendada, m.nome AS materia, m.cor, m.icone FROM revisoes r LEFT JOIN materias m ON m.id = r.materia_id
          WHERE r.usuario_id = ? AND r.status = 'pendente' AND (r.data_agendada = ? OR (? = CURDATE() AND r.data_agendada < CURDATE()))
          ORDER BY r.data_agendada"
    );
    $stmt->execute([$uid, $data, $data]);
    $revisoes_dia = $stmt->fetchAll();

    $horas_do_dia = fn(string $d) => (float) $prefs[DIAS_SEMANA_COLUNAS[(int) date('N', strtotime($d))]];
    $link = fn(string $d) => e(url('aluno/cronograma.php?data=' . $d));
}

$titulo_pagina  = 'Cronograma';
$pagina_ativa   = 'cronograma';
$scripts_extras = ['cronograma.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<?php if ($vista === 'quadro'): ?>
<!-- ===================================================================
     QUADRO DA SEMANA
     =================================================================== -->
<div class="pagina-cabecalho">
    <div>
        <h1>Cronograma</h1>
        <p>Seus blocos de estudo de 50 minutos, montados a partir da sua rotina.</p>
    </div>
    <div class="linha">
        <form method="post" data-confirmar="Refazer o cronograma?"
              data-confirmar-texto="Os blocos que ainda não foram feitos serão distribuídos de novo a partir de agora. Os concluídos ficam salvos."
              data-confirmar-botao="Refazer">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="gerar">
            <button class="btn btn--contorno" type="submit"><?= icone('revisoes', 'icone--sm') ?> Refazer</button>
        </form>
        <a class="btn btn--primario" href="<?= e(url('aluno/cronograma.php?vista=ajustar')) ?>"><?= icone('configuracoes', 'icone--sm') ?> Ajustar rotina</a>
    </div>
</div>

<div class="kpis">
    <article class="kpi">
        <div class="kpi__topo"><span>Feito nesta semana</span><?= icone('check-circulo') ?></div>
        <div class="kpi__valor"><?= $feitos ?><small> de <?= $total_semana ?> blocos</small></div>
        <div class="progresso" role="progressbar" aria-valuenow="<?= $pct_semana ?>" aria-valuemin="0" aria-valuemax="100"><div class="progresso__barra" style="width: <?= $pct_semana ?>%;"></div></div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Tempo planejado</span><?= icone('relogio') ?></div>
        <div class="kpi__valor"><?= e(formatar_minutos((int) $min_planejados)) ?></div>
        <div class="kpi__detalhe">Nesta semana</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Próximo bloco</span><?= icone('play') ?></div>
        <?php if ($proximo): ?>
            <div class="kpi__valor kpi__valor--texto"><?= e(data_relativa($proximo['data'])) ?>, <?= e(hora_curta($proximo['hora_inicio'])) ?></div>
            <div class="kpi__detalhe"><?= chip_materia($proximo['materia'], $proximo['cor'], $proximo['icone']) ?></div>
        <?php else: ?>
            <div class="kpi__valor">-</div>
            <div class="kpi__detalhe">Nenhum bloco pela frente</div>
        <?php endif; ?>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Prova</span><?= icone('cronograma') ?></div>
        <?php if (!empty($prefs['data_prova'])): $faltam = (int) (new DateTime($hoje))->diff(new DateTime($prefs['data_prova']))->format('%r%a'); ?>
            <div class="kpi__valor"><?= max(0, $faltam) ?><small> <?= $faltam === 1 ? 'dia' : 'dias' ?></small></div>
            <div class="kpi__detalhe"><?= e(data_br($prefs['data_prova'])) ?></div>
        <?php else: ?>
            <div class="kpi__valor">-</div>
            <div class="kpi__detalhe"><a href="<?= e(url('aluno/cronograma.php?vista=ajustar')) ?>">Definir data da prova</a></div>
        <?php endif; ?>
    </article>
</div>

<!-- Semana -->
<section class="card quadro-semana">
    <div class="card__cabecalho">
        <div>
            <h2 class="card__titulo">Semana de <?= e($segunda->format('d/m')) ?> a <?= e($domingo->format('d/m')) ?></h2>
            <p class="card__subtitulo">Clique em um dia para ver os blocos e marcar o que já estudou.</p>
        </div>
        <div class="dia-nav">
            <a class="botao-icone" href="<?= $link((clone $segunda)->modify('-7 days')->format('Y-m-d')) ?>" aria-label="Semana anterior" style="transform: rotate(180deg);"><?= icone('seta-direita', 'icone--sm') ?></a>
            <?php if ($segunda->format('Y-m-d') !== (new DateTime('monday this week'))->format('Y-m-d')): ?>
                <a class="chip" href="<?= e(url('aluno/cronograma.php')) ?>">Esta semana</a>
            <?php endif; ?>
            <a class="botao-icone" href="<?= $link((clone $segunda)->modify('+7 days')->format('Y-m-d')) ?>" aria-label="Próxima semana"><?= icone('seta-direita', 'icone--sm') ?></a>
        </div>
    </div>

    <div class="semana-grade">
        <?php for ($i = 0; $i < 7; $i++):
            $dia = (clone $segunda)->modify("+$i day");
            $chave = $dia->format('Y-m-d');
            $blocos = $por_dia[$chave] ?? [];
            $feitos_dia = count(array_filter($blocos, fn($b) => $b['situacao'] === 'concluido'));
            $classes = 'dia-coluna'
                . ($chave === $hoje ? ' dia-coluna--hoje' : '')
                . ($chave === $data ? ' dia-coluna--selecionado' : '')
                . ($chave < $hoje ? ' dia-coluna--passado' : '');
        ?>
            <a class="<?= $classes ?>" href="<?= $link($chave) ?>" <?= $chave === $data ? 'aria-current="date"' : '' ?>>
                <span class="dia-coluna__topo">
                    <span class="dia-coluna__nome"><?= $nomes_dias[(int) $dia->format('N')] ?></span>
                    <span class="dia-coluna__numero numero"><?= $dia->format('d') ?></span>
                    <?php if ($chave === $hoje): ?><span class="dia-coluna__hoje">Hoje</span><?php endif; ?>
                </span>

                <span class="dia-coluna__blocos">
                    <?php foreach ($blocos as $b): ?>
                        <span class="bloco-semana bloco-semana--<?= $b['situacao'] ?>" style="--cor: <?= cor_materia($b['cor']) ?>;">
                            <span class="bloco-semana__hora numero"><?= e(hora_curta($b['hora_inicio'])) ?></span>
                            <span class="bloco-semana__nome"><?= e($b['materia']) ?></span>
                            <?php if ($b['situacao'] === 'concluido'): ?><?= icone('check', 'icone--xs') ?><?php endif; ?>
                        </span>
                    <?php endforeach; ?>

                    <?php if (!$blocos): ?>
                        <span class="dia-coluna__vazio">
                            <?php if ($horas_do_dia($chave) <= 0): ?>Folga
                            <?php elseif ($chave < $hoje): ?>Sem blocos
                            <?php elseif ($chave === $hoje): ?>Sem blocos hoje
                            <?php elseif ($chave > date('Y-m-d', strtotime('+' . (DIAS_GERADOS - 1) . ' days'))): ?>Ainda não gerado
                            <?php else: ?>Livre<?php endif; ?>
                        </span>
                    <?php endif; ?>
                </span>

                <?php if ($blocos): ?>
                    <span class="dia-coluna__rodape"><?= $feitos_dia ?>/<?= count($blocos) ?> feitos</span>
                <?php endif; ?>

                <!-- Versão compacta (celular): um ponto por bloco, na cor da matéria -->
                <span class="dia-coluna__pontos" aria-hidden="true">
                    <?php foreach ($blocos as $b): ?>
                        <i class="<?= $b['situacao'] === 'concluido' ? 'feito' : '' ?>" style="--cor: <?= cor_materia($b['cor']) ?>;"></i>
                    <?php endforeach; ?>
                    <?php if (!$blocos && $horas_do_dia($chave) <= 0): ?><small>folga</small><?php endif; ?>
                </span>
            </a>
        <?php endfor; ?>
    </div>

    <ul class="legenda-situacao" aria-label="Legenda">
        <li><span class="bloco-legenda bloco-legenda--pendente"></span> A fazer</li>
        <li><span class="bloco-legenda bloco-legenda--concluido"></span> Feito</li>
        <li><span class="bloco-legenda bloco-legenda--perdido"></span> Não feito (pode remarcar)</li>
    </ul>
</section>

<div class="grade-lateral mt-5">
    <!-- Detalhe do dia -->
    <section class="card">
        <div class="card__cabecalho">
            <div>
                <h2 class="card__titulo linha" style="gap: 8px;">
                    <?= e(ucfirst(data_extenso($data))) ?>
                    <?php if ($data === $hoje): ?><span class="selo selo--accent">Hoje</span><?php endif; ?>
                </h2>
                <p class="card__subtitulo">
                    <?php if ($blocos_dia): ?>
                        <?= count($blocos_dia) ?> <?= count($blocos_dia) === 1 ? 'bloco' : 'blocos' ?> de estudo, das <?= e(hora_curta($blocos_dia[0]['hora_inicio'])) ?> às <?= e(hora_curta(end($blocos_dia)['hora_fim'])) ?>
                    <?php else: ?>
                        Nenhum bloco neste dia
                    <?php endif; ?>
                </p>
            </div>
            <div class="dia-nav">
                <a class="botao-icone" href="<?= $link(date('Y-m-d', strtotime($data . ' -1 day'))) ?>" aria-label="Dia anterior" style="transform: rotate(180deg);"><?= icone('seta-direita', 'icone--sm') ?></a>
                <?php if ($data !== $hoje): ?><a class="chip" href="<?= e(url('aluno/cronograma.php')) ?>">Hoje</a><?php endif; ?>
                <a class="botao-icone" href="<?= $link(date('Y-m-d', strtotime($data . ' +1 day'))) ?>" aria-label="Próximo dia"><?= icone('seta-direita', 'icone--sm') ?></a>
            </div>
        </div>

        <?php if (!$blocos_dia): ?>
            <div class="vazio" style="padding: 24px 8px;">
                <?= icone('cronograma') ?>
                <?php if ($horas_do_dia($data) <= 0): ?>
                    <p>Dia de folga: você não reservou horas de estudo para <?= e($nomes_longos[(int) date('N', strtotime($data))]) ?>.</p>
                    <a class="btn btn--secundario btn--sm" href="<?= e(url('aluno/cronograma.php?vista=ajustar')) ?>">Ajustar rotina</a>
                <?php elseif ($data < $hoje): ?>
                    <p>Este dia já passou e não tinha blocos marcados.</p>
                <?php elseif ($data > date('Y-m-d', strtotime('+' . (DIAS_GERADOS - 1) . ' days'))): ?>
                    <p>O cronograma é montado para os próximos <?= DIAS_GERADOS ?> dias. Este dia entra quando chegar a hora.</p>
                <?php else: ?>
                    <p>Os horários deste dia já passaram ou estão livres. Use "Refazer" para redistribuir os blocos.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ol class="lista-blocos">
                <?php foreach ($blocos_dia as $b): [$rotulo, $classe_selo, $icone_selo] = ROTULOS_SITUACAO[$b['situacao']]; ?>
                    <li class="linha-bloco linha-bloco--<?= $b['situacao'] ?>" style="--cor: <?= cor_materia($b['cor']) ?>;">
                        <div class="linha-bloco__hora numero">
                            <b><?= e(hora_curta($b['hora_inicio'])) ?></b>
                            <span><?= e(hora_curta($b['hora_fim'])) ?></span>
                        </div>
                        <div class="linha-bloco__corpo">
                            <span class="linha-bloco__materia"><?= icone($b['icone'], 'icone--sm') ?> <?= e($b['materia']) ?></span>
                            <span class="selo <?= $classe_selo ?>"><?= icone($icone_selo, 'icone--xs') ?> <?= $rotulo ?></span>
                        </div>
                        <?php if ($b['status'] === 'pendente'): ?>
                            <div class="linha-bloco__acoes">
                                <?php if ($b['situacao'] !== 'perdido'): ?>
                                    <a class="btn btn--sm <?= $b['situacao'] === 'agora' ? 'btn--primario' : 'btn--secundario' ?>" href="<?= e(url('aluno/pomodoro.php?bloco=' . $b['id'])) ?>"><?= icone('play', 'icone--xs') ?> Estudar</a>
                                <?php endif; ?>
                                <form method="post">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="concluir">
                                    <input type="hidden" name="bloco_id" value="<?= (int) $b['id'] ?>">
                                    <button class="btn btn--contorno btn--sm" type="submit" title="Marcar como estudado"><?= icone('check', 'icone--xs') ?> Concluir</button>
                                </form>
                                <form method="post">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="redistribuir">
                                    <input type="hidden" name="bloco_id" value="<?= (int) $b['id'] ?>">
                                    <button class="btn btn--contorno btn--sm" type="submit" title="Mover para o próximo dia com horário livre">Remarcar</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <aside class="pilha">
        <section class="card">
            <div class="card__cabecalho">
                <div>
                    <h2 class="card__titulo">Matérias da semana</h2>
                    <p class="card__subtitulo">Blocos feitos e planejados</p>
                </div>
            </div>
            <?php if (!$resumo_materias): ?>
                <p class="texto-pequeno texto-secundario">Nenhum bloco nesta semana.</p>
            <?php else: ?>
                <div class="pilha pilha--sm">
                    <?php foreach ($resumo_materias as $nome => $m): ?>
                        <div class="barra-materia" style="--cor: <?= cor_materia($m['cor']) ?>;">
                            <span><?= chip_materia($nome, $m['cor'], $m['icone']) ?></span>
                            <span class="barra-materia__trilha"><span class="barra-materia__valor" style="display: block; width: <?= round($m['feitos'] / $m['total'] * 100) ?>%;"></span></span>
                            <span class="barra-materia__numero"><?= $m['feitos'] ?>/<?= $m['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__cabecalho">
                <h2 class="card__titulo">Revisões <?= $data === $hoje ? 'de hoje' : 'do dia' ?></h2>
                <a class="link-ver-todos" href="<?= e(url('aluno/revisoes.php')) ?>">Todas</a>
            </div>
            <?php if (!$revisoes_dia): ?>
                <p class="texto-pequeno texto-secundario">Nenhuma revisão para este dia.</p>
            <?php else: ?>
                <ul class="lista" style="gap: 8px;">
                    <?php foreach ($revisoes_dia as $r): ?>
                        <li class="lista-item faixa-materia" style="--cor: <?= cor_materia($r['cor']) ?>; padding: 12px;">
                            <span class="lista-item__corpo">
                                <span class="lista-item__titulo" style="display: block;"><?= e($r['titulo']) ?></span>
                                <span class="lista-item__meta">
                                    <?= chip_materia($r['materia'] ?? null, $r['cor'], $r['icone']) ?>
                                    <?php if ($r['data_agendada'] < $hoje): ?><span class="texto-erro">Atrasada</span><?php endif; ?>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php else: /* ===================================================================
     AJUSTAR ROTINA (3 passos + prévia ao vivo)
     =================================================================== */
    $valor_horas = fn(string $col) => $prefs ? (float) $prefs[$col] : (in_array($col, ['horas_sab', 'horas_dom'], true) ? 1 : 2);
?>
<div class="pagina-cabecalho">
    <div>
        <?php if ($prefs): ?><p class="texto-pequeno"><a href="<?= e(url('aluno/cronograma.php')) ?>">Cronograma</a></p><?php endif; ?>
        <h1><?= $prefs ? 'Ajustar rotina' : 'Monte seu cronograma' ?></h1>
        <p>Em 3 passos: o que estudar, quanto tempo você tem e quando começar. O sistema distribui os blocos para você.</p>
    </div>
</div>

<form method="post" class="ajuste" id="form-cronograma">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="salvar">

    <div class="pilha">
        <!-- Passo 1 -->
        <section class="card passo">
            <div class="passo__cabecalho">
                <span class="passo__numero">1</span>
                <div>
                    <h2 class="card__titulo">O que você quer estudar?</h2>
                    <p class="card__subtitulo">Ligue as matérias e escolha a prioridade. Prioridade maior significa mais blocos na semana.</p>
                </div>
            </div>
            <div class="materias-prioridade">
                <?php foreach (materias_lista() as $m):
                    $marcada = isset($pesos[$m['id']]) || !$prefs;
                    $peso = $pesos[$m['id']] ?? 3;
                ?>
                    <div class="prioridade-linha <?= $marcada ? '' : 'prioridade-linha--off' ?>" style="--cor: <?= cor_materia($m['cor']) ?>;" data-materia="<?= (int) $m['id'] ?>" data-nome="<?= e($m['nome']) ?>">
                        <label class="prioridade-linha__materia">
                            <input type="checkbox" name="materias[]" value="<?= (int) $m['id'] ?>" <?= $marcada ? 'checked' : '' ?> data-materia-check>
                            <span class="interruptor" aria-hidden="true"></span>
                            <?= chip_materia($m['nome'], $m['cor'], $m['icone']) ?>
                        </label>
                        <div class="segmento segmento--prioridade" role="radiogroup" aria-label="Prioridade de <?= e($m['nome']) ?>">
                            <?php foreach (PRIORIDADES as $valor => $rotulo): ?>
                                <input type="radio" id="peso-<?= (int) $m['id'] ?>-<?= $valor ?>" name="peso[<?= (int) $m['id'] ?>]" value="<?= $valor ?>" <?= $peso === $valor ? 'checked' : '' ?> data-peso>
                                <label for="peso-<?= (int) $m['id'] ?>-<?= $valor ?>"><?= e($rotulo) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Passo 2 -->
        <section class="card passo">
            <div class="passo__cabecalho">
                <span class="passo__numero">2</span>
                <div>
                    <h2 class="card__titulo">Quanto tempo você tem por dia?</h2>
                    <p class="card__subtitulo">Cada bloco tem 50 minutos de estudo e 10 de pausa. Deixe em 0 os dias de folga.</p>
                </div>
            </div>
            <div class="horas-semana">
                <?php foreach (DIAS_SEMANA_COLUNAS as $n => $col): $h = $valor_horas($col); ?>
                    <div class="horas-dia" data-horas-dia>
                        <span class="horas-dia__nome"><?= $nomes_dias[$n] ?></span>
                        <button type="button" class="horas-dia__botao" data-menos aria-label="Menos meia hora na <?= e($nomes_longos[$n]) ?>">−</button>
                        <output class="horas-dia__valor numero" data-valor><?= e(str_replace('.', ',', rtrim(rtrim(number_format($h, 1, '.', ''), '0'), '.'))) ?>h</output>
                        <button type="button" class="horas-dia__botao" data-mais aria-label="Mais meia hora na <?= e($nomes_longos[$n]) ?>">+</button>
                        <span class="horas-dia__blocos" data-blocos><?= blocos_por_horas($h) ?> blocos</span>
                        <input type="hidden" name="<?= $col ?>" value="<?= e((string) $h) ?>" data-horas>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Passo 3 -->
        <section class="card passo">
            <div class="passo__cabecalho">
                <span class="passo__numero">3</span>
                <div>
                    <h2 class="card__titulo">Quando começar?</h2>
                    <p class="card__subtitulo">O horário do primeiro bloco do dia e, se tiver, a data da sua prova.</p>
                </div>
            </div>
            <div class="form-grid">
                <div class="campo">
                    <label class="campo__rotulo" for="hora_inicio">Primeiro bloco do dia às</label>
                    <input class="input" type="time" id="hora_inicio" name="hora_inicio" required value="<?= e(hora_curta($prefs['hora_inicio'] ?? '14:00:00')) ?>">
                </div>
                <div class="campo">
                    <label class="campo__rotulo" for="data_prova">Data da prova <span class="texto-secundario">(opcional)</span></label>
                    <input class="input" type="date" id="data_prova" name="data_prova" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e($prefs['data_prova'] ?? '') ?>">
                    <span class="campo__ajuda">Os blocos param na véspera da prova.</span>
                </div>
            </div>
        </section>
    </div>

    <!-- Prévia ao vivo -->
    <aside class="card previa" aria-live="polite">
        <h2 class="card__titulo">Sua semana vai ficar assim</h2>
        <div class="previa__numeros">
            <div><b class="numero" id="previa-blocos">0</b><span>blocos por semana</span></div>
            <div><b class="numero" id="previa-horas">0h</b><span>de estudo</span></div>
        </div>
        <div class="pilha pilha--sm" id="previa-materias"></div>
        <p class="texto-xs texto-secundario" id="previa-aviso"></p>
        <button type="submit" class="btn btn--primario btn--bloco"><?= icone('check', 'icone--sm') ?> <?= $prefs ? 'Salvar e refazer cronograma' : 'Gerar meu cronograma' ?></button>
        <?php if ($prefs): ?>
            <a class="btn btn--contorno btn--bloco" href="<?= e(url('aluno/cronograma.php')) ?>">Cancelar</a>
        <?php endif; ?>
    </aside>
</form>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
