<?php
/**
 * Cliente mínimo da API da Anthropic (Messages API) via cURL.
 * A resposta é forçada a seguir um JSON Schema (structured outputs), então
 * não é preciso "caçar" JSON no meio do texto.
 */

/** A chave da API está configurada? */
function ia_disponivel(): bool
{
    return defined('ANTHROPIC_API_KEY') && trim(ANTHROPIC_API_KEY) !== '';
}

/**
 * Faz a chamada e devolve o JSON já decodificado.
 * Lança RuntimeException com mensagem amigável (em português) em caso de falha.
 */
function ia_chamar(string $instrucoes, string $conteudo, array $schema): array
{
    if (!ia_disponivel()) {
        throw new RuntimeException('A geração com IA ainda não foi configurada neste servidor.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP está desativada. Ative-a no php.ini do XAMPP.');
    }

    $corpo = [
        'model'         => ANTHROPIC_MODELO,
        'max_tokens'    => 16000,
        'system'        => $instrucoes,
        'messages'      => [['role' => 'user', 'content' => $conteudo]],
        'output_config' => [
            'effort' => 'low', // tarefa simples: respostas mais rápidas e baratas
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ],
        // Se o modelo recusar o pedido, a API tenta sozinha um modelo alternativo
        'fallbacks'     => 'default',
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER     => [
            'content-type: application/json',
            'x-api-key: ' . trim(ANTHROPIC_API_KEY),
            'anthropic-version: 2023-06-01',
            'anthropic-beta: server-side-fallback-2026-07-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($corpo, JSON_UNESCAPED_UNICODE),
    ]);

    $resposta = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroRede = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        error_log('[IA] Falha de conexão: ' . $erroRede);
        throw new RuntimeException('Não foi possível falar com o serviço de IA. Verifique a internet e tente de novo.');
    }

    $dados = json_decode($resposta, true);

    if ($status !== 200) {
        error_log('[IA] HTTP ' . $status . ': ' . $resposta);
        throw new RuntimeException(match (true) {
            $status === 401, $status === 403 => 'A chave da API da Anthropic é inválida. Confira o config.php.',
            $status === 429                  => 'Muitas gerações ao mesmo tempo. Espere um minuto e tente de novo.',
            $status >= 500                   => 'O serviço de IA está instável agora. Tente de novo em instantes.',
            default                          => 'A IA não aceitou o pedido. Tente com um texto diferente.',
        });
    }

    // A IA pode recusar o pedido (stop_reason "refusal"): sempre conferir antes de ler o conteúdo
    if (($dados['stop_reason'] ?? '') === 'refusal') {
        throw new RuntimeException('A IA não pôde gerar material a partir desse texto. Tente outro conteúdo.');
    }
    if (($dados['stop_reason'] ?? '') === 'max_tokens') {
        throw new RuntimeException('O texto ficou grande demais para gerar de uma vez. Tente um trecho menor.');
    }

    // Junta os blocos de texto da resposta (blocos de "thinking" são ignorados)
    $texto = '';
    foreach ($dados['content'] ?? [] as $bloco) {
        if (($bloco['type'] ?? '') === 'text') {
            $texto .= $bloco['text'];
        }
    }

    $json = json_decode($texto, true);
    if (!is_array($json)) {
        error_log('[IA] Resposta fora do formato: ' . $texto);
        throw new RuntimeException('A resposta da IA veio num formato inesperado. Tente de novo.');
    }
    return $json;
}

/** Gera flashcards (pergunta na frente, resposta no verso). */
function ia_gerar_flashcards(string $texto): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'titulo' => ['type' => 'string'],
            'flashcards' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['frente' => ['type' => 'string'], 'verso' => ['type' => 'string']],
                    'required' => ['frente', 'verso'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['titulo', 'flashcards'],
        'additionalProperties' => false,
    ];

    $instrucoes = 'Você cria material de estudo para alunos brasileiros do ensino médio que se preparam para o ENEM e vestibulares. '
        . 'A partir do texto do aluno, crie de 6 a 12 flashcards em português do Brasil. '
        . 'Na frente, uma pergunta curta e objetiva sobre um conceito importante do texto. '
        . 'No verso, a resposta em no máximo 3 frases, correta e direta. '
        . 'Use só informações que estejam no texto ou que sejam conhecimento escolar consolidado. '
        . 'O título deve ter até 6 palavras e dizer o assunto.';

    $resultado = ia_chamar($instrucoes, "Texto para estudar:\n\n" . $texto, $schema);
    $cards = array_values(array_filter($resultado['flashcards'] ?? [], fn($c) => trim($c['frente'] ?? '') !== '' && trim($c['verso'] ?? '') !== ''));
    if (!$cards) {
        throw new RuntimeException('A IA não conseguiu extrair conceitos desse texto. Tente um resumo mais completo.');
    }
    return ['titulo' => $resultado['titulo'] ?? 'Flashcards', 'itens' => array_slice($cards, 0, 20)];
}

/** Gera um quiz de múltipla escolha com 4 alternativas. */
function ia_gerar_quiz(string $texto): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'titulo' => ['type' => 'string'],
            'questoes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'pergunta' => ['type' => 'string'],
                        'alternativa_a' => ['type' => 'string'],
                        'alternativa_b' => ['type' => 'string'],
                        'alternativa_c' => ['type' => 'string'],
                        'alternativa_d' => ['type' => 'string'],
                        'resposta_correta' => ['type' => 'string', 'enum' => ['A', 'B', 'C', 'D']],
                        'explicacao' => ['type' => 'string'],
                    ],
                    'required' => ['pergunta', 'alternativa_a', 'alternativa_b', 'alternativa_c', 'alternativa_d', 'resposta_correta', 'explicacao'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['titulo', 'questoes'],
        'additionalProperties' => false,
    ];

    $instrucoes = 'Você cria questões no estilo do ENEM para alunos brasileiros do ensino médio. '
        . 'A partir do texto do aluno, crie de 5 a 8 questões de múltipla escolha em português do Brasil, '
        . 'cada uma com 4 alternativas plausíveis e apenas uma correta. Varie a letra correta entre as questões. '
        . 'A explicação deve dizer em até 3 frases por que a correta está certa. '
        . 'O título deve ter até 6 palavras e dizer o assunto.';

    $resultado = ia_chamar($instrucoes, "Texto para estudar:\n\n" . $texto, $schema);
    $questoes = array_values(array_filter($resultado['questoes'] ?? [], fn($q) => in_array($q['resposta_correta'] ?? '', ['A', 'B', 'C', 'D'], true)));
    if (!$questoes) {
        throw new RuntimeException('A IA não conseguiu montar questões com esse texto. Tente um resumo mais completo.');
    }
    return ['titulo' => $resultado['titulo'] ?? 'Quiz', 'itens' => array_slice($questoes, 0, 15)];
}
