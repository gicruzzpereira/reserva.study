<?php
/**
 * Escolha do simulado:
 *   - Simulado geral: 35 questões de todas as matérias, no formato do ENEM
 *   - Por matéria: escolha a matéria (agrupadas por área) e 5, 10 ou 15 questões
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

$busca = trim($_GET['busca'] ?? '');

// Matérias com o total de questões (e quantas são objetivas) e o último desempenho do aluno
$stmt = $pdo->prepare(
    "SELECT m.id, m.nome, m.cor, m.icone,
            (SELECT COUNT(*) FROM questoes q WHERE q.materia_id = m.id) AS total_questoes,
            (SELECT COUNT(*) FROM questoes q WHERE q.materia_id = m.id AND q.tipo = 'multipla') AS objetivas,
            (SELECT ROUND(h.pontuacao / h.total_questoes * 100) FROM historico_simulados h
              WHERE h.materia_id = m.id AND h.usuario_id = ? ORDER BY h.data_realizacao DESC LIMIT 1) AS ultimo
       FROM materias m
      WHERE (? = '' OR m.nome LIKE ? OR m.descricao LIKE ?)
      ORDER BY m.nome"
);
$stmt->execute([$uid, $busca, "%$busca%", "%$busca%"]);
$materias = $stmt->fetchAll();

// Agrupa por área do ENEM (matérias novas, sem área definida, vão para "Outras")
$areas = [];
foreach ($materias as $m) {
    $areas[ORDEM_AREAS[$m['nome']] ?? 'Outras'][] = $m;
}
$ordem = array_flip(array_unique(array_values(ORDEM_AREAS)));
uksort($areas, fn($a, $b) => ($ordem[$a] ?? 99) <=> ($ordem[$b] ?? 99));

$stmt = $pdo->prepare('SELECT COUNT(*) FROM caderno_erros WHERE usuario_id = ? AND superada = 0');
$stmt->execute([$uid]);
$erros_pendentes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT id, pontuacao, total_questoes, data_realizacao FROM historico_simulados WHERE usuario_id = ? AND origem = 'geral' ORDER BY data_realizacao DESC LIMIT 1");
$stmt->execute([$uid]);
$ultimo_geral = $stmt->fetch() ?: null;

$total_materias_geral = count(array_filter($materias, fn($m) => (int) $m['objetivas'] > 0));
$materia_sugerida = (int) ($_GET['materia'] ?? 0);

/** Tempo estimado: ~3 min por questão objetiva (como no ENEM). */
$tempo = fn(int $questoes) => formatar_minutos($questoes * 3);

$titulo_pagina  = 'Simulados';
$pagina_ativa   = 'simulados';
$scripts_extras = ['simulados.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Simulados</h1>
        <p>Faça uma prova completa de todas as matérias ou treine uma matéria específica. As questões são sorteadas a cada tentativa.</p>
    </div>
    <?php if ($erros_pendentes): ?>
        <a class="btn btn--contorno" href="<?= e(url('aluno/simulado.php?modo=caderno')) ?>">
            <?= icone('caderno', 'icone--sm') ?> Refazer <?= $erros_pendentes ?> <?= $erros_pendentes === 1 ? 'questão errada' : 'questões erradas' ?>
        </a>
    <?php endif; ?>
</div>

<?php if ($busca === ''): ?>
<!-- ===================== Simulado geral ===================== -->
<section class="simulado-geral">
    <div class="simulado-geral__texto">
        <span class="simulado-geral__selo"><?= icone('coroa', 'icone--xs') ?> Formato ENEM</span>
        <h2>Simulado geral</h2>
        <p><?= SIMULADO_ENEM_TOTAL ?> questões objetivas de todas as <?= $total_materias_geral ?> matérias, agrupadas por área e corrigidas na hora. No final, você vê o seu desempenho em cada matéria.</p>
        <ul class="simulado-geral__fatos">
            <li><b class="numero"><?= SIMULADO_ENEM_TOTAL ?></b><span>questões</span></li>
            <li><b class="numero"><?= $total_materias_geral ?></b><span>matérias</span></li>
            <li><b class="numero"><?= e($tempo(SIMULADO_ENEM_TOTAL)) ?></b><span>tempo estimado</span></li>
        </ul>
    </div>
    <div class="simulado-geral__acao">
        <ol class="simulado-geral__areas">
            <?php foreach (array_values(array_unique(ORDEM_AREAS)) as $n => $area): ?>
                <li><span class="numero"><?= $n + 1 ?></span><?= e($area) ?></li>
            <?php endforeach; ?>
        </ol>
        <label class="texto-pequeno" for="lingua-enem">Língua estrangeira</label>
        <select class="input" id="lingua-enem" style="margin: 8px 0;">
            <option value="ingles">Inglês</option>
            <option value="espanhol">Espanhol</option>
        </select>
        <a class="btn btn--primario btn--bloco" id="btn-simulado-linguagens" href="<?= e(url('aluno/simulado.php?modo=linguagens&lingua=ingles')) ?>">
            Simulado de Linguagens <?= icone('seta-direita', 'icone--sm') ?>
        </a>
        <a class="btn btn--secundario btn--bloco" href="<?= e(url('aluno/simulado.php?modo=exatas')) ?>">
            Simulado de Exatas e Natureza <?= icone('seta-direita', 'icone--sm') ?>
        </a>
        <a class="simulado-geral__ultimo" href="<?= e(url('aluno/simulado.php?modo=geral&qtd=' . SIMULADO_ENEM_TOTAL)) ?>">
            Simulado geral completo: <?= SIMULADO_ENEM_TOTAL ?> questões
        </a>
        <a class="simulado-geral__ultimo" href="<?= e(url('aluno/simulado.php?modo=geral&qtd=' . SIMULADO_GERAL_TOTAL)) ?>">
            Versão rápida: <?= SIMULADO_GERAL_TOTAL ?> questões
        </a>
        <?php if ($ultimo_geral): $pct = (int) round($ultimo_geral['pontuacao'] / max(1, $ultimo_geral['total_questoes']) * 100); ?>
            <a class="simulado-geral__ultimo" href="<?= e(url('aluno/resultado.php?id=' . $ultimo_geral['id'])) ?>">
                Último: <?= (int) $ultimo_geral['pontuacao'] ?>/<?= (int) $ultimo_geral['total_questoes'] ?> (<?= $pct ?>%) em <?= e(data_br($ultimo_geral['data_realizacao'])) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ===================== Por matéria ===================== -->
<div class="secao-titulo mt-5">
    <h2>Por matéria</h2>
    <?php if ($busca !== ''): ?>
        <a class="link-ver-todos" href="<?= e(url('aluno/simulados.php')) ?>">Ver todas as matérias</a>
    <?php endif; ?>
</div>

<?php if (!$materias): ?>
    <div class="card vazio">
        <?= icone('busca') ?>
        <p>Nenhuma matéria encontrada para "<?= e($busca) ?>".</p>
        <a class="btn btn--secundario" href="<?= e(url('aluno/simulados.php')) ?>">Ver todas</a>
    </div>
<?php else: ?>
<form method="get" action="<?= e(url('aluno/simulado.php')) ?>" id="form-simulado">
    <?php foreach ($areas as $area => $lista): ?>
        <fieldset class="area-materias">
            <legend class="area-materias__titulo"><?= e($area) ?></legend>
            <div class="materias-grade">
                <?php foreach ($lista as $m): $sem_questoes = (int) $m['total_questoes'] === 0; ?>
                    <label class="materia-opcao <?= $sem_questoes ? 'materia-opcao--vazia' : '' ?>" style="--cor: <?= cor_materia($m['cor']) ?>;">
                        <input type="radio" name="materia" value="<?= (int) $m['id'] ?>" data-total="<?= (int) $m['total_questoes'] ?>" data-nome="<?= e($m['nome']) ?>"
                               <?= $sem_questoes ? 'disabled' : '' ?>
                               <?= $materia_sugerida === (int) $m['id'] ? 'checked' : '' ?>>
                        <span class="materia-opcao__card">
                            <span class="materia-opcao__check"><?= icone('check', 'icone--xs') ?></span>
                            <span class="materia-opcao__icone"><?= icone($m['icone']) ?></span>
                            <span>
                                <span class="materia-opcao__nome"><?= e($m['nome']) ?></span><br>
                                <span class="materia-opcao__meta"><?= (int) $m['total_questoes'] ?> questões no banco</span>
                            </span>
                            <span class="materia-opcao__ultimo">
                                <?php if ($m['ultimo'] !== null): ?>
                                    Último: <b class="numero"><?= (int) $m['ultimo'] ?>%</b>
                                <?php else: ?>
                                    Ainda não fez
                                <?php endif; ?>
                            </span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <div class="barra-iniciar">
        <div class="barra-iniciar__grupo">
            <span class="barra-iniciar__escolha" id="escolha-materia">Escolha uma matéria</span>
            <span id="rotulo-qtd" class="visualmente-oculto">Quantidade de questões</span>
            <div class="segmento" role="radiogroup" aria-labelledby="rotulo-qtd">
                <?php foreach (QTD_POR_MATERIA as $valor): ?>
                    <input type="radio" id="qtd-<?= $valor ?>" name="qtd" value="<?= $valor ?>" <?= $valor === 10 ? 'checked' : '' ?>>
                    <label for="qtd-<?= $valor ?>"><?= $valor ?> questões</label>
                <?php endforeach; ?>
            </div>
            <span class="texto-xs texto-secundario" id="tempo-estimado"></span>
        </div>
        <button type="submit" class="btn btn--primario" id="btn-iniciar">
            Começar <?= icone('seta-direita', 'icone--sm') ?>
        </button>
    </div>
</form>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
