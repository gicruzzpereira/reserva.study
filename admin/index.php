<?php
/**
 * Painel administrativo: visão geral do sistema.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('admin');

$pdo = db();
$totais = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno') AS alunos,
        (SELECT COUNT(*) FROM usuarios WHERE tipo = 'aluno' AND plano = 'premium') AS premium,
        (SELECT COUNT(*) FROM materias) AS materias,
        (SELECT COUNT(*) FROM questoes) AS questoes,
        (SELECT COUNT(*) FROM historico_simulados WHERE data_realizacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS simulados_semana"
)->fetch();

// Questões por matéria (para ver onde o banco está mais fraco)
$por_materia = $pdo->query(
    "SELECT m.nome, m.cor, m.icone, COUNT(q.id) AS total,
            SUM(q.tipo = 'multipla') AS multipla, SUM(q.tipo = 'dissertativa') AS dissertativa
       FROM materias m LEFT JOIN questoes q ON q.materia_id = m.id
      GROUP BY m.id ORDER BY total ASC, m.nome"
)->fetchAll();

$ultimos = $pdo->query(
    "SELECT h.pontuacao, h.total_questoes, h.data_realizacao, u.nome AS aluno, m.nome AS materia, m.cor, m.icone
       FROM historico_simulados h
       JOIN usuarios u ON u.id = h.usuario_id
       LEFT JOIN materias m ON m.id = h.materia_id
      ORDER BY h.data_realizacao DESC LIMIT 8"
)->fetchAll();

$solicitacoes_senha = [];
$existe_tabela_senha = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'solicitacoes_senha'")->fetchColumn();
if ($existe_tabela_senha) {
    $solicitacoes_senha = $pdo->query(
        "SELECT s.id, s.email, s.criado_em, u.nome AS aluno
           FROM solicitacoes_senha s LEFT JOIN usuarios u ON u.id = s.usuario_id
          WHERE s.status = 'pendente' ORDER BY s.criado_em DESC LIMIT 8"
    )->fetchAll();
}

$titulo_pagina = 'Painel administrativo';
$pagina_ativa  = 'admin-painel';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Olá, <?= e(primeiro_nome($usuario['nome'])) ?>.</h1>
        <p>Resumo do banco de questões e da atividade dos alunos.</p>
    </div>
    <a class="btn btn--primario" href="<?= e(url('admin/questao_form.php')) ?>"><?= icone('mais', 'icone--sm') ?> Nova questão</a>
</div>

<div class="kpis">
    <a class="kpi" href="<?= e(url('admin/usuarios.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Alunos</span><?= icone('usuarios') ?></div>
        <div class="kpi__valor"><?= (int) $totais['alunos'] ?></div>
        <div class="kpi__detalhe"><?= (int) $totais['premium'] ?> no plano Premium</div>
    </a>
    <a class="kpi" href="<?= e(url('admin/questoes.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Questões</span><?= icone('questoes') ?></div>
        <div class="kpi__valor"><?= (int) $totais['questoes'] ?></div>
        <div class="kpi__detalhe">Cadastradas no banco</div>
    </a>
    <a class="kpi" href="<?= e(url('admin/materias.php')) ?>" style="color: inherit;">
        <div class="kpi__topo"><span>Matérias</span><?= icone('materias') ?></div>
        <div class="kpi__valor"><?= (int) $totais['materias'] ?></div>
        <div class="kpi__detalhe">Cada uma com sua cor</div>
    </a>
    <article class="kpi">
        <div class="kpi__topo"><span>Simulados</span><?= icone('simulado') ?></div>
        <div class="kpi__valor"><?= (int) $totais['simulados_semana'] ?></div>
        <div class="kpi__detalhe">Feitos nos últimos 7 dias</div>
    </article>
</div>

<?php if ($solicitacoes_senha): ?>
<div class="aviso aviso--aviso" style="margin-bottom: 20px;">
    <?= icone('alerta', 'icone--sm') ?>
    <span><b><?= count($solicitacoes_senha) ?> solicitação(ões) de recuperação de senha.</b> Lembre aos alunos que nenhuma senha deve ser compartilhada; as senhas são criptografadas.</span>
</div>
<div class="card" style="margin-bottom: 20px;">
    <div class="card__cabecalho"><h2 class="card__titulo">Alertas de recuperação de senha</h2></div>
    <ul class="lista">
        <?php foreach ($solicitacoes_senha as $s): ?>
            <li class="linha linha--entre texto-pequeno">
                <span><b><?= e($s['aluno'] ?: $s['email']) ?></b><br><span class="texto-secundario">Solicitou recuperação em <?= e(data_br($s['criado_em'], true)) ?></span></span>
                <span class="selo selo--aviso">Pendente</span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="grade-lateral">
    <section class="card">
        <div class="card__cabecalho">
            <h2 class="card__titulo">Simulados recentes</h2>
        </div>
        <?php if (!$ultimos): ?>
            <div class="vazio"><?= icone('simulado') ?><p>Nenhum simulado feito ainda.</p></div>
        <?php else: ?>
            <div class="tabela-container">
                <table class="tabela">
                    <thead><tr><th>Aluno</th><th>Matéria</th><th>Acertos</th><th>Data</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimos as $s): $pct = round($s['pontuacao'] / max(1, $s['total_questoes']) * 100); ?>
                        <tr>
                            <td><?= e($s['aluno']) ?></td>
                            <td><?= chip_materia($s['materia'] ?? 'Refazendo erros', $s['cor'], $s['icone'] ?? null) ?></td>
                            <td><span class="selo <?= $pct >= 70 ? 'selo--sucesso' : ($pct >= 50 ? 'selo--aviso' : 'selo--erro') ?>"><?= (int) $s['pontuacao'] ?>/<?= (int) $s['total_questoes'] ?></span></td>
                            <td class="texto-secundario"><?= e(data_br($s['data_realizacao'], true)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card__cabecalho">
            <h2 class="card__titulo">Questões por matéria</h2>
        </div>
        <ul class="lista">
            <?php foreach ($por_materia as $m): ?>
                <li class="linha linha--entre texto-pequeno">
                    <?= chip_materia($m['nome'], $m['cor'], $m['icone']) ?>
                    <span class="texto-secundario"><?= (int) $m['multipla'] ?> obj. / <?= (int) $m['dissertativa'] ?> diss.
                        <b class="numero" style="color: var(--text-primary); margin-left: 6px;"><?= (int) $m['total'] ?></b></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
