<?php
/**
 * CRUD de matérias (listagem + formulário de criação/edição na mesma página).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('admin');
$pdo = db();

$icones = icones_materias();

/** Valida os dados do formulário e devolve [dados, erros]. */
function validar_materia(array $entrada, array $icones): array
{
    $dados = [
        'nome'      => trim($entrada['nome'] ?? ''),
        'descricao' => trim($entrada['descricao'] ?? ''),
        'cor'       => strtoupper(trim($entrada['cor'] ?? '')),
        'icone'     => $entrada['icone'] ?? '',
    ];
    $erros = [];
    if (mb_strlen($dados['nome']) < 2 || mb_strlen($dados['nome']) > 80) {
        $erros['nome'] = 'O nome deve ter entre 2 e 80 caracteres.';
    }
    if (mb_strlen($dados['descricao']) > 255) {
        $erros['descricao'] = 'Use no máximo 255 caracteres.';
    }
    if (!preg_match('/^#[0-9A-F]{6}$/', $dados['cor'])) {
        $erros['cor'] = 'Escolha uma cor válida.';
    }
    if (!isset($icones[$dados['icone']])) {
        $erros['icone'] = 'Escolha um ícone da lista.';
    }
    return [$dados, $erros];
}

$editando = null;
$dados = ['nome' => '', 'descricao' => '', 'cor' => '#7EE0C3', 'icone' => 'livro'];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'excluir') {
        $pdo->prepare('DELETE FROM materias WHERE id = ?')->execute([$id]);
        toast('Matéria excluída, junto com as questões dela.');
        redirecionar('admin/materias.php');
    }

    [$dados, $erros] = validar_materia($_POST, $icones);

    if (!$erros) {
        $duplicada = $pdo->prepare('SELECT 1 FROM materias WHERE nome = ? AND id <> ?');
        $duplicada->execute([$dados['nome'], $id]);
        if ($duplicada->fetchColumn()) {
            $erros['nome'] = 'Já existe uma matéria com esse nome.';
        }
    }

    if (!$erros) {
        if ($acao === 'editar' && $id > 0) {
            $pdo->prepare('UPDATE materias SET nome = ?, descricao = ?, cor = ?, icone = ? WHERE id = ?')
                ->execute([$dados['nome'], $dados['descricao'] ?: null, $dados['cor'], $dados['icone'], $id]);
            toast('Matéria atualizada.');
        } else {
            $pdo->prepare('INSERT INTO materias (nome, descricao, cor, icone) VALUES (?, ?, ?, ?)')
                ->execute([$dados['nome'], $dados['descricao'] ?: null, $dados['cor'], $dados['icone']]);
            toast('Matéria criada.');
        }
        redirecionar('admin/materias.php');
    }

    if ($acao === 'editar') {
        $editando = ['id' => $id];
    }
    toast('Confira os campos do formulário.', 'erro');
} elseif (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM materias WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch();
    if (!$editando) {
        toast('Matéria não encontrada.', 'erro');
        redirecionar('admin/materias.php');
    }
    $dados = $editando;
}

$materias = $pdo->query(
    'SELECT m.*, COUNT(q.id) AS questoes FROM materias m LEFT JOIN questoes q ON q.materia_id = m.id
      GROUP BY m.id ORDER BY m.nome'
)->fetchAll();

$erro_campo = fn(string $c) => isset($erros[$c]) ? ' campo--erro' : '';

$titulo_pagina = 'Matérias';
$pagina_ativa  = 'materias';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Matérias</h1>
        <p>A cor e o ícone aparecem nos cards de simulado e no painel dos alunos.</p>
    </div>
</div>

<div class="grade-lateral">
    <section class="card">
        <?php if (!$materias): ?>
            <div class="vazio"><?= icone('materias') ?><p>Nenhuma matéria cadastrada. Crie a primeira ao lado.</p></div>
        <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Matéria</th><th>Questões</th><th><span class="visualmente-oculto">Ações</span></th></tr></thead>
                <tbody>
                <?php foreach ($materias as $m): ?>
                    <tr>
                        <td>
                            <div class="linha" style="flex-wrap: nowrap;">
                                <span class="lista-item__icone" style="--cor: <?= e($m['cor']) ?>; width: 40px; height: 40px;"><?= icone($m['icone'], 'icone--sm') ?></span>
                                <div>
                                    <strong><?= e($m['nome']) ?></strong>
                                    <div class="texto-xs texto-secundario"><?= e($m['descricao'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><a href="<?= e(url('admin/questoes.php?materia=' . $m['id'])) ?>" class="numero"><?= (int) $m['questoes'] ?></a></td>
                        <td>
                            <div class="tabela__acoes">
                                <a class="botao-icone" href="?editar=<?= (int) $m['id'] ?>" title="Editar"><?= icone('editar', 'icone--sm') ?><span class="visualmente-oculto">Editar <?= e($m['nome']) ?></span></a>
                                <form method="post" data-confirmar="Excluir <?= e($m['nome']) ?>?"
                                      data-confirmar-texto="As <?= (int) $m['questoes'] ?> questões desta matéria e o histórico ligado a ela também serão apagados.">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="botao-icone" title="Excluir"><?= icone('lixeira', 'icone--sm') ?><span class="visualmente-oculto">Excluir</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card" id="formulario">
        <div class="card__cabecalho">
            <h2 class="card__titulo"><?= $editando ? 'Editar matéria' : 'Nova matéria' ?></h2>
            <?php if ($editando): ?><a class="link-ver-todos" href="<?= e(url('admin/materias.php')) ?>">Cancelar</a><?php endif; ?>
        </div>

        <form method="post" data-validar class="pilha pilha--sm">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="<?= $editando ? 'editar' : 'criar' ?>">
            <input type="hidden" name="id" value="<?= (int) ($editando['id'] ?? 0) ?>">

            <div class="campo<?= $erro_campo('nome') ?>">
                <label class="campo__rotulo" for="nome">Nome</label>
                <input class="input" id="nome" name="nome" required minlength="2" maxlength="80" value="<?= e($dados['nome']) ?>">
                <span class="campo__erro"><?= e($erros['nome'] ?? '') ?></span>
            </div>

            <div class="campo<?= $erro_campo('descricao') ?>">
                <label class="campo__rotulo" for="descricao">Descrição</label>
                <textarea class="textarea" id="descricao" name="descricao" maxlength="255" style="min-height: 90px;"><?= e($dados['descricao'] ?? '') ?></textarea>
                <span class="campo__erro"><?= e($erros['descricao'] ?? '') ?></span>
            </div>

            <div class="form-grid">
                <div class="campo<?= $erro_campo('cor') ?>">
                    <label class="campo__rotulo" for="cor">Cor</label>
                    <input class="input" type="color" id="cor" name="cor" value="<?= e($dados['cor']) ?>" list="cores-sugeridas" style="padding: 6px; height: 46px; cursor: pointer;">
                    <datalist id="cores-sugeridas">
                        <?php foreach (['#FFD35B', '#A8E8F9', '#B9A4FF', '#5CC48A', '#FF8A65', '#4DA3FF', '#F78FC0', '#7EE0C3', '#D4E157', '#FFB0A0'] as $sugestao): ?>
                            <option value="<?= $sugestao ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <span class="campo__ajuda">Use uma cor clara e diferente das outras matérias.</span>
                    <span class="campo__erro"><?= e($erros['cor'] ?? '') ?></span>
                </div>
                <div class="campo<?= $erro_campo('icone') ?>">
                    <label class="campo__rotulo" for="icone">Ícone</label>
                    <select class="select" id="icone" name="icone" required>
                        <?php foreach ($icones as $chave => $nome): ?>
                            <option value="<?= e($chave) ?>" <?= $dados['icone'] === $chave ? 'selected' : '' ?>><?= e($nome) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="campo__erro"><?= e($erros['icone'] ?? '') ?></span>
                </div>
            </div>

            <button type="submit" class="btn btn--primario btn--bloco mt-4"><?= $editando ? 'Salvar alterações' : 'Criar matéria' ?></button>
        </form>
    </section>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
