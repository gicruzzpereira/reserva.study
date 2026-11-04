<?php
/**
 * Timer Pomodoro. Pode ser aberto a partir de um bloco do cronograma (?bloco=ID).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$bloco = null;
if (!empty($_GET['bloco'])) {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.materia_id, b.hora_inicio, b.hora_fim, b.data, b.status, m.nome AS materia
           FROM cronograma_blocos b JOIN materias m ON m.id = b.materia_id
          WHERE b.id = ? AND b.usuario_id = ?"
    );
    $stmt->execute([(int) $_GET['bloco'], $uid]);
    $bloco = $stmt->fetch() ?: null;
}

$materia_inicial = $bloco['materia_id'] ?? (int) ($_GET['materia'] ?? 0);

// Sessões de hoje
$stmt = $pdo->prepare(
    'SELECT s.minutos, s.concluida_em, m.nome, m.cor FROM sessoes_estudo s JOIN materias m ON m.id = s.materia_id
      WHERE s.usuario_id = ? AND DATE(s.concluida_em) = CURDATE() ORDER BY s.concluida_em DESC'
);
$stmt->execute([$uid]);
$sessoes_hoje = $stmt->fetchAll();
$minutos_hoje = array_sum(array_column($sessoes_hoje, 'minutos'));

// Minutos por matéria na semana
$stmt = $pdo->prepare(
    'SELECT m.nome, m.cor, SUM(s.minutos) AS minutos FROM sessoes_estudo s JOIN materias m ON m.id = s.materia_id
      WHERE s.usuario_id = ? AND YEARWEEK(s.concluida_em, 1) = YEARWEEK(CURDATE(), 1)
      GROUP BY m.id ORDER BY minutos DESC'
);
$stmt->execute([$uid]);
$por_materia = $stmt->fetchAll();
$maior = max(1, ...array_map('intval', array_column($por_materia, 'minutos') ?: [1]));

$titulo_pagina  = 'Pomodoro';
$pagina_ativa   = 'pomodoro';
$scripts_extras = ['pomodoro.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Pomodoro</h1>
        <p>Foco total por alguns minutos, depois uma pausa curta. Cada ciclo concluído conta nas suas horas da semana.</p>
    </div>
</div>

<div class="pomodoro">
    <section class="card timer" id="timer"
             data-bloco="<?= (int) ($bloco['id'] ?? 0) ?>"
             data-bloco-pendente="<?= ($bloco && $bloco['status'] === 'pendente') ? '1' : '0' ?>">
        <?php if ($bloco): ?>
            <span class="selo selo--accent"><?= icone('cronograma', 'icone--xs') ?> Bloco de <?= e($bloco['materia']) ?>, <?= e(hora_curta($bloco['hora_inicio'])) ?> às <?= e(hora_curta($bloco['hora_fim'])) ?></span>
        <?php endif; ?>

        <div class="timer__anel">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="timer__trilha" cx="60" cy="60" r="54" fill="none" stroke-width="6"/>
                <circle class="timer__progresso" id="timer-progresso" cx="60" cy="60" r="54" fill="none" stroke-width="6"
                        stroke-linecap="round" stroke-dasharray="339.292" stroke-dashoffset="0"/>
            </svg>
            <div class="timer__centro">
                <span class="timer__tempo" id="timer-tempo" role="timer" aria-live="off">25:00</span>
                <span class="timer__fase" id="timer-fase">Foco</span>
            </div>
        </div>

        <div class="timer__ciclos" id="timer-ciclos" aria-label="Ciclos de hoje"></div>

        <div class="timer__controles">
            <button type="button" class="btn btn--primario" id="btn-iniciar"><?= icone('play', 'icone--sm') ?> <span>Começar</span></button>
            <button type="button" class="btn btn--secundario" id="btn-reiniciar">Reiniciar</button>
            <button type="button" class="btn btn--contorno" id="btn-pular" title="Pular para a próxima fase">Pular</button>
        </div>

        <div id="area-concluir-bloco" hidden>
            <form method="post" action="<?= e(url('aluno/cronograma.php')) ?>">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="concluir">
                <input type="hidden" name="bloco_id" value="<?= (int) ($bloco['id'] ?? 0) ?>">
                <button class="btn btn--sucesso" type="submit"><?= icone('check', 'icone--sm') ?> Marcar bloco como concluído</button>
            </form>
        </div>
    </section>

    <aside class="pilha">
        <section class="card pilha pilha--sm">
            <h2 class="card__titulo">Configuração</h2>
            <div class="campo">
                <label class="campo__rotulo" for="materia">Matéria</label>
                <select class="select" id="materia" required>
                    <option value="">Escolha antes de começar</option>
                    <?php foreach (materias_lista() as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" data-cor="<?= cor_materia($m['cor']) ?>" <?= (int) $materia_inicial === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="campo">
                    <label class="campo__rotulo" for="min-foco">Foco (min)</label>
                    <input class="input" type="number" id="min-foco" min="5" max="90" value="25">
                </div>
                <div class="campo">
                    <label class="campo__rotulo" for="min-pausa">Pausa (min)</label>
                    <input class="input" type="number" id="min-pausa" min="1" max="30" value="5">
                </div>
            </div>
            <p class="texto-xs texto-secundario">Os tempos ficam salvos neste navegador.</p>
        </section>

        <section class="card">
            <div class="card__cabecalho">
                <h2 class="card__titulo">Hoje</h2>
                <span class="numero texto-accent" id="minutos-hoje" data-minutos="<?= (int) $minutos_hoje ?>"><?= e(formatar_minutos((int) $minutos_hoje)) ?></span>
            </div>
            <ul class="lista" id="lista-sessoes" style="gap: 8px;">
                <?php foreach ($sessoes_hoje as $s): ?>
                    <li class="linha linha--entre texto-pequeno">
                        <span class="linha" style="gap: 8px;"><span class="ponto-cor" style="--cor: <?= e($s['cor']) ?>;"></span><?= e($s['nome']) ?></span>
                        <span class="texto-secundario"><?= (int) $s['minutos'] ?> min, <?= e(date('H:i', strtotime($s['concluida_em']))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!$sessoes_hoje): ?>
                <p class="texto-pequeno texto-secundario" id="sem-sessoes">Nenhum ciclo concluído hoje.</p>
            <?php endif; ?>
        </section>

        <?php if ($por_materia): ?>
            <section class="card pilha pilha--sm">
                <h2 class="card__titulo">Na semana</h2>
                <?php foreach ($por_materia as $p): ?>
                    <div>
                        <div class="linha linha--entre texto-pequeno"><span><?= e($p['nome']) ?></span><span class="texto-secundario numero"><?= e(formatar_minutos((int) $p['minutos'])) ?></span></div>
                        <div style="height: 6px; border-radius: 6px; margin-top: 6px; width: <?= max(4, round($p['minutos'] / $maior * 100)) ?>%; background: <?= e($p['cor']) ?>;"></div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
