<?php
/**
 * Salva um ciclo de foco do Pomodoro.
 * Entrada (JSON): { materia_id, minutos, bloco_id? }
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login_api('aluno');
$uid = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$entrada  = entrada_json();
$materia  = materia_por_id((int) ($entrada['materia_id'] ?? 0));
$minutos  = (int) ($entrada['minutos'] ?? 0);
$bloco_id = (int) ($entrada['bloco_id'] ?? 0) ?: null;

if (!$materia) {
    responder_json(['ok' => false, 'mensagem' => 'Escolha uma matéria válida.'], 422);
}
if ($minutos < 1 || $minutos > 90) {
    responder_json(['ok' => false, 'mensagem' => 'Duração inválida.'], 422);
}

// O bloco precisa ser do próprio aluno
if ($bloco_id) {
    $stmt = db()->prepare('SELECT 1 FROM cronograma_blocos WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$bloco_id, $uid]);
    if (!$stmt->fetchColumn()) {
        $bloco_id = null;
    }
}

// Proteção simples contra envios repetidos: no máximo um ciclo por minuto
$stmt = db()->prepare('SELECT 1 FROM sessoes_estudo WHERE usuario_id = ? AND concluida_em > DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
$stmt->execute([$uid]);
if ($stmt->fetchColumn()) {
    responder_json(['ok' => false, 'mensagem' => 'Esse ciclo já foi registrado.'], 429);
}

db()->prepare('INSERT INTO sessoes_estudo (usuario_id, materia_id, bloco_id, minutos) VALUES (?, ?, ?, ?)')
    ->execute([$uid, $materia['id'], $bloco_id, $minutos]);

responder_json(['ok' => true, 'materia' => $materia['nome'], 'cor' => $materia['cor']]);
