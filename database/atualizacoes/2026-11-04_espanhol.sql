USE reserva_study;
SET NAMES utf8mb4;

INSERT IGNORE INTO materias (id, nome, descricao, cor, icone)
VALUES (11, 'Espanhol', 'Compreensão escrita, gramática e interpretação de textos em espanhol.', '#FB7185', 'livro');

SET @m = (SELECT id FROM materias WHERE nome = 'Espanhol');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);

INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, '¿Cuál es el artículo definido femenino singular?' e, 'el' a, 'la' b, 'los' c, 'unas' d, 'B' r, '"La" es el artículo definido femenino singular.' g, 'facil' n
    UNION ALL SELECT 'multipla', '¿Cómo se dice "obrigado" em espanhol?', 'perdón', 'gracias', 'hola', 'adiós', 'B', 'Para agradecer, usa-se "gracias".', 'facil'
    UNION ALL SELECT 'multipla', 'Complete: "Yo ___ estudiante."', 'eres', 'somos', 'soy', 'es', 'C', 'Com "yo", usa-se a forma "soy" do verbo ser.', 'medio'
    UNION ALL SELECT 'multipla', 'Qual palavra significa "livro" em português?', 'livro', 'rato', 'cidade', 'caderno', 'A', '"Libro" significa "livro".', 'facil'
    UNION ALL SELECT 'multipla', '¿Cuál es el plural de "niño"?', 'niños', 'niñas', 'niñoes', 'niñes', 'A', 'O plural de "niño" é "niños".', 'facil'
    UNION ALL SELECT 'multipla', 'En "Ella ha viajado mucho", ¿qué tiempo verbal se utiliza?', 'pretérito indefinido', 'presente', 'pretérito perfecto compuesto', 'futuro', 'C', 'A construção "ha viajado" forma o pretérito perfeito composto.', 'medio'
    UNION ALL SELECT 'multipla', '¿Qué significa la palabra "desarrollo"?', 'desenvolvimento', 'descaso', 'desconforto', 'descanso', 'A', '"Desarrollo" significa desenvolvimento.', 'facil'
    UNION ALL SELECT 'multipla', 'Complete con el adjetivo posesivo: "Esta es ___ casa."', 'mi', 'mis', 'mío', 'mí', 'A', 'Para "casa", substantivo singular, usa-se "mi".', 'medio'
    UNION ALL SELECT 'multipla', '¿Cuál oración está en futuro?', 'Ayer fui al mercado.', 'Voy al mercado.', 'Iré al mercado.', 'Estoy yendo al mercado.', 'C', '"Iré" é a forma do futuro simples do verbo ir.', 'medio'
    UNION ALL SELECT 'multipla', '¿Qué conectivo expresa contraste?', 'aunque', 'porque', 'cuando', 'para', 'A', '"Aunque" indica contraste ou concessão.', 'medio'
    UNION ALL SELECT 'multipla', '¿Cuál es el antónimo de "grande"?', 'alto', 'pequeño', 'largo', 'nuevo', 'B', 'O antônimo de "grande" é "pequeño".', 'facil'
    UNION ALL SELECT 'multipla', 'La palabra "zapato" pertenece a la categoría de:', 'verbo', 'adjetivo', 'sustantivo', 'adverbio', 'C', '"Zapato" é um substantivo que designa um calçado.', 'facil'
    UNION ALL SELECT 'dissertativa', 'Escribe tres frases usando el presente de indicativo.', NULL, NULL, NULL, NULL, NULL, 'A resposta deve conter frases com verbos conjugados no presente.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Explique la diferencia entre "muy" y "mucho".', NULL, NULL, NULL, NULL, NULL, '"Muy" modifica adjetivos ou advérbios. "Mucho" modifica verbos ou substantivos.', 'medio'
    UNION ALL SELECT 'dissertativa', 'Describa una rutina diaria en español usando al menos cinco verbos.', NULL, NULL, NULL, NULL, NULL, 'A resposta deve usar verbos conjugados para ações do cotidiano.', 'medio'
) novas WHERE @n < 15;
