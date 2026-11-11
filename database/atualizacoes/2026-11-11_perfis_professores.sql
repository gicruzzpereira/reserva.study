-- Vínculo de cada conta de professor a uma única matéria.
-- Depois de aplicar este arquivo, execute: php database/seed_professores.php
CREATE TABLE IF NOT EXISTS professor_materias (
    usuario_id INT UNSIGNED NOT NULL PRIMARY KEY,
    materia_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_professor_materia (materia_id),
    CONSTRAINT fk_professor_materias_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_professor_materias_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE
) ENGINE=InnoDB;
