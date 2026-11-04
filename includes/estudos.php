<?php
/**
 * Regras de negócio compartilhadas: matérias, revisão espaçada, sequência de
 * estudos, cronograma automático e limite de gerações de IA.
 */

/* ---------------------------------------------------------------------
 * Matérias
 * ------------------------------------------------------------------- */

function materias_lista(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = db()->query('SELECT id, nome, descricao, cor, icone FROM materias ORDER BY nome')->fetchAll();
    }
    return $lista;
}

/** Cor de matéria validada (evita injeção de CSS); cor neutra quando não há matéria. */
function cor_materia(?string $cor): string
{
    return ($cor && preg_match('/^#[0-9A-Fa-f]{6}$/', $cor)) ? $cor : '#9CC4D6';
}

/**
 * Chip com a identidade da matéria: ícone + nome na cor dela.
 * É o mesmo componente em todas as telas, para o aluno reconhecer a matéria pela cor.
 */
function chip_materia(?string $nome, ?string $cor, ?string $icone = null): string
{
    $nome = $nome ?: 'Geral';
    return '<span class="chip-materia" style="--cor: ' . cor_materia($cor) . ';">'
         . ($icone ? icone($icone, 'icone--xs') : '<span class="chip-materia__ponto"></span>')
         . e($nome) . '</span>';
}

/* ---------------------------------------------------------------------
 * Simulados
 * ------------------------------------------------------------------- */

/** Quantidades de questões oferecidas no simulado por matéria. */
const QTD_POR_MATERIA = [5, 10, 15];

/** Total padrão de questões do simulado geral rápido. */
const SIMULADO_GERAL_TOTAL = 35;

/** Total do simulado geral completo, no formato de 90 questões objetivas do ENEM. */
const SIMULADO_ENEM_TOTAL = 90;

/** Opções disponíveis para o simulado geral. */
const QTD_SIMULADO_GERAL = [35, 90];

/** Quantidade padrão dos simulados por área. */
const SIMULADO_AREA_TOTAL = 90;

/** Ordem das matérias no simulado geral, por área do ENEM. */
const ORDEM_AREAS = [
    'Português' => 'Linguagens', 'Inglês' => 'Linguagens', 'Espanhol' => 'Linguagens',
    'História' => 'Ciências Humanas', 'Geografia' => 'Ciências Humanas', 'Filosofia' => 'Ciências Humanas', 'Sociologia' => 'Ciências Humanas',
    'Biologia' => 'Ciências da Natureza', 'Química' => 'Ciências da Natureza', 'Física' => 'Ciências da Natureza',
    'Matemática' => 'Matemática',
];

/**
 * Sorteia as questões do simulado geral: só múltipla escolha (para ser
 * corrigido na hora), repartidas por igual entre as matérias e agrupadas
 * por área, como no ENEM. Devolve a lista de ids na ordem da prova.
 */
function sortear_por_materias(array $nomes, int $total): array
{
    $placa = implode(',', array_fill(0, count($nomes), '?'));
    $stmt = db()->prepare(
        "SELECT m.id, m.nome, COUNT(q.id) AS total FROM materias m
           JOIN questoes q ON q.materia_id = m.id AND q.tipo = 'multipla'
          WHERE m.nome IN ($placa)
          GROUP BY m.id HAVING total > 0"
    );
    $stmt->execute($nomes);
    $disponiveis = $stmt->fetchAll();
    if (!$disponiveis) return [];

    $cotas = [];
    $base = intdiv($total, count($disponiveis));
    foreach ($disponiveis as $materia) {
        $cotas[$materia['id']] = min($base, (int) $materia['total']);
    }

    $sobra = $total - array_sum($cotas);
    $ordem = $disponiveis;
    shuffle($ordem);
    while ($sobra > 0) {
        $alterou = false;
        foreach ($ordem as $materia) {
            if ($sobra > 0 && $cotas[$materia['id']] < (int) $materia['total']) {
                $cotas[$materia['id']]++;
                $sobra--;
                $alterou = true;
            }
        }
        if (!$alterou) break;
    }

    $posicao = array_flip($nomes);
    usort($disponiveis, fn($a, $b) => [$posicao[$a['nome']] ?? 99, $a['nome']] <=> [$posicao[$b['nome']] ?? 99, $b['nome']]);

    $ids = [];
    $selecionar = db()->prepare("SELECT id FROM questoes WHERE materia_id = ? AND tipo = 'multipla' ORDER BY RAND() LIMIT ?");
    foreach ($disponiveis as $materia) {
        $selecionar->bindValue(1, (int) $materia['id'], PDO::PARAM_INT);
        $selecionar->bindValue(2, $cotas[$materia['id']], PDO::PARAM_INT);
        $selecionar->execute();
        array_push($ids, ...array_map('intval', $selecionar->fetchAll(PDO::FETCH_COLUMN)));
    }
    return $ids;
}

function sortear_simulado_geral(int $total = SIMULADO_GERAL_TOTAL): array
{
    $disponiveis = db()->query(
        "SELECT m.id, m.nome, COUNT(q.id) AS total FROM materias m
           JOIN questoes q ON q.materia_id = m.id AND q.tipo = 'multipla'
          GROUP BY m.id HAVING total > 0"
    )->fetchAll();
    if (!$disponiveis) {
        return [];
    }

    // Cota igual para cada matéria; o resto vai para matérias sorteadas
    $cotas = [];
    $base = intdiv($total, count($disponiveis));
    foreach ($disponiveis as $m) {
        $cotas[$m['id']] = min($base, (int) $m['total']);
    }
    $sobra = $total - array_sum($cotas);
    $ordem_sorteio = $disponiveis;
    shuffle($ordem_sorteio);
    while ($sobra > 0) {
        $deu = false;
        foreach ($ordem_sorteio as $m) {
            if ($sobra > 0 && $cotas[$m['id']] < (int) $m['total']) {
                $cotas[$m['id']]++;
                $sobra--;
                $deu = true;
            }
        }
        if (!$deu) {
            break; // não há mais questões em nenhuma matéria
        }
    }

    // Ordem da prova: áreas do ENEM e, dentro da área, o nome da matéria
    $posicao = array_flip(array_keys(ORDEM_AREAS));
    usort($disponiveis, fn($a, $b) => [$posicao[$a['nome']] ?? 99, $a['nome']] <=> [$posicao[$b['nome']] ?? 99, $b['nome']]);

    $ids = [];
    $stmt = db()->prepare("SELECT id FROM questoes WHERE materia_id = ? AND tipo = 'multipla' ORDER BY RAND() LIMIT ?");
    foreach ($disponiveis as $m) {
        $stmt->bindValue(1, (int) $m['id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $cotas[$m['id']], PDO::PARAM_INT);
        $stmt->execute();
        array_push($ids, ...array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }
    return $ids;
}

function materia_por_id(int $id): ?array
{
    foreach (materias_lista() as $materia) {
        if ((int) $materia['id'] === $id) {
            return $materia;
        }
    }
    return null;
}

/* ---------------------------------------------------------------------
 * Revisão espaçada
 * ------------------------------------------------------------------- */

/** Intervalos padrão da revisão espaçada (em dias). */
const INTERVALOS_REVISAO = [1, 7, 30];

/** Abaixo deste percentual o aluno ganha uma revisão de reforço extra. */
const LIMITE_REFORCO = 60;

/** Agenda as revisões de 1, 7 e 30 dias (e um reforço em 3 dias se o desempenho foi baixo). */
function agendar_revisoes(int $usuarioId, ?int $materiaId, string $titulo, string $origem,
                          ?int $referenciaId = null, ?float $percentual = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO revisoes (usuario_id, materia_id, titulo, origem, referencia_id, intervalo_dias, data_agendada)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY))'
    );

    foreach (INTERVALOS_REVISAO as $dias) {
        $stmt->execute([$usuarioId, $materiaId, $titulo, $origem, $referenciaId, $dias, $dias]);
    }

    if ($percentual !== null && $percentual < LIMITE_REFORCO) {
        $stmt->execute([$usuarioId, $materiaId, 'Reforço: ' . $titulo, 'reforco', $referenciaId, 3, 3]);
    }
}

/** Revisão única (ex.: flashcard marcado como "Não sabia"). */
function agendar_revisao_unica(int $usuarioId, ?int $materiaId, string $titulo, string $origem,
                               ?int $referenciaId, int $dias = 1): void
{
    db()->prepare(
        'INSERT INTO revisoes (usuario_id, materia_id, titulo, origem, referencia_id, intervalo_dias, data_agendada)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL ? DAY))'
    )->execute([$usuarioId, $materiaId, $titulo, $origem, $referenciaId, $dias, $dias]);
}

/** Quantidade de revisões pendentes até hoje (inclui atrasadas). */
function revisoes_pendentes_hoje(int $usuarioId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM revisoes WHERE usuario_id = ? AND status = 'pendente' AND data_agendada <= CURDATE()"
    );
    $stmt->execute([$usuarioId]);
    return (int) $stmt->fetchColumn();
}

/* ---------------------------------------------------------------------
 * Tempo de estudo e sequência de dias
 * ------------------------------------------------------------------- */

/** Minutos estudados (Pomodoro) na semana atual, de segunda a domingo. */
function minutos_semana(int $usuarioId): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(minutos), 0) FROM sessoes_estudo
          WHERE usuario_id = ? AND YEARWEEK(concluida_em, 1) = YEARWEEK(CURDATE(), 1)'
    );
    $stmt->execute([$usuarioId]);
    return (int) $stmt->fetchColumn();
}

/** Datas (Y-m-d) em que o aluno estudou: Pomodoro, simulado ou revisão concluída. */
function dias_estudados(int $usuarioId, int $ultimosDias = 120): array
{
    $stmt = db()->prepare(
        "SELECT DATE(concluida_em) d FROM sessoes_estudo WHERE usuario_id = :u AND concluida_em >= DATE_SUB(CURDATE(), INTERVAL :n DAY)
         UNION SELECT DATE(data_realizacao) FROM historico_simulados WHERE usuario_id = :u2 AND data_realizacao >= DATE_SUB(CURDATE(), INTERVAL :n2 DAY)
         UNION SELECT DATE(concluida_em) FROM revisoes WHERE usuario_id = :u3 AND status = 'concluida' AND concluida_em >= DATE_SUB(CURDATE(), INTERVAL :n3 DAY)
         UNION SELECT DATE(enviada_em) FROM redacoes WHERE usuario_id = :u4 AND status = 'enviada' AND enviada_em >= DATE_SUB(CURDATE(), INTERVAL :n4 DAY)"
    );
    $stmt->execute([
        'u' => $usuarioId, 'n' => $ultimosDias,
        'u2' => $usuarioId, 'n2' => $ultimosDias,
        'u3' => $usuarioId, 'n3' => $ultimosDias,
        'u4' => $usuarioId, 'n4' => $ultimosDias,
    ]);
    return array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Dias seguidos de estudo, contando até hoje (ou até ontem, se hoje ainda não estudou). */
function sequencia_atual(array $dias): int
{
    $data = new DateTime('today');
    if (!isset($dias[$data->format('Y-m-d')])) {
        $data->modify('-1 day');
    }
    $sequencia = 0;
    while (isset($dias[$data->format('Y-m-d')])) {
        $sequencia++;
        $data->modify('-1 day');
    }
    return $sequencia;
}

/** "1h 35min", "50min" */
function formatar_minutos(int $minutos): string
{
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    if ($h === 0) {
        return $m . 'min';
    }
    return $h . 'h' . ($m ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'min' : '');
}

/* ---------------------------------------------------------------------
 * Cronograma automático
 * ------------------------------------------------------------------- */

const DIAS_SEMANA_COLUNAS = [1 => 'horas_seg', 2 => 'horas_ter', 3 => 'horas_qua', 4 => 'horas_qui',
                             5 => 'horas_sex', 6 => 'horas_sab', 7 => 'horas_dom'];

/** Quantos dias à frente o cronograma é montado (esta semana e a próxima). */
const DIAS_GERADOS = 14;

/** Rótulos da prioridade (peso 1 a 5) de cada matéria no cronograma. */
const PRIORIDADES = [1 => 'Leve', 2 => 'Baixa', 3 => 'Média', 4 => 'Alta', 5 => 'Máxima'];

/** Quantos blocos cabem em um dia com essa quantidade de horas (mesma regra de slots_do_dia). */
function blocos_por_horas(float $horas): int
{
    return count(slots_do_dia($horas, '08:00:00'));
}

/** Duração de um bloco de estudo e do intervalo entre blocos (minutos). */
const BLOCO_MINUTOS = 50;
const PAUSA_MINUTOS = 10;

function cronograma_preferencias(int $usuarioId): ?array
{
    $stmt = db()->prepare('SELECT * FROM cronograma_preferencias WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?: null;
}

function cronograma_materias(int $usuarioId): array
{
    $stmt = db()->prepare('SELECT materia_id, peso FROM cronograma_materias WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/**
 * Monta os horários de um dia: blocos de 50 min com 10 min de pausa.
 * Se sobrarem 30 min ou mais, entra um bloco curto de 25 min.
 */
function slots_do_dia(float $horas, string $horaInicio): array
{
    $minutosLivres = (int) round($horas * 60);
    $inicio = strtotime('1970-01-01 ' . $horaInicio);
    $slots = [];

    while ($minutosLivres >= BLOCO_MINUTOS) {
        $slots[] = [date('H:i:s', $inicio), date('H:i:s', $inicio + BLOCO_MINUTOS * 60)];
        $inicio += (BLOCO_MINUTOS + PAUSA_MINUTOS) * 60;
        $minutosLivres -= BLOCO_MINUTOS + PAUSA_MINUTOS;
    }
    if ($minutosLivres >= 30) {
        $slots[] = [date('H:i:s', $inicio), date('H:i:s', $inicio + 25 * 60)];
    }
    return $slots;
}

/**
 * Gera os blocos dos próximos DIAS_GERADOS dias (ou até a véspera da prova), distribuindo
 * as matérias proporcionalmente aos pesos. Blocos pendentes futuros são refeitos;
 * blocos concluídos são preservados. Retorna a quantidade de blocos criados.
 */
function gerar_cronograma(int $usuarioId): int
{
    $prefs = cronograma_preferencias($usuarioId);
    $pesos = cronograma_materias($usuarioId);
    if (!$prefs || !$pesos) {
        return 0;
    }

    // 1) Lista de horários disponíveis na semana
    $hoje = new DateTime('today');
    $limite = (clone $hoje)->modify('+' . (DIAS_GERADOS - 1) . ' days');
    if (!empty($prefs['data_prova'])) {
        $vesperaProva = (new DateTime($prefs['data_prova']))->modify('-1 day');
        if ($vesperaProva < $limite) {
            $limite = $vesperaProva;
        }
    }

    $horarios = [];
    for ($dia = clone $hoje; $dia <= $limite; $dia->modify('+1 day')) {
        $coluna = DIAS_SEMANA_COLUNAS[(int) $dia->format('N')];
        foreach (slots_do_dia((float) $prefs[$coluna], $prefs['hora_inicio']) as [$ini, $fim]) {
            // Hoje: ignora horários que já passaram
            if ($dia == $hoje && strtotime($dia->format('Y-m-d') . ' ' . $ini) < time()) {
                continue;
            }
            $horarios[] = [$dia->format('Y-m-d'), $ini, $fim];
        }
    }

    // 2) Quantos blocos cada matéria recebe (proporcional ao peso, maiores restos)
    $total = count($horarios);
    $somaPesos = array_sum($pesos);
    $cotas = [];
    $restos = [];
    foreach ($pesos as $materiaId => $peso) {
        $exato = $total * $peso / $somaPesos;
        $cotas[$materiaId] = (int) floor($exato);
        $restos[$materiaId] = $exato - floor($exato);
    }
    arsort($restos);
    $faltam = $total - array_sum($cotas);
    foreach (array_keys($restos) as $materiaId) {
        if ($faltam-- <= 0) {
            break;
        }
        $cotas[$materiaId]++;
    }

    // 3) Ordem intercalada (round-robin ponderado suave), evitando a mesma matéria em sequência
    $sequencia = [];
    $atual = array_fill_keys(array_keys($cotas), 0);
    $restantes = $cotas;
    for ($i = 0; $i < $total; $i++) {
        $escolhida = null;
        foreach ($restantes as $materiaId => $qtd) {
            if ($qtd <= 0) {
                continue;
            }
            $atual[$materiaId] += $cotas[$materiaId];
            if ($escolhida === null || $atual[$materiaId] > $atual[$escolhida]) {
                $escolhida = $materiaId;
            }
        }
        if ($escolhida === null) {
            break;
        }
        $atual[$escolhida] -= $total;
        $restantes[$escolhida]--;
        $sequencia[] = $escolhida;
    }

    // 4) Grava (remove apenas os pendentes de hoje em diante)
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM cronograma_blocos WHERE usuario_id = ? AND status = 'pendente' AND data >= CURDATE()")
            ->execute([$usuarioId]);

        $inserir = $pdo->prepare(
            'INSERT INTO cronograma_blocos (usuario_id, materia_id, data, hora_inicio, hora_fim) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($horarios as $i => [$data, $ini, $fim]) {
            if (!isset($sequencia[$i])) {
                break;
            }
            $inserir->execute([$usuarioId, $sequencia[$i], $data, $ini, $fim]);
        }
        $pdo->commit();
    } catch (Throwable $erro) {
        $pdo->rollBack();
        throw $erro;
    }

    return min(count($horarios), count($sequencia));
}

/**
 * Marca um bloco como concluído e coloca o conteúdo na revisão espaçada
 * (uma vez por matéria por dia, para não lotar a agenda). Retorna o nome da matéria ou null.
 */
function concluir_bloco(int $usuarioId, int $blocoId): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT b.materia_id, m.nome FROM cronograma_blocos b JOIN materias m ON m.id = b.materia_id
          WHERE b.id = ? AND b.usuario_id = ? AND b.status = 'pendente'"
    );
    $stmt->execute([$blocoId, $usuarioId]);
    $bloco = $stmt->fetch();
    if (!$bloco) {
        return null;
    }

    $pdo->prepare("UPDATE cronograma_blocos SET status = 'concluido' WHERE id = ?")->execute([$blocoId]);

    $ja = $pdo->prepare("SELECT 1 FROM revisoes WHERE usuario_id = ? AND materia_id = ? AND origem = 'conteudo' AND DATE(criado_em) = CURDATE()");
    $ja->execute([$usuarioId, $bloco['materia_id']]);
    if (!$ja->fetchColumn()) {
        agendar_revisoes($usuarioId, (int) $bloco['materia_id'], 'Revisar o que estudou de ' . $bloco['nome'], 'conteudo', $blocoId);
    }
    return $bloco['nome'];
}

/**
 * Move um bloco não concluído para o próximo dia com horas disponíveis,
 * logo depois do último bloco já marcado naquele dia. Retorna a nova data ou null.
 */
function redistribuir_bloco(int $usuarioId, int $blocoId): ?string
{
    $stmt = db()->prepare("SELECT * FROM cronograma_blocos WHERE id = ? AND usuario_id = ? AND status = 'pendente'");
    $stmt->execute([$blocoId, $usuarioId]);
    $bloco = $stmt->fetch();
    $prefs = cronograma_preferencias($usuarioId);
    if (!$bloco || !$prefs) {
        return null;
    }

    $duracao = strtotime('1970-01-01 ' . $bloco['hora_fim']) - strtotime('1970-01-01 ' . $bloco['hora_inicio']);
    $ultimo = db()->prepare(
        "SELECT MAX(hora_fim) FROM cronograma_blocos WHERE usuario_id = ? AND data = ? AND status <> 'redistribuido'"
    );

    $dia = new DateTime(max($bloco['data'], date('Y-m-d')));
    for ($tentativa = 0; $tentativa < 14; $tentativa++) {
        $dia->modify('+1 day');
        if ((float) $prefs[DIAS_SEMANA_COLUNAS[(int) $dia->format('N')]] <= 0) {
            continue;
        }
        $ultimo->execute([$usuarioId, $dia->format('Y-m-d')]);
        $fimUltimo = $ultimo->fetchColumn();
        $inicio = $fimUltimo
            ? strtotime('1970-01-01 ' . $fimUltimo) + PAUSA_MINUTOS * 60
            : strtotime('1970-01-01 ' . $prefs['hora_inicio']);
        if ($inicio + $duracao > strtotime('1970-01-01 23:30:00')) {
            continue; // dia já está cheio
        }

        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE cronograma_blocos SET status = 'redistribuido' WHERE id = ?")->execute([$blocoId]);
        $pdo->prepare('INSERT INTO cronograma_blocos (usuario_id, materia_id, data, hora_inicio, hora_fim) VALUES (?, ?, ?, ?, ?)')
            ->execute([$usuarioId, $bloco['materia_id'], $dia->format('Y-m-d'),
                       date('H:i:s', $inicio), date('H:i:s', $inicio + $duracao)]);
        $pdo->commit();
        return $dia->format('Y-m-d');
    }
    return null;
}

/* ---------------------------------------------------------------------
 * Perfil do aluno
 * ------------------------------------------------------------------- */

const SERIES = ['1' => '1º ano', '2' => '2º ano', '3' => '3º ano', 'cursinho' => 'Cursinho', 'outro' => 'Outro'];

const OBJETIVOS = ['ENEM', 'FUVEST', 'UNICAMP', 'UNESP', 'Vestibular de federal', 'Vestibular de particular', 'Concurso militar', 'Outro'];

/* ---------------------------------------------------------------------
 * Foto de perfil
 * ------------------------------------------------------------------- */

const PASTA_AVATARES = '/uploads/avatars/';
const FOTO_MAX_BYTES = 3 * 1024 * 1024;
const FOTO_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Caminho em disco da foto (ou null se não existir). */
function caminho_foto(?string $arquivo): ?string
{
    if (!$arquivo || !preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', $arquivo)) {
        return null;
    }
    $caminho = RAIZ_APP . PASTA_AVATARES . $arquivo;
    return is_file($caminho) ? $caminho : null;
}

/**
 * Avatar do usuário: foto, se houver, ou as iniciais.
 * $classe define o tamanho (ex.: "avatar", "perfil-topo__avatar").
 */
function avatar_html(array $usuario, string $classe = 'avatar', string $estilo = ''): string
{
    $atributos = 'class="' . e($classe) . '"' . ($estilo ? ' style="' . e($estilo) . '"' : '');
    if ($caminho = caminho_foto($usuario['foto'] ?? null)) {
        $versao = filemtime($caminho);
        return '<span ' . $atributos . '><img src="' . e(url(ltrim(PASTA_AVATARES, '/') . $usuario['foto'])) . '?v=' . $versao
             . '" alt="Foto de ' . e($usuario['nome'] ?? '') . '" loading="lazy"></span>';
    }
    return '<span ' . $atributos . ' aria-hidden="true">' . e(iniciais($usuario['nome'] ?? '?')) . '</span>';
}

/**
 * Valida e guarda uma foto enviada. Devolve o nome do arquivo salvo.
 * O navegador já manda a imagem recortada (quadrada) e comprimida; aqui
 * conferimos tamanho, tipo real (pelo conteúdo, não pela extensão) e
 * dimensões. Se a extensão GD estiver ativa, a imagem também é regravada,
 * o que descarta qualquer conteúdo escondido no arquivo.
 */
function salvar_foto_perfil(int $usuarioId, array $arquivo): string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'] ?? '')) {
        throw new RuntimeException('Não foi possível receber a imagem. Tente de novo.');
    }
    if ($arquivo['size'] > FOTO_MAX_BYTES) {
        throw new RuntimeException('A imagem passa de 3 MB. Escolha uma menor.');
    }

    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
    $info = @getimagesize($arquivo['tmp_name']);
    if (!isset(FOTO_TIPOS[$tipo]) || !$info || $info['mime'] !== $tipo) {
        throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP.');
    }
    if ($info[0] < 64 || $info[1] < 64 || $info[0] > 6000 || $info[1] > 6000) {
        throw new RuntimeException('A imagem precisa ter entre 64 e 6000 pixels de lado.');
    }

    $nome = $usuarioId . '-' . bin2hex(random_bytes(8)) . '.' . FOTO_TIPOS[$tipo];
    $destino = RAIZ_APP . PASTA_AVATARES . $nome;

    if (extension_loaded('gd') && ($img = @imagecreatefromstring(file_get_contents($arquivo['tmp_name'])))) {
        // Recorte quadrado central em 400x400, regravado como JPG
        $lado = min(imagesx($img), imagesy($img));
        $quadro = imagecreatetruecolor(400, 400);
        imagecopyresampled($quadro, $img, 0, 0, (int) ((imagesx($img) - $lado) / 2), (int) ((imagesy($img) - $lado) / 2), 400, 400, $lado, $lado);
        $nome = preg_replace('/\.\w+$/', '.jpg', $nome);
        $destino = RAIZ_APP . PASTA_AVATARES . $nome;
        imagejpeg($quadro, $destino, 86);
    } elseif (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }

    return $nome;
}

/** Apaga o arquivo da foto antiga (se existir). */
function apagar_foto_perfil(?string $arquivo): void
{
    if ($caminho = caminho_foto($arquivo)) {
        @unlink($caminho);
    }
}

/* ---------------------------------------------------------------------
 * Planos do Premium (valores exibidos no checkout; nada é cobrado)
 * ------------------------------------------------------------------- */

const PLANOS_PREMIUM = [
    'mensal' => ['nome' => 'Mensal', 'valor' => 19.90, 'meses' => 1],
    'anual'  => ['nome' => 'Anual',  'valor' => 179.00, 'meses' => 12],
];

/** "R$ 19,90" */
function moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/** Assinatura ativa do aluno (ou null). */
function assinatura_ativa(int $usuarioId): ?array
{
    $stmt = db()->prepare("SELECT * FROM assinaturas WHERE usuario_id = ? AND status = 'ativa' ORDER BY criada_em DESC LIMIT 1");
    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?: null;
}

/* ---------------------------------------------------------------------
 * Gerações de IA (plano gratuito tem limite mensal)
 * ------------------------------------------------------------------- */

/** Gerações restantes no mês (null = ilimitado). Zera o contador quando o mês muda. */
function ia_geracoes_restantes(array $usuario): ?int
{
    if (eh_premium($usuario)) {
        return null;
    }
    $mesAtual = date('Y-m');
    if ($usuario['geracoes_ia_ref'] !== $mesAtual) {
        db()->prepare('UPDATE usuarios SET geracoes_ia_mes = 0, geracoes_ia_ref = ? WHERE id = ?')
            ->execute([$mesAtual, $usuario['id']]);
        return IA_LIMITE_GRATUITO;
    }
    return max(0, IA_LIMITE_GRATUITO - (int) $usuario['geracoes_ia_mes']);
}

function ia_registrar_uso(int $usuarioId): void
{
    db()->prepare('UPDATE usuarios SET geracoes_ia_mes = geracoes_ia_mes + 1, geracoes_ia_ref = ? WHERE id = ?')
        ->execute([date('Y-m'), $usuarioId]);
}

/* ---------------------------------------------------------------------
 * Datas relativas
 * ------------------------------------------------------------------- */

/** "Hoje", "Amanhã", "Ontem", "Em 3 dias", "Há 2 dias" */
function data_relativa(string $data): string
{
    $diff = (int) (new DateTime('today'))->diff(new DateTime($data))->format('%r%a');
    return match (true) {
        $diff === 0  => 'Hoje',
        $diff === 1  => 'Amanhã',
        $diff === -1 => 'Ontem',
        $diff > 1    => 'Em ' . $diff . ' dias',
        default      => 'Há ' . abs($diff) . ' dias',
    };
}

/** Hora curta "14:00" a partir de "14:00:00". */
function hora_curta(string $hora): string
{
    return substr($hora, 0, 5);
}
