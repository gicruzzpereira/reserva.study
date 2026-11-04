<?php
/**
 * Módulo premium: gerar flashcards ou quizzes com IA a partir de um texto.
 * Plano gratuito: IA_LIMITE_GRATUITO gerações por mês para experimentar.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$premium    = eh_premium($usuario);
$restantes  = ia_geracoes_restantes($usuario); // null = ilimitado
$pode_gerar = ia_disponivel() && ($restantes === null || $restantes > 0);

// Premium pode partir do caderno de erros: monta o texto com as questões erradas
$texto_inicial = '';
if (($_GET['origem'] ?? '') === 'caderno') {
    if (!$premium) {
        toast('Gerar a partir do caderno de erros é um recurso Premium.', 'info');
        redirecionar('aluno/planos.php');
    }
    $stmt = $pdo->prepare(
        'SELECT m.nome, q.enunciado, q.tipo, q.resposta_correta, q.gabarito_comentario,
                q.alternativa_a, q.alternativa_b, q.alternativa_c, q.alternativa_d
           FROM caderno_erros c JOIN questoes q ON q.id = c.questao_id JOIN materias m ON m.id = q.materia_id
          WHERE c.usuario_id = ? AND c.superada = 0 ORDER BY c.atualizado_em DESC LIMIT 12'
    );
    $stmt->execute([$uid]);
    $partes = [];
    foreach ($stmt->fetchAll() as $q) {
        $resposta = $q['tipo'] === 'multipla'
            ? $q['alternativa_' . strtolower($q['resposta_correta'])]
            : '';
        $partes[] = "[{$q['nome']}] {$q['enunciado']}\nResposta: {$resposta}\n{$q['gabarito_comentario']}";
    }
    $texto_inicial = implode("\n\n", $partes);
}

$stmt = $pdo->prepare(
    "SELECT g.id, g.tipo, g.titulo, g.criado_em, m.nome AS materia,
            (SELECT COUNT(*) FROM flashcards f WHERE f.geracao_id = g.id) +
            (SELECT COUNT(*) FROM quizzes_ia z WHERE z.geracao_id = g.id) AS itens
       FROM ia_geracoes g LEFT JOIN materias m ON m.id = g.materia_id
      WHERE g.usuario_id = ? ORDER BY g.criado_em DESC LIMIT 20"
);
$stmt->execute([$uid]);
$geracoes = $stmt->fetchAll();

$titulo_pagina  = 'Flashcards e quizzes com IA';
$pagina_ativa   = 'ia';
$scripts_extras = ['ia.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1 class="linha" style="gap: 10px;">Estudar com IA <?= $premium ? '<span class="selo selo--premium">Premium</span>' : '' ?></h1>
        <p class="largura-texto">Cole um resumo ou trecho da apostila e receba flashcards ou um quiz no estilo ENEM.</p>
    </div>
    <span class="contador-ia">
        <?= icone('brilho', 'icone--xs') ?>
        <?= $restantes === null ? 'Gerações ilimitadas' : $restantes . ' de ' . IA_LIMITE_GRATUITO . ' gerações grátis este mês' ?>
    </span>
</div>

<div class="grade-lateral">
    <section class="card">
        <?php if (!ia_disponivel()): ?>
            <div class="aviso" style="margin-bottom: 20px;">
                <?= icone('info', 'icone--sm') ?>
                <div>
                    <strong>A geração com IA ainda não está ligada.</strong>
                    O administrador precisa colocar a chave da API da Anthropic em <code>config/config.php</code>.
                    Enquanto isso, seus flashcards salvos continuam disponíveis ao lado.
                </div>
            </div>
        <?php elseif (!$pode_gerar): ?>
            <div class="aviso" style="margin-bottom: 20px;">
                <?= icone('coroa', 'icone--sm') ?>
                <div>
                    <strong>Suas gerações grátis deste mês acabaram.</strong>
                    Elas voltam no dia 1º. Com o Premium, você gera quantas quiser.
                    <a href="<?= e(url('aluno/planos.php')) ?>">Conhecer o Premium</a>
                </div>
            </div>
        <?php endif; ?>

        <form id="form-ia" class="pilha" data-validar>
            <div class="campo">
                <span class="campo__rotulo">O que você quer gerar?</span>
                <div class="segmento" role="radiogroup" aria-label="Tipo de material">
                    <input type="radio" id="tipo-flash" name="tipo" value="flashcards" checked>
                    <label for="tipo-flash">Flashcards</label>
                    <input type="radio" id="tipo-quiz" name="tipo" value="quiz">
                    <label for="tipo-quiz">Quiz</label>
                </div>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="texto">Texto de estudo</label>
                <textarea class="textarea" id="texto" name="texto" required minlength="80" maxlength="12000" style="min-height: 240px;"
                          placeholder="Ex.: cole aqui seu resumo sobre a Revolução Industrial, as leis de Newton ou funções do 2º grau."
                          <?= $pode_gerar ? '' : 'disabled' ?>><?= e($texto_inicial) ?></textarea>
                <span class="campo__ajuda"><span id="contador-texto">0</span> de 12.000 caracteres (mínimo 80).</span>
                <span class="campo__erro"></span>
            </div>

            <div class="campo">
                <label class="campo__rotulo" for="materia_id">Matéria <span class="texto-secundario">(opcional)</span></label>
                <select class="select" id="materia_id" name="materia_id" <?= $pode_gerar ? '' : 'disabled' ?>>
                    <option value="">Sem matéria</option>
                    <?php foreach (materias_lista() as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="btn btn--primario" type="submit" id="btn-gerar" <?= $pode_gerar ? '' : 'disabled' ?>>
                <?= icone('brilho', 'icone--sm') ?> Gerar com IA
            </button>

            <div class="gerando" id="gerando" role="status">
                <span>Gerando seu material. Isso leva alguns segundos.</span>
                <span class="gerando__barra"></span>
            </div>
        </form>
    </section>

    <aside class="card">
        <div class="card__cabecalho">
            <h2 class="card__titulo">Seus materiais</h2>
            <?php if ($premium): ?>
                <a class="link-ver-todos" href="<?= e(url('aluno/ia.php?origem=caderno')) ?>">Usar caderno de erros</a>
            <?php endif; ?>
        </div>
        <?php if (!$geracoes): ?>
            <p class="texto-pequeno texto-secundario">O que você gerar fica salvo aqui para estudar de novo.</p>
        <?php else: ?>
            <ul class="lista" style="gap: 8px;">
                <?php foreach ($geracoes as $g): ?>
                    <li>
                        <a class="lista-item" href="<?= e(url('aluno/ia_ver.php?id=' . $g['id'])) ?>" style="color: inherit; padding: 12px;">
                            <span class="lista-item__icone" style="width: 38px; height: 38px;"><?= icone($g['tipo'] === 'quiz' ? 'simulado' : 'caderno', 'icone--sm') ?></span>
                            <span class="lista-item__corpo">
                                <span class="lista-item__titulo" style="display: block;"><?= e($g['titulo']) ?></span>
                                <span class="lista-item__meta"><?= $g['tipo'] === 'quiz' ? 'Quiz' : 'Flashcards' ?>, <?= (int) $g['itens'] ?> itens<?= $g['materia'] ? ', ' . e($g['materia']) : '' ?></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
