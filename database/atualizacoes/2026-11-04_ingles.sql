USE reservastudy;
SET NAMES utf8mb4;

INSERT IGNORE INTO materias (id, nome, descricao, cor, icone)
VALUES (10, 'Inglês', 'Compreensão escrita, gramática e interpretação de textos em inglês.', '#60A5FA', 'livro');

SET @m = (SELECT id FROM materias WHERE nome = 'Inglês');
SET @n = (SELECT COUNT(*) FROM questoes WHERE materia_id = @m);

INSERT INTO questoes (materia_id, tipo, enunciado, alternativa_a, alternativa_b, alternativa_c, alternativa_d, resposta_correta, gabarito_comentario, dificuldade)
SELECT @m, t, e, a, b, c, d, r, g, n FROM (
    SELECT 'multipla' t, 'What is the past tense of the verb "go"?' e, 'goed' a, 'went' b, 'gone' c, 'goes' d, 'B' r, 'The verb "go" is irregular. Its past tense is "went".' g, 'facil' n
    UNION ALL SELECT 'multipla', 'Choose the correct sentence.', 'She dont like coffee.', 'She doesn''t likes coffee.', 'She doesn''t like coffee.', 'She not like coffee.', 'C', 'With "she", use the auxiliary "does" + "not" and the base verb: "doesn''t like".', 'medio'
    UNION ALL SELECT 'multipla', 'What does the word "environment" mean?', 'ambiente', 'trabalho', 'viagem', 'dinheiro', 'A', '"Environment" means "ambiente" or "meio ambiente" in Portuguese.', 'facil'
    UNION ALL SELECT 'multipla', 'Complete: "If it rains, we ___ stay home."', 'will', 'would', 'were', 'did', 'A', 'In a first conditional, use "if" + simple present and "will" in the result clause.', 'medio'
    UNION ALL SELECT 'multipla', 'Which word is a synonym for "happy"?', 'sad', 'angry', 'glad', 'tired', 'C', '"Glad" means feeling pleasure or contentment, so it is a synonym of "happy".', 'facil'
    UNION ALL SELECT 'multipla', 'In "They have lived here for ten years", the verb form indicates:', 'a completed action with no connection to the present', 'an action that started in the past and continues now', 'an action happening at this exact moment', 'a future action', 'B', 'The present perfect "have lived" shows duration from the past to the present.', 'dificil'
    UNION ALL SELECT 'multipla', 'Choose the correct comparative form of "good".', 'gooder', 'more good', 'better', 'best', 'C', 'The irregular comparative of "good" is "better".', 'facil'
    UNION ALL SELECT 'multipla', 'What is the plural of "child"?', 'childs', 'childrens', 'children', 'childes', 'C', '"Child" is irregular: its plural is "children".', 'facil'
    UNION ALL SELECT 'multipla', 'Which sentence uses the present continuous correctly?', 'I am study English now.', 'I studying English now.', 'I am studying English now.', 'I study was now.', 'C', 'Use the verb "to be" + verb ending in "-ing": "am studying".', 'medio'
    UNION ALL SELECT 'multipla', 'What does "although" introduce?', 'a concessive idea', 'a cause-effect sequence', 'a place', 'a comparison of quantity', 'A', '"Although" introduces a contrast or concession, similar to "even though".', 'medio'
    UNION ALL SELECT 'multipla', 'Choose the correct passive voice: "They publish the magazine monthly."', 'The magazine is published monthly.', 'The magazine published is monthly.', 'The magazine was publish monthly.', 'Monthly publishes the magazine.', 'A', 'Present simple passive: "be" + past participle. "Is published" is correct.', 'medio'
    UNION ALL SELECT 'multipla', 'Which modal verb expresses strong advice?', 'can', 'should', 'may', 'might', 'B', '"Should" is commonly used to give advice or recommendations.', 'facil'
    UNION ALL SELECT 'dissertativa', 'Write three sentences using the present perfect with experiences you have had.', NULL, NULL, NULL, NULL, NULL, 'A correct answer uses "have/has" + past participle, such as "I have visited São Paulo".', 'medio'
    UNION ALL SELECT 'dissertativa', 'Explain the difference between "there is" and "there are".', NULL, NULL, NULL, NULL, NULL, 'Use "there is" with singular nouns and "there are" with plural nouns.', 'facil'
    UNION ALL SELECT 'dissertativa', 'Describe a daily routine using at least five verbs in the simple present.', NULL, NULL, NULL, NULL, NULL, 'A complete answer describes habitual actions with verbs in the present, such as wake up, eat, study and sleep.', 'medio'
) novas WHERE @n < 15;
