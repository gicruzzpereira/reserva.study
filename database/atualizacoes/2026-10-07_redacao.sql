-- =====================================================================
-- RESERVA STUDY - Módulo de Redação (produção textual)
-- Banco de temas + redações dos alunos (uma por dia).
-- Pode ser executado mais de uma vez sem duplicar nada.
-- =====================================================================

USE reserva_study;
SET NAMES utf8mb4;

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
 'Celulares, pilhas e baterias contêm metais pesados que contaminam o solo e a água quando descartados no lixo comum.\n\nA Política Nacional de Resíduos Sólidos prevê a logística reversa, que responsabiliza fabricantes pelo recolhimento desses produtos.\n\nDados da ONU indicam que apenas uma pequena parcela do lixo eletrônico mundial é coletada e reciclada de forma adequada.\n\nEnquanto isso, o consumo acelerado de aparelhos estimula a substituição frequente de dispositivos que ainda poderiam ter uma vida útil maior.'),
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
