<?php
/**
 * Simulado em andamento: uma questão por vez, com feedback imediato.
 *
 * As questões sorteadas ficam guardadas na sessão. O gabarito NÃO vai para o
 * navegador: cada resposta é conferida no servidor (api/simulado_responder.php)
 * e a pontuação final é recalculada lá (api/simulado_finalizar.php).
 *
 * Modos:
 *   ?materia=ID&qtd=N     simulado por matéria (N = 5, 10 ou 15)
 *   ?modo=geral           simulado geral: 35 questões de todas as matérias
 *   ?modo=caderno         refazer questões erradas (opcional &materia=ID)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$modo       = in_array($_GET['modo'] ?? '', ['caderno', 'geral'], true) ? $_GET['modo'] : 'normal';
$materia_id = $modo === 'geral' ? 0 : (int) ($_GET['materia'] ?? 0);
$qtd        = (int) ($_GET['qtd'] ?? 5);
$qtd        = in_array($qtd, QTD_POR_MATERIA, true) ? $qtd : 5;

$materia = $materia_id ? materia_por_id($materia_id) : null;

if ($modo === 'normal') {
    if (!$materia) {
        toast('Escolha uma matéria para começar o simulado.', 'aviso');
        redirecionar('aluno/simulados.php');
    }
    $stmt = $pdo->prepare('SELECT id FROM questoes WHERE materia_id = ? ORDER BY RAND() LIMIT ' . $qtd);
    $stmt->execute([$materia_id]);
} elseif ($modo === 'geral') {
    $stmt = null;
    $qtd_geral = (int) ($_GET['qtd'] ?? SIMULADO_GERAL_TOTAL);
    $qtd_geral = in_array($qtd_geral, QTD_SIMULADO_GERAL, true) ? $qtd_geral : SIMULADO_GERAL_TOTAL;
    $ids = sortear_simulado_geral($qtd_geral);
} else {
    $stmt = $pdo->prepare(
        'SELECT q.id FROM caderno_erros c JOIN questoes q ON q.id = c.questao_id
          WHERE c.usuario_id = ? AND c.superada = 0 AND (? = 0 OR q.materia_id = ?)
          ORDER BY RAND() LIMIT 20'
    );
    $stmt->execute([$uid, $materia_id, $materia_id]);
}
if ($stmt) {
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
if ($modo === 'normal' && $ids && count($ids) < $qtd) {
    toast($materia['nome'] . ' tem só ' . count($ids) . ' questões no momento. O simulado foi montado com todas elas.', 'info');
}

if (!$ids) {
    toast($modo === 'caderno' ? 'Você não tem questões erradas pendentes. Bom trabalho!' : 'Ainda não há questões suficientes para este simulado.', 'info');
    redirecionar($modo === 'caderno' ? 'aluno/caderno.php' : 'aluno/simulados.php');
}

// Dados das questões (sem resposta correta e sem gabarito)
$marcadores = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT q.id, q.tipo, q.enunciado, q.alternativa_a, q.alternativa_b, q.alternativa_c, q.alternativa_d,
            q.dificuldade, m.nome AS materia, m.cor
       FROM questoes q JOIN materias m ON m.id = q.materia_id
      WHERE q.id IN ($marcadores)"
);
$stmt->execute($ids);
$por_id = [];
foreach ($stmt->fetchAll() as $q) {
    $por_id[(int) $q['id']] = [
        'id'          => (int) $q['id'],
        'tipo'        => $q['tipo'],
        'enunciado'   => $q['enunciado'],
        'alternativas'=> $q['tipo'] === 'multipla'
            ? ['A' => $q['alternativa_a'], 'B' => $q['alternativa_b'], 'C' => $q['alternativa_c'], 'D' => $q['alternativa_d']]
            : null,
        'dificuldade' => rotulo($q['dificuldade']),
        'materia'     => $q['materia'],
        'cor'         => $q['cor'],
    ];
}
$questoes = array_values(array_map(fn($id) => $por_id[$id], $ids)); // mantém a ordem sorteada

// Guarda o simulado na sessão (uma tentativa ativa por vez)
$_SESSION['simulado'] = [
    'materia_id' => $materia_id ?: null,
    'modo'       => $modo,
    'questoes'   => $ids,
    'respostas'  => [],
    'iniciado'   => time(),
];

$titulo_pagina  = match ($modo) { 'caderno' => 'Refazendo erros', 'geral' => 'Simulado geral', default => 'Simulado de ' . $materia['nome'] };
$pagina_ativa   = 'simulados';
$scripts_extras = ['simulado.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="simulado" id="simulado">
    <div class="linha linha--entre" style="margin-bottom: 16px;">
        <h1 style="font-size: var(--fs-lg);"><?= e($titulo_pagina) ?></h1>
        <form method="get" action="<?= e(url('aluno/simulados.php')) ?>" data-confirmar="Sair do simulado?"
              data-confirmar-texto="As respostas desta tentativa não serão salvas." data-confirmar-botao="Sair">
            <button class="btn btn--contorno btn--sm" type="submit">Sair</button>
        </form>
    </div>

    <div class="simulado__topo">
        <div class="progresso" role="progressbar" aria-valuemin="0" aria-valuemax="<?= count($questoes) ?>" id="progresso">
            <div class="progresso__barra" id="progresso-barra"></div>
        </div>
        <span class="simulado__contador" id="contador">1 de <?= count($questoes) ?></span>
    </div>

    <article class="card" id="area-questao" aria-live="polite">
        <!-- preenchido por simulado.js -->
    </article>

    <div class="simulado__acoes" id="acoes"></div>
</div>

<script type="application/json" id="dados-simulado"><?= json_encode($questoes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
