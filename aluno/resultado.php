<?php
/**
 * Resultado de um simulado: pontuação, percentual e revisão questão a questão.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];

$stmt = db()->prepare(
    'SELECT h.*, m.nome AS materia, m.cor, m.icone FROM historico_simulados h
       LEFT JOIN materias m ON m.id = h.materia_id
      WHERE h.id = ? AND h.usuario_id = ?'
);
$stmt->execute([(int) ($_GET['id'] ?? 0), $uid]);
$simulado = $stmt->fetch();

if (!$simulado) {
    toast('Simulado não encontrado.', 'erro');
    redirecionar('aluno/historico.php');
}

$stmt = db()->prepare(
    'SELECT r.resposta_dada, r.correta, q.id, q.tipo, q.enunciado, q.resposta_correta, q.gabarito_comentario,
            q.alternativa_a, q.alternativa_b, q.alternativa_c, q.alternativa_d,
            m.nome AS materia_questao, m.cor AS cor_questao, m.icone AS icone_questao
       FROM respostas_simulado r JOIN questoes q ON q.id = r.questao_id JOIN materias m ON m.id = q.materia_id
      WHERE r.simulado_id = ? ORDER BY r.id'
);
$stmt->execute([$simulado['id']]);
$respostas = $stmt->fetchAll();

// Simulados com várias matérias (geral ou refazer erros): desempenho por matéria
$varias_materias = !$simulado['materia_id'];
$por_materia = [];
foreach ($respostas as $r) {
    $por_materia[$r['materia_questao']] ??= ['cor' => $r['cor_questao'], 'icone' => $r['icone_questao'], 'acertos' => 0, 'total' => 0];
    $por_materia[$r['materia_questao']]['total']++;
    $por_materia[$r['materia_questao']]['acertos'] += $r['correta'] ? 1 : 0;
}
uasort($por_materia, fn($a, $b) => $b['acertos'] / $b['total'] <=> $a['acertos'] / $a['total']);

$total    = (int) $simulado['total_questoes'];
$acertos  = (int) $simulado['pontuacao'];
$pct      = $total ? (int) round($acertos / $total * 100) : 0;
$erros    = $total - $acertos;
$cor      = $pct >= 70 ? 'var(--success)' : ($pct >= 50 ? 'var(--aviso)' : 'var(--danger-claro)');
$mensagem = match (true) {
    $pct >= 90 => 'Excelente. Você domina esse conteúdo.',
    $pct >= 70 => 'Bom resultado. Revise os erros para fechar as lacunas.',
    $pct >= 50 => 'No caminho. Vale reforçar a teoria antes do próximo.',
    default    => 'Esse conteúdo precisa de mais atenção. Agendamos uma revisão de reforço.',
};

/** Texto da alternativa a partir da letra. */
$texto_alternativa = fn(array $q, ?string $letra) =>
    $letra && isset($q['alternativa_' . strtolower($letra)]) ? $letra . ') ' . $q['alternativa_' . strtolower($letra)] : 'Sem resposta';

$titulo_pagina = 'Resultado do simulado';
$pagina_ativa  = 'simulados';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="simulado" style="--cor: <?= cor_materia($simulado['cor']) ?>;">
    <section class="card placar" style="border-top: 4px solid var(--cor);">
        <div class="placar__anel" style="--p: <?= $pct ?>; --cor-placar: <?= $cor ?>;">
            <strong class="numero"><?= $pct ?>%</strong>
        </div>
        <div>
            <p class="linha texto-secundario texto-pequeno" style="gap: 8px;">
                <?= chip_materia($simulado['materia'] ?? ($simulado['origem'] === 'geral' ? 'Simulado geral' : 'Refazendo erros'), $simulado['cor'], $simulado['icone']) ?>
                <span><?= e(data_br($simulado['data_realizacao'], true)) ?></span>
            </p>
            <h1 style="margin: 4px 0 8px;"><?= e($mensagem) ?></h1>
            <div class="placar__numeros">
                <div><b class="texto-sucesso"><?= $acertos ?></b><span>acertos</span></div>
                <div><b class="texto-erro"><?= $erros ?></b><span>erros</span></div>
                <div><b><?= $total ?></b><span>questões</span></div>
            </div>
            <div class="linha mt-5">
                <?php if ($simulado['materia_id']): ?>
                    <a class="btn btn--primario" href="<?= e(url('aluno/simulado.php?materia=' . $simulado['materia_id'] . '&qtd=' . min(15, max(5, $total)))) ?>">Fazer outro</a>
                <?php elseif ($simulado['origem'] === 'geral'): ?>
                    <a class="btn btn--primario" href="<?= e(url('aluno/simulado.php?modo=geral')) ?>">Fazer outro simulado geral</a>
                <?php endif; ?>
                <?php if ($erros): ?>
                    <a class="btn btn--secundario" href="<?= e(url('aluno/caderno.php')) ?>">Ver caderno de erros</a>
                <?php endif; ?>
                <a class="btn btn--contorno" href="<?= e(url('aluno/painel.php')) ?>">Voltar ao painel</a>
            </div>
        </div>
    </section>

    <?php if ($varias_materias && count($por_materia) > 1): ?>
        <section class="card mt-5">
            <div class="card__cabecalho">
                <div>
                    <h2 class="card__titulo">Desempenho por matéria</h2>
                    <p class="card__subtitulo">Matérias abaixo de <?= LIMITE_REFORCO ?>% ganharam uma revisão de reforço.</p>
                </div>
            </div>
            <div class="pilha pilha--sm">
                <?php foreach ($por_materia as $nome => $m): $pct_m = (int) round($m['acertos'] / $m['total'] * 100); ?>
                    <div class="barra-materia barra-materia--resultado" style="--cor: <?= cor_materia($m['cor']) ?>;">
                        <span><?= chip_materia($nome, $m['cor'], $m['icone']) ?></span>
                        <span class="barra-materia__trilha"><span class="barra-materia__valor" style="display: block; width: <?= $pct_m ?>%;"></span></span>
                        <span class="barra-materia__numero"><?= $m['acertos'] ?>/<?= $m['total'] ?></span>
                        <span class="selo <?= $pct_m >= 70 ? 'selo--sucesso' : ($pct_m >= LIMITE_REFORCO ? 'selo--aviso' : 'selo--erro') ?>"><?= $pct_m ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <h2 class="mt-5" style="margin-bottom: 16px; font-size: var(--fs-md);">Revisão das questões</h2>

    <?php foreach ($respostas as $i => $r): ?>
        <article class="revisao-questao">
            <div class="revisao-questao__cabecalho">
                <span class="linha" style="gap: 8px;">
                    <span class="texto-secundario texto-pequeno">Questão <?= $i + 1 ?></span>
                    <?php if ($varias_materias): ?><?= chip_materia($r['materia_questao'], $r['cor_questao'], $r['icone_questao']) ?><?php endif; ?>
                </span>
                <?php if ($r['correta']): ?>
                    <span class="selo selo--sucesso"><?= icone('check', 'icone--xs') ?> Acertou</span>
                <?php elseif ($r['resposta_dada'] === null): ?>
                    <span class="selo">Não respondida</span>
                <?php else: ?>
                    <span class="selo selo--erro"><?= icone('fechar', 'icone--xs') ?> Errou</span>
                <?php endif; ?>
            </div>
            <p style="white-space: pre-line;"><?= e($r['enunciado']) ?></p>

            <div class="revisao-questao__resposta">
                <?php if ($r['tipo'] === 'multipla'): ?>
                    <div><span class="texto-secundario">Sua resposta: </span><?= e($texto_alternativa($r, $r['resposta_dada'])) ?></div>
                    <?php if (!$r['correta']): ?>
                        <div style="border-left: 3px solid var(--success);"><span class="texto-secundario">Correta: </span><?= e($texto_alternativa($r, $r['resposta_correta'])) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div><span class="texto-secundario">Sua resposta: </span><?= e($r['resposta_dada'] ?? 'Sem resposta') ?></div>
                <?php endif; ?>
            </div>

            <?php if ($r['gabarito_comentario']): ?>
                <details <?= $r['correta'] ? '' : 'open' ?>>
                    <summary><?= $r['tipo'] === 'multipla' ? 'Comentário' : 'Gabarito' ?></summary>
                    <p style="margin-top: 8px; white-space: pre-line;"><?= e($r['gabarito_comentario']) ?></p>
                </details>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
