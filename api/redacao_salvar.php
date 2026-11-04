<?php
/**
 * Salva o rascunho da redação do dia (chamado automaticamente enquanto o aluno escreve).
 * Entrada (JSON): { titulo, texto }
 * Saída: { ok, palavras, linhas, salvo_em }
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login_api('aluno');
$uid = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$hoje = date('Y-m-d');
$tema = tema_do_dia($hoje);
if (!$tema) {
    responder_json(['ok' => false, 'mensagem' => 'Nenhum tema cadastrado.'], 404);
}

$entrada = entrada_json();
$titulo  = mb_substr(trim((string) ($entrada['titulo'] ?? '')), 0, 150);
$texto   = rtrim((string) ($entrada['texto'] ?? ''));

if (mb_strlen($texto) > REDACAO_MAX_CARACTERES) {
    responder_json(['ok' => false, 'mensagem' => 'O texto passou do limite de ' . REDACAO_MAX_CARACTERES . ' caracteres.'], 422);
}

$existente = redacao_do_dia($uid, $hoje);
if ($existente && $existente['status'] === 'enviada') {
    responder_json(['ok' => false, 'mensagem' => 'A redação de hoje já foi enviada.'], 409);
}

$palavras = contar_palavras($texto);
$linhas   = estimar_linhas($texto);

db()->prepare(
    'INSERT INTO redacoes (usuario_id, tema_id, data_tema, titulo, texto, palavras, linhas)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), texto = VALUES(texto),
                             palavras = VALUES(palavras), linhas = VALUES(linhas)'
)->execute([$uid, $tema['id'], $hoje, $titulo ?: null, $texto, $palavras, $linhas]);

responder_json(['ok' => true, 'palavras' => $palavras, 'linhas' => $linhas, 'salvo_em' => date('H:i')]);
