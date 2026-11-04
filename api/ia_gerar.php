<?php
/**
 * Gera flashcards ou quiz com IA e salva no banco.
 * Entrada (JSON): { tipo: "flashcards" | "quiz", texto, materia_id? }
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login_api('aluno');
$uid = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
}

if (!ia_disponivel()) {
    responder_json(['ok' => false, 'mensagem' => 'A geração com IA ainda não foi configurada. Avise o administrador.'], 503);
}

$restantes = ia_geracoes_restantes($usuario);
if ($restantes !== null && $restantes <= 0) {
    responder_json(['ok' => false, 'mensagem' => 'Suas gerações grátis deste mês acabaram. Conheça o plano Premium.'], 402);
}

$entrada = entrada_json();
$tipo    = ($entrada['tipo'] ?? '') === 'quiz' ? 'quiz' : 'flashcards';
$texto   = trim((string) ($entrada['texto'] ?? ''));
$materia = materia_por_id((int) ($entrada['materia_id'] ?? 0));

if (mb_strlen($texto) < 80) {
    responder_json(['ok' => false, 'mensagem' => 'Cole um texto com pelo menos 80 caracteres.'], 422);
}
if (mb_strlen($texto) > 12000) {
    responder_json(['ok' => false, 'mensagem' => 'O texto passou de 12.000 caracteres. Divida em partes menores.'], 422);
}

// Evita gerações em paralelo da mesma conta (cliques repetidos)
session_write_close();

try {
    $resultado = $tipo === 'quiz' ? ia_gerar_quiz($texto) : ia_gerar_flashcards($texto);
} catch (RuntimeException $erro) {
    responder_json(['ok' => false, 'mensagem' => $erro->getMessage()], 502);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO ia_geracoes (usuario_id, materia_id, tipo, titulo, texto_origem) VALUES (?, ?, ?, ?, ?)')
        ->execute([$uid, $materia['id'] ?? null, $tipo, mb_substr($resultado['titulo'], 0, 150), $texto]);
    $geracaoId = (int) $pdo->lastInsertId();

    if ($tipo === 'flashcards') {
        $inserir = $pdo->prepare('INSERT INTO flashcards (geracao_id, usuario_id, frente, verso) VALUES (?, ?, ?, ?)');
        foreach ($resultado['itens'] as $card) {
            $inserir->execute([$geracaoId, $uid, $card['frente'], $card['verso']]);
        }
    } else {
        $inserir = $pdo->prepare(
            'INSERT INTO quizzes_ia (geracao_id, usuario_id, pergunta, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, explicacao)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($resultado['itens'] as $q) {
            $inserir->execute([$geracaoId, $uid, $q['pergunta'], $q['alternativa_a'], $q['alternativa_b'],
                               $q['alternativa_c'], $q['alternativa_d'], $q['resposta_correta'], $q['explicacao']]);
        }
    }

    ia_registrar_uso($uid);
    $pdo->commit();
} catch (Throwable $erro) {
    $pdo->rollBack();
    error_log('[IA] Erro ao salvar: ' . $erro->getMessage());
    responder_json(['ok' => false, 'mensagem' => 'O material foi gerado, mas não conseguimos salvar. Tente de novo.'], 500);
}

responder_json(['ok' => true, 'redirecionar' => url('aluno/ia_ver.php?id=' . $geracaoId)]);
