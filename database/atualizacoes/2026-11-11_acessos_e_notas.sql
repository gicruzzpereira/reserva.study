USE reserva_study;
SET NAMES utf8mb4;

ALTER TABLE usuarios
    MODIFY tipo ENUM('aluno','admin','professor','suporte') NOT NULL DEFAULT 'aluno';

CREATE TABLE IF NOT EXISTS notas_alunos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id       INT UNSIGNED NOT NULL,
    materia_id     INT UNSIGNED NULL,
    professor_id   INT UNSIGNED NOT NULL,
    atualizado_por INT UNSIGNED NULL,
    avaliacao      VARCHAR(150) NOT NULL,
    nota           DECIMAL(6,2) UNSIGNED NOT NULL,
    comentario     TEXT NULL,
    data_avaliacao DATE NOT NULL,
    criado_em      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_notas_aluno_data (aluno_id, data_avaliacao),
    KEY idx_notas_materia (materia_id),
    CONSTRAINT fk_notas_aluno FOREIGN KEY (aluno_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_notas_materia FOREIGN KEY (materia_id) REFERENCES materias (id) ON DELETE SET NULL,
    CONSTRAINT fk_notas_professor FOREIGN KEY (professor_id) REFERENCES usuarios (id) ON DELETE RESTRICT,
    CONSTRAINT fk_notas_atualizado_por FOREIGN KEY (atualizado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB;
