<?php
/**
 * Acesso de suporte: consulta somente as solicitações de recuperação de senha.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('suporte');

$pdo = db();
$existe_tabela = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'solicitacoes_senha'"
)->fetchColumn();
$solicitacoes = [];
if ($existe_tabela) {
    $solicitacoes = $pdo->query(
        "SELECT s.email, s.criado_em, u.nome
           FROM solicitacoes_senha s
           LEFT JOIN usuarios u ON u.id = s.usuario_id
          WHERE s.status = 'pendente'
          ORDER BY s.criado_em DESC"
    )->fetchAll();
}

$titulo_pagina = 'Solicitações de acesso';
$pagina_ativa = 'suporte';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Solicitações de acesso</h1>
        <p>Consulte os pedidos de recuperação de senha pendentes.</p>
    </div>
</div>

<div class="aviso" style="margin-bottom: 20px;">
    <?= icone('info', 'icone--sm') ?>
    <span>Esta área é somente para consulta. Senhas são criptografadas e nunca ficam visíveis para o suporte.</span>
</div>

<section class="card">
    <?php if (!$existe_tabela): ?>
        <div class="vazio">
            <?= icone('alerta') ?>
            <p>A tabela de solicitações não está instalada. Aplique a atualização <code>2026-11-04_solicitacoes_senha.sql</code>.</p>
        </div>
    <?php elseif (!$solicitacoes): ?>
        <div class="vazio">
            <?= icone('check-circulo') ?>
            <p>Não há solicitações de recuperação pendentes.</p>
        </div>
    <?php else: ?>
        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Solicitante</th><th>E-mail</th><th>Data do pedido</th></tr></thead>
                <tbody>
                <?php foreach ($solicitacoes as $solicitacao): ?>
                    <tr>
                        <td><?= e($solicitacao['nome'] ?: 'Conta não identificada') ?></td>
                        <td><?= e($solicitacao['email']) ?></td>
                        <td class="texto-secundario"><?= e(data_br($solicitacao['criado_em'], true)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
