<?php
/**
 * Cria/atualiza as contas padrão de professores e associa cada uma à matéria.
 * Execute pela raiz do projeto, após aplicar a migração SQL:
 *     php database/seed_professores.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

$materias = [
    'Espanhol' => 'professor.espanhol@reserva.study',
    'Inglês' => 'professor.ingles@reserva.study',
    'Português' => 'professor.portugues@reserva.study',
    'Filosofia' => 'professor.filosofia@reserva.study',
    'Geografia' => 'professor.geografia@reserva.study',
    'História' => 'professor.historia@reserva.study',
    'Sociologia' => 'professor.sociologia@reserva.study',
    'Biologia' => 'professor.biologia@reserva.study',
    'Física' => 'professor.fisica@reserva.study',
    'Química' => 'professor.quimica@reserva.study',
    'Matemática' => 'professor.matematica@reserva.study',
];

$pdo = db();
$pdo->beginTransaction();
try {
    $buscarMateria = $pdo->prepare('SELECT id FROM materias WHERE nome = ?');
    $salvarUsuario = $pdo->prepare(
        "INSERT INTO usuarios (nome, email, senha, tipo, plano, ativo)
         VALUES (?, ?, ?, 'professor', 'gratuito', 1)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), senha = VALUES(senha), tipo = 'professor', ativo = 1"
    );
    $buscarUsuario = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $vincular = $pdo->prepare(
        'INSERT INTO professor_materias (usuario_id, materia_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE materia_id = VALUES(materia_id)'
    );
    $hash = password_hash('123456', PASSWORD_DEFAULT);

    foreach ($materias as $nomeMateria => $email) {
        $buscarMateria->execute([$nomeMateria]);
        $materiaId = $buscarMateria->fetchColumn();
        if (!$materiaId) {
            throw new RuntimeException("Matéria não encontrada no banco: {$nomeMateria}");
        }

        $salvarUsuario->execute(['Professor de ' . $nomeMateria, $email, $hash]);
        $buscarUsuario->execute([$email]);
        $usuarioId = $buscarUsuario->fetchColumn();
        $vincular->execute([(int) $usuarioId, (int) $materiaId]);
    }

    $pdo->commit();
    echo count($materias) . " perfis de professor configurados. Senha padrão: 123456\n";
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $erro->getMessage() . "\n");
    exit(1);
}
