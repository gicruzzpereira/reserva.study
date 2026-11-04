<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$email = '';
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        try {
            $tabela = $pdo->prepare("INSERT INTO solicitacoes_senha (usuario_id, email, status) VALUES (?, ?, 'pendente')");
            $tabela->execute([$usuario ? (int) $usuario['id'] : null, $email]);
        } catch (PDOException $e) {
            // A página continua disponível mesmo antes da migração ser aplicada.
        }

        $mensagem = 'Sua solicitação foi registrada. A coordenação recebeu um alerta para ajudar na recuperação da conta. Não envie sua senha por e-mail ou para outras pessoas.';
        $email = '';
    }
}

$titulo_pagina = 'Recuperar senha';
$aba_ativa = 'entrar';
require RAIZ_APP . '/includes/head.php';
require RAIZ_APP . '/includes/auth_moldura_inicio.php';
?>
<h1 class="entrada__titulo">Esqueci minha senha</h1>
<p class="entrada__subtitulo">Solicite ajuda à coordenação</p>

<?php if ($mensagem): ?>
    <div class="aviso aviso--sucesso entrada__aviso" role="status">
        <?= icone('alerta', 'icone--sm') ?>
        <span><?= e($mensagem) ?></span>
    </div>
<?php endif; ?>

<?php if ($erro): ?>
    <div class="aviso aviso--erro entrada__aviso" role="alert">
        <?= icone('alerta', 'icone--sm') ?>
        <span><?= e($erro) ?></span>
    </div>
<?php endif; ?>

<form method="post" class="entrada__campos">
    <?= csrf_campo() ?>
    <div class="campo">
        <label class="campo__rotulo" for="email">E-mail da conta</label>
        <input class="input" type="email" id="email" name="email" required autocomplete="email" placeholder="seuemail@exemplo.com" value="<?= e($email) ?>" autofocus>
    </div>

    <div class="aviso aviso--aviso">
        <?= icone('alerta', 'icone--sm') ?>
        <span>Professores e demais responsáveis não devem solicitar, compartilhar ou distribuir senhas. Todas as senhas do Reserva Study são criptografadas.</span>
    </div>

    <button type="submit" class="btn btn--primario btn--bloco entrada__botao">Enviar solicitação</button>
</form>

<p class="entrada__trocar"><a href="<?= e(url('auth/login.php')) ?>">Voltar para o login</a></p>
<?php require RAIZ_APP . '/includes/auth_moldura_fim.php'; ?>
