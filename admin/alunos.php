<?php
/**
 * Lista de alunos, resultados acadêmicos e histórico individual.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login(['admin', 'professor']);
$pdo = db();
$aluno_id = (int) ($_GET['aluno'] ?? 0);

if ($aluno_id) {
    $stmt = $pdo->prepare("SELECT id, nome, email, ativo, serie, escola, objetivo FROM usuarios WHERE id = ? AND tipo = 'aluno'");
    $stmt->execute([$aluno_id]);
    $aluno = $stmt->fetch();
    if (!$aluno) {
        toast('Aluno não encontrado.', 'erro');
        redirecionar('admin/alunos.php');
    }

    $stmt = $pdo->prepare(
        'SELECT h.pontuacao, h.total_questoes, h.data_realizacao, h.origem,
                m.nome AS materia, m.cor, m.icone
           FROM historico_simulados h
           LEFT JOIN materias m ON m.id = h.materia_id
          WHERE h.usuario_id = ?
          ORDER BY h.data_realizacao DESC'
    );
    $stmt->execute([$aluno_id]);
    $simulados = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT r.titulo, r.nota, r.status, r.data_tema, r.enviada_em, t.titulo AS tema
           FROM redacoes r
           JOIN temas_redacao t ON t.id = r.tema_id
          WHERE r.usuario_id = ?
          ORDER BY r.data_tema DESC'
    );
    $stmt->execute([$aluno_id]);
    $redacoes = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT n.avaliacao, n.nota, n.comentario, n.data_avaliacao,
                m.nome AS materia, u.nome AS professor
           FROM notas_alunos n
           LEFT JOIN materias m ON m.id = n.materia_id
           JOIN usuarios u ON u.id = n.professor_id
          WHERE n.aluno_id = ?
          ORDER BY n.data_avaliacao DESC, n.id DESC'
    );
    $stmt->execute([$aluno_id]);
    $notas = $stmt->fetchAll();

    $titulo_pagina = 'Resultados do aluno';
    $pagina_ativa = 'alunos';
    require RAIZ_APP . '/includes/layout_inicio.php';
    ?>

    <div class="pagina-cabecalho">
        <div>
            <h1><?= e($aluno['nome']) ?></h1>
            <p><?= e($aluno['email']) ?><?= $aluno['serie'] ? ' · ' . e($aluno['serie']) . 'º ano' : '' ?><?= $aluno['escola'] ? ' · ' . e($aluno['escola']) : '' ?></p>
        </div>
        <div class="form-acoes">
            <a class="btn btn--contorno" href="<?= e(url('admin/alunos.php')) ?>">Voltar aos alunos</a>
            <?php if (in_array($usuario['tipo'], ['admin', 'professor'], true)): ?>
            <a class="btn btn--primario" href="<?= e(url('admin/notas.php?aluno=' . $aluno_id)) ?>"><?= icone('mais', 'icone--sm') ?> Lançar nota</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="card mt-5">
        <div class="card__cabecalho"><h2 class="card__titulo">Notas dos professores</h2></div>
        <?php if (!$notas): ?>
            <div class="vazio"><?= icone('historico') ?><p>Nenhuma nota lançada.</p></div>
        <?php else: ?>
            <div class="tabela-container"><table class="tabela">
                <thead><tr><th>Data</th><th>Avaliação</th><th>Matéria</th><th class="tabela__numero">Nota</th><th>Comentário</th><th>Professor</th></tr></thead>
                <tbody><?php foreach ($notas as $nota): ?>
                    <tr>
                        <td><?= e(data_br($nota['data_avaliacao'])) ?></td>
                        <td><?= e($nota['avaliacao']) ?></td>
                        <td><?= e($nota['materia'] ?? 'Sem matéria') ?></td>
                        <td class="tabela__numero numero"><?= e(number_format((float) $nota['nota'], 2, ',', '.')) ?></td>
                        <td><?= e($nota['comentario'] ?? '') ?></td>
                        <td><?= e($nota['professor']) ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="card mt-5">
        <div class="card__cabecalho"><h2 class="card__titulo">Resultados dos simulados</h2></div>
        <?php if (!$simulados): ?>
            <div class="vazio"><?= icone('simulado') ?><p>Este aluno ainda não realizou simulados.</p></div>
        <?php else: ?>
            <div class="tabela-container"><table class="tabela">
                <thead><tr><th>Data</th><th>Matéria</th><th>Resultado</th><th>Aproveitamento</th></tr></thead>
                <tbody><?php foreach ($simulados as $simulado):
                    $percentual = (int) round((int) $simulado['pontuacao'] / max(1, (int) $simulado['total_questoes']) * 100); ?>
                    <tr>
                        <td><?= e(data_br($simulado['data_realizacao'], true)) ?></td>
                        <td><?= e($simulado['materia'] ?? ($simulado['origem'] === 'geral' ? 'Simulado geral' : 'Caderno de erros')) ?></td>
                        <td><?= (int) $simulado['pontuacao'] ?> / <?= (int) $simulado['total_questoes'] ?></td>
                        <td><?= $percentual ?>%</td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>

    <section class="card mt-5">
        <div class="card__cabecalho"><h2 class="card__titulo">Redações</h2></div>
        <?php if (!$redacoes): ?>
            <div class="vazio"><?= icone('redacao') ?><p>Este aluno ainda não enviou redações.</p></div>
        <?php else: ?>
            <div class="tabela-container"><table class="tabela">
                <thead><tr><th>Data</th><th>Tema</th><th>Status</th><th class="tabela__numero">Nota autoavaliada</th></tr></thead>
                <tbody><?php foreach ($redacoes as $redacao): ?>
                    <tr>
                        <td><?= e(data_br($redacao['enviada_em'] ?: $redacao['data_tema'])) ?></td>
                        <td><?= e($redacao['titulo'] ?: $redacao['tema']) ?></td>
                        <td><?= e(rotulo($redacao['status'])) ?></td>
                        <td class="tabela__numero"><?= $redacao['nota'] !== null ? (int) $redacao['nota'] : '-' ?></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <?php
    require RAIZ_APP . '/includes/layout_fim.php';
    exit;
}

$busca = trim($_GET['busca'] ?? '');
$stmt = $pdo->prepare(
    "SELECT u.id, u.nome, u.email, u.ativo, u.serie,
            (SELECT COUNT(*) FROM historico_simulados h WHERE h.usuario_id = u.id) AS simulados,
            (SELECT COUNT(*) FROM redacoes r WHERE r.usuario_id = u.id AND r.status = 'enviada') AS redacoes,
            (SELECT COUNT(*) FROM notas_alunos n WHERE n.aluno_id = u.id) AS notas
       FROM usuarios u
      WHERE u.tipo = 'aluno' AND (? = '' OR u.nome LIKE ? OR u.email LIKE ?)
      ORDER BY u.ativo DESC, u.nome"
);
$stmt->execute([$busca, "%$busca%", "%$busca%"]);
$alunos = $stmt->fetchAll();

$titulo_pagina = 'Alunos e resultados';
$pagina_ativa = 'alunos';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Alunos e resultados</h1>
        <p>Consulte o desempenho acadêmico dos alunos<?= $usuario['tipo'] === 'admin' ? ' e gerencie os acessos.' : '.' ?></p>
    </div>
    <?php if ($usuario['tipo'] === 'admin'): ?>
    <a class="btn btn--contorno" href="<?= e(url('admin/usuarios.php')) ?>">Gerenciar contas</a>
    <?php endif; ?>
</div>

<section class="card">
    <form class="filtros" method="get" style="margin-bottom: 16px;">
        <div class="campo campo--largo">
            <label class="campo__rotulo" for="busca-alunos">Buscar aluno por nome ou e-mail</label>
            <input class="input" type="search" id="busca-alunos" name="busca" value="<?= e($busca) ?>">
        </div>
        <button class="btn btn--secundario" type="submit"><?= icone('busca', 'icone--sm') ?> Buscar</button>
    </form>

    <?php if (!$alunos): ?>
        <div class="vazio"><?= icone('usuarios') ?><p>Nenhum aluno encontrado.</p></div>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <thead><tr><th>Aluno</th><th>Simulados</th><th>Redações</th><th>Notas</th><th>Conta</th><th><span class="visualmente-oculto">Ações</span></th></tr></thead>
            <tbody><?php foreach ($alunos as $aluno): ?>
                <tr style="<?= $aluno['ativo'] ? '' : 'opacity: 0.55;' ?>">
                    <td>
                        <strong><?= e($aluno['nome']) ?></strong>
                        <div class="texto-xs texto-secundario"><?= e($aluno['email']) ?><?= $aluno['serie'] ? ' · ' . e($aluno['serie']) . 'º ano' : '' ?></div>
                    </td>
                    <td><?= (int) $aluno['simulados'] ?></td>
                    <td><?= (int) $aluno['redacoes'] ?></td>
                    <td><?= (int) $aluno['notas'] ?></td>
                    <td><span class="selo <?= $aluno['ativo'] ? 'selo--sucesso' : 'selo--aviso' ?>"><?= $aluno['ativo'] ? 'Ativa' : 'Desativada' ?></span></td>
                    <td><a class="btn btn--sm btn--contorno" href="<?= e(url('admin/alunos.php?aluno=' . $aluno['id'])) ?>">Ver resultados</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
