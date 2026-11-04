<?php
/**
 * Marca um flashcard como "sabia" ou "nao_sabia".
 * "Não sabia" entra na revisão espaçada (revisão no dia seguinte).
 * Entrada (JSON): { id, status }
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login_api('aluno');
$uid = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$entrada = entrada_json();
$status  = $entrada['status'] ?? '';
if (!in_array($status, ['sabia', 'nao_sabia'], true)) {
    responder_json(['ok' => false, 'mensagem' => 'Resposta inválida.'], 422);
}

$stmt = db()->prepare(
    'SELECT f.id, f.frente, g.materia_id FROM flashcards f JOIN ia_geracoes g ON g.id = f.geracao_id
      WHERE f.id = ? AND f.usuario_id = ?'
);
$stmt->execute([(int) ($entrada['id'] ?? 0), $uid]);
$card = $stmt->fetch();
if (!$card) {
    responder_json(['ok' => false, 'mensagem' => 'Flashcard não encontrado.'], 404);
}

db()->prepare('UPDATE flashcards SET status = ? WHERE id = ?')->execute([$status, $card['id']]);

if ($status === 'nao_sabia') {
    // Uma revisão pendente por card basta
    $existe = db()->prepare("SELECT 1 FROM revisoes WHERE usuario_id = ? AND origem = 'flashcard' AND referencia_id = ? AND status = 'pendente'");
    $existe->execute([$uid, $card['id']]);
    if (!$existe->fetchColumn()) {
        agendar_revisao_unica($uid, $card['materia_id'] ? (int) $card['materia_id'] : null,
            'Flashcard: ' . mb_strimwidth($card['frente'], 0, 110, '...'), 'flashcard', (int) $card['id'], 1);
    }
}

responder_json(['ok' => true]);
