<?php
/**
 * Caderno de erros: questões erradas nos simulados, com motivo do erro,
 * filtros, gráfico dos motivos e atalho para refazer.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$motivos = ['atencao' => 'Falta de atenção', 'conteudo' => 'Não sabia o conteúdo', 'interpretacao' => 'Erro de interpretação'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $motivo = $_POST['motivo'] ?? '';
    if (isset($motivos[$motivo])) {
        $pdo->prepare('UPDATE caderno_erros SET motivo = ? WHERE id = ? AND usuario_id = ?')->execute([$motivo, $id, $uid]);
        toast('Motivo salvo.');
    }
    redirecionar('aluno/caderno.php?' . http_build_query(array_intersect_key($_GET, array_flip(['materia', 'motivo', 'situacao']))));
}

// Filtros
$f_materia  = (int) ($_GET['materia'] ?? 0);
$f_motivo   = $_GET['motivo'] ?? '';
$f_motivo   = isset($motivos[$f_motivo]) || $f_motivo === 'sem' ? $f_motivo : '';
$f_situacao = ($_GET['situacao'] ?? 'pendentes') === 'superadas' ? 'superadas' : 'pendentes';

$where  = ['c.usuario_id = ?', 'c.superada = ?'];
$params = [$uid, $f_situacao === 'superadas' ? 1 : 0];
if ($f_materia) {
    $where[] = 'q.materia_id = ?';
    $params[] = $f_materia;
}
if ($f_motivo === 'sem') {
    $where[] = 'c.motivo IS NULL';
} elseif ($f_motivo) {
    $where[] = 'c.motivo = ?';
    $params[] = $f_motivo;
}

$stmt = $pdo->prepare(
    'SELECT c.id, c.motivo, c.vezes_errada, c.resposta_dada, c.atualizado_em,
            q.tipo, q.enunciado, q.resposta_correta, q.gabarito_comentario,
            q.alternativa_a, q.alternativa_b, q.alternativa_c, q.alternativa_d,
            m.nome AS materia, m.cor, m.icone
       FROM caderno_erros c JOIN questoes q ON q.id = c.questao_id JOIN materias m ON m.id = q.materia_id
      WHERE ' . implode(' AND ', $where) . ' ORDER BY c.atualizado_em DESC'
);
$stmt->execute($params);
$erros = $stmt->fetchAll();

// Gráfico: motivos mais frequentes (pendentes e superados)
$stmt = $pdo->prepare('SELECT motivo, COUNT(*) total FROM caderno_erros WHERE usuario_id = ? AND motivo IS NOT NULL GROUP BY motivo');
$stmt->execute([$uid]);
$contagem = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare('SELECT SUM(superada = 0), SUM(superada = 1), SUM(superada = 0 AND motivo IS NULL) FROM caderno_erros WHERE usuario_id = ?');
$stmt->execute([$uid]);
[$total_pendentes, $total_superadas, $sem_motivo] = array_map('intval', $stmt->fetch(PDO::FETCH_NUM));

// Erros pendentes por matéria (para ver onde se concentram)
$stmt = $pdo->prepare(
    'SELECT m.id, m.nome, m.cor, m.icone, COUNT(c.id) AS total
       FROM materias m LEFT JOIN questoes q ON q.materia_id = m.id
       LEFT JOIN caderno_erros c ON c.questao_id = q.id AND c.usuario_id = ? AND c.superada = 0
      GROUP BY m.id ORDER BY total DESC, m.nome'
);
$stmt->execute([$uid]);
$erros_materia = $stmt->fetchAll();
$maior_erros = max(1, (int) ($erros_materia[0]['total'] ?? 1));
$materia_critica = ($erros_materia[0]['total'] ?? 0) > 0 ? $erros_materia[0] : null;

$link_refazer = url('aluno/simulado.php?modo=caderno' . ($f_materia ? '&materia=' . $f_materia : ''));
$texto_alt = fn(array $q, ?string $l) => $l && isset($q['alternativa_' . strtolower($l)]) ? $l . ') ' . $q['alternativa_' . strtolower($l)] : (string) $l;

$titulo_pagina  = 'Caderno de erros';
$pagina_ativa   = 'caderno';
$usa_chartjs    = (bool) $contagem;
$scripts_extras = $contagem ? ['graficos.js'] : [];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Caderno de erros</h1>
        <p class="largura-texto">Toda questão errada entra aqui. Ao acertar de novo, ela é marcada como superada.</p>
    </div>
    <?php if ($total_pendentes): ?>
        <a class="btn btn--primario" href="<?= e($link_refazer) ?>"><?= icone('simulado', 'icone--sm') ?> Refazer questões erradas</a>
    <?php endif; ?>
</div>

<div class="kpis">
    <article class="kpi <?= $total_pendentes ? 'kpi--alerta' : '' ?>">
        <div class="kpi__topo"><span>Erros pendentes</span><?= icone('caderno') ?></div>
        <div class="kpi__valor"><?= $total_pendentes ?></div>
        <div class="kpi__detalhe">Ainda não refeitos com acerto</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Superados</span><?= icone('check-circulo') ?></div>
        <div class="kpi__valor"><?= $total_superadas ?><small> / <?= $total_pendentes + $total_superadas ?></small></div>
        <div class="kpi__detalhe">Acertou ao refazer</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Sem motivo</span><?= icone('questoes') ?></div>
        <div class="kpi__valor"><?= $sem_motivo ?></div>
        <div class="kpi__detalhe">Classifique para ver o padrão</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Mais erros em</span><?= icone('alerta') ?></div>
        <div class="kpi__valor"><?= $materia_critica ? (int) $materia_critica['total'] : '-' ?></div>
        <div class="kpi__detalhe"><?= $materia_critica ? chip_materia($materia_critica['nome'], $materia_critica['cor'], $materia_critica['icone']) : 'Nenhum erro pendente' ?></div>
    </article>
</div>

<div class="grade-lateral">
    <div>
        <form class="filtros" method="get">
            <div class="campo">
                <label class="campo__rotulo" for="f-situacao">Situação</label>
                <select class="select" id="f-situacao" name="situacao" data-enviar-ao-mudar>
                    <option value="pendentes">Pendentes (<?= $total_pendentes ?>)</option>
                    <option value="superadas" <?= $f_situacao === 'superadas' ? 'selected' : '' ?>>Superadas (<?= $total_superadas ?>)</option>
                </select>
            </div>
            <div class="campo">
                <label class="campo__rotulo" for="f-materia">Matéria</label>
                <select class="select" id="f-materia" name="materia" data-enviar-ao-mudar>
                    <option value="0">Todas</option>
                    <?php foreach (materias_lista() as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= $f_materia === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label class="campo__rotulo" for="f-motivo">Motivo</label>
                <select class="select" id="f-motivo" name="motivo" data-enviar-ao-mudar>
                    <option value="">Todos</option>
                    <option value="sem" <?= $f_motivo === 'sem' ? 'selected' : '' ?>>Sem motivo</option>
                    <?php foreach ($motivos as $chave => $nome): ?>
                        <option value="<?= $chave ?>" <?= $f_motivo === $chave ? 'selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <noscript><button class="btn btn--secundario">Filtrar</button></noscript>
        </form>

        <?php if (!$erros): ?>
            <div class="card vazio">
                <?= icone('caderno') ?>
                <p><?= $f_situacao === 'superadas' ? 'Nenhuma questão superada com esses filtros.' : 'Nenhum erro pendente com esses filtros.' ?></p>
            </div>
        <?php endif; ?>

        <?php foreach ($erros as $c): ?>
            <article class="revisao-questao faixa-materia" style="--cor: <?= cor_materia($c['cor']) ?>;">
                <div class="revisao-questao__cabecalho">
                    <span class="linha" style="gap: 8px;">
                        <?= chip_materia($c['materia'], $c['cor'], $c['icone']) ?>
                        <?php if ($c['vezes_errada'] > 1): ?><span class="selo selo--erro">Errou <?= (int) $c['vezes_errada'] ?>x</span><?php endif; ?>
                    </span>
                    <span class="texto-xs texto-secundario"><?= e(data_br($c['atualizado_em'])) ?></span>
                </div>
                <p style="white-space: pre-line;"><?= e($c['enunciado']) ?></p>

                <div class="revisao-questao__resposta">
                    <div><span class="texto-secundario">Você respondeu: </span><?= e($c['tipo'] === 'multipla' ? $texto_alt($c, $c['resposta_dada']) : mb_strimwidth((string) $c['resposta_dada'], 0, 300, '...')) ?></div>
                    <?php if ($c['tipo'] === 'multipla'): ?>
                        <div style="border-left: 3px solid var(--success);"><span class="texto-secundario">Correta: </span><?= e($texto_alt($c, $c['resposta_correta'])) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($c['gabarito_comentario']): ?>
                    <details>
                        <summary><?= $c['tipo'] === 'multipla' ? 'Ver comentário' : 'Ver gabarito' ?></summary>
                        <p style="margin-top: 8px; white-space: pre-line;"><?= e($c['gabarito_comentario']) ?></p>
                    </details>
                <?php endif; ?>

                <form method="post" class="linha mt-4" style="gap: 8px;">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <span class="texto-xs texto-secundario">Por que errou?</span>
                    <?php foreach ($motivos as $chave => $nome): ?>
                        <button class="btn btn--sm <?= $c['motivo'] === $chave ? 'btn--primario' : 'btn--contorno' ?>" name="motivo" value="<?= $chave ?>"
                                aria-pressed="<?= $c['motivo'] === $chave ? 'true' : 'false' ?>"><?= e($nome) ?></button>
                    <?php endforeach; ?>
                </form>
            </article>
        <?php endforeach; ?>
    </div>

    <aside class="pilha">
        <section class="card">
            <div class="card__cabecalho">
                <h2 class="card__titulo">Por que você erra</h2>
            </div>
            <?php if (!$contagem): ?>
                <p class="texto-pequeno texto-secundario">Classifique o motivo dos erros para ver o padrão aqui.</p>
            <?php else: ?>
                <div class="grafico grafico--pequeno" style="height: 240px;">
                    <canvas data-grafico="rosca" data-sufixo=" questões"
                            data-rotulos="<?= e(json_encode(array_values(array_map(fn($k) => $motivos[$k], array_keys($contagem))), JSON_UNESCAPED_UNICODE)) ?>"
                            data-valores="<?= e(json_encode(array_map('intval', array_values($contagem)))) ?>"
                            aria-label="Gráfico com os motivos de erro mais frequentes"></canvas>
                </div>
            <?php endif; ?>
            <?php if ($sem_motivo): ?>
                <p class="texto-xs texto-secundario mt-4"><?= $sem_motivo ?> <?= $sem_motivo === 1 ? 'erro ainda está' : 'erros ainda estão' ?> sem motivo.</p>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card__cabecalho">
                <h2 class="card__titulo">Erros por matéria</h2>
            </div>
            <div class="pilha pilha--sm">
                <?php foreach ($erros_materia as $m): $qtd = (int) $m['total']; ?>
                    <a class="barra-materia <?= $qtd ? '' : 'barra-materia--vazia' ?>" style="--cor: <?= cor_materia($m['cor']) ?>; color: inherit;"
                       href="<?= e(url('aluno/caderno.php?materia=' . $m['id'])) ?>" title="Ver erros de <?= e($m['nome']) ?>">
                        <span><?= chip_materia($m['nome'], $m['cor'], $m['icone']) ?></span>
                        <span class="barra-materia__trilha"><span class="barra-materia__valor" style="display: block; width: <?= round($qtd / $maior_erros * 100) ?>%;"></span></span>
                        <span class="barra-materia__numero"><?= $qtd ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (eh_premium($usuario) && $total_pendentes): ?>
                <a class="btn btn--secundario btn--bloco mt-5" href="<?= e(url('aluno/ia.php?origem=caderno')) ?>"><?= icone('brilho', 'icone--sm') ?> Gerar flashcards dos erros</a>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
