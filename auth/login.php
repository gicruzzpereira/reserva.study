<?php
/**
 * Tela de login.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

// Já logado: vai direto para o painel
if ($logado = usuario_logado()) {
    redirecionar(pagina_inicial($logado));
}

$email = '';
$erro  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        $erro = 'Informe e-mail e senha.';
    } else {
        $stmt = db()->prepare('SELECT id, nome, senha, tipo, ativo FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $erro = 'E-mail ou senha incorretos.';
        } elseif ((int) $usuario['ativo'] !== 1) {
            $erro = 'Esta conta está desativada. Fale com a coordenação.';
        } else {
            entrar($usuario);
            toast('Bem-vindo de volta, ' . primeiro_nome($usuario['nome']) . '!');

            // Volta para a página que o usuário tentou abrir antes do login (se for interna)
            $voltar = $_SESSION['voltar_para'] ?? '';
            unset($_SESSION['voltar_para']);
            if ($voltar !== '' && str_starts_with($voltar, base_url() . '/') && !str_contains($voltar, '/auth/')) {
                header('Location: ' . $voltar);
                exit;
            }
            redirecionar(pagina_inicial($usuario));
        }
    }
}

$titulo_pagina = 'Entrar';
$aba_ativa = 'entrar';
require RAIZ_APP . '/includes/head.php';
require RAIZ_APP . '/includes/auth_moldura_inicio.php';
?>
                <h1 class="entrada__titulo">Acesse sua conta</h1>
                <p class="entrada__subtitulo">Entre como aluno, professor ou suporte técnico</p>

                <?php if ($erro): ?>
                    <div class="aviso aviso--erro entrada__aviso" role="alert">
                        <?= icone('alerta', 'icone--sm') ?>
                        <span><?= e($erro) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" data-validar class="entrada__campos" id="form-login">
                    <?= csrf_campo() ?>
                    <div class="campo">
                        <label class="campo__rotulo" for="email">E-mail</label>
                        <input class="input" type="email" id="email" name="email" required autocomplete="email"
                               placeholder="seuemail@exemplo.com" value="<?= e($email) ?>" autofocus>
                        <span class="campo__erro"></span>
                    </div>
                    <div class="campo">
                        <div class="linha linha--entre" style="align-items: center; margin-bottom: 8px;">
                            <label class="campo__rotulo" for="senha" style="margin: 0;">Senha</label>
                            <a class="texto-xs" href="<?= e(url('auth/esqueci-senha.php')) ?>">Esqueci minha senha</a>
                        </div>
                        <div class="campo-senha">
                            <input class="input" type="password" id="senha" name="senha" required autocomplete="current-password" placeholder="Sua senha">
                            <button type="button" class="botao-icone" data-mostrar-senha="senha" aria-label="Mostrar senha"><?= icone('olho', 'icone--sm') ?></button>
                        </div>
                        <span class="campo__erro"></span>
                    </div>

                    <p class="texto-xs texto-secundario" style="margin: -8px 0 4px;">Por segurança, todas as senhas são criptografadas e não podem ser visualizadas.</p>

                    <button type="submit" class="btn btn--primario btn--bloco entrada__botao">Entrar</button>

                    <div class="entrada__ou"><span>ou</span></div>

                    <button type="button" class="btn btn--contorno btn--bloco entrada__teste" data-conta-teste
                            data-email="aluno@reservastudy.com" data-senha="aluno123">
                        <?= icone('usuario', 'icone--sm') ?> Entrar com a conta de teste
                    </button>
                </form>

                <p class="entrada__trocar">Ainda não tem conta? <a href="<?= e(url('auth/cadastro.php')) ?>">Criar conta</a></p>
<?php require RAIZ_APP . '/includes/auth_moldura_fim.php'; ?>