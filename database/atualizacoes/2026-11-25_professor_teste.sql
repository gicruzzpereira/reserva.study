USE reservastudy;
SET NAMES utf8mb4;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS acesso_todas_materias TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo;

INSERT INTO usuarios (nome, email, senha, tipo, acesso_todas_materias, plano, ativo)
VALUES (
    'Professor TESTE',
    'professor.teste@reserva.study',
    '$2y$10$r2m8kfCQsWIlM0m4LaDSI.M7D4Gh0lgLkuAJWMIgWWG1fWHVFGSou',
    'professor',
    1,
    'gratuito',
    1
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha = VALUES(senha),
    tipo = 'professor',
    acesso_todas_materias = 1,
    plano = 'gratuito',
    ativo = 1;
