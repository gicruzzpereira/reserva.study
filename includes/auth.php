<?php
/**
 * Autenticação e controle de acesso.
 * A sessão guarda apenas o id; os dados do usuário são relidos do banco a cada
 * requisição, para que mudanças de plano ou desativação valham na hora.
 */

/** Retorna o usuário logado (ou null). */
function usuario_logado(): ?array
{
    static $usuario = false;

    if ($usuario === false) {
        $usuario = null;
        if (!empty($_SESSION['usuario_id'])) {
            $stmt = db()->prepare(
                'SELECT id, nome, email, tipo, plano, geracoes_ia_mes, geracoes_ia_ref, serie, escola, objetivo, curso_desejado, foto, ativo, criado_em
                   FROM usuarios WHERE id = ?'
            );
            $stmt->execute([$_SESSION['usuario_id']]);
            $encontrado = $stmt->fetch();
            if ($encontrado && (int) $encontrado['ativo'] === 1) {
                $usuario = $encontrado;
            } else {
                // Conta removida ou desativada: encerra a sessão
                unset($_SESSION['usuario_id']);
            }
        }
    }

    return $usuario;
}

/**
 * Exige login (e, opcionalmente, um perfil). Retorna o usuário.
 * Aluno que tenta abrir a área admin (ou o contrário) é redirecionado ao próprio painel.
 */
function exigir_login(?string $tipo = null): array
{
    $usuario = usuario_logado();

    if (!$usuario) {
        toast('Entre na sua conta para continuar.', 'info');
        $_SESSION['voltar_para'] = $_SERVER['REQUEST_URI'] ?? '';
        redirecionar('auth/login.php');
    }

    if ($tipo !== null && $usuario['tipo'] !== $tipo) {
        toast('Você não tem permissão para acessar essa área.', 'erro');
        redirecionar(pagina_inicial($usuario));
    }

    return $usuario;
}

/** Versão para endpoints JSON: responde 401/403 em vez de redirecionar. */
function exigir_login_api(?string $tipo = null): array
{
    $usuario = usuario_logado();
    if (!$usuario) {
        responder_json(['ok' => false, 'mensagem' => 'Sua sessão expirou. Entre novamente.'], 401);
    }
    if ($tipo !== null && $usuario['tipo'] !== $tipo) {
        responder_json(['ok' => false, 'mensagem' => 'Acesso negado.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_valido()) {
        responder_json(['ok' => false, 'mensagem' => 'Requisição inválida. Recarregue a página.'], 419);
    }
    return $usuario;
}

/** Página inicial de cada perfil. */
function pagina_inicial(array $usuario): string
{
    return $usuario['tipo'] === 'admin' ? 'admin/index.php' : 'aluno/painel.php';
}

/** Faz o login: regenera o id da sessão para evitar fixação de sessão. */
function entrar(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    unset($_SESSION['csrf_token']);
}

/** Usuário tem acesso premium? */
function eh_premium(array $usuario): bool
{
    return $usuario['plano'] === 'premium';
}
