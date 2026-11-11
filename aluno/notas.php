<?php
/**
 * Notas lançadas pelos professores para o aluno autenticado.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$stmt = db()->prepare(
    'SELECT n.avaliacao, n.nota, n.comentario, n.data_avaliacao,
            m.nome AS materia, u.nome AS professor
       FROM notas_alunos n
       LEFT JOIN materias m ON m.id = n.materia_id
       JOIN usuarios u ON u.id = n.professor_id
      WHERE n.aluno_id = ?
      ORDER BY n.data_avaliacao DESC, n.id DESC'
);
$stmt->execute([(int) $usuario['id']]);
$notas = $stmt->fetchAll();
$media = $notas ? array_sum(array_column($notas, 'nota')) / count($notas) : null;

$titulo_pagina = 'Minhas notas';
$pagina_ativa = 'notas';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Minhas notas</h1>
        <p>Notas e comentários lançados pelos professores. A autoavaliação de redações aparece no Histórico.</p>
    </div>
</div>

<?php if ($notas): ?>
    <div class="kpis">
        <article class="kpi">
            <div class="kpi__topo"><span>Avaliações</span><?= icone('historico') ?></div>
            <div class="kpi__valor"><?= count($notas) ?></div>
            <div class="kpi__detalhe">Notas lançadas pelos professores</div>
        </article>
        <article class="kpi">
            <div class="kpi__topo"><span>Média simples</span><?= icone('check-circulo') ?></div>
            <div class="kpi__valor"><?= e(number_format((float) $media, 2, ',', '.')) ?></div>
            <div class="kpi__detalhe">Escala de 0 a 1000</div>
        </article>
    </div>
<?php endif; ?>

<section class="card">
    <?php if (!$notas): ?>
        <div class="vazio"><?= icone('historico') ?><p>Você ainda não tem notas lançadas pelos professores.</p></div>
    <?php else: ?>
        <div class="tabela-container"><table class="tabela">
            <thead><tr><th>Data</th><th>Avaliação</th><th>Matéria</th><th class="tabela__numero">Nota</th><th>Comentário</th><th>Professor</th></tr></thead>
            <tbody><?php foreach ($notas as $nota): ?>
                <tr>
                    <td><?= e(data_br($nota['data_avaliacao'])) ?></td>
                    <td><?= e($nota['avaliacao']) ?></td>
                    <td><?= e($nota['materia'] ?? '—') ?></td>
                    <td class="tabela__numero numero"><?= e(number_format((float) $nota['nota'], 2, ',', '.')) ?></td>
                    <td><?= e($nota['comentario'] ?? '') ?></td>
                    <td><?= e($nota['professor']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
