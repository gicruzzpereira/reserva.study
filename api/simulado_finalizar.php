<?php
/**
 * Finaliza o simulado em andamento.
 *
 * A pontuação é recalculada aqui, a partir das respostas conferidas no servidor
 * (sessão), e não a partir de nada enviado pelo JavaScript. Também:
 *   - grava o histórico e cada resposta;
 *   - coloca as questões erradas no caderno de erros;
 *   - marca como superadas as questões do caderno que o aluno acertou;
 *   - agenda as revisões espaçadas (1, 7 e 30 dias, mais reforço se < 60%).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login_api('aluno');
$uid = (int) $usuario['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$simulado = $_SESSION['simulado'] ?? null;
if (!$simulado) {
    responder_json(['ok' => false, 'mensagem' => 'Nenhum simulado em andamento.'], 409);
}

$total     = count($simulado['questoes']);
$respostas = $simulado['respostas'];
$acertos   = 0;
foreach ($simulado['questoes'] as $qid) {
    if (!empty($respostas[$qid]['correta'])) {
        $acertos++;
    }
}

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare(
        'INSERT INTO historico_simulados (usuario_id, materia_id, pontuacao, total_questoes, origem) VALUES (?, ?, ?, ?, ?)'
    )->execute([$uid, $simulado['materia_id'], $acertos, $total, match ($simulado['modo']) { 'caderno' => 'caderno_erros', 'geral' => 'geral', default => 'normal' }]);
    $simuladoId = (int) $pdo->lastInsertId();

    $salvarResposta = $pdo->prepare(
        'INSERT INTO respostas_simulado (simulado_id, questao_id, resposta_dada, correta) VALUES (?, ?, ?, ?)'
    );
    // Errou: entra no caderno (ou volta a ficar pendente, somando mais um erro)
    $registrarErro = $pdo->prepare(
        'INSERT INTO caderno_erros (usuario_id, questao_id, simulado_id, resposta_dada)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE vezes_errada = vezes_errada + 1, superada = 0,
                                 simulado_id = VALUES(simulado_id), resposta_dada = VALUES(resposta_dada)'
    );
    // Acertou uma questão que estava no caderno: marca como superada
    $superar = $pdo->prepare('UPDATE caderno_erros SET superada = 1 WHERE usuario_id = ? AND questao_id = ?');

    foreach ($simulado['questoes'] as $qid) {
        $resposta = $respostas[$qid]['resposta'] ?? null;
        $correta  = !empty($respostas[$qid]['correta']);

        $salvarResposta->execute([$simuladoId, $qid, $resposta, $correta ? 1 : 0]);

        if ($correta) {
            $superar->execute([$uid, $qid]);
        } elseif ($resposta !== null) {
            // Questões puladas (sem resposta) não entram no caderno
            $registrarErro->execute([$uid, $qid, $simuladoId, $resposta]);
        }
    }

    // Revisão espaçada (só para simulados de uma matéria)
    if ($simulado['modo'] === 'normal' && $simulado['materia_id']) {
        $materia = materia_por_id((int) $simulado['materia_id']);
        $percentual = $total ? $acertos / $total * 100 : 0;
        agendar_revisoes($uid, (int) $simulado['materia_id'], 'Revisar simulado de ' . $materia['nome'],
                         'simulado', $simuladoId, $percentual);
    }

    // Simulado geral: reforço para cada matéria com menos de 60% de acerto
    if ($simulado['modo'] === 'geral') {
        $marcadores = implode(',', array_fill(0, $total, '?'));
        $stmt = $pdo->prepare("SELECT id, materia_id FROM questoes WHERE id IN ($marcadores)");
        $stmt->execute($simulado['questoes']);
        $por_materia = [];
        foreach ($stmt->fetchAll() as $q) {
            $por_materia[$q['materia_id']][] = !empty($respostas[$q['id']]['correta']);
        }
        foreach ($por_materia as $materiaId => $resultados) {
            $pct = array_sum($resultados) / count($resultados) * 100;
            if ($pct < LIMITE_REFORCO) {
                $materia = materia_por_id((int) $materiaId);
                agendar_revisao_unica($uid, (int) $materiaId, 'Reforço de ' . $materia['nome'] . ' (simulado geral)', 'reforco', $simuladoId, 2);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $erro) {
    $pdo->rollBack();
    responder_json(['ok' => false, 'mensagem' => 'Não foi possível salvar o resultado. Tente de novo.'], 500);
}

unset($_SESSION['simulado']);

responder_json([
    'ok'           => true,
    'acertos'      => $acertos,
    'total'        => $total,
    'redirecionar' => url('aluno/resultado.php?id=' . $simuladoId),
]);
