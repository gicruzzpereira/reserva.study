<?php
/**
 * Confere a resposta de uma questão do simulado em andamento.
 *
 * Entrada (JSON):
 *   { questao_id, resposta }          múltipla: letra A-D | dissertativa: texto do aluno
 *   { questao_id, autoavaliacao }     dissertativa: "acertei" ou "errei"
 * Saída: { ok, correta, resposta_correta, comentario }
 *
 * A primeira resposta de cada questão fica registrada na sessão e não pode ser trocada.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login_api('aluno');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$simulado = $_SESSION['simulado'] ?? null;
$entrada  = entrada_json();
$questaoId = (int) ($entrada['questao_id'] ?? 0);

if (!$simulado || !in_array($questaoId, $simulado['questoes'], true)) {
    responder_json(['ok' => false, 'mensagem' => 'Este simulado não está mais ativo. Comece outro.'], 409);
}

$stmt = db()->prepare('SELECT tipo, resposta_correta, gabarito_comentario FROM questoes WHERE id = ?');
$stmt->execute([$questaoId]);
$questao = $stmt->fetch();
if (!$questao) {
    responder_json(['ok' => false, 'mensagem' => 'Questão não encontrada.'], 404);
}

$registro = &$_SESSION['simulado']['respostas'][$questaoId];

if ($questao['tipo'] === 'multipla') {
    // Já respondida: devolve o mesmo resultado (não permite trocar a resposta)
    if ($registro === null) {
        $letra = strtoupper(trim((string) ($entrada['resposta'] ?? '')));
        if (!in_array($letra, ['A', 'B', 'C', 'D'], true)) {
            unset($_SESSION['simulado']['respostas'][$questaoId]);
            responder_json(['ok' => false, 'mensagem' => 'Alternativa inválida.'], 422);
        }
        $registro = ['resposta' => $letra, 'correta' => $letra === $questao['resposta_correta']];
    }

    responder_json([
        'ok'               => true,
        'correta'          => $registro['correta'],
        'resposta_correta' => $questao['resposta_correta'],
        'comentario'       => $questao['gabarito_comentario'],
    ]);
}

// Dissertativa, passo 1: guarda o texto e libera o gabarito
if (isset($entrada['resposta'])) {
    if ($registro === null) {
        $texto = trim((string) $entrada['resposta']);
        if (mb_strlen($texto) < 3) {
            unset($_SESSION['simulado']['respostas'][$questaoId]);
            responder_json(['ok' => false, 'mensagem' => 'Escreva sua resposta antes de ver o gabarito.'], 422);
        }
        $registro = ['resposta' => mb_substr($texto, 0, 5000), 'correta' => null];
    }
    responder_json(['ok' => true, 'correta' => null, 'resposta_correta' => null, 'comentario' => $questao['gabarito_comentario']]);
}

// Dissertativa, passo 2: autoavaliação (só depois de ver o gabarito, e só uma vez)
$avaliacao = $entrada['autoavaliacao'] ?? '';
if ($registro === null || !in_array($avaliacao, ['acertei', 'errei'], true)) {
    responder_json(['ok' => false, 'mensagem' => 'Responda a questão antes de se autoavaliar.'], 422);
}
if ($registro['correta'] === null) {
    $registro['correta'] = $avaliacao === 'acertei';
}
responder_json(['ok' => true, 'correta' => $registro['correta'], 'resposta_correta' => null, 'comentario' => null]);
