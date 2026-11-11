<?php
/**
 * Funções auxiliares usadas em todo o sistema.
 */

/** Escapa um valor para exibição segura em HTML. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Caminho base da aplicação (ex.: "/study-reserva"), detectado automaticamente se não configurado. */
function base_url(): string
{
    static $base = null;

    if ($base === null) {
        if (BASE_URL !== null) {
            $base = rtrim(BASE_URL, '/');
        } else {
            $raizDocumentos = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
            $raizApp        = str_replace('\\', '/', RAIZ_APP);
            $base = ($raizDocumentos !== '' && stripos($raizApp, $raizDocumentos) === 0)
                ? rtrim(substr($raizApp, strlen($raizDocumentos)), '/')
                : '';
        }
    }

    return $base;
}

/** Monta uma URL interna a partir de um caminho relativo à raiz do projeto. */
function url(string $caminho = ''): string
{
    return base_url() . '/' . ltrim($caminho, '/');
}

/** URL de um arquivo estático com versão (evita cache antigo após alterações). */
function asset(string $caminho): string
{
    $arquivo = RAIZ_APP . '/assets/' . ltrim($caminho, '/');
    $versao  = file_exists($arquivo) ? filemtime($arquivo) : 1;
    return url('assets/' . ltrim($caminho, '/')) . '?v=' . $versao;
}

/** Redireciona e encerra a execução. */
function redirecionar(string $caminho): void
{
    header('Location: ' . url($caminho));
    exit;
}

/** Envia uma resposta JSON e encerra (usado pelos endpoints da pasta /api). */
function responder_json(array $dados, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lê o corpo JSON enviado por fetch() (ou o $_POST, se for formulário). */
function entrada_json(): array
{
    $corpo = file_get_contents('php://input');
    $dados = $corpo ? json_decode($corpo, true) : null;
    return is_array($dados) ? $dados : $_POST;
}

/* ---------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------- */

/** Retorna (e cria, se necessário) o token CSRF da sessão. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto com o token, para colocar dentro dos formulários. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Verifica o token enviado por formulário (POST) ou pelo cabeçalho X-CSRF-Token (fetch). */
function csrf_valido(): bool
{
    $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($enviado) && $enviado !== '' && hash_equals(csrf_token(), $enviado);
}

/** Interrompe a requisição se o token CSRF for inválido. */
function exigir_csrf(): void
{
    if (!csrf_valido()) {
        http_response_code(419);
        exit('Sessão expirada ou requisição inválida. Volte e tente novamente.');
    }
}

/* ---------------------------------------------------------------------
 * Toasts (mensagens de sucesso/erro exibidas na próxima página)
 * ------------------------------------------------------------------- */

/** Agenda uma mensagem. Tipos: sucesso, erro, aviso, info. */
function toast(string $mensagem, string $tipo = 'sucesso'): void
{
    $_SESSION['toasts'][] = ['mensagem' => $mensagem, 'tipo' => $tipo];
}

/** Retorna e limpa as mensagens pendentes. */
function toasts_pendentes(): array
{
    $lista = $_SESSION['toasts'] ?? [];
    unset($_SESSION['toasts']);
    return $lista;
}

/* ---------------------------------------------------------------------
 * Formatação
 * ------------------------------------------------------------------- */

/** Iniciais do nome para o avatar (ex.: "Laura Fialho" → "LF"). */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    $primeira = mb_substr($partes[0] ?? '', 0, 1);
    $ultima   = count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '';
    return mb_strtoupper($primeira . $ultima);
}

/** Primeiro nome do usuário. */
function primeiro_nome(string $nome): string
{
    return explode(' ', trim($nome))[0];
}

/** Data no formato brasileiro (dd/mm/aaaa). */
function data_br(?string $data, bool $comHora = false): string
{
    if (!$data) {
        return '-';
    }
    return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($data));
}

/** Data por extenso curta: "terça, 7 de outubro". */
function data_extenso(?string $data = null): string
{
    $dias  = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho',
              'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $t = $data ? strtotime($data) : time();
    return $dias[(int) date('w', $t)] . ', ' . date('j', $t) . ' de ' . $meses[(int) date('n', $t) - 1];
}

/** Rótulos legíveis dos valores de ENUM do banco. */
function rotulo(string $valor): string
{
    $rotulos = [
        'facil' => 'Fácil', 'medio' => 'Médio', 'dificil' => 'Difícil',
        'multipla' => 'Múltipla escolha', 'dissertativa' => 'Dissertativa',
        'atencao' => 'Falta de atenção', 'conteudo' => 'Não sabia o conteúdo',
        'interpretacao' => 'Erro de interpretação',
        'gratuito' => 'Gratuito', 'premium' => 'Premium',
        'aluno' => 'Aluno', 'admin' => 'Administrador',
        'professor' => 'Professor', 'suporte' => 'Suporte técnico',
    ];
    return $rotulos[$valor] ?? $valor;
}

/* ---------------------------------------------------------------------
 * Marca (logo oficial em assets/img/marca/)
 * ------------------------------------------------------------------- */

/**
 * Logo do Reserva Study.
 *   $tipo:    'horizontal' (lâmpada + nome) ou 'simbolo' (só a lâmpada)
 *   $fundo:   'auto'  = troca sozinha com o tema (original no claro, versão clara no escuro)
 *             'escuro' = sempre a versão clara (para áreas azuis da marca)
 *   $altura:  altura em pixels
 */
function logo_html(string $tipo = 'horizontal', string $fundo = 'auto', int $altura = 36): string
{
    $base = $tipo === 'simbolo' ? 'img/marca/logo-simbolo' : 'img/marca/logo-horizontal';
    $clara = $base . ($tipo === 'simbolo' ? '-claro.png' : '-clara.png');
    $alt = $tipo === 'simbolo' ? '' : 'Reserva Study';
    $img = fn(string $arquivo, string $classe) => '<img class="logo ' . $classe . '" src="' . e(asset($arquivo)) . '" alt="' . $alt
        . '" height="' . $altura . '" style="height: ' . $altura . 'px;" decoding="async">';

    if ($fundo === 'escuro') {
        return $img($clara, '');
    }
    // As duas versões vão para a página; o CSS mostra a certa para o tema atual
    return $img($clara, 'logo--tema-escuro') . $img($base . '.png', 'logo--tema-claro');
}