<?php
/**
 * Gerenciamento de usuários: lista de alunos, ativação/desativação da conta
 * e criação de novos administradores.
 * O plano de cada aluno aparece só para consulta: quem assina ou cancela o
 * Premium é o próprio aluno, na página de planos.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('admin');
$pdo = db();

$erros = [];
$novo  = ['nome' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'ativo' && $id !== (int) $usuario['id']) {
        $ativo = ($_POST['ativo'] ?? '') === '1' ? 1 : 0;
        $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')->execute([$ativo, $id]);
        toast($ativo ? 'Conta reativada.' : 'Conta desativada. O usuário não consegue mais entrar.', $ativo ? 'sucesso' : 'aviso');
        redirecionar('admin/usuarios.php');
    }

    if ($acao === 'novo_admin') {
        $novo['nome']  = trim($_POST['nome'] ?? '');
        $novo['email'] = trim($_POST['email'] ?? '');
        $senha         = $_POST['senha'] ?? '';

        if (mb_strlen($novo['nome']) < 3) {
            $erros['nome'] = 'Informe o nome.';
        }
        if (!filter_var($novo['email'], FILTER_VALIDATE_EMAIL)) {
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
            $pdo->prepare("INSERT INTO usuarios (nome, email, senha, tipo, plano) VALUES (?, ?, ?, 'admin', 'premium')")
                ->execute([$novo['nome'], $novo['email'], password_hash($senha, PASSWORD_DEFAULT)]);
            toast('Administrador criado.');
            redirecionar('admin/usuarios.php');
        }
        toast('Confira os dados do novo administrador.', 'erro');
    }
}

$busca = trim($_GET['busca'] ?? '');
$stmt = $pdo->prepare(
    "SELECT u.id, u.nome, u.email, u.tipo, u.plano, u.ativo, u.foto, u.criado_em,
            (SELECT COUNT(*) FROM historico_simulados h WHERE h.usuario_id = u.id) AS simulados
       FROM usuarios u
      WHERE (? = '' OR u.nome LIKE ? OR u.email LIKE ?)
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
        <p>Contas de alunos e administradores. O plano é escolhido pelo próprio aluno.</p>
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
                                    <?php if ($u['tipo'] === 'admin'): ?><span class="selo" style="margin-left: 6px;">Admin</span><?php endif; ?>
                                    <div class="texto-xs texto-secundario"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="numero"><?= $u['tipo'] === 'aluno' ? (int) $u['simulados'] : '-' ?></td>
                        <td>
                            <?php if ($u['tipo'] === 'aluno'): ?>
                                <span class="selo <?= $u['plano'] === 'premium' ? 'selo--premium' : '' ?>"><?= e(rotulo($u['plano'])) ?></span>
                            <?php else: ?>
                                <span class="texto-secundario texto-xs">Acesso total</span>
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
            <h2 class="card__titulo">Novo administrador</h2>
        </div>
        <p class="texto-pequeno texto-secundario" style="margin-bottom: 16px;">O cadastro público só cria alunos. Administradores são criados aqui.</p>
        <form method="post" data-validar class="pilha pilha--sm">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="novo_admin">
            <div class="campo<?= $erro_campo('nome') ?>">
                <label class="campo__rotulo" for="adm-nome">Nome</label>
                <input class="input" id="adm-nome" name="nome" required minlength="3" value="<?= e($novo['nome']) ?>">
                <span class="campo__erro"><?= e($erros['nome'] ?? '') ?></span>
            </div>
            <div class="campo<?= $erro_campo('email') ?>">
                <label class="campo__rotulo" for="adm-email">E-mail</label>
                <input class="input" type="email" id="adm-email" name="email" required value="<?= e($novo['email']) ?>">
                <span class="campo__erro"><?= e($erros['email'] ?? '') ?></span>
            </div>
            <div class="campo<?= $erro_campo('senha') ?>">
                <label class="campo__rotulo" for="adm-senha">Senha provisória</label>
                <input class="input" type="password" id="adm-senha" name="senha" required minlength="6" autocomplete="new-password">
                <span class="campo__erro"><?= e($erros['senha'] ?? '') ?></span>
            </div>
            <button class="btn btn--primario btn--bloco mt-4" type="submit">Criar administrador</button>
        </form>
    </section>
</div>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
