<?php
/**
 * Exporta os dados do usuário em JSON (direito de acesso, LGPD).
 * A senha nunca é incluída.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login();
$uid = (int) $usuario['id'];
$pdo = db();

/** Executa uma consulta do usuário e devolve todas as linhas. */
$buscar = function (string $sql) use ($pdo, $uid): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
};

$dados = [
    'exportado_em' => date('c'),
    'conta' => [
        'nome' => $usuario['nome'], 'email' => $usuario['email'], 'tipo' => $usuario['tipo'],
        'plano' => $usuario['plano'], 'serie' => $usuario['serie'], 'escola' => $usuario['escola'],
        'objetivo' => $usuario['objetivo'], 'curso_desejado' => $usuario['curso_desejado'],
        'criado_em' => $usuario['criado_em'],
    ],
];

if ($usuario['tipo'] === 'aluno') {
    $dados['simulados'] = $buscar(
        'SELECT h.id, m.nome AS materia, h.pontuacao, h.total_questoes, h.origem, h.data_realizacao
           FROM historico_simulados h LEFT JOIN materias m ON m.id = h.materia_id WHERE h.usuario_id = ? ORDER BY h.data_realizacao'
    );
    $dados['sessoes_pomodoro'] = $buscar(
        'SELECT m.nome AS materia, s.minutos, s.concluida_em FROM sessoes_estudo s JOIN materias m ON m.id = s.materia_id
          WHERE s.usuario_id = ? ORDER BY s.concluida_em'
    );
    $dados['revisoes'] = $buscar('SELECT titulo, origem, intervalo_dias, data_agendada, status, concluida_em FROM revisoes WHERE usuario_id = ? ORDER BY data_agendada');
    $dados['caderno_de_erros'] = $buscar(
        'SELECT q.enunciado, c.resposta_dada, c.motivo, c.vezes_errada, c.superada, c.atualizado_em
           FROM caderno_erros c JOIN questoes q ON q.id = c.questao_id WHERE c.usuario_id = ?'
    );
    $dados['cronograma'] = $buscar(
        'SELECT m.nome AS materia, b.data, b.hora_inicio, b.hora_fim, b.status FROM cronograma_blocos b
           JOIN materias m ON m.id = b.materia_id WHERE b.usuario_id = ? ORDER BY b.data, b.hora_inicio'
    );
    $dados['materiais_ia'] = $buscar('SELECT tipo, titulo, criado_em FROM ia_geracoes WHERE usuario_id = ? ORDER BY criado_em');
    $dados['redacoes'] = $buscar(
        'SELECT t.titulo AS tema, r.data_tema, r.titulo, r.texto, r.palavras, r.linhas, r.status, r.nota, r.enviada_em
           FROM redacoes r JOIN temas_redacao t ON t.id = r.tema_id WHERE r.usuario_id = ? ORDER BY r.data_tema'
    );
    $dados['assinaturas'] = $buscar('SELECT ciclo, metodo, valor, status, criada_em, cancelada_em FROM assinaturas WHERE usuario_id = ? ORDER BY criada_em');
}

$arquivo = 'reserva-study-meus-dados-' . date('Y-m-d') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Cache-Control: no-store');
echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
