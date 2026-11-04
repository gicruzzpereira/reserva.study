<?php
/**
 * Estudo de um material gerado pela IA: flashcards (virar o card) ou quiz.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT g.*, m.nome AS materia FROM ia_geracoes g LEFT JOIN materias m ON m.id = g.materia_id WHERE g.id = ? AND g.usuario_id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0), $uid]);
$geracao = $stmt->fetch();
if (!$geracao) {
    toast('Material não encontrado.', 'erro');
    redirecionar('aluno/ia.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    exigir_csrf();
    $pdo->prepare('DELETE FROM ia_geracoes WHERE id = ? AND usuario_id = ?')->execute([$geracao['id'], $uid]);
    toast('Material excluído.');
    redirecionar('aluno/ia.php');
}

if ($geracao['tipo'] === 'flashcards') {
    $stmt = $pdo->prepare('SELECT id, frente, verso, status FROM flashcards WHERE geracao_id = ? ORDER BY id');
} else {
    $stmt = $pdo->prepare('SELECT pergunta, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, explicacao FROM quizzes_ia WHERE geracao_id = ? ORDER BY id');
}
$stmt->execute([$geracao['id']]);
$itens = $stmt->fetchAll();

$titulo_pagina  = $geracao['titulo'];
$pagina_ativa   = 'ia';
$scripts_extras = [$geracao['tipo'] === 'flashcards' ? 'flashcards.js' : 'quiz_ia.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <p class="texto-pequeno"><a href="<?= e(url('aluno/ia.php')) ?>">Estudar com IA</a></p>
        <h1><?= e($geracao['titulo']) ?></h1>
        <p><?= $geracao['tipo'] === 'quiz' ? 'Quiz' : 'Flashcards' ?> com <?= count($itens) ?> itens<?= $geracao['materia'] ? ', ' . e($geracao['materia']) : '' ?>. Gerado em <?= e(data_br($geracao['criado_em'])) ?>.</p>
    </div>
    <form method="post" data-confirmar="Excluir este material?" data-confirmar-texto="Os itens gerados serão apagados.">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="excluir">
        <button class="btn btn--contorno btn--sm" type="submit"><?= icone('lixeira', 'icone--xs') ?> Excluir</button>
    </form>
</div>

<?php if ($geracao['tipo'] === 'flashcards'): ?>
    <div class="flashcard-area" id="flashcards">
        <div class="flashcard-progresso">
            <span id="fc-posicao">1 de <?= count($itens) ?></span>
            <span><span class="texto-sucesso" id="fc-sabia">0</span> sabia, <span class="texto-erro" id="fc-nao">0</span> não sabia</span>
        </div>
        <div class="progresso" style="margin-bottom: 16px;"><div class="progresso__barra" id="fc-barra"></div></div>

        <div class="flashcard" id="fc-card" tabindex="0" role="button" aria-label="Virar o card">
            <div class="flashcard__interno">
                <div class="flashcard__face">
                    <span class="flashcard__rotulo">Pergunta</span>
                    <p id="fc-frente"></p>
                    <span class="flashcard__dica">Clique ou aperte espaço para ver a resposta</span>
                </div>
                <div class="flashcard__face flashcard__face--verso">
                    <span class="flashcard__rotulo">Resposta</span>
                    <p id="fc-verso"></p>
                </div>
            </div>
        </div>

        <div class="flashcard-acoes" id="fc-acoes">
            <button class="btn btn--nao-sabia" type="button" data-resposta="nao_sabia"><?= icone('fechar', 'icone--sm') ?> Não sabia</button>
            <button class="btn btn--sabia" type="button" data-resposta="sabia"><?= icone('check', 'icone--sm') ?> Sabia</button>
        </div>
        <p class="texto-xs texto-secundario" style="text-align: center; margin-top: 12px;">Os que você marcar como "Não sabia" entram nas suas revisões de amanhã.</p>

        <div class="card vazio" id="fc-fim" hidden>
            <?= icone('check-circulo') ?>
            <p id="fc-resumo"></p>
            <div class="linha" style="justify-content: center;">
                <button class="btn btn--secundario" type="button" id="fc-recomecar">Estudar de novo</button>
                <a class="btn btn--primario" href="<?= e(url('aluno/ia.php')) ?>">Gerar outro</a>
            </div>
        </div>
    </div>
    <script type="application/json" id="dados-flashcards"><?= json_encode($itens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php else: ?>
    <div class="simulado" id="quiz">
        <div class="simulado__topo">
            <div class="progresso"><div class="progresso__barra" id="quiz-barra"></div></div>
            <span class="simulado__contador" id="quiz-contador"></span>
        </div>
        <article class="card" id="quiz-area" aria-live="polite"></article>
        <div class="simulado__acoes" id="quiz-acoes"></div>
    </div>
    <script type="application/json" id="dados-quiz"><?= json_encode($itens, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
