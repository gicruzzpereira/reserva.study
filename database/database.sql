-- =====================================================================
-- RESERVA STUDY — Plataforma de estudos para o ensino médio
-- Script de criação do banco de dados + dados de exemplo (seed)
-- Compatível com MySQL 5.7+ / MariaDB 10.4+ (XAMPP)
-- =====================================================================

DROP DATABASE IF EXISTS reserva_study;
CREATE DATABASE reserva_study
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE reserva_study;

SET NAMES utf8mb4;
SET time_zone = '-03:00';

-- ---------------------------------------------------------------------
-- USUÁRIOS (alunos e administradores)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(100)  NOT NULL,
    email            VARCHAR(150)  NOT NULL,
    senha            VARCHAR(255)  NOT NULL,                 -- hash gerado por password_hash()
    tipo             ENUM('aluno','admin')        NOT NULL DEFAULT 'aluno',
    plano            ENUM('gratuito','premium')   NOT NULL DEFAULT 'gratuito',
    geracoes_ia_mes  TINYINT UNSIGNED NOT NULL DEFAULT 0,    -- gerações de IA usadas no mês corrente
    geracoes_ia_ref  CHAR(7)       NULL,                     -- mês de referência do contador (AAAA-MM)
    serie            ENUM('1','2','3','cursinho','outro') NULL,  -- ano do ensino médio
    escola           VARCHAR(120)  NULL,
    objetivo         VARCHAR(60)   NULL,                     -- ENEM, FUVEST, etc.
    curso_desejado   VARCHAR(80)   NULL,
    foto             VARCHAR(100)  NULL,                     -- arquivo em uploads/avatars/
    ativo            TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_tipo (tipo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MATÉRIAS
-- ---------------------------------------------------------------------
CREATE TABLE materias (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(80)  NOT NULL,
    descricao  VARCHAR(255) NULL,
    cor        CHAR(7)      NOT NULL DEFAULT '#F5A201',     -- cor em hexadecimal
    icone      VARCHAR(40)  NOT NULL DEFAULT 'livro',      -- chave do ícone SVG (includes/icones.php)
    criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_materias_nome (nome)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- QUESTÕES
-- ---------------------------------------------------------------------
CREATE TABLE questoes (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_id           INT UNSIGNED NOT NULL,
    tipo                 ENUM('multipla','dissertativa') NOT NULL,
    enunciado            TEXT         NOT NULL,
    alternativa_a        TEXT         NULL,
    alternativa_b        TEXT         NULL,
    alternativa_c        TEXT         NULL,
    alternativa_d        TEXT         NULL,
    resposta_correta     CHAR(1)      NULL,                 -- A, B, C ou D (apenas múltipla escolha)
    gabarito_comentario  TEXT         NULL,                 -- gabarito (dissertativa) ou comentário explicativo
    dificuldade          ENUM('facil','medio','dificil') NOT NULL DEFAULT 'medio',
    criado_em            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_questoes_materia_tipo (materia_id, tipo),
    CONSTRAINT fk_questoes_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- HISTÓRICO DE SIMULADOS
-- ---------------------------------------------------------------------
CREATE TABLE historico_simulados (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT UNSIGNED NOT NULL,
    materia_id       INT UNSIGNED NULL,                     -- NULL = simulado misto (ex.: refazer erros)
    pontuacao        SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- quantidade de acertos
    total_questoes   SMALLINT UNSIGNED NOT NULL,
    origem           ENUM('normal','caderno_erros','geral') NOT NULL DEFAULT 'normal',
    data_realizacao  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_historico_usuario_data (usuario_id, data_realizacao),
    KEY idx_historico_materia (materia_id),
    CONSTRAINT fk_historico_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_historico_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- RESPOSTAS DE CADA SIMULADO
-- ---------------------------------------------------------------------
CREATE TABLE respostas_simulado (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    simulado_id    INT UNSIGNED NOT NULL,
    questao_id     INT UNSIGNED NOT NULL,
    resposta_dada  TEXT         NULL,                       -- letra (múltipla) ou texto (dissertativa)
    correta        TINYINT(1)   NOT NULL DEFAULT 0,         -- dissertativa: vem da autoavaliação
    KEY idx_respostas_simulado (simulado_id),
    KEY idx_respostas_questao (questao_id),
    CONSTRAINT fk_respostas_simulado FOREIGN KEY (simulado_id)
        REFERENCES historico_simulados (id) ON DELETE CASCADE,
    CONSTRAINT fk_respostas_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CRONOGRAMA: preferências do aluno
-- ---------------------------------------------------------------------
CREATE TABLE cronograma_preferencias (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    horas_seg     DECIMAL(3,1) NOT NULL DEFAULT 0,
    horas_ter     DECIMAL(3,1) NOT NULL DEFAULT 0,
    horas_qua     DECIMAL(3,1) NOT NULL DEFAULT 0,
    horas_qui     DECIMAL(3,1) NOT NULL DEFAULT 0,
    horas_sex     DECIMAL(3,1) NOT NULL DEFAULT 0,
    horas_sab     DECIMAL(3,1) NOT NULL DEFAULT 0,
    horas_dom     DECIMAL(3,1) NOT NULL DEFAULT 0,
    hora_inicio   TIME         NOT NULL DEFAULT '14:00:00', -- horário em que os blocos começam
    data_prova    DATE         NULL,
    atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_preferencias_usuario (usuario_id),
    CONSTRAINT fk_preferencias_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Matérias escolhidas para o cronograma e seus pesos (1 a 5)
CREATE TABLE cronograma_materias (
    usuario_id  INT UNSIGNED NOT NULL,
    materia_id  INT UNSIGNED NOT NULL,
    peso        TINYINT UNSIGNED NOT NULL DEFAULT 3,
    PRIMARY KEY (usuario_id, materia_id),
    CONSTRAINT fk_cron_materias_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_cron_materias_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Blocos de estudo gerados automaticamente
CREATE TABLE cronograma_blocos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    materia_id   INT UNSIGNED NOT NULL,
    data         DATE         NOT NULL,
    hora_inicio  TIME         NOT NULL,
    hora_fim     TIME         NOT NULL,
    tipo         ENUM('estudo','revisao') NOT NULL DEFAULT 'estudo',
    status       ENUM('pendente','concluido','redistribuido') NOT NULL DEFAULT 'pendente',
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_blocos_usuario_data (usuario_id, data),
    CONSTRAINT fk_blocos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_blocos_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- SESSÕES DE ESTUDO (Pomodoro)
-- ---------------------------------------------------------------------
CREATE TABLE sessoes_estudo (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    materia_id    INT UNSIGNED NOT NULL,
    bloco_id      INT UNSIGNED NULL,                        -- bloco do cronograma que originou a sessão
    minutos       SMALLINT UNSIGNED NOT NULL,
    concluida_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sessoes_usuario_data (usuario_id, concluida_em),
    CONSTRAINT fk_sessoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_sessoes_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE,
    CONSTRAINT fk_sessoes_bloco FOREIGN KEY (bloco_id)
        REFERENCES cronograma_blocos (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- REVISÕES ESPAÇADAS (1, 7 e 30 dias + reforços)
-- ---------------------------------------------------------------------
CREATE TABLE revisoes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    materia_id      INT UNSIGNED NULL,
    titulo          VARCHAR(150) NOT NULL,
    origem          ENUM('simulado','conteudo','flashcard','reforco') NOT NULL DEFAULT 'simulado',
    referencia_id   INT UNSIGNED NULL,                      -- id do simulado/flashcard de origem
    intervalo_dias  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    data_agendada   DATE         NOT NULL,
    status          ENUM('pendente','concluida') NOT NULL DEFAULT 'pendente',
    concluida_em    DATETIME     NULL,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_revisoes_usuario_status_data (usuario_id, status, data_agendada),
    CONSTRAINT fk_revisoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_revisoes_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CADERNO DE ERROS
-- ---------------------------------------------------------------------
CREATE TABLE caderno_erros (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    questao_id     INT UNSIGNED NOT NULL,
    simulado_id    INT UNSIGNED NULL,                       -- último simulado em que errou
    resposta_dada  TEXT         NULL,
    motivo         ENUM('atencao','conteudo','interpretacao') NULL,
    vezes_errada   SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    superada       TINYINT(1)   NOT NULL DEFAULT 0,
    criado_em      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_caderno_usuario_questao (usuario_id, questao_id),
    KEY idx_caderno_motivo (usuario_id, motivo),
    CONSTRAINT fk_caderno_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_caderno_questao FOREIGN KEY (questao_id)
        REFERENCES questoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_caderno_simulado FOREIGN KEY (simulado_id)
        REFERENCES historico_simulados (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MÓDULO PREMIUM: gerações de IA, flashcards e quizzes
-- ---------------------------------------------------------------------
CREATE TABLE ia_geracoes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    materia_id    INT UNSIGNED NULL,
    tipo          ENUM('flashcards','quiz') NOT NULL,
    titulo        VARCHAR(150) NOT NULL,
    texto_origem  MEDIUMTEXT   NULL,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_geracoes_usuario (usuario_id, criado_em),
    CONSTRAINT fk_geracoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_geracoes_materia FOREIGN KEY (materia_id)
        REFERENCES materias (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE flashcards (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    geracao_id   INT UNSIGNED NOT NULL,
    usuario_id   INT UNSIGNED NOT NULL,
    frente       TEXT         NOT NULL,
    verso        TEXT         NOT NULL,
    status       ENUM('novo','sabia','nao_sabia') NOT NULL DEFAULT 'novo',
    criado_em    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_flashcards_geracao (geracao_id),
    KEY idx_flashcards_usuario_status (usuario_id, status),
    CONSTRAINT fk_flashcards_geracao FOREIGN KEY (geracao_id)
        REFERENCES ia_geracoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_flashcards_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quizzes_ia (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    geracao_id        INT UNSIGNED NOT NULL,
    usuario_id        INT UNSIGNED NOT NULL,
    pergunta          TEXT         NOT NULL,
    alternativa_a     TEXT         NOT NULL,
    alternativa_b     TEXT         NOT NULL,
    alternativa_c     TEXT         NOT NULL,
    alternativa_d     TEXT         NOT NULL,
    resposta_correta  CHAR(1)      NOT NULL,
    explicacao        TEXT         NULL,
    KEY idx_quizzes_geracao (geracao_id),
    CONSTRAINT fk_quizzes_geracao FOREIGN KEY (geracao_id)
        REFERENCES ia_geracoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_quizzes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ---------------------------------------------------------------------
-- ASSINATURAS DO PREMIUM (checkout demonstrativo, sem cobrança real)
-- ---------------------------------------------------------------------
CREATE TABLE assinaturas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    ciclo         ENUM('mensal','anual') NOT NULL,
    metodo        ENUM('cartao','pix')   NOT NULL,
    valor         DECIMAL(8,2) NOT NULL,              -- valor exibido no checkout (nada é cobrado)
    status        ENUM('ativa','cancelada') NOT NULL DEFAULT 'ativa',
    criada_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelada_em  DATETIME     NULL,
    KEY idx_assinaturas_usuario (usuario_id, status),
    CONSTRAINT fk_assinaturas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
-- DADOS DE EXEMPLO (SEED)
-- =====================================================================

-- Usuários
--   admin@reservastudy.com / admin123
--   aluno@reservastudy.com / aluno123
INSERT INTO usuarios (id, nome, email, senha, tipo, plano) VALUES
(1, 'Administrador', 'admin@reservastudy.com', '$2y$10$4l3XPqqNp7jOBeg/3DkiFe4uqrIyzqRBYVNBwcJre1fV0yB6wcIsm', 'admin', 'premium'),
(2, 'Laura Fialho',  'aluno@reservastudy.com', '$2y$10$HO1nsXX.SCQ0tMX2sNQrQOsiGMyuXB6ylMND/kRYPnsAlQRNMpOd6', 'aluno', 'gratuito');

-- Perfil de exemplo da aluna de teste
UPDATE usuarios SET serie = '3', escola = 'Colégio Estadual Machado de Assis', objetivo = 'ENEM', curso_desejado = 'Medicina'
 WHERE id = 2;

-- Matérias
INSERT INTO materias (id, nome, descricao, cor, icone) VALUES
(1, 'Matemática', 'Álgebra, funções, geometria, probabilidade e matemática financeira.', '#FFD35B', 'calculadora'),
(2, 'Física',     'Cinemática, dinâmica, energia, eletricidade e termologia.',          '#A8E8F9', 'atomo'),
(3, 'Química',    'Estrutura atômica, ligações, estequiometria e soluções.',            '#B9A4FF', 'frasco'),
(4, 'Biologia',   'Citologia, genética, ecologia, evolução e fisiologia.',              '#5CC48A', 'folha'),
(5, 'História',   'História do Brasil e História Geral.',                               '#FF8A65', 'coluna'),
(6, 'Geografia',  'Cartografia, climatologia, geopolítica e espaço urbano.',            '#4DA3FF', 'globo'),
(7, 'Português',  'Gramática, interpretação de texto e literatura.',                    '#F78FC0', 'pena');

-- ---------------------------------------------------------------------
-- Questões — Matemática
-- ---------------------------------------------------------------------
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(1, 'multipla', 'Quais são as raízes da equação x² − 5x + 6 = 0?',
 '1 e 6', '2 e 3', '−2 e −3', '−1 e 6', 'B',
 'Pela soma e produto: as raízes somam 5 e multiplicadas dão 6. Os números são 2 e 3. Conferindo: 2² − 5·2 + 6 = 0.', 'facil'),
(1, 'multipla', 'Em uma progressão aritmética, o primeiro termo é 3 e a razão é 4. Qual é o 10º termo?',
 '36', '39', '43', '40', 'B',
 'Termo geral da PA: aₙ = a₁ + (n − 1)·r. Logo, a₁₀ = 3 + 9·4 = 39.', 'medio'),
(1, 'multipla', 'Dada a função f(x) = 2x + 3, qual é o valor de f(f(1))?',
 '10', '11', '13', '15', 'C',
 'Primeiro f(1) = 2·1 + 3 = 5. Depois f(5) = 2·5 + 3 = 13.', 'medio'),
(1, 'multipla', 'Ao lançar dois dados comuns, qual é a probabilidade de a soma das faces ser 7?',
 '1/6', '1/12', '7/36', '1/9', 'A',
 'Há 36 resultados possíveis. Os favoráveis são (1,6), (2,5), (3,4), (4,3), (5,2) e (6,1): 6 casos. P = 6/36 = 1/6.', 'dificil'),
(1, 'dissertativa', 'Explique, com suas palavras, o que significa log₂ 8 = 3 e calcule o valor de log₂ 32 + log₃ 9.',
 NULL, NULL, NULL, NULL, NULL,
 'log₂ 8 = 3 significa que 3 é o expoente ao qual a base 2 deve ser elevada para resultar em 8 (2³ = 8). Assim, log₂ 32 = 5 (pois 2⁵ = 32) e log₃ 9 = 2 (pois 3² = 9). Resultado: 5 + 2 = 7.', 'medio'),
(1, 'dissertativa', 'Um capital de R$ 1.000,00 é aplicado a juros compostos de 10% ao mês durante 3 meses. Calcule o montante final e compare com o montante que seria obtido a juros simples, nas mesmas condições.',
 NULL, NULL, NULL, NULL, NULL,
 'Juros compostos: M = C·(1 + i)ⁿ = 1000·(1,1)³ = R$ 1.331,00. Juros simples: M = C·(1 + i·n) = 1000·(1 + 0,3) = R$ 1.300,00. Os juros compostos rendem R$ 31,00 a mais, porque os juros de cada mês passam a render juros nos meses seguintes.', 'dificil');

-- Questões — Física
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(2, 'multipla', 'Um carro percorre 120 km em 2 horas. Qual é a sua velocidade média?',
 '50 km/h', '60 km/h', '240 km/h', '120 km/h', 'B',
 'Velocidade média = distância ÷ tempo = 120 km ÷ 2 h = 60 km/h.', 'facil'),
(2, 'multipla', 'Uma força resultante de 10 N atua sobre um corpo de massa 2 kg. Qual é a aceleração adquirida?',
 '20 m/s²', '0,2 m/s²', '5 m/s²', '12 m/s²', 'C',
 'Pela 2ª Lei de Newton, F = m·a. Logo, a = F/m = 10/2 = 5 m/s².', 'medio'),
(2, 'multipla', 'Dois resistores de 4 Ω e 6 Ω são ligados em série a uma fonte de 20 V. Qual é a corrente elétrica no circuito?',
 '2 A', '5 A', '0,5 A', '200 A', 'A',
 'Em série, a resistência equivalente é a soma: 4 + 6 = 10 Ω. Pela Lei de Ohm, i = U/R = 20/10 = 2 A.', 'medio'),
(2, 'multipla', 'Um objeto é abandonado do repouso a 45 m de altura. Desprezando a resistência do ar e usando g = 10 m/s², quanto tempo ele leva para atingir o solo?',
 '3 s', '4,5 s', '9 s', '2 s', 'A',
 'Na queda livre, h = g·t²/2. Então 45 = 5·t², t² = 9 e t = 3 s.', 'dificil'),
(2, 'dissertativa', 'Explique a diferença entre calor e temperatura, dando um exemplo do cotidiano.',
 NULL, NULL, NULL, NULL, NULL,
 'Temperatura é a grandeza que mede o grau de agitação das partículas de um corpo. Calor é a energia térmica em trânsito, que flui espontaneamente do corpo de maior temperatura para o de menor temperatura. Exemplo: ao segurar uma xícara de café quente, calor flui da xícara para a mão até que as temperaturas se igualem (equilíbrio térmico).', 'medio'),
(2, 'dissertativa', 'Um bloco de 2 kg desliza a 10 m/s em uma superfície horizontal sem atrito e sobe uma rampa também sem atrito. Calcule a energia cinética inicial do bloco e a altura máxima que ele atinge (g = 10 m/s²).',
 NULL, NULL, NULL, NULL, NULL,
 'Energia cinética: Ec = m·v²/2 = 2·10²/2 = 100 J. Sem atrito, a energia mecânica se conserva: toda a Ec vira energia potencial no ponto mais alto. m·g·h = 100 → 2·10·h = 100 → h = 5 m.', 'dificil');

-- Questões — Química
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(3, 'multipla', 'Qual é o símbolo químico do elemento sódio?',
 'So', 'S', 'Na', 'Sd', 'C',
 'O símbolo Na vem do latim natrium. O símbolo S pertence ao enxofre.', 'facil'),
(3, 'multipla', 'Uma solução aquosa apresenta concentração de íons H⁺ igual a 10⁻³ mol/L a 25 °C. Qual é o pH e a classificação dessa solução?',
 'pH 3, ácida', 'pH 3, básica', 'pH 11, básica', 'pH 7, neutra', 'A',
 'pH = −log[H⁺] = −log(10⁻³) = 3. Como o pH é menor que 7 (a 25 °C), a solução é ácida.', 'medio'),
(3, 'multipla', 'Qual é a massa molar da água (H₂O)? Dados: H = 1 g/mol; O = 16 g/mol.',
 '17 g/mol', '18 g/mol', '16 g/mol', '20 g/mol', 'B',
 'H₂O tem 2 átomos de hidrogênio e 1 de oxigênio: 2·1 + 16 = 18 g/mol.', 'medio'),
(3, 'multipla', 'Na combustão completa do metano, CH₄ + a O₂ → CO₂ + b H₂O, quais são os coeficientes a e b que balanceiam a equação?',
 'a = 1 e b = 2', 'a = 2 e b = 1', 'a = 2 e b = 2', 'a = 3 e b = 2', 'C',
 'Os 4 H do metano formam 2 H₂O (b = 2). No lado dos produtos há 2 O no CO₂ + 2 O na água = 4 O, ou seja, 2 O₂ (a = 2).', 'dificil'),
(3, 'dissertativa', 'Diferencie ligação iônica de ligação covalente e dê um exemplo de substância formada por cada uma delas.',
 NULL, NULL, NULL, NULL, NULL,
 'Ligação iônica: transferência de elétrons entre um metal e um ametal, formando íons de cargas opostas que se atraem (ex.: NaCl, o sal de cozinha). Ligação covalente: compartilhamento de pares de elétrons entre ametais (ou ametal e hidrogênio) (ex.: H₂O, CO₂, O₂).', 'medio'),
(3, 'dissertativa', 'Quantos mols e quantas moléculas existem em 88 g de gás carbônico (CO₂)? Dados: C = 12 g/mol; O = 16 g/mol; constante de Avogadro = 6,0 × 10²³.',
 NULL, NULL, NULL, NULL, NULL,
 'Massa molar do CO₂ = 12 + 2·16 = 44 g/mol. Número de mols: 88 ÷ 44 = 2 mol. Número de moléculas: 2 × 6,0 × 10²³ = 1,2 × 10²⁴ moléculas.', 'dificil');

-- Questões — Biologia
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(4, 'multipla', 'Qual organela celular é responsável pela respiração celular e pela produção da maior parte do ATP?',
 'Ribossomo', 'Mitocôndria', 'Lisossomo', 'Complexo golgiense', 'B',
 'A mitocôndria realiza o ciclo de Krebs e a cadeia respiratória, etapas que produzem a maior parte do ATP da célula.', 'facil'),
(4, 'multipla', 'No cruzamento entre dois indivíduos heterozigotos (Aa × Aa), com dominância completa, qual é a proporção fenotípica esperada na descendência?',
 '1 : 2 : 1', '3 : 1', '1 : 1', '9 : 3 : 3 : 1', 'B',
 'Os genótipos esperados são 1 AA : 2 Aa : 1 aa. Como AA e Aa têm o mesmo fenótipo (dominante), a proporção fenotípica é 3 : 1.', 'medio'),
(4, 'multipla', 'Qual gás é liberado pelas plantas como produto da fotossíntese?',
 'Gás carbônico (CO₂)', 'Nitrogênio (N₂)', 'Oxigênio (O₂)', 'Metano (CH₄)', 'C',
 'Na fotossíntese, a planta absorve CO₂ e água e, com energia luminosa, produz glicose e libera O₂, que vem da quebra da molécula de água (fotólise).', 'medio'),
(4, 'multipla', 'Na molécula de RNA, a base nitrogenada timina (presente no DNA) é substituída por:',
 'Adenina', 'Citosina', 'Guanina', 'Uracila', 'D',
 'O RNA possui as bases A, U, C e G. A uracila ocupa o lugar da timina e também pareia com a adenina.', 'dificil'),
(4, 'dissertativa', 'Explique as principais diferenças entre mitose e meiose quanto à finalidade e ao número de células e cromossomos formados.',
 NULL, NULL, NULL, NULL, NULL,
 'A mitose serve para crescimento e regeneração: uma célula origina 2 células-filhas geneticamente iguais e com o mesmo número de cromossomos (2n → 2n). A meiose forma gametas (ou esporos): uma célula origina 4 células com metade dos cromossomos (2n → n) e com variabilidade genética, graças ao crossing-over e à segregação independente.', 'medio'),
(4, 'dissertativa', 'O que é seleção natural? Explique o conceito usando como exemplo o surgimento de bactérias resistentes a antibióticos.',
 NULL, NULL, NULL, NULL, NULL,
 'Seleção natural é o processo em que indivíduos com características mais vantajosas para o ambiente sobrevivem e se reproduzem mais, transmitindo essas características. Numa população de bactérias já existem, por mutação, algumas resistentes. O antibiótico elimina as sensíveis e as resistentes sobrevivem e se multiplicam, tornando a população predominantemente resistente. O antibiótico não cria a resistência: ele seleciona.', 'dificil');

-- Questões — História
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(5, 'multipla', 'Em que ano foi proclamada a Independência do Brasil?',
 '1500', '1808', '1822', '1889', 'C',
 'A Independência foi proclamada por D. Pedro I em 7 de setembro de 1822. 1808 é a chegada da Família Real e 1889, a Proclamação da República.', 'facil'),
(5, 'multipla', 'A Lei Áurea, que aboliu a escravidão no Brasil em 1888, foi assinada por:',
 'D. Pedro I', 'Princesa Isabel', 'D. Pedro II', 'Marechal Deodoro da Fonseca', 'B',
 'A Princesa Isabel assinou a Lei Áurea em 13 de maio de 1888, como regente, enquanto D. Pedro II estava fora do país.', 'medio'),
(5, 'multipla', 'Qual era o lema da Revolução Francesa (1789)?',
 'Liberdade, Igualdade, Fraternidade', 'Ordem e Progresso', 'Paz, Terra e Pão', 'Deus, Pátria e Família', 'A',
 '"Liberdade, Igualdade, Fraternidade" sintetizava os ideais iluministas da burguesia revolucionária. "Paz, Terra e Pão" é um lema da Revolução Russa.', 'medio'),
(5, 'multipla', 'A Consolidação das Leis do Trabalho (CLT) foi criada em 1943, durante qual período da história brasileira?',
 'República Velha', 'Estado Novo', 'Governo Juscelino Kubitschek', 'Ditadura Militar', 'B',
 'A CLT foi decretada por Getúlio Vargas em 1943, no Estado Novo (1937–1945), período autoritário marcado pelo trabalhismo e pela centralização do poder.', 'dificil'),
(5, 'dissertativa', 'Cite e explique pelo menos duas causas da Primeira Guerra Mundial (1914–1918).',
 NULL, NULL, NULL, NULL, NULL,
 'Entre as causas estão: o imperialismo e a disputa por mercados e colônias entre as potências europeias; o nacionalismo exacerbado (como o pan-eslavismo e o revanchismo francês após a perda da Alsácia-Lorena); a corrida armamentista; e a política de alianças (Tríplice Aliança × Tríplice Entente). O estopim foi o assassinato do arquiduque Francisco Ferdinando, em Sarajevo, em 1914.', 'medio'),
(5, 'dissertativa', 'O que foi o Ato Institucional nº 5 (AI-5), de 1968, e quais foram suas principais consequências?',
 NULL, NULL, NULL, NULL, NULL,
 'O AI-5 foi o mais duro dos atos institucionais da Ditadura Militar, decretado pelo presidente Costa e Silva em dezembro de 1968. Ele permitiu fechar o Congresso, cassar mandatos, suspender direitos políticos e o habeas corpus para crimes políticos. Consequências: intensificação da censura, das prisões, da tortura e da perseguição a opositores, inaugurando os chamados "anos de chumbo".', 'dificil');

-- Questões — Geografia
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(6, 'multipla', 'Qual é o maior bioma brasileiro em extensão territorial?',
 'Cerrado', 'Amazônia', 'Caatinga', 'Pampa', 'B',
 'A Amazônia ocupa cerca de 49% do território brasileiro. O Cerrado é o segundo maior bioma.', 'facil'),
(6, 'multipla', 'Em um mapa com escala 1 : 100.000, uma distância de 5 cm corresponde, na realidade, a:',
 '500 m', '5 km', '50 km', '0,5 km', 'B',
 '1 cm no mapa equivale a 100.000 cm = 1 km na realidade. Portanto, 5 cm correspondem a 5 km.', 'medio'),
(6, 'multipla', 'Como se chama o fenômeno climático caracterizado pelo aquecimento anormal das águas superficiais do Oceano Pacífico Equatorial?',
 'La Niña', 'El Niño', 'Monções', 'Inversão térmica', 'B',
 'O El Niño é o aquecimento anormal do Pacífico Equatorial; no Brasil, costuma provocar secas no Norte e Nordeste e chuvas no Sul. A La Niña é o resfriamento dessas águas.', 'medio'),
(6, 'multipla', 'Brasília está no fuso UTC−3 e Londres, no fuso UTC+0 (sem horário de verão). Quando são 15h em Brasília, que horas são em Londres?',
 '12h', '18h', '15h', '21h', 'B',
 'Londres está 3 horas adiantada em relação a Brasília (está a leste). Logo, 15h + 3h = 18h.', 'dificil'),
(6, 'dissertativa', 'Explique o que é o fenômeno da ilha de calor urbana e cite duas de suas causas.',
 NULL, NULL, NULL, NULL, NULL,
 'Ilha de calor é a elevação da temperatura nas áreas centrais e mais urbanizadas de uma cidade em relação às áreas periféricas e rurais. Causas: impermeabilização do solo com asfalto e concreto (que absorvem e irradiam calor), a escassez de áreas verdes, a concentração de edifícios que dificultam a circulação do ar e a emissão de calor e poluentes por veículos e indústrias.', 'medio'),
(6, 'dissertativa', 'Discuta as causas e as consequências do êxodo rural no Brasil ao longo do século XX.',
 NULL, NULL, NULL, NULL, NULL,
 'Causas: concentração fundiária, mecanização do campo (que reduziu a necessidade de mão de obra), falta de infraestrutura e de serviços no meio rural e atração exercida pela industrialização das cidades, principalmente do Sudeste. Consequências: urbanização acelerada e desordenada, crescimento de favelas e periferias, déficit habitacional, sobrecarga de serviços públicos e aumento do trabalho informal.', 'dificil');

-- Questões — Português
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade) VALUES
(7, 'multipla', 'Na frase "Ela resolveu o exercício rapidamente", a palavra "rapidamente" pertence a qual classe gramatical?',
 'Adjetivo', 'Advérbio', 'Substantivo', 'Verbo', 'B',
 '"Rapidamente" modifica o verbo "resolveu", indicando o modo como a ação aconteceu: é um advérbio de modo. Muitos advérbios de modo terminam em "-mente".', 'facil'),
(7, 'multipla', 'Qual figura de linguagem está presente em "Seus olhos são duas estrelas"?',
 'Metonímia', 'Metáfora', 'Hipérbole', 'Ironia', 'B',
 'Há uma comparação implícita, sem conectivo ("como", "tal qual"): os olhos são identificados com estrelas pelo brilho. Isso é metáfora.', 'medio'),
(7, 'multipla', 'Qual opção completa corretamente a frase: "Amanhã vou ___ escola mais cedo."?',
 'a', 'à', 'há', 'ah', 'B',
 'O verbo "ir" exige a preposição "a" (quem vai, vai a algum lugar) e "escola" é palavra feminina que admite o artigo "a". A fusão a + a gera a crase: "à escola".', 'medio'),
(7, 'multipla', 'Em "Embora estivesse cansado, terminou todo o trabalho", a oração destacada pela conjunção "embora" é classificada como subordinada adverbial:',
 'Causal', 'Concessiva', 'Condicional', 'Final', 'B',
 '"Embora" introduz uma ideia contrária à principal que, mesmo assim, não impede que ela aconteça: é uma oração subordinada adverbial concessiva.', 'dificil'),
(7, 'dissertativa', 'Explique a diferença entre linguagem denotativa e linguagem conotativa, dando um exemplo de cada.',
 NULL, NULL, NULL, NULL, NULL,
 'Denotação é o uso da palavra no seu sentido literal, de dicionário (ex.: "O gato subiu no telhado"). Conotação é o uso da palavra em sentido figurado, que depende do contexto (ex.: "Aquele jogador é um gato", no sentido de bonito; ou "Ela tem um coração de pedra").', 'medio'),
(7, 'dissertativa', 'Cite três características do Romantismo brasileiro e indique um autor representativo desse movimento.',
 NULL, NULL, NULL, NULL, NULL,
 'Características: subjetivismo e sentimentalismo; idealização da mulher e do amor; nacionalismo e valorização da natureza; indianismo (o índio como herói nacional); escapismo e, na 2ª geração, o pessimismo do "mal do século". Autores: José de Alencar (Iracema, O Guarani), Gonçalves Dias, Álvares de Azevedo e Castro Alves.', 'dificil');

-- ---------------------------------------------------------------------
-- Filosofia e Sociologia (matérias 8 e 9)
-- ---------------------------------------------------------------------
INSERT INTO materias (nome, descricao, cor, icone)
SELECT 'Filosofia', 'Filosofia antiga, moderna e contemporânea, ética e política.', '#3FD0C9', 'lampada'
 WHERE NOT EXISTS (SELECT 1 FROM materias WHERE nome = 'Filosofia');

INSERT INTO materias (nome, descricao, cor, icone)
SELECT 'Sociologia', 'Clássicos da sociologia, cultura, trabalho, poder e sociedade.', '#B8E06A', 'pessoas'
 WHERE NOT EXISTS (SELECT 1 FROM materias WHERE nome = 'Sociologia');

SET @filosofia  = (SELECT id FROM materias WHERE nome = 'Filosofia');
SET @sociologia = (SELECT id FROM materias WHERE nome = 'Sociologia');

-- Só insere as questões se a matéria ainda não tiver nenhuma
SET @tem_fil = (SELECT COUNT(*) FROM questoes WHERE materia_id = @filosofia);
SET @tem_soc = (SELECT COUNT(*) FROM questoes WHERE materia_id = @sociologia);

-- ---------------------------------------------------------------------
-- Questões: Filosofia
-- ---------------------------------------------------------------------
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT * FROM (
    SELECT @filosofia AS m, 'multipla' AS t,
     'Qual filósofo grego ficou conhecido pelo método da maiêutica e pela frase "Só sei que nada sei"?' AS e,
     'Platão' AS a, 'Sócrates' AS b, 'Aristóteles' AS c, 'Tales de Mileto' AS d, 'B' AS r,
     'Sócrates conduzia o diálogo com perguntas para que o interlocutor reconhecesse a própria ignorância e chegasse ao conhecimento por si mesmo: a maiêutica, o "parto das ideias".' AS g, 'facil' AS n
    UNION ALL SELECT @filosofia, 'multipla',
     'A "Alegoria da Caverna", que compara a busca pelo conhecimento à saída de prisioneiros da escuridão para a luz, está na obra A República, de:',
     'Aristóteles', 'Descartes', 'Platão', 'Immanuel Kant', 'C',
     'Na alegoria de Platão, as sombras na parede representam o mundo sensível, das aparências, e a saída da caverna representa o acesso ao mundo inteligível, das ideias, pela razão.', 'medio'
    UNION ALL SELECT @filosofia, 'multipla',
     'A frase "Penso, logo existo" é a base do pensamento de qual filósofo e de qual corrente?',
     'John Locke, empirismo', 'René Descartes, racionalismo', 'David Hume, ceticismo', 'Friedrich Nietzsche, niilismo', 'B',
     'Descartes usa a dúvida metódica: pode duvidar de tudo, menos de que está pensando. Por isso o pensamento é a primeira certeza, base do racionalismo moderno.', 'medio'
    UNION ALL SELECT @filosofia, 'multipla',
     'Segundo o imperativo categórico de Immanuel Kant, uma ação é moralmente correta quando:',
     'produz a maior felicidade para o maior número de pessoas.',
     'a regra que a orienta pode valer como lei universal para todos.',
     'busca o meio-termo entre dois vícios.',
     'expressa a vontade de potência de quem age.', 'B',
     'Para Kant, devemos agir de modo que a máxima da nossa ação possa ser universalizada. A alternativa A é o utilitarismo, a C é a ética de Aristóteles e a D remete a Nietzsche.', 'dificil'
    UNION ALL SELECT @filosofia, 'dissertativa',
     'Explique a diferença entre a explicação mítica e a explicação filosófica (logos) do mundo na Grécia Antiga.',
     NULL, NULL, NULL, NULL, NULL,
     'O mito explica a origem e os fenômenos do mundo por narrativas que envolvem deuses e forças sobrenaturais, transmitidas pela tradição e aceitas sem exigir prova. A filosofia, que surge por volta do século VI a.C., busca explicações racionais (logos), baseadas em argumentos e na observação da natureza, que podem ser discutidas e criticadas. Exemplo: os pré-socráticos procuravam o princípio de todas as coisas (arché) na água, no ar ou no átomo.', 'medio'
    UNION ALL SELECT @filosofia, 'dissertativa',
     'Compare as visões de Thomas Hobbes e Jean-Jacques Rousseau sobre o estado de natureza e o contrato social.',
     NULL, NULL, NULL, NULL, NULL,
     'Para Hobbes, no estado de natureza há uma "guerra de todos contra todos" (o homem é o lobo do homem); por medo da morte, as pessoas firmam um contrato e entregam o poder a um soberano forte (o Leviatã), que garante a paz. Para Rousseau, o ser humano nasce bom e é corrompido pela sociedade e pela propriedade privada; o contrato social legítimo deve expressar a vontade geral do povo, que continua soberano.', 'dificil'
) novas
WHERE @tem_fil = 0;

-- ---------------------------------------------------------------------
-- Questões: Sociologia
-- ---------------------------------------------------------------------
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT * FROM (
    SELECT @sociologia AS m, 'multipla' AS t,
     'Quem criou o termo "sociologia" e é considerado um dos fundadores da disciplina, ligado ao positivismo?' AS e,
     'Auguste Comte' AS a, 'Karl Marx' AS b, 'Max Weber' AS c, 'Émile Durkheim' AS d, 'A' AS r,
     'Auguste Comte cunhou o termo no século XIX e defendia estudar a sociedade com os métodos das ciências naturais (positivismo). O lema "Ordem e Progresso" da bandeira brasileira vem dessa corrente.' AS g, 'facil' AS n
    UNION ALL SELECT @sociologia, 'multipla',
     'Para Émile Durkheim, os fatos sociais têm três características. Quais são elas?',
     'Individualidade, subjetividade e espontaneidade',
     'Coercitividade, exterioridade e generalidade',
     'Racionalidade, burocracia e carisma',
     'Alienação, mais-valia e luta de classes', 'B',
     'Fatos sociais são maneiras de agir, pensar e sentir que existem fora do indivíduo (exterioridade), se impõem a ele (coercitividade) e são comuns ao grupo (generalidade). Exemplos: a língua, as leis e os costumes.', 'medio'
    UNION ALL SELECT @sociologia, 'multipla',
     'No pensamento de Karl Marx, a mais-valia corresponde:',
     'ao lucro obtido no comércio entre países.',
     'ao valor produzido pelo trabalhador que não é pago a ele e fica com o capitalista.',
     'aos impostos cobrados pelo Estado sobre a produção.',
     'ao salário mínimo garantido por lei.', 'B',
     'O trabalhador produz mais valor do que recebe em salário. Essa diferença, apropriada pelo dono dos meios de produção, é a mais-valia, base da exploração no capitalismo segundo Marx.', 'medio'
    UNION ALL SELECT @sociologia, 'multipla',
     'Max Weber descreveu tipos de dominação legítima. A dominação baseada em regras impessoais, leis escritas e na burocracia é a:',
     'tradicional', 'carismática', 'racional-legal', 'patrimonial', 'C',
     'Na dominação racional-legal, obedece-se a normas e a cargos, não a pessoas. A tradicional se apoia nos costumes (como na monarquia) e a carismática nas qualidades extraordinárias de um líder.', 'dificil'
    UNION ALL SELECT @sociologia, 'dissertativa',
     'O que é etnocentrismo? Dê um exemplo e explique como o relativismo cultural se opõe a ele.',
     NULL, NULL, NULL, NULL, NULL,
     'Etnocentrismo é julgar outras culturas tomando a própria como referência de certo ou superior, o que costuma gerar preconceito. Exemplo: considerar "primitivos" os hábitos alimentares ou religiosos de povos indígenas. O relativismo cultural propõe compreender cada cultura a partir de seus próprios valores e contexto, sem hierarquizá-las.', 'medio'
    UNION ALL SELECT @sociologia, 'dissertativa',
     'Explique o conceito de indústria cultural, de Theodor Adorno e Max Horkheimer, e cite uma de suas consequências.',
     NULL, NULL, NULL, NULL, NULL,
     'Indústria cultural é a produção da cultura (música, filmes, programas de TV) como mercadoria em série, padronizada para o consumo de massa e voltada ao lucro. Consequências: a padronização dos gostos, a passividade do público, que consome sem refletir, e o uso do entretenimento como forma de manter a ordem social, reduzindo o senso crítico.', 'dificil'
) novas
WHERE @tem_soc = 0;

-- ---------------------------------------------------------------------
-- Dados de atividade do aluno de teste (para o painel não começar vazio)
-- ---------------------------------------------------------------------
INSERT INTO historico_simulados (usuario_id, materia_id, pontuacao, total_questoes, origem, data_realizacao) VALUES
(2, 1, 4, 5, 'normal', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 2, 3, 5, 'normal', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 4, 5, 6, 'normal', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 5, 3, 6, 'normal', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 3, 2, 5, 'normal', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 5, 6, 'normal', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 7, 4, 5, 'normal', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO sessoes_estudo (usuario_id, materia_id, minutos, concluida_em) VALUES
(2, 1, 25, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 5, 25, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 3, 50, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 1, 25, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 7, 25, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO revisoes (usuario_id, materia_id, titulo, origem, intervalo_dias, data_agendada) VALUES
(2, 3, 'Revisar simulado de Química',         'simulado', 1, CURDATE()),
(2, 1, 'Revisar simulado de Matemática',      'simulado', 1, CURDATE()),
(2, 3, 'Reforço: estequiometria e pH',        'reforco',  3, DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
(2, 4, 'Revisar simulado de Biologia',        'simulado', 7, DATE_ADD(CURDATE(), INTERVAL 2 DAY));

-- ---------------------------------------------------------------------
-- REDAÇÃO (temas do dia e redações dos alunos)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS temas_redacao (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo              VARCHAR(200) NOT NULL,
    eixo                VARCHAR(40)  NOT NULL,              -- Educação, Saúde, Tecnologia...
    textos_motivadores  TEXT         NOT NULL,              -- um texto por parágrafo (separados por linha em branco)
    UNIQUE KEY uk_temas_titulo (titulo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS redacoes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT UNSIGNED NOT NULL,
    tema_id        INT UNSIGNED NOT NULL,
    data_tema      DATE         NOT NULL,                   -- dia do tema (uma redação por aluno por dia)
    titulo         VARCHAR(150) NULL,
    texto          MEDIUMTEXT   NOT NULL,
    palavras       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    linhas         SMALLINT UNSIGNED NOT NULL DEFAULT 0,    -- estimativa de linhas na folha do ENEM
    status         ENUM('rascunho','enviada') NOT NULL DEFAULT 'rascunho',
    autoavaliacao  VARCHAR(40)  NULL,                       -- notas das 5 competências: "160,120,160,120,80"
    nota           SMALLINT UNSIGNED NULL,                  -- 0 a 1000 (autoavaliação)
    correcao_ia    TEXT         NULL,                       -- JSON da correção por IA
    enviada_em     DATETIME     NULL,
    atualizado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_redacao_dia (usuario_id, data_tema),
    KEY idx_redacoes_usuario (usuario_id, status),
    CONSTRAINT fk_redacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_redacoes_tema FOREIGN KEY (tema_id) REFERENCES temas_redacao (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Temas (INSERT IGNORE: não duplica se rodar de novo)
INSERT IGNORE INTO temas_redacao (titulo, eixo, textos_motivadores) VALUES
('Desafios para a valorização da leitura entre os jovens brasileiros', 'Educação',
 'A leitura amplia o vocabulário, desenvolve o senso crítico e é base para o desempenho em todas as disciplinas escolares.\n\nBibliotecas públicas fechadas, livros caros e a concorrência com as telas estão entre os obstáculos apontados por educadores para formar novos leitores.'),
('Caminhos para combater a evasão escolar no ensino médio', 'Educação',
 'A Constituição Federal estabelece, no artigo 205, que a educação é direito de todos e dever do Estado e da família.\n\nMuitos jovens deixam a escola para trabalhar e ajudar na renda da família, ou por não verem sentido no que aprendem em sala de aula.'),
('Os desafios do combate ao bullying nas escolas', 'Educação',
 'A Lei 13.185 de 2015 instituiu o Programa de Combate à Intimidação Sistemática, que obriga as escolas a adotar medidas de prevenção ao bullying.\n\nAs agressões, que antes ficavam restritas ao pátio, passaram a continuar nas redes sociais, onde ganham mais alcance.'),
('Caminhos para a valorização dos professores no Brasil', 'Educação',
 'Professores relatam salas lotadas, jornadas duplas e baixa remuneração como motivos de desânimo com a carreira.\n\nPaíses com bons resultados em avaliações internacionais costumam investir na formação continuada e no prestígio social do magistério.'),
('A persistência da desigualdade no acesso à internet no Brasil', 'Tecnologia',
 'Durante o ensino remoto, muitos estudantes não conseguiram acompanhar as aulas por falta de conexão ou de um aparelho próprio.\n\nO acesso à internet passou a ser condição para estudar, trabalhar e acessar serviços públicos, como o agendamento de consultas.'),
('Desafios para o combate à desinformação na era digital', 'Tecnologia',
 'Notícias falsas se espalham mais rápido do que as verdadeiras porque costumam apelar para a emoção e para o medo.\n\nA educação midiática, que ensina a checar fontes e datas, é apontada por especialistas como uma das formas mais eficazes de enfrentar o problema.'),
('A inteligência artificial e o futuro do trabalho no Brasil', 'Tecnologia',
 'Ferramentas de inteligência artificial já escrevem textos, criam imagens e automatizam tarefas que antes dependiam de pessoas.\n\nAo mesmo tempo em que algumas funções desaparecem, surgem novas profissões que exigem formação técnica e capacidade de adaptação.'),
('A proteção de dados pessoais na era digital', 'Tecnologia',
 'A Lei Geral de Proteção de Dados (Lei 13.709 de 2018) garante ao cidadão o direito de saber como suas informações são coletadas e usadas.\n\nVazamentos de dados expõem pessoas a golpes e fraudes, e muitos usuários aceitam termos de uso sem ler o que estão autorizando.'),
('Os impactos das redes sociais na saúde mental dos adolescentes', 'Saúde',
 'A comparação constante com vidas aparentemente perfeitas nas redes pode gerar ansiedade e baixa autoestima.\n\nAlgoritmos são desenhados para prender a atenção, o que favorece o uso excessivo e prejudica o sono e os estudos.'),
('O envelhecimento da população e os desafios para o cuidado com os idosos', 'Saúde',
 'O Estatuto da Pessoa Idosa assegura direito à saúde, ao lazer, à convivência familiar e à proteção contra a violência.\n\nCom o aumento da expectativa de vida, cresce a demanda por cuidadores, serviços de saúde e cidades acessíveis.'),
('Caminhos para ampliar a vacinação no Brasil', 'Saúde',
 'O Programa Nacional de Imunizações é reconhecido internacionalmente e oferece vacinas gratuitas pelo SUS.\n\nA queda da cobertura vacinal favorece a volta de doenças que estavam controladas, como o sarampo.'),
('Os desafios do saneamento básico no Brasil', 'Saúde',
 'A falta de esgoto tratado e de água potável está ligada a doenças como diarreia, hepatite A e leptospirose.\n\nO Marco Legal do Saneamento, de 2020, estabeleceu metas para universalizar o acesso aos serviços no país.'),
('A gravidez na adolescência como desafio para a saúde pública', 'Saúde',
 'A gravidez precoce aumenta os riscos para a saúde da mãe e do bebê e é uma das causas de abandono escolar entre meninas.\n\nEspecialistas defendem educação sexual nas escolas e acesso a informação e a métodos contraceptivos.'),
('Caminhos para reduzir o desperdício de alimentos no Brasil', 'Meio ambiente',
 'Grande parte dos alimentos se perde entre a colheita, o transporte, o comércio e a casa do consumidor.\n\nEnquanto isso, milhões de pessoas convivem com a fome, o que torna o desperdício também uma questão social.'),
('O descarte inadequado do lixo eletrônico em questão', 'Meio ambiente',
 'Celulares, pilhas e baterias contêm metais pesados que contaminam o solo e a água quando descartados no lixo comum.\n\nA Política Nacional de Resíduos Sólidos prevê a logística reversa, que responsabiliza fabricantes pelo recolhimento desses produtos.'),
('Desafios para a preservação da Amazônia', 'Meio ambiente',
 'A Amazônia abriga uma enorme biodiversidade e influencia o regime de chuvas de várias regiões do país.\n\nO desmatamento ilegal, o garimpo e as queimadas ameaçam a floresta e os povos que vivem nela.'),
('A crise hídrica e o uso consciente da água no Brasil', 'Meio ambiente',
 'Mesmo sendo um dos países com mais água doce no mundo, o Brasil enfrenta períodos de racionamento em grandes cidades.\n\nVazamentos na rede de distribuição, desmatamento de nascentes e consumo excessivo agravam o problema.'),
('Desafios para a reciclagem e a economia circular no Brasil', 'Meio ambiente',
 'A maior parte do lixo produzido nas cidades ainda vai para aterros, mesmo podendo ser reciclada.\n\nCatadores de materiais recicláveis têm papel essencial na cadeia, mas costumam trabalhar sem direitos e com baixa renda.'),
('Os desafios da mobilidade urbana nas grandes cidades brasileiras', 'Mobilidade',
 'Congestionamentos fazem trabalhadores passarem horas por dia no deslocamento entre casa e trabalho.\n\nInvestir em transporte público de qualidade, ciclovias e calçadas acessíveis é apontado como caminho para cidades mais humanas.'),
('Desafios para garantir a segurança no trânsito', 'Mobilidade',
 'O trânsito está entre as principais causas de morte de jovens no Brasil.\n\nExcesso de velocidade, uso do celular ao volante e a combinação de álcool e direção estão entre os fatores de risco mais comuns.'),
('Caminhos para combater a intolerância religiosa no Brasil', 'Direitos humanos',
 'A Constituição garante a liberdade de crença e o livre exercício dos cultos religiosos (artigo 5º, inciso VI).\n\nTerreiros de religiões de matriz africana estão entre os principais alvos de ataques e depredações.'),
('O combate ao racismo estrutural na sociedade brasileira', 'Direitos humanos',
 'Racismo estrutural é aquele que está presente nas instituições e nas relações sociais, e não apenas em atitudes individuais.\n\nA desigualdade racial aparece na renda, no acesso à universidade e nos índices de violência.'),
('A violência contra a mulher como desafio da sociedade brasileira', 'Direitos humanos',
 'A Lei Maria da Penha (Lei 11.340 de 2006) criou mecanismos para coibir a violência doméstica e familiar contra a mulher.\n\nMuitas vítimas não denunciam por medo, por dependência financeira ou por falta de uma rede de apoio.'),
('Desafios para a inclusão de pessoas com deficiência no mercado de trabalho', 'Trabalho',
 'A Lei de Cotas obriga empresas com cem ou mais funcionários a reservar vagas para pessoas com deficiência.\n\nBarreiras arquitetônicas e o preconceito ainda dificultam a contratação e o crescimento profissional dessas pessoas.'),
('O trabalho por aplicativo e os direitos do trabalhador', 'Trabalho',
 'Motoristas e entregadores de aplicativo têm horário flexível, mas, em geral, não contam com férias, descanso remunerado ou aposentadoria.\n\nO debate sobre como regulamentar essa forma de trabalho envolve empresas, trabalhadores e o poder público.'),
('A valorização dos povos indígenas e de suas culturas', 'Cultura',
 'A Constituição reconhece aos povos indígenas sua organização social, costumes, línguas, crenças e direitos sobre as terras que ocupam.\n\nA demarcação de terras e o respeito aos saberes tradicionais são centrais para a preservação dessas culturas.'),
('Desafios para a valorização da cultura popular brasileira', 'Cultura',
 'Festas como o bumba meu boi, o maracatu e as cavalhadas guardam a memória e a identidade de diferentes regiões do país.\n\nSem incentivo e sem transmissão entre gerações, muitas manifestações correm o risco de desaparecer.'),
('O consumismo e seus impactos na sociedade contemporânea', 'Cultura',
 'A publicidade e as redes sociais associam o consumo à felicidade e ao sucesso pessoal.\n\nO consumo excessivo gera endividamento das famílias e aumenta a produção de lixo e a exploração de recursos naturais.'),
('Caminhos para combater a insegurança alimentar no Brasil', 'Cidadania',
 'A alimentação é um direito social previsto no artigo 6º da Constituição Federal.\n\nA insegurança alimentar vai da preocupação com a falta de comida até a fome, e atinge principalmente famílias de baixa renda.'),
('A situação das pessoas em situação de rua nas cidades brasileiras', 'Cidadania',
 'Desemprego, conflitos familiares e dependência química estão entre os motivos que levam pessoas a viver nas ruas.\n\nAbrigos, programas de moradia e acesso ao trabalho são apontados como caminhos para a reinserção social.'),
('A importância da participação dos jovens na política', 'Cidadania',
 'No Brasil, o voto é facultativo a partir dos 16 anos e obrigatório a partir dos 18.\n\nGrêmios estudantis, conselhos de juventude e movimentos sociais são espaços em que os jovens podem exercer a cidadania.'),
('Os impactos das apostas online na vida dos brasileiros', 'Cidadania',
 'Propagandas de apostas online se tornaram comuns em transmissões esportivas e nas redes sociais.\n\nEspecialistas alertam para o risco de vício e de endividamento, principalmente entre jovens e famílias de baixa renda.');

-- =====================================================================
-- Banco de questões ampliado (15 por matéria)
-- =====================================================================
-- ---------------------------------------------------------------------
-- Matemática
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Matemática');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Quanto é 25% de 240?' e, '50' a, '60' b, '48' c, '72' d, 'B' r, '25% equivale a 1/4. Então 240 ÷ 4 = 60.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Qual é a solução da equação 2x + 7 = 19?', '5', '6', '7', '13', 'B', 'Subtraindo 7 dos dois lados: 2x = 12. Dividindo por 2: x = 6.', 'facil'
    UNION ALL SELECT 'multipla', 'Qual é a área de um triângulo com base de 10 cm e altura de 6 cm?', '60 cm²', '30 cm²', '16 cm²', '20 cm²', 'B', 'A área do triângulo é base × altura ÷ 2 = 10 × 6 ÷ 2 = 30 cm².', 'facil'
    UNION ALL SELECT 'multipla', 'Em um triângulo retângulo, os catetos medem 6 cm e 8 cm. Quanto mede a hipotenusa?', '10 cm', '12 cm', '14 cm', '9,5 cm', 'A', 'Pelo teorema de Pitágoras: h² = 6² + 8² = 36 + 64 = 100, então h = 10 cm.', 'medio'
    UNION ALL SELECT 'multipla', 'Um produto custa R$ 200,00 e recebe 15% de desconto. Qual é o novo preço?', 'R$ 170,00', 'R$ 185,00', 'R$ 30,00', 'R$ 180,00', 'A', 'O desconto é 15% de 200 = 30. O novo preço é 200 − 30 = R$ 170,00.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual é a média aritmética dos números 6, 8, 10 e 12?', '8', '9', '10', '36', 'B', 'Soma: 6 + 8 + 10 + 12 = 36. Dividindo pela quantidade de números (4): 36 ÷ 4 = 9.', 'facil'
    UNION ALL SELECT 'multipla', 'Quantos anagramas podem ser formados com as letras da palavra AMOR?', '12', '16', '24', '4', 'C', 'São 4 letras distintas, então o número de anagramas é 4! = 4 × 3 × 2 × 1 = 24.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Resolva o sistema de equações: x + y = 10 e x − y = 4. Mostre como chegou à resposta.', NULL, NULL, NULL, NULL, NULL, 'Somando as duas equações: 2x = 14, logo x = 7. Substituindo na primeira: 7 + y = 10, logo y = 3. Solução: x = 7 e y = 3.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Uma progressão geométrica tem primeiro termo 3 e razão 2. Calcule o 6º termo e a soma dos 6 primeiros termos.', NULL, NULL, NULL, NULL, NULL, 'Termo geral: aₙ = a₁ · qⁿ⁻¹, então a₆ = 3 · 2⁵ = 96. Soma: Sₙ = a₁ · (qⁿ − 1) / (q − 1) = 3 · (64 − 1) / 1 = 189.', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Física
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Física');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Qual é a unidade de força no Sistema Internacional de Unidades?' e, 'Joule' a, 'Newton' b, 'Watt' c, 'Pascal' d, 'B' r, 'Força é medida em newton (N). Joule mede energia, watt mede potência e pascal mede pressão.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Qual é a energia potencial gravitacional de um corpo de 2 kg a 5 m de altura? (g = 10 m/s²)', '10 J', '50 J', '100 J', '25 J', 'C', 'Ep = m · g · h = 2 · 10 · 5 = 100 J.', 'medio'
    UNION ALL SELECT 'multipla', 'A velocidade da luz no vácuo é de aproximadamente:', '300.000 km/s', '3.000 km/s', '340 m/s', '300 km/s', 'A', 'A luz percorre cerca de 300.000 km por segundo no vácuo. 340 m/s é a velocidade aproximada do som no ar.', 'facil'
    UNION ALL SELECT 'multipla', 'Uma lâmpada consome 600 J de energia em 10 segundos. Qual é a sua potência?', '6 W', '60 W', '600 W', '6.000 W', 'B', 'Potência = energia ÷ tempo = 600 J ÷ 10 s = 60 W.', 'medio'
    UNION ALL SELECT 'multipla', 'O princípio de que "toda ação provoca uma reação de mesma intensidade e sentido oposto" é a:', '1ª lei de Newton', '2ª lei de Newton', '3ª lei de Newton', 'Lei da gravitação universal', 'C', 'É a 3ª lei de Newton, o princípio da ação e reação. A 1ª é a lei da inércia e a 2ª relaciona força, massa e aceleração.', 'facil'
    UNION ALL SELECT 'multipla', 'Um corpo parte do repouso com aceleração constante de 2 m/s². Qual é a sua velocidade após 5 segundos?', '7 m/s', '10 m/s', '2,5 m/s', '25 m/s', 'B', 'No movimento uniformemente variado: v = v₀ + a · t = 0 + 2 · 5 = 10 m/s.', 'medio'
    UNION ALL SELECT 'multipla', 'Como se chama o fenômeno em que a luz muda de direção ao passar do ar para a água?', 'Reflexão', 'Refração', 'Difração', 'Polarização', 'B', 'Na refração, a luz muda de velocidade ao trocar de meio e, por isso, muda de direção. É o que faz um lápis parecer "quebrado" dentro de um copo com água.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Por que um objeto de metal parece mais frio ao toque do que um de madeira, mesmo estando os dois à mesma temperatura?', NULL, NULL, NULL, NULL, NULL, 'Os dois estão à mesma temperatura, mas o metal é um bom condutor térmico e retira calor da mão muito mais rápido que a madeira, que é isolante. A sensação de frio vem dessa perda rápida de calor, e não de uma temperatura menor.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Dois resistores de 6 Ω são ligados em paralelo a uma fonte de 12 V. Calcule a resistência equivalente e a corrente total do circuito.', NULL, NULL, NULL, NULL, NULL, 'Em paralelo, com resistores iguais: Req = R / n = 6 / 2 = 3 Ω. Pela Lei de Ohm: i = U / R = 12 / 3 = 4 A.', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Química
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Química');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'O número atômico (Z) de um elemento indica o número de:' e, 'nêutrons no núcleo' a, 'prótons no núcleo' b, 'partículas do núcleo' c, 'elétrons da última camada' d, 'B' r, 'O número atômico é a quantidade de prótons, e é ele que identifica o elemento químico. Prótons + nêutrons formam o número de massa.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Qual das opções é uma mistura homogênea?', 'Água e óleo', 'Água e areia', 'Água com sal dissolvido', 'Granito', 'C', 'Na mistura homogênea não se distinguem as fases. O sal dissolvido forma uma única fase com a água; as outras opções têm duas ou mais fases.', 'facil'
    UNION ALL SELECT 'multipla', 'Qual é a fórmula do gás carbônico?', 'CO', 'CO₂', 'C₂O', 'CH₄', 'B', 'O gás carbônico tem um átomo de carbono e dois de oxigênio: CO₂. CO é o monóxido de carbono e CH₄ é o metano.', 'facil'
    UNION ALL SELECT 'multipla', 'Como se chama a passagem direta do estado sólido para o gasoso?', 'Fusão', 'Vaporização', 'Sublimação', 'Condensação', 'C', 'Na sublimação a substância passa do sólido ao gasoso sem virar líquido, como acontece com a naftalina e o gelo-seco.', 'medio'
    UNION ALL SELECT 'multipla', 'Os elementos da família 1A da tabela periódica (com exceção do hidrogênio) são chamados de:', 'Halogênios', 'Gases nobres', 'Metais alcalinos', 'Calcogênios', 'C', 'A família 1A reúne os metais alcalinos (lítio, sódio, potássio...). Halogênios são a 7A, gases nobres a 8A e calcogênios a 6A.', 'medio'
    UNION ALL SELECT 'multipla', 'Dissolvem-se 20 g de sal em água até formar 500 mL de solução. Qual é a concentração em g/L?', '10 g/L', '40 g/L', '20 g/L', '4 g/L', 'B', 'Concentração = massa ÷ volume em litros = 20 g ÷ 0,5 L = 40 g/L.', 'medio'
    UNION ALL SELECT 'multipla', 'A reação entre um ácido e uma base (neutralização) produz:', 'sal e água', 'óxido e hidrogênio', 'apenas água', 'gás carbônico', 'A', 'Na neutralização, o H⁺ do ácido reage com o OH⁻ da base formando água, e os íons restantes formam um sal. Exemplo: HCl + NaOH → NaCl + H₂O.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Diferencie fenômeno físico de fenômeno químico e dê um exemplo de cada.', NULL, NULL, NULL, NULL, NULL, 'No fenômeno físico a substância não muda sua composição, só a forma ou o estado (ex.: gelo derretendo, papel sendo rasgado). No fenômeno químico formam-se novas substâncias (ex.: queima do papel, ferro enferrujando, cozimento de um ovo).', 'medio'
    UNION ALL SELECT 'dissertativa', 'Na reação 2 H₂ + O₂ → 2 H₂O, quantos gramas de água se formam a partir de 4 g de H₂? (H = 1 g/mol; O = 16 g/mol)', NULL, NULL, NULL, NULL, NULL, 'Massa molar do H₂ = 2 g/mol, então 4 g = 2 mol de H₂. A proporção é 2 mol de H₂ para 2 mol de H₂O, logo formam-se 2 mol de água. Massa molar da água = 18 g/mol: 2 × 18 = 36 g.', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Biologia
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Biologia');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Qual organela é responsável pela fotossíntese nas células vegetais?' e, 'Mitocôndria' a, 'Cloroplasto' b, 'Ribossomo' c, 'Vacúolo' d, 'B' r, 'O cloroplasto contém clorofila, que capta a energia da luz usada na fotossíntese. A mitocôndria faz a respiração celular.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'No sistema ABO, qual tipo sanguíneo é considerado doador universal?', 'A', 'B', 'AB', 'O', 'D', 'As hemácias do tipo O não têm aglutinogênios A nem B, por isso podem ser doadas para todos os tipos do sistema ABO. O tipo AB é o receptor universal.', 'facil'
    UNION ALL SELECT 'multipla', 'Em uma cadeia alimentar, os produtores são:', 'os herbívoros', 'os decompositores', 'as plantas e as algas', 'os carnívoros', 'C', 'Produtores fabricam o próprio alimento por fotossíntese e iniciam a cadeia alimentar. Herbívoros e carnívoros são consumidores.', 'facil'
    UNION ALL SELECT 'multipla', 'Qual das doenças abaixo é causada por vírus?', 'Tuberculose', 'Dengue', 'Malária', 'Doença de Chagas', 'B', 'A dengue é causada por um vírus transmitido pelo mosquito Aedes aegypti. A tuberculose é causada por bactéria; malária e doença de Chagas, por protozoários.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual estrutura controla a entrada e a saída de substâncias da célula?', 'Parede celular', 'Membrana plasmática', 'Núcleo', 'Citoplasma', 'B', 'A membrana plasmática tem permeabilidade seletiva: escolhe o que entra e o que sai da célula.', 'facil'
    UNION ALL SELECT 'multipla', 'Qual molécula carrega a informação genética dos seres vivos?', 'ATP', 'DNA', 'Glicose', 'Celulose', 'B', 'O DNA guarda as informações hereditárias na sequência de suas bases nitrogenadas (A, T, C e G).', 'facil'
    UNION ALL SELECT 'multipla', 'A teoria evolutiva baseada no "uso e desuso" e na herança dos caracteres adquiridos é o:', 'darwinismo', 'lamarckismo', 'criacionismo', 'neodarwinismo', 'B', 'Lamarck propôs que órgãos usados se desenvolvem e que essas mudanças passam aos descendentes. A ideia foi superada pela seleção natural de Darwin e pela genética.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Explique o que é o efeito estufa e por que a sua intensificação é um problema.', NULL, NULL, NULL, NULL, NULL, 'O efeito estufa é natural: gases como o CO₂ e o metano retêm parte do calor do Sol e mantêm a Terra aquecida. A queima de combustíveis fósseis e o desmatamento aumentam esses gases, o que intensifica o efeito e provoca o aquecimento global, com derretimento de geleiras, eventos climáticos extremos e perda de biodiversidade.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Em ervilhas, a semente amarela (V) é dominante sobre a verde (v). No cruzamento Vv × vv, quais são as proporções esperadas de genótipos e de fenótipos?', NULL, NULL, NULL, NULL, NULL, 'O quadro de Punnett dá 2 Vv e 2 vv. Genótipos: 1 Vv : 1 vv (50% cada). Fenótipos: 50% amarelas (Vv) e 50% verdes (vv).', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- História
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'História');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Quem comandava a esquadra portuguesa que chegou ao Brasil em 1500?' e, 'Vasco da Gama' a, 'Pedro Álvares Cabral' b, 'Cristóvão Colombo' c, 'Américo Vespúcio' d, 'B' r, 'Pedro Álvares Cabral chegou ao litoral da Bahia em abril de 1500. Vasco da Gama chegou às Índias em 1498 e Colombo chegou à América em 1492, a serviço da Espanha.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Em que ano foi proclamada a República no Brasil?', '1822', '1888', '1889', '1930', 'C', 'A República foi proclamada em 15 de novembro de 1889, pelo marechal Deodoro da Fonseca. 1822 é a Independência e 1888, a Abolição.', 'facil'
    UNION ALL SELECT 'multipla', 'Qual foi o principal produto da economia do Brasil colônia nos séculos XVI e XVII?', 'Café', 'Açúcar', 'Borracha', 'Algodão', 'B', 'A produção de açúcar nos engenhos do Nordeste, baseada no trabalho escravizado, foi o centro da economia colonial nesse período. O café ganhou força no século XIX.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual é a figura mais conhecida da Inconfidência Mineira (1789)?', 'Tiradentes', 'Zumbi dos Palmares', 'Frei Caneca', 'Dom Pedro I', 'A', 'Joaquim José da Silva Xavier, o Tiradentes, foi o único condenado à morte entre os inconfidentes, em 1792.', 'facil'
    UNION ALL SELECT 'multipla', 'Em que país começou a Revolução Industrial, no século XVIII?', 'França', 'Inglaterra', 'Alemanha', 'Estados Unidos', 'B', 'A Inglaterra reunia capital acumulado, carvão e ferro, mão de obra disponível e mercados consumidores, condições que permitiram o início da industrialização.', 'facil'
    UNION ALL SELECT 'multipla', 'Em que ano caiu o Muro de Berlim?', '1961', '1989', '1991', '1945', 'B', 'O muro foi derrubado em novembro de 1989, símbolo do fim da Guerra Fria. Ele havia sido construído em 1961; a União Soviética acabou em 1991.', 'medio'
    UNION ALL SELECT 'multipla', 'Como é chamado o período da história brasileira entre 1964 e 1985?', 'Estado Novo', 'República Velha', 'Ditadura Militar', 'Era Vargas', 'C', 'De 1964 a 1985 o país foi governado por militares, com censura, perseguição política e restrição de direitos. O Estado Novo foi de 1937 a 1945.', 'facil'
    UNION ALL SELECT 'dissertativa', 'O que foi o Quilombo dos Palmares e qual é a sua importância para a história do Brasil?', NULL, NULL, NULL, NULL, NULL, 'Palmares foi o maior quilombo do Brasil colonial, formado no século XVII na Serra da Barriga (atual Alagoas) por pessoas que fugiam da escravidão. Resistiu por quase um século e teve Zumbi como principal líder. É símbolo da resistência negra; o 20 de novembro, dia da morte de Zumbi, é o Dia da Consciência Negra.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Quais foram as principais características da Guerra Fria? Cite dois conflitos ou disputas desse período.', NULL, NULL, NULL, NULL, NULL, 'Foi a disputa entre Estados Unidos (capitalismo) e União Soviética (socialismo) após 1945, sem confronto militar direto entre os dois. Teve corrida armamentista e nuclear, corrida espacial e disputa por áreas de influência. Exemplos: Guerra da Coreia, Guerra do Vietnã, Crise dos Mísseis em Cuba e a divisão da Alemanha.', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Geografia
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Geografia');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Qual linha imaginária divide a Terra nos hemisférios Norte e Sul?' e, 'Meridiano de Greenwich' a, 'Linha do Equador' b, 'Trópico de Capricórnio' c, 'Círculo Polar Ártico' d, 'B' r, 'A Linha do Equador separa os hemisférios Norte e Sul. O Meridiano de Greenwich separa os hemisférios Leste e Oeste.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Qual é o maior rio do Brasil em volume de água?', 'São Francisco', 'Paraná', 'Amazonas', 'Tocantins', 'C', 'O Amazonas é o rio de maior volume de água do mundo e drena a maior bacia hidrográfica do planeta.', 'facil'
    UNION ALL SELECT 'multipla', 'Segundo o IBGE, em quantas grandes regiões o Brasil é dividido?', '4', '5', '6', '7', 'B', 'São cinco regiões: Norte, Nordeste, Centro-Oeste, Sudeste e Sul.', 'facil'
    UNION ALL SELECT 'multipla', 'Qual tipo de clima predomina no sertão nordestino?', 'Equatorial', 'Tropical úmido', 'Semiárido', 'Subtropical', 'C', 'O semiárido tem chuvas escassas e irregulares e temperaturas altas o ano todo. A vegetação típica é a caatinga.', 'medio'
    UNION ALL SELECT 'multipla', 'Como se chama a migração em massa da população do campo para as cidades?', 'Êxodo rural', 'Conurbação', 'Gentrificação', 'Transumância', 'A', 'O êxodo rural acelerou a urbanização brasileira no século XX. Conurbação é o encontro de cidades vizinhas que crescem até se unir.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual tipo de rocha se forma pelo resfriamento e solidificação do magma?', 'Sedimentar', 'Metamórfica', 'Ígnea (magmática)', 'Calcária', 'C', 'Rochas ígneas, como o granito e o basalto, vêm do magma resfriado. Sedimentares vêm do acúmulo de sedimentos e metamórficas da transformação de outras rochas.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual bloco econômico reúne Brasil, Argentina, Paraguai e Uruguai como membros fundadores?', 'União Europeia', 'Mercosul', 'Nafta', 'Brics', 'B', 'O Mercosul foi criado em 1991 pelo Tratado de Assunção para integrar a economia desses países.', 'facil'
    UNION ALL SELECT 'dissertativa', 'Explique a diferença entre tempo atmosférico e clima, com um exemplo de cada.', NULL, NULL, NULL, NULL, NULL, 'Tempo é a condição da atmosfera em um momento e lugar específicos (ex.: "hoje está chovendo em Curitiba"). Clima é o padrão do tempo observado ao longo de muitos anos em uma região (ex.: "o clima do Nordeste interior é semiárido").', 'medio'
    UNION ALL SELECT 'dissertativa', 'Por que a matriz energética brasileira é considerada mais limpa do que a média mundial?', NULL, NULL, NULL, NULL, NULL, 'Porque uma parte grande da energia do Brasil vem de fontes renováveis: hidrelétricas, biomassa (como o etanol da cana) e, cada vez mais, energia eólica e solar. No mundo predominam combustíveis fósseis (petróleo, carvão e gás), que emitem mais gases de efeito estufa.', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Português
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Português');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Segundo o Novo Acordo Ortográfico, qual palavra está escrita corretamente?' e, 'idéia' a, 'heróico' b, 'saída' c, 'vôo' d, 'C' r, 'Saída mantém o acento por causa do hiato com i tônico. O acordo eliminou o acento de ideia, heroico (ditongos abertos ei e oi em paroxítonas) e voo.' g, 'medio' n
    UNION ALL SELECT 'multipla', 'Qual é o sujeito da oração "Chegaram as encomendas"?', 'Oculto', 'As encomendas', 'Inexistente', 'Indeterminado', 'B', 'Quem chegou? As encomendas. O sujeito está depois do verbo (ordem inversa), mas é simples e determinado.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual é o plural correto de "cidadão"?', 'cidadões', 'cidadãos', 'cidadães', 'cidadãoes', 'B', 'Cidadão faz o plural em -ãos, como irmão (irmãos) e mão (mãos).', 'facil'
    UNION ALL SELECT 'multipla', 'Qual figura de linguagem aparece em "O vento sussurrava segredos pela janela"?', 'Personificação', 'Hipérbole', 'Metonímia', 'Ironia', 'A', 'A personificação (prosopopeia) atribui ações humanas a seres inanimados: o vento "sussurra" e conta "segredos".', 'medio'
    UNION ALL SELECT 'multipla', 'Qual das palavras abaixo é um substantivo?', 'bonito', 'correr', 'felicidade', 'rapidamente', 'C', 'Felicidade nomeia um sentimento, por isso é substantivo (abstrato). Bonito é adjetivo, correr é verbo e rapidamente é advérbio.', 'facil'
    UNION ALL SELECT 'multipla', 'Em uma propaganda com a frase "Compre agora e economize!", predomina a função da linguagem:', 'referencial', 'apelativa (conativa)', 'poética', 'metalinguística', 'B', 'A função apelativa tenta convencer o receptor a agir, com verbos no imperativo, típicos da publicidade.', 'medio'
    UNION ALL SELECT 'multipla', 'Quem escreveu o romance Dom Casmurro?', 'José de Alencar', 'Machado de Assis', 'Graciliano Ramos', 'Jorge Amado', 'B', 'Dom Casmurro (1899) é de Machado de Assis, principal nome do Realismo brasileiro. O livro é narrado por Bentinho, que desconfia de Capitu.', 'facil'
    UNION ALL SELECT 'dissertativa', 'Explique a diferença entre "mas" e "mais" e escreva uma frase com cada um.', NULL, NULL, NULL, NULL, NULL, '"Mas" é conjunção adversativa e indica oposição, com sentido de "porém" (ex.: "Estudei, mas não fui bem na prova"). "Mais" indica quantidade ou intensidade, oposto de "menos" (ex.: "Preciso estudar mais Física").', 'facil'
    UNION ALL SELECT 'dissertativa', 'Cite duas características do Modernismo brasileiro e o evento que marcou o seu início.', NULL, NULL, NULL, NULL, NULL, 'O marco inicial foi a Semana de Arte Moderna de 1922, em São Paulo. Características: ruptura com o academicismo e o Parnasianismo, verso livre, linguagem coloquial próxima da fala brasileira, humor e nacionalismo crítico. Autores: Mário de Andrade, Oswald de Andrade e Manuel Bandeira.', 'dificil'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Filosofia
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Filosofia');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Qual é o significado da palavra "filosofia", de origem grega?' e, 'Amor à sabedoria' a, 'Estudo da natureza' b, 'Arte de discursar' c, 'Busca da felicidade' d, 'A' r, 'Filosofia vem do grego philo (amor, amizade) e sophia (sabedoria).' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Para Aristóteles, a virtude moral consiste:', 'em cumprir um dever universal', 'no justo meio entre o excesso e a falta', 'na vontade de potência', 'no acúmulo de prazeres', 'B', 'A virtude é o meio-termo entre dois vícios. A coragem, por exemplo, fica entre a covardia (falta) e a temeridade (excesso).', 'medio'
    UNION ALL SELECT 'multipla', 'O sofista que afirmou "O homem é a medida de todas as coisas" foi:', 'Protágoras', 'Platão', 'Heráclito', 'Sócrates', 'A', 'Protágoras defendia que o conhecimento depende da perspectiva de cada pessoa, uma forma de relativismo.', 'medio'
    UNION ALL SELECT 'multipla', 'A corrente filosófica que afirma que todo conhecimento vem da experiência sensível é o:', 'racionalismo', 'empirismo', 'idealismo', 'existencialismo', 'B', 'Para empiristas como John Locke e David Hume, a mente nasce como uma "folha em branco" e se preenche pela experiência. O racionalismo valoriza a razão como fonte do conhecimento.', 'medio'
    UNION ALL SELECT 'multipla', 'A ideia de que "a existência precede a essência" pertence a:', 'Jean-Paul Sartre', 'Immanuel Kant', 'René Descartes', 'Platão', 'A', 'Para Sartre, existencialista, o ser humano primeiro existe e depois se define por suas escolhas; por isso é "condenado a ser livre" e responsável pelo que faz de si.', 'dificil'
    UNION ALL SELECT 'multipla', 'O ramo da filosofia que estuda a origem, os limites e a validade do conhecimento é a:', 'ética', 'estética', 'epistemologia', 'política', 'C', 'Epistemologia (ou teoria do conhecimento) investiga como conhecemos e o que garante que um conhecimento é verdadeiro. Ética trata da ação moral e estética, do belo e da arte.', 'medio'
    UNION ALL SELECT 'multipla', 'Em O Príncipe, Maquiavel defende que o governante deve:', 'ser sempre bondoso, mesmo que perca o poder', 'manter o poder usando a virtù e lidando com a fortuna', 'obedecer em tudo à Igreja', 'dividir o poder igualmente com o povo', 'B', 'Para Maquiavel, a política tem lógica própria, separada da moral religiosa. O príncipe precisa de virtù (habilidade) para lidar com a fortuna (acaso) e conservar o Estado.', 'dificil'
    UNION ALL SELECT 'dissertativa', 'O que é ética e qual é a diferença entre ética e moral?', NULL, NULL, NULL, NULL, NULL, 'Moral é o conjunto de normas, valores e costumes que orientam o comportamento de um grupo em determinada época (ex.: regras de convivência). Ética é a reflexão filosófica sobre esses valores: pergunta por que agimos de certo modo e o que torna uma ação boa ou justa.', 'medio'
    UNION ALL SELECT 'dissertativa', 'O que é senso comum e como a filosofia se diferencia dele?', NULL, NULL, NULL, NULL, NULL, 'Senso comum é o conhecimento do dia a dia, transmitido pela tradição e pela experiência, aceito sem questionamento. A filosofia parte da dúvida e da reflexão crítica: examina conceitos, exige argumentos e questiona aquilo que parece óbvio.', 'medio'
) novas WHERE @n < 15;

-- ---------------------------------------------------------------------
-- Sociologia
-- ---------------------------------------------------------------------
SET @m = (SELECT id FROM materias WHERE nome = 'Sociologia');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);
INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'Como se chama o processo pelo qual o indivíduo aprende as normas e os valores da sociedade?' e, 'Socialização' a, 'Alienação' b, 'Mobilidade social' c, 'Estratificação' d, 'A' r, 'A socialização acontece ao longo da vida, por meio da família, da escola, dos amigos e da mídia.' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Qual instituição social é responsável pela socialização primária?', 'Escola', 'Família', 'Estado', 'Empresa', 'B', 'A socialização primária ocorre na infância, principalmente na família. A escola e outras instituições participam da socialização secundária.', 'facil'
    UNION ALL SELECT 'multipla', 'Para Karl Marx, a história das sociedades é marcada principalmente pela:', 'solidariedade orgânica', 'luta de classes', 'ação social', 'anomia', 'B', 'Marx via a sociedade dividida entre classes com interesses opostos, como burguesia e proletariado. Solidariedade orgânica e anomia são conceitos de Durkheim; ação social, de Weber.', 'medio'
    UNION ALL SELECT 'multipla', 'O que significa mobilidade social?', 'Mudança de cidade ou estado', 'Mudança de posição na estrutura social', 'Migração para outro país', 'Troca de emprego no mesmo cargo', 'B', 'Mobilidade social é a passagem de um indivíduo ou grupo de uma posição social para outra, para cima (ascendente) ou para baixo (descendente).', 'medio'
    UNION ALL SELECT 'multipla', 'Qual conceito de Durkheim descreve a ausência ou o enfraquecimento das normas sociais?', 'Anomia', 'Alienação', 'Fetichismo da mercadoria', 'Burocracia', 'A', 'Anomia é a situação em que as regras sociais perdem força, gerando desorientação. Durkheim usou o conceito ao estudar o suicídio.', 'dificil'
    UNION ALL SELECT 'multipla', 'Qual movimento social brasileiro tem como principal bandeira a reforma agrária?', 'MST', 'UNE', 'CUT', 'OAB', 'A', 'O Movimento dos Trabalhadores Rurais Sem Terra (MST), fundado em 1984, luta pela distribuição de terras no campo. A UNE representa estudantes e a CUT, trabalhadores sindicalizados.', 'medio'
    UNION ALL SELECT 'multipla', 'Segundo o sociólogo T. H. Marshall, a cidadania plena envolve direitos:', 'civis, políticos e sociais', 'apenas políticos', 'econômicos e militares', 'apenas civis', 'A', 'Marshall descreveu a cidadania como a soma de direitos civis (liberdades individuais), políticos (votar e ser votado) e sociais (educação, saúde, previdência).', 'dificil'
    UNION ALL SELECT 'dissertativa', 'O que é cultura para a sociologia e por que ela não se resume a arte e erudição?', NULL, NULL, NULL, NULL, NULL, 'Para a sociologia, cultura é tudo o que um grupo cria e transmite: língua, costumes, crenças, valores, técnicas, culinária e formas de se vestir. Todos os grupos têm cultura; não existe povo "sem cultura" ou cultura superior, ideia que levaria ao etnocentrismo.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Explique a diferença entre preconceito e discriminação e dê um exemplo.', NULL, NULL, NULL, NULL, NULL, 'Preconceito é um julgamento prévio e negativo sobre uma pessoa ou grupo, sem conhecimento real (está no campo das ideias). Discriminação é a ação que trata alguém de forma desigual por causa desse preconceito. Exemplo: achar que um candidato é menos capaz por ser negro é preconceito; não contratá-lo por isso é discriminação, que no Brasil pode ser crime.', 'medio'
) novas WHERE @n < 15;
