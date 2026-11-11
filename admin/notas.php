<?php
/**
 * Lançamento e consulta de notas manuais (0 a 1000).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login(['admin', 'professor']);
$pdo = db();
$materiaProfessor = materia_do_professor($usuario);
if ($usuario['tipo'] === 'professor' && !$materiaProfessor) {
    http_response_code(403);
    exit('Este perfil de professor ainda nao esta vinculado a uma materia.');
}
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$dados = [
    'aluno_id' => (int) ($_GET['aluno'] ?? 0),
    'materia_id' => $materiaProfessor ? (int) $materiaProfessor['id'] : '',
    'avaliacao' => '',
    'nota' => '',
    'comentario' => '',
    'data_avaliacao' => date('Y-m-d'),
];
$erros = [];

if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM notas_alunos WHERE id = ?');
    $stmt->execute([$id]);
    $encontrada = $stmt->fetch();
    if (!$encontrada) {
        toast('Nota não encontrada.', 'erro');
        redirecionar('admin/notas.php');
    }
    if ($usuario['tipo'] === 'professor' && (int) $encontrada['professor_id'] !== (int) $usuario['id']) {
        http_response_code(403);
        exit('Voce so pode editar as notas lancadas por seu perfil.');
    }
    $dados = array_merge($dados, $encontrada);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'excluir') {
        $stmt = $pdo->prepare('SELECT professor_id FROM notas_alunos WHERE id = ?');
        $stmt->execute([$id]);
        $professorNota = $stmt->fetchColumn();
        if ($professorNota === false) {
            toast('Nota não encontrada.', 'erro');
            redirecionar('admin/notas.php');
        }
        if ($usuario['tipo'] === 'professor' && (int) $professorNota !== (int) $usuario['id']) {
            http_response_code(403);
            exit('Voce so pode excluir as notas lancadas por seu perfil.');
        }
        $pdo->prepare('DELETE FROM notas_alunos WHERE id = ?')->execute([$id]);
        toast('Nota excluída.');
        redirecionar('admin/notas.php');
    }
    if ($acao !== 'salvar') {
        toast('Ação inválida.', 'erro');
        redirecionar('admin/notas.php');
    }

    foreach (['avaliacao', 'nota', 'comentario', 'data_avaliacao', 'aluno_id', 'materia_id'] as $campo) {
        $valor = $_POST[$campo] ?? '';
        $dados[$campo] = is_string($valor) ? trim($valor) : '';
    }
    if ($materiaProfessor) {
        $dados['materia_id'] = (string) $materiaProfessor['id'];
    }
    $aluno_id = filter_var($dados['aluno_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($aluno_id === false) {
        $erros['aluno_id'] = 'Escolha um aluno.';
    } else {
        $dados['aluno_id'] = $aluno_id;
        $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE id = ? AND tipo = 'aluno'");
        $stmt->execute([$dados['aluno_id']]);
        if (!$stmt->fetchColumn()) {
            $erros['aluno_id'] = 'Aluno inválido.';
        }
    }

    if (mb_strlen($dados['avaliacao']) < 2 || mb_strlen($dados['avaliacao']) > 150) {
        $erros['avaliacao'] = 'Informe o nome da avaliação (2 a 150 caracteres).';
    }

    $nota_normalizada = str_replace(',', '.', $dados['nota']);
    if (!is_numeric($nota_normalizada) || (float) $nota_normalizada < 0 || (float) $nota_normalizada > 1000) {
        $erros['nota'] = 'Informe uma nota entre 0 e 1000.';
    }

    if (mb_strlen($dados['comentario']) > 5000) {
        $erros['comentario'] = 'Use no máximo 5000 caracteres.';
    }

    $data_valida = DateTime::createFromFormat('!Y-m-d', $dados['data_avaliacao']);
    if (!$data_valida || $data_valida->format('Y-m-d') !== $dados['data_avaliacao']) {
        $erros['data_avaliacao'] = 'Informe uma data válida.';
    }

    if ($dados['materia_id'] !== '') {
        $materia_id = filter_var($dados['materia_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($materia_id === false || !materia_por_id($materia_id)) {
            $erros['materia_id'] = 'Escolha uma matéria válida.';
        } else {
            $dados['materia_id'] = $materia_id;
        }
    } else {
        $dados['materia_id'] = null;
    }

    if ($id) {
        $stmt = $pdo->prepare('SELECT professor_id FROM notas_alunos WHERE id = ?');
        $stmt->execute([$id]);
        $professorNota = $stmt->fetchColumn();
        if ($professorNota === false) {
            toast('Nota não encontrada.', 'erro');
            redirecionar('admin/notas.php');
        }
        if ($usuario['tipo'] === 'professor' && (int) $professorNota !== (int) $usuario['id']) {
            http_response_code(403);
            exit('Voce so pode editar as notas lancadas por seu perfil.');
        }
    }

    if (!$erros) {
        $valores = [
            $dados['aluno_id'],
            $dados['materia_id'],
            $dados['avaliacao'],
            number_format((float) $nota_normalizada, 2, '.', ''),
            $dados['comentario'] ?: null,
            $dados['data_avaliacao'],
        ];
        if ($id) {
            $pdo->prepare(
                'UPDATE notas_alunos
                    SET aluno_id = ?, materia_id = ?, avaliacao = ?, nota = ?, comentario = ?,
                        data_avaliacao = ?, atualizado_por = ?
                  WHERE id = ?'
            )->execute([...$valores, (int) $usuario['id'], $id]);
            toast('Nota atualizada.');
        } else {
            $pdo->prepare(
                'INSERT INTO notas_alunos
                    (aluno_id, materia_id, professor_id, avaliacao, nota, comentario, data_avaliacao)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$valores[0], $valores[1], (int) $usuario['id'], ...array_slice($valores, 2)]);
            toast('Nota lançada.');
        }
        redirecionar('admin/notas.php');
    }
    toast('Confira os campos destacados.', 'erro');
}

$alunos = $pdo->query("SELECT id, nome, email, ativo FROM usuarios WHERE tipo = 'aluno' ORDER BY nome")->fetchAll();
$materias = $materiaProfessor ? [$materiaProfessor] : materias_lista();
$sqlNotas =
    'SELECT n.id, n.avaliacao, n.nota, n.data_avaliacao, n.aluno_id,
            a.nome AS aluno, m.nome AS materia, p.nome AS professor
       FROM notas_alunos n
       JOIN usuarios a ON a.id = n.aluno_id
       LEFT JOIN materias m ON m.id = n.materia_id
       JOIN usuarios p ON p.id = n.professor_id
      ' . ($materiaProfessor ? 'WHERE n.professor_id = ? ' : '') . 'ORDER BY n.data_avaliacao DESC, n.id DESC';
$stmtNotas = $pdo->prepare($sqlNotas);
if ($materiaProfessor) {
    $stmtNotas->execute([(int) $usuario['id']]);
} else {
    $stmtNotas->execute();
}
$notas = $stmtNotas->fetchAll();

$erro_campo = fn(string $campo) => isset($erros[$campo]) ? ' campo--erro' : '';
$titulo_pagina = 'Notas';
$pagina_ativa = 'notas';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Notas</h1>
        <p>Lance avaliações na escala de 0 a 1000. O aluno poderá consultar as notas em sua conta.</p>
    </div>
</div>

<div class="grade-lateral">
    <section class="card">
        <div class="card__cabecalho"><h2 class="card__titulo"><?= $id ? 'Editar nota' : 'Lançar nota' ?></h2></div>
        <form method="post" data-validar class="pilha pilha--sm">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="acao" value="salvar">

            <div class="campo<?= $erro_campo('aluno_id') ?>">
                <label class="campo__rotulo" for="aluno_id">Aluno</label>
                <select class="select" id="aluno_id" name="aluno_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($alunos as $aluno): ?>
                        <option value="<?= (int) $aluno['id'] ?>" <?= (int) $dados['aluno_id'] === (int) $aluno['id'] ? 'selected' : '' ?>>
                            <?= e($aluno['nome']) ?> · <?= e($aluno['email']) ?><?= !$aluno['ativo'] ? ' (conta desativada)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="campo__erro"><?= e($erros['aluno_id'] ?? '') ?></span>
            </div>

            <div class="campo<?= $erro_campo('avaliacao') ?>">
                <label class="campo__rotulo" for="avaliacao">Avaliação</label>
                <input class="input" id="avaliacao" name="avaliacao" required minlength="2" maxlength="150" value="<?= e($dados['avaliacao']) ?>" placeholder="Ex.: Prova de Matemática">
                <span class="campo__erro"><?= e($erros['avaliacao'] ?? '') ?></span>
            </div>

            <div class="form-grid">
                <div class="campo<?= $erro_campo('materia_id') ?>">
                    <label class="campo__rotulo" for="materia_id">Matéria (opcional)</label>
                    <select class="select" id="materia_id" name="materia_id">
                        <?php if (!$materiaProfessor): ?><option value="">Sem matéria</option><?php endif; ?>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= (int) $materia['id'] ?>" <?= (int) $dados['materia_id'] === (int) $materia['id'] && $dados['materia_id'] !== '' ? 'selected' : '' ?>><?= e($materia['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="campo__erro"><?= e($erros['materia_id'] ?? '') ?></span>
                </div>
                <div class="campo<?= $erro_campo('nota') ?>">
                    <label class="campo__rotulo" for="nota">Nota (0 a 1000)</label>
                    <input class="input" type="number" id="nota" name="nota" required min="0" max="1000" step="0.01" value="<?= e($dados['nota']) ?>">
                    <span class="campo__erro"><?= e($erros['nota'] ?? '') ?></span>
                </div>
            </div>

            <div class="form-grid">
                <div class="campo<?= $erro_campo('data_avaliacao') ?>">
                    <label class="campo__rotulo" for="data_avaliacao">Data da avaliação</label>
                    <input class="input" type="date" id="data_avaliacao" name="data_avaliacao" required value="<?= e($dados['data_avaliacao']) ?>">
                    <span class="campo__erro"><?= e($erros['data_avaliacao'] ?? '') ?></span>
                </div>
            </div>

            <div class="campo<?= $erro_campo('comentario') ?>">
                <label class="campo__rotulo" for="comentario">Comentário (opcional)</label>
                <textarea class="textarea" id="comentario" name="comentario" maxlength="5000"><?= e($dados['comentario']) ?></textarea>
                <span class="campo__erro"><?= e($erros['comentario'] ?? '') ?></span>
            </div>

            <button class="btn btn--primario btn--bloco" type="submit"><?= $id ? 'Salvar alterações' : 'Lançar nota' ?></button>
            <?php if ($id): ?><a class="btn btn--contorno btn--bloco" href="<?= e(url('admin/notas.php')) ?>">Cancelar</a><?php endif; ?>
        </form>
    </section>

    <section class="card">
        <div class="card__cabecalho"><h2 class="card__titulo">Notas lançadas</h2></div>
        <?php if (!$notas): ?>
            <div class="vazio"><?= icone('historico') ?><p>Nenhuma nota lançada ainda.</p></div>
        <?php else: ?>
            <div class="tabela-container"><table class="tabela">
                <thead><tr><th>Aluno</th><th>Avaliação</th><th>Matéria</th><th>Data</th><th class="tabela__numero">Nota</th><th>Ações</th></tr></thead>
                <tbody><?php foreach ($notas as $nota): ?>
                    <tr>
                        <td><a href="<?= e(url('admin/alunos.php?aluno=' . $nota['aluno_id'])) ?>"><?= e($nota['aluno']) ?></a></td>
                        <td><?= e($nota['avaliacao']) ?></td>
                        <td><?= e($nota['materia'] ?? '—') ?></td>
                        <td><?= e(data_br($nota['data_avaliacao'])) ?></td>
                        <td class="tabela__numero numero"><?= e(number_format((float) $nota['nota'], 2, ',', '.')) ?></td>
                        <td>
                            <div class="tabela__acoes">
                                <a class="botao-icone" href="<?= e(url('admin/notas.php?id=' . $nota['id'])) ?>" title="Editar"><?= icone('editar', 'icone--sm') ?><span class="visualmente-oculto">Editar</span></a>
                                <form method="post" data-confirmar="Excluir esta nota?" data-confirmar-texto="O aluno não verá mais esta avaliação.">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $nota['id'] ?>">
                                    <button class="botao-icone" type="submit" title="Excluir"><?= icone('lixeira', 'icone--sm') ?><span class="visualmente-oculto">Excluir</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
