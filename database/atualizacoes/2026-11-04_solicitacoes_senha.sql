USE reservastudy;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS solicitacoes_senha (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    email VARCHAR(150) NOT NULL,
    status ENUM('pendente','atendida','cancelada') NOT NULL DEFAULT 'pendente',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atendida_em DATETIME NULL,
    INDEX idx_solicitacoes_status_data (status, criado_em),
    CONSTRAINT fk_solicitacoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
