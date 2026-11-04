<?php
/**
 * Regras do módulo de Redação: tema do dia, contagem de palavras/linhas,
 * competências do ENEM e correção por IA.
 */

/** Limites de linhas da folha de redação do ENEM. */
const REDACAO_MIN_LINHAS = 7;
const REDACAO_MAX_LINHAS = 30;
const REDACAO_MAX_CARACTERES = 6000;

/** Média de caracteres por linha manuscrita na folha do ENEM (estimativa). */
const CARACTERES_POR_LINHA = 72;

/** As 5 competências avaliadas na redação do ENEM. */
const COMPETENCIAS = [
    1 => ['Norma-padrão', 'Domínio da escrita formal da língua portuguesa: ortografia, acentuação, concordância e pontuação.'],
    2 => ['Tema e tipo textual', 'Compreensão da proposta e uso de conhecimentos de várias áreas em um texto dissertativo-argumentativo.'],
    3 => ['Argumentação', 'Seleção, organização e interpretação de informações, fatos e opiniões em defesa de um ponto de vista.'],
    4 => ['Coesão', 'Uso de conectivos e mecanismos linguísticos que ligam frases e parágrafos.'],
    5 => ['Proposta de intervenção', 'Proposta para o problema com agente, ação, meio, finalidade e detalhamento, respeitando os direitos humanos.'],
];

/** Notas possíveis em cada competência. */
const NOTAS_COMPETENCIA = [0, 40, 80, 120, 160, 200];

/**
 * Tema do dia: os temas são embaralhados por ano (ordem fixa e "aleatória")
 * e cada dia do ano pega o próximo da fila. Assim todos os alunos recebem o
 * mesmo tema no mesmo dia e nenhum se repete até o ciclo acabar.
 */
function tema_do_dia(?string $data = null): ?array
{
    static $cache = [];
    $data = $data ?? date('Y-m-d');
    if (array_key_exists($data, $cache)) {
        return $cache[$data];
    }

    $ano = substr($data, 0, 4);
    $stmt = db()->prepare("SELECT * FROM temas_redacao ORDER BY MD5(CONCAT(id, '-', ?))");
    $stmt->execute([$ano]);
    $temas = $stmt->fetchAll();

    $cache[$data] = $temas ? $temas[(int) date('z', strtotime($data)) % count($temas)] : null;
    return $cache[$data];
}

function tema_por_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM temas_redacao WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Texto da proposta no modelo do ENEM. */
function proposta_redacao(array $tema): string
{
    return 'A partir da leitura dos textos motivadores e com base nos conhecimentos construídos ao longo de sua formação, '
         . 'redija um texto dissertativo-argumentativo em modalidade escrita formal da língua portuguesa sobre o tema "'
         . $tema['titulo'] . '", apresentando proposta de intervenção que respeite os direitos humanos. '
         . 'Selecione, organize e relacione, de forma coerente e coesa, argumentos e fatos para defesa de seu ponto de vista.';
}

/** Textos motivadores separados (um por parágrafo). */
function textos_motivadores(array $tema): array
{
    return array_values(array_filter(array_map('trim', preg_split("/\R\s*\R/", $tema['textos_motivadores']))));
}

function contar_palavras(string $texto): int
{
    return count(preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY));
}

/** Estimativa de linhas: cada parágrafo ocupa ceil(caracteres / 72) linhas. */
function estimar_linhas(string $texto): int
{
    $linhas = 0;
    foreach (preg_split('/\R/u', $texto) as $paragrafo) {
        $tamanho = mb_strlen(trim($paragrafo));
        if ($tamanho > 0) {
            $linhas += (int) ceil($tamanho / CARACTERES_POR_LINHA);
        }
    }
    return $linhas;
}

/** Redação do aluno em uma data (ou null). */
function redacao_do_dia(int $usuarioId, string $data): ?array
{
    $stmt = db()->prepare('SELECT * FROM redacoes WHERE usuario_id = ? AND data_tema = ?');
    $stmt->execute([$usuarioId, $data]);
    return $stmt->fetch() ?: null;
}

/** Faixa de desempenho para uma nota de 0 a 1000. */
function faixa_nota(int $nota): array
{
    return match (true) {
        $nota >= 900 => ['Excelente', 'selo--sucesso'],
        $nota >= 700 => ['Bom', 'selo--sucesso'],
        $nota >= 500 => ['Regular', 'selo--aviso'],
        default      => ['Precisa melhorar', 'selo--erro'],
    };
}

/**
 * Correção por IA nas 5 competências. Devolve
 * ['competencias' => [['nota' => 160, 'comentario' => '...'] x5], 'nota_total', 'comentario_geral', 'pontos_fortes' => [], 'melhorias' => []]
 */
function ia_corrigir_redacao(array $tema, string $titulo, string $texto): array
{
    $schema = [
        'type' => 'object',
        'properties' => [
            'competencias' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'nota' => ['type' => 'integer', 'enum' => NOTAS_COMPETENCIA],
                        'comentario' => ['type' => 'string'],
                    ],
                    'required' => ['nota', 'comentario'],
                    'additionalProperties' => false,
                ],
            ],
            'comentario_geral' => ['type' => 'string'],
            'pontos_fortes' => ['type' => 'array', 'items' => ['type' => 'string']],
            'melhorias' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['competencias', 'comentario_geral', 'pontos_fortes', 'melhorias'],
        'additionalProperties' => false,
    ];

    $lista = '';
    foreach (COMPETENCIAS as $n => [$nome, $descricao]) {
        $lista .= "Competência $n ($nome): $descricao\n";
    }

    $instrucoes = 'Você é um corretor experiente da redação do ENEM e corrige textos de alunos brasileiros do ensino médio. '
        . "Avalie a redação nas 5 competências oficiais, nesta ordem, dando a cada uma nota 0, 40, 80, 120, 160 ou 200:\n$lista"
        . 'Seja justo e criterioso, como a banca do ENEM. Fuga total ao tema ou texto que não seja dissertativo-argumentativo zera a redação. '
        . 'Em cada competência, escreva um comentário curto (até 3 frases) explicando a nota com exemplos do próprio texto. '
        . 'Em comentario_geral, faça um resumo encorajador de até 3 frases. Liste até 3 pontos fortes e até 3 melhorias práticas. '
        . 'Escreva em português do Brasil, falando diretamente com o aluno.';

    $conteudo = 'Tema: ' . $tema['titulo'] . "\n" . ($titulo !== '' ? "Título do aluno: $titulo\n" : '') . "\nRedação:\n" . $texto;

    $resultado = ia_chamar($instrucoes, $conteudo, $schema);
    $competencias = array_slice($resultado['competencias'] ?? [], 0, 5);
    if (count($competencias) !== 5) {
        throw new RuntimeException('A correção veio incompleta. Tente de novo.');
    }

    $resultado['competencias'] = $competencias;
    $resultado['nota_total'] = array_sum(array_column($competencias, 'nota'));
    return $resultado;
}
