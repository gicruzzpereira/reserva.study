<?php
/**
 * Cadastro público: cria apenas contas de aluno.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if ($logado = usuario_logado()) {
    redirecionar(pagina_inicial($logado));
}

$dados = ['nome' => '', 'email' => ''];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $dados['nome']  = trim($_POST['nome'] ?? '');
    $dados['email'] = trim($_POST['email'] ?? '');
    $senha          = $_POST['senha'] ?? '';
    $confirmacao    = $_POST['confirmacao'] ?? '';

    // Validação no servidor (a do front pode ser burlada)
    if (mb_strlen($dados['nome']) < 3 || mb_strlen($dados['nome']) > 100) {
        $erros['nome'] = 'Informe seu nome (3 a 100 caracteres).';
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($dados['email']) > 150) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    if (mb_strlen($senha) < 6) {
        $erros['senha'] = 'A senha precisa ter pelo menos 6 caracteres.';
    }
    if ($senha !== $confirmacao) {
        $erros['confirmacao'] = 'As senhas não conferem.';
    }

    if (!$erros) {
        $existe = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $existe->execute([$dados['email']]);
        if ($existe->fetchColumn()) {
            $erros['email'] = 'Já existe uma conta com este e-mail.';
        }
    }

    if (!$erros) {
        // O tipo é fixo: o cadastro público nunca cria administradores
        db()->prepare("INSERT INTO usuarios (nome, email, senha, tipo, plano) VALUES (?, ?, ?, 'aluno', 'gratuito')")
            ->execute([$dados['nome'], $dados['email'], password_hash($senha, PASSWORD_DEFAULT)]);

        entrar(['id' => db()->lastInsertId()]);
        toast('Conta criada! Comece montando seu cronograma.');
        redirecionar('aluno/painel.php');
    }
}

/** Classe e mensagem de erro de um campo. */
$erro_campo = fn(string $campo) => isset($erros[$campo]) ? ' campo--erro' : '';

$titulo_pagina = 'Criar conta';
$aba_ativa = 'cadastro';
require RAIZ_APP . '/includes/head.php';
require RAIZ_APP . '/includes/auth_moldura_inicio.php';
?>
                <h1 class="entrada__titulo">Crie sua conta</h1>
                <p class="entrada__subtitulo">Leva menos de um minuto e é gratuito</p>

                <form method="post" data-validar class="entrada__campos">
                    <?= csrf_campo() ?>
                    <div class="campo<?= $erro_campo('nome') ?>">
                        <label class="campo__rotulo" for="nome">Nome completo</label>
                        <input class="input" type="text" id="nome" name="nome" required minlength="3" maxlength="100"
                               autocomplete="name" placeholder="Como você se chama?" value="<?= e($dados['nome']) ?>" autofocus>
                        <span class="campo__erro"><?= e($erros['nome'] ?? '') ?></span>
                    </div>
                    <div class="campo<?= $erro_campo('email') ?>">
                        <label class="campo__rotulo" for="email">E-mail</label>
                        <input class="input" type="email" id="email" name="email" required maxlength="150"
                               autocomplete="email" placeholder="seuemail@exemplo.com" value="<?= e($dados['email']) ?>">
                        <span class="campo__erro"><?= e($erros['email'] ?? '') ?></span>
                    </div>
                    <div class="form-grid">
                        <div class="campo<?= $erro_campo('senha') ?>">
                            <label class="campo__rotulo" for="senha">Senha</label>
                            <div class="campo-senha">
                                <input class="input" type="password" id="senha" name="senha" required minlength="6" autocomplete="new-password" placeholder="6+ caracteres">
                                <button type="button" class="botao-icone" data-mostrar-senha="senha" aria-label="Mostrar senha"><?= icone('olho', 'icone--sm') ?></button>
                            </div>
                            <span class="campo__erro"><?= e($erros['senha'] ?? '') ?></span>
                        </div>
                        <div class="campo<?= $erro_campo('confirmacao') ?>">
                            <label class="campo__rotulo" for="confirmacao">Confirmar senha</label>
                            <input class="input" type="password" id="confirmacao" name="confirmacao" required
                                   data-igual-a="senha" autocomplete="new-password" placeholder="Repita a senha">
                            <span class="campo__erro"><?= e($erros['confirmacao'] ?? '') ?></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primario btn--bloco entrada__botao">Criar conta</button>
                </form>

                <p class="entrada__trocar">Já tem conta? <a href="<?= e(url('auth/login.php')) ?>">Entrar</a></p>
<?php require RAIZ_APP . '/includes/auth_moldura_fim.php'; ?>