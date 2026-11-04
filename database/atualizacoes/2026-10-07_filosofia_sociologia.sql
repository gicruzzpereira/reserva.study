-- =====================================================================
-- RESERVA STUDY - Atualização: matérias Filosofia e Sociologia
-- Para bancos já instalados. Pode ser executado mais de uma vez:
-- só insere o que ainda não existe.
-- (Instalações novas já recebem tudo pelo database.sql.)
-- =====================================================================

USE reserva_study;
SET NAMES utf8mb4;

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
