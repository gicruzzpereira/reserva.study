<?php
/**
 * Criação e edição de questões.
 * Os campos de alternativas aparecem ou somem conforme o tipo (JS em questao_form.js).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login(['admin', 'professor']);
$pdo = db();
$materiaProfessor = materia_do_professor($usuario);
if ($usuario['tipo'] === 'professor' && !$materiaProfessor) {
    http_response_code(403);
    exit('Este perfil de professor ainda não está vinculado a uma matéria.');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$dados = [
    'materia_id' => (int) ($_GET['materia'] ?? 0), 'tipo' => 'multipla', 'enunciado' => '',
    'alternativa_a' => '', 'alternativa_b' => '', 'alternativa_c' => '', 'alternativa_d' => '',
    'resposta_correta' => '', 'gabarito_comentario' => '', 'dificuldade' => 'medio',
];
$erros = [];

if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM questoes WHERE id = ?');
    $stmt->execute([$id]);
    $encontrada = $stmt->fetch();
    if (!$encontrada) {
        toast('Questão não encontrada.', 'erro');
        redirecionar('admin/questoes.php');
    }
    exigir_materia_do_professor($usuario, (int) $encontrada['materia_id']);
    $dados = array_merge($dados, array_map(fn($v) => $v ?? '', $encontrada));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    foreach ($dados as $campo => $_) {
        $dados[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    }
    $dados['materia_id'] = (int) $dados['materia_id'];

    exigir_materia_do_professor($usuario, $dados['materia_id']);

    if ($id) {
        $stmt = $pdo->prepare('SELECT materia_id FROM questoes WHERE id = ?');
        $stmt->execute([$id]);
        $materiaAtual = $stmt->fetchColumn();
        if ($materiaAtual === false) {
            http_response_code(404);
            exit('Questão não encontrada.');
        }
        exigir_materia_do_professor($usuario, (int) $materiaAtual);
    }

    // Validação no servidor
    if (!materia_por_id($dados['materia_id'])) {
        $erros['materia_id'] = 'Escolha a matéria.';
    }
    if (!in_array($dados['tipo'], ['multipla', 'dissertativa'], true)) {
        $erros['tipo'] = 'Tipo inválido.';
    }
    if (mb_strlen($dados['enunciado']) < 10) {
        $erros['enunciado'] = 'Escreva o enunciado (mínimo de 10 caracteres).';
    }
    if (!in_array($dados['dificuldade'], ['facil', 'medio', 'dificil'], true)) {
        $erros['dificuldade'] = 'Escolha o nível.';
    }

    if ($dados['tipo'] === 'multipla') {
        foreach (['a', 'b', 'c', 'd'] as $letra) {
            if ($dados['alternativa_' . $letra] === '') {
                $erros['alternativa_' . $letra] = 'Preencha a alternativa ' . strtoupper($letra) . '.';
            }
        }
        if (!in_array($dados['resposta_correta'], ['A', 'B', 'C', 'D'], true)) {
            $erros['resposta_correta'] = 'Marque qual alternativa é a correta.';
        }
    } else {
        // Dissertativa: sem alternativas, gabarito obrigatório
        foreach (['a', 'b', 'c', 'd'] as $letra) {
            $dados['alternativa_' . $letra] = '';
        }
        $dados['resposta_correta'] = '';
        if (mb_strlen($dados['gabarito_comentario']) < 5) {
            $erros['gabarito_comentario'] = 'Questões dissertativas precisam de gabarito.';
        }
    }

    if (!$erros) {
        $valores = [
            $dados['materia_id'], $dados['tipo'], $dados['enunciado'],
            $dados['alternativa_a'] ?: null, $dados['alternativa_b'] ?: null,
            $dados['alternativa_c'] ?: null, $dados['alternativa_d'] ?: null,
            $dados['resposta_correta'] ?: null, $dados['gabarito_comentario'] ?: null, $dados['dificuldade'],
        ];
        if ($id) {
            $pdo->prepare(
                'UPDATE questoes SET materia_id = ?, tipo = ?, enunciado = ?, alternativa_a = ?, alternativa_b = ?,
                        alternativa_c = ?, alternativa_d = ?, resposta_correta = ?, gabarito_comentario = ?, dificuldade = ?
                  WHERE id = ?' . ($materiaProfessor ? ' AND materia_id = ?' : '')
            )->execute([...$valores, $id, ...($materiaProfessor ? [(int) $materiaProfessor['id']] : [])]);
            toast('Questão atualizada.');
        } else {
            $pdo->prepare(
                'INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c,
                        alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute($valores);
            toast('Questão criada.');
        }
        redirecionar('admin/questoes.php?materia=' . $dados['materia_id']);
    }
    toast('Confira os campos destacados.', 'erro');
}

$erro_campo = fn(string $c) => isset($erros[$c]) ? ' campo--erro' : '';

$titulo_pagina  = $id ? 'Editar questão' : 'Nova questão';
$pagina_ativa   = 'questoes';
$scripts_extras = ['questao_form.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1><?= $id ? 'Editar questão' : 'Nova questão' ?></h1>
        <p>Questões objetivas têm 4 alternativas. Dissertativas são corrigidas pelo próprio aluno com base no gabarito.</p>
    </div>
    <a class="btn btn--contorno" href="<?= e(url('admin/questoes.php')) ?>">Voltar</a>
</div>

<form method="post" data-validar class="card" style="max-width: 880px;" id="form-questao">
    <?= csrf_campo() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="form-grid">
        <div class="campo<?= $erro_campo('materia_id') ?>">
            <label class="campo__rotulo" for="materia_id">Matéria</label>
            <select class="select" id="materia_id" name="materia_id" required>
                <?php if (!$materiaProfessor): ?><option value="">Selecione</option><?php endif; ?>
                <?php foreach (($materiaProfessor ? [$materiaProfessor] : materias_lista()) as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (int) $dados['materia_id'] === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="campo__erro"><?= e($erros['materia_id'] ?? '') ?></span>
        </div>

        <div class="campo<?= $erro_campo('dificuldade') ?>">
            <span class="campo__rotulo">Nível</span>
            <div class="segmento" role="radiogroup" aria-label="Nível de dificuldade">
                <?php foreach (['facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil'] as $valor => $nome): ?>
                    <input type="radio" id="dif-<?= $valor ?>" name="dificuldade" value="<?= $valor ?>" <?= $dados['dificuldade'] === $valor ? 'checked' : '' ?>>
                    <label for="dif-<?= $valor ?>"><?= $nome ?></label>
                <?php endforeach; ?>
            </div>
            <span class="campo__erro"><?= e($erros['dificuldade'] ?? '') ?></span>
        </div>

        <div class="campo campo--inteiro">
            <span class="campo__rotulo">Tipo</span>
            <div class="segmento" role="radiogroup" aria-label="Tipo de questão">
                <input type="radio" id="tipo-multipla" name="tipo" value="multipla" <?= $dados['tipo'] === 'multipla' ? 'checked' : '' ?>>
                <label for="tipo-multipla">Múltipla escolha</label>
                <input type="radio" id="tipo-dissertativa" name="tipo" value="dissertativa" <?= $dados['tipo'] === 'dissertativa' ? 'checked' : '' ?>>
                <label for="tipo-dissertativa">Dissertativa</label>
            </div>
        </div>

        <div class="campo campo--inteiro<?= $erro_campo('enunciado') ?>">
            <label class="campo__rotulo" for="enunciado">Enunciado</label>
            <textarea class="textarea" id="enunciado" name="enunciado" required minlength="10"><?= e($dados['enunciado']) ?></textarea>
            <span class="campo__erro"><?= e($erros['enunciado'] ?? '') ?></span>
        </div>
    </div>

    <!-- Alternativas: só para múltipla escolha -->
    <fieldset id="bloco-alternativas" style="border: 0; padding: 0; margin: 24px 0 0;" <?= $dados['tipo'] === 'dissertativa' ? 'hidden' : '' ?>>
        <legend class="campo__rotulo" style="margin-bottom: 12px;">Alternativas <span class="texto-secundario texto-xs">(marque a correta)</span></legend>
        <div class="pilha pilha--sm">
            <?php foreach (['A', 'B', 'C', 'D'] as $letra): $campo = 'alternativa_' . strtolower($letra); ?>
                <div class="campo<?= $erro_campo($campo) ?>">
                    <div class="linha" style="flex-wrap: nowrap; align-items: center;">
                        <label class="alternativa__letra" style="cursor: pointer;" title="Marcar <?= $letra ?> como correta">
                            <input type="radio" name="resposta_correta" value="<?= $letra ?>" class="visualmente-oculto" data-alternativa-correta
                                   <?= $dados['resposta_correta'] === $letra ? 'checked' : '' ?>>
                            <?= $letra ?>
                        </label>
                        <input class="input" name="<?= $campo ?>" aria-label="Alternativa <?= $letra ?>" required data-so-multipla
                               value="<?= e($dados[$campo]) ?>">
                    </div>
                    <span class="campo__erro"><?= e($erros[$campo] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (isset($erros['resposta_correta'])): ?>
                <p class="texto-erro texto-xs"><?= e($erros['resposta_correta']) ?></p>
            <?php endif; ?>
        </div>
    </fieldset>

    <div class="campo mt-5<?= $erro_campo('gabarito_comentario') ?>">
        <label class="campo__rotulo" for="gabarito_comentario" id="rotulo-gabarito">
            <?= $dados['tipo'] === 'dissertativa' ? 'Gabarito' : 'Comentário explicativo (opcional)' ?>
        </label>
        <textarea class="textarea" id="gabarito_comentario" name="gabarito_comentario"
                  <?= $dados['tipo'] === 'dissertativa' ? 'required minlength="5"' : '' ?>><?= e($dados['gabarito_comentario']) ?></textarea>
        <span class="campo__ajuda">Aparece para o aluno depois que ele responde.</span>
        <span class="campo__erro"><?= e($erros['gabarito_comentario'] ?? '') ?></span>
    </div>

    <div class="form-acoes">
        <a class="btn btn--secundario" href="<?= e(url('admin/questoes.php')) ?>">Cancelar</a>
        <button type="submit" class="btn btn--primario"><?= $id ? 'Salvar alterações' : 'Criar questão' ?></button>
    </div>
</form>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
