-- =====================================================================
-- RESERVA STUDY - Banco de questões ampliado: 15 questões por matéria
-- (11 de múltipla escolha e 4 dissertativas) + simulado geral.
-- Pode ser executado mais de uma vez: cada matéria só recebe as questões
-- novas se ainda tiver menos de 15.
-- =====================================================================

USE reservastudy;
SET NAMES utf8mb4;

-- Simulado geral (todas as matérias)
ALTER TABLE historico_simulados MODIFY origem ENUM('normal','caderno_erros','geral') NOT NULL DEFAULT 'normal';

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
