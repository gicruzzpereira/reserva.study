<?php
/**
 * Gerenciamento de usuários: administradores gerenciam a equipe e todos os
 * usuários; professores gerenciam apenas contas de alunos.
 * O plano de cada aluno aparece só para consulta: quem assina ou cancela o
 * Premium é o próprio aluno, na página de planos.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('admin');
$eh_admin = $usuario['tipo'] === 'admin';
$pdo = db();

$erros = [];
$novo  = ['nome' => '', 'email' => '', 'tipo' => 'professor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'ativo') {
        $alvo = $pdo->prepare('SELECT tipo FROM usuarios WHERE id = ?');
        $alvo->execute([$id]);
        $tipo_alvo = $alvo->fetchColumn();
        $pode_gerenciar = $tipo_alvo !== false
            && ($eh_admin || $tipo_alvo === 'aluno')
            && $id !== (int) $usuario['id'];
        if ($pode_gerenciar) {
            $ativo = ($_POST['ativo'] ?? '') === '1' ? 1 : 0;
            $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')->execute([$ativo, $id]);
            toast($ativo ? 'Conta reativada.' : 'Conta desativada. O usuário não consegue mais entrar.', $ativo ? 'sucesso' : 'aviso');
        } else {
            toast('Você não tem permissão para alterar essa conta.', 'erro');
        }
        redirecionar('admin/usuarios.php');
    }

    if ($acao === 'criar') {
        $novo['nome']  = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
        $novo['email'] = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $novo['tipo']  = $eh_admin
            ? (is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '')
            : 'aluno';
        $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

        if (!in_array($novo['tipo'], ['aluno', 'professor', 'suporte', 'admin'], true)
            || (!$eh_admin && $novo['tipo'] !== 'aluno')) {
            $erros['tipo'] = 'Perfil de acesso inválido.';
        }
        if (mb_strlen($novo['nome']) < 3 || mb_strlen($novo['nome']) > 100) {
            $erros['nome'] = 'Informe um nome com 3 a 100 caracteres.';
        }
        if (mb_strlen($novo['email']) > 150 || !filter_var($novo['email'], FILTER_VALIDATE_EMAIL)) {
            $erros['email'] = 'E-mail inválido.';
        } else {
            $existe = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ?');
            $existe->execute([$novo['email']]);
            if ($existe->fetchColumn()) {
                $erros['email'] = 'Esse e-mail já está em uso.';
            }
        }
        if (mb_strlen($senha) < 6) {
            $erros['senha'] = 'Mínimo de 6 caracteres.';
        }

        if (!$erros) {
            $plano = $novo['tipo'] === 'aluno' ? 'gratuito' : 'premium';
            $pdo->prepare('INSERT INTO usuarios (nome, email, senha, tipo, plano) VALUES (?, ?, ?, ?, ?)')
                ->execute([$novo['nome'], $novo['email'], password_hash($senha, PASSWORD_DEFAULT), $novo['tipo'], $plano]);
            toast(rotulo($novo['tipo']) . ' criado(a).');
            redirecionar('admin/usuarios.php');
        }
        toast('Confira os dados da nova conta.', 'erro');
    }
}

$busca = trim($_GET['busca'] ?? '');
$filtro_tipo = $eh_admin ? '' : " AND u.tipo = 'aluno'";
$stmt = $pdo->prepare(
    "SELECT u.id, u.nome, u.email, u.tipo, u.plano, u.ativo, u.foto, u.criado_em,
            (SELECT COUNT(*) FROM historico_simulados h WHERE h.usuario_id = u.id) AS simulados
       FROM usuarios u
      WHERE (? = '' OR u.nome LIKE ? OR u.email LIKE ?)
        $filtro_tipo
      ORDER BY u.tipo = 'admin' DESC, u.nome"
);
$stmt->execute([$busca, "%$busca%", "%$busca%"]);
$usuarios = $stmt->fetchAll();

$erro_campo = fn(string $c) => isset($erros[$c]) ? ' campo--erro' : '';

$titulo_pagina = 'Usuários';
$pagina_ativa  = 'usuarios';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Usuários</h1>
        <p><?= $eh_admin ? 'Contas da equipe e dos alunos.' : 'Contas de alunos.' ?> <?= $eh_admin ? '' : 'Professores podem ativar ou desativar contas de alunos.' ?></p>
    </div>
</div>

<div class="grade-lateral">
    <section class="card">
        <form class="filtros" method="get" style="margin-bottom: 16px;">
            <div class="campo campo--largo">
                <label class="campo__rotulo" for="busca-usuarios">Buscar por nome ou e-mail</label>
                <input class="input" type="search" id="busca-usuarios" name="busca" value="<?= e($busca) ?>">
            </div>
            <button class="btn btn--secundario" type="submit"><?= icone('busca', 'icone--sm') ?> Buscar</button>
        </form>

        <div class="tabela-container">
            <table class="tabela">
                <thead><tr><th>Usuário</th><th>Simulados</th><th>Plano</th><th>Conta</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios as $u): $eu = (int) $u['id'] === (int) $usuario['id']; ?>
                    <tr style="<?= $u['ativo'] ? '' : 'opacity: 0.55;' ?>">
                        <td>
                            <div class="linha" style="flex-wrap: nowrap;">
                                <?= avatar_html($u, 'avatar', 'width: 36px; height: 36px; font-size: 12px;') ?>
                                <div>
                                    <strong><?= e($u['nome']) ?></strong>
                                    <span class="selo" style="margin-left: 6px;"><?= e(rotulo($u['tipo'])) ?></span>
                                    <div class="texto-xs texto-secundario"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="numero"><?= $u['tipo'] === 'aluno' ? (int) $u['simulados'] : '-' ?></td>
                        <td>
                            <?php if ($u['tipo'] === 'aluno'): ?>
                                <span class="selo <?= $u['plano'] === 'premium' ? 'selo--premium' : '' ?>"><?= e(rotulo($u['plano'])) ?></span>
                            <?php else: ?>
                                <span class="texto-secundario texto-xs"><?= e(rotulo($u['tipo'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($eu): ?>
                                <span class="texto-xs texto-secundario">Você</span>
                            <?php else: ?>
                                <form method="post" <?= $u['ativo'] ? 'data-confirmar="Desativar ' . e($u['nome']) . '?" data-confirmar-texto="A pessoa não vai conseguir entrar até você reativar a conta." data-confirmar-botao="Desativar"' : '' ?>>
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="acao" value="ativo">
                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                    <input type="hidden" name="ativo" value="<?= $u['ativo'] ? '0' : '1' ?>">
                                    <button class="btn btn--sm <?= $u['ativo'] ? 'btn--contorno' : 'btn--secundario' ?>"><?= $u['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card__cabecalho">
            <h2 class="card__titulo"><?= $eh_admin ? 'Criar acesso' : 'Criar conta de aluno' ?></h2>
        </div>
        <p class="texto-pequeno texto-secundario" style="margin-bottom: 16px;">
            <?= $eh_admin
                ? 'Crie contas de administrador, professor, suporte técnico ou aluno. O cadastro público cria apenas alunos.'
                : 'Esta ação cria uma conta de aluno; professores não podem criar contas da equipe.' ?>
        </p>
        <form method="post" data-validar class="pilha pilha--sm">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="criar">
            <?php if ($eh_admin): ?>
            <div class="campo<?= $erro_campo('tipo') ?>">
                <label class="campo__rotulo" for="tipo">Perfil</label>
                <select class="select" id="tipo" name="tipo" required>
                    <?php foreach (['professor', 'suporte', 'admin', 'aluno'] as $tipo): ?>
                        <option value="<?= e($tipo) ?>" <?= $novo['tipo'] === $tipo ? 'selected' : '' ?>><?= e(rotulo($tipo)) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="campo__erro"><?= e($erros['tipo'] ?? '') ?></span>
            </div>
            <?php else: ?>
            <input type="hidden" name="tipo" value="aluno">
            <?php endif; ?>
            <div class="campo<?= $erro_campo('nome') ?>">
                <label class="campo__rotulo" for="adm-nome">Nome</label>
                <input class="input" id="adm-nome" name="nome" required minlength="3" maxlength="100" value="<?= e($novo['nome']) ?>">
                <span class="campo__erro"><?= e($erros['nome'] ?? '') ?></span>
            </div>
            <div class="campo<?= $erro_campo('email') ?>">
                <label class="campo__rotulo" for="adm-email">E-mail</label>
                <input class="input" type="email" id="adm-email" name="email" required maxlength="150" value="<?= e($novo['email']) ?>">
                <span class="campo__erro"><?= e($erros['email'] ?? '') ?></span>
            </div>
            <div class="campo<?= $erro_campo('senha') ?>">
                <label class="campo__rotulo" for="adm-senha">Senha provisória</label>
                <input class="input" type="password" id="adm-senha" name="senha" required minlength="6" autocomplete="new-password">
                <span class="campo__erro"><?= e($erros['senha'] ?? '') ?></span>
            </div>
            <button class="btn btn--primario btn--bloco mt-4" type="submit">Criar conta</button>
        </form>
    </section>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
