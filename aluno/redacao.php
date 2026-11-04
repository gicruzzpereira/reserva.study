<?php
/**
 * Redação: tema do dia (um por dia, igual para todos), editor com contagem
 * de linhas, envio, autoavaliação pelas 5 competências do ENEM, correção por
 * IA e histórico. ?id=N abre uma redação anterior (somente leitura).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid  = (int) $usuario['id'];
$pdo  = db();
$hoje = date('Y-m-d');

/** Redação do próprio aluno pelo id. */
function minha_redacao(int $id, int $uid): ?array
{
    $stmt = db()->prepare('SELECT * FROM redacoes WHERE id = ? AND usuario_id = ?');
    $stmt->execute([$id, $uid]);
    return $stmt->fetch() ?: null;
}

/* ===================== Ações (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';
    $voltar = 'aluno/redacao.php';

    /* ---------- Enviar a redação do dia ---------- */
    if ($acao === 'enviar') {
        $rascunho = redacao_do_dia($uid, $hoje);
        $tema   = $rascunho ? tema_por_id((int) $rascunho['tema_id']) : tema_do_dia($hoje);
        $titulo = mb_substr(trim($_POST['titulo'] ?? ''), 0, 150);
        $texto  = rtrim($_POST['texto'] ?? '');
        $linhas = estimar_linhas($texto);
        $atual  = redacao_do_dia($uid, $hoje);

        if ($atual && $atual['status'] === 'enviada') {
            toast('Você já enviou a redação de hoje. Amanhã tem tema novo!', 'info');
        } elseif (mb_strlen($texto) > REDACAO_MAX_CARACTERES) {
            toast('O texto passou do limite de caracteres.', 'erro');
        } elseif ($linhas < REDACAO_MIN_LINHAS) {
            toast('Escreva pelo menos ' . REDACAO_MIN_LINHAS . ' linhas. No ENEM, textos menores recebem nota zero.', 'erro');
        } elseif ($linhas > REDACAO_MAX_LINHAS) {
            toast('O texto passou de ' . REDACAO_MAX_LINHAS . ' linhas. Corte alguns trechos antes de enviar.', 'erro');
        } else {
            $pdo->prepare(
                "INSERT INTO redacoes (usuario_id, tema_id, data_tema, titulo, texto, palavras, linhas, status, enviada_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'enviada', NOW())
                 ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), texto = VALUES(texto), palavras = VALUES(palavras),
                                         linhas = VALUES(linhas), status = 'enviada', enviada_em = NOW()"
            )->execute([$uid, $tema['id'], $hoje, $titulo ?: null, $texto, contar_palavras($texto), $linhas]);
            toast('Redação enviada! Agora avalie seu texto pelas 5 competências.');
        }
        redirecionar($voltar);
    }

    $redacao = minha_redacao((int) ($_POST['id'] ?? 0), $uid);
    if ($redacao && $redacao['data_tema'] !== $hoje) {
        $voltar .= '?id=' . $redacao['id'];
    }

    /* ---------- Autoavaliação ---------- */
    if ($acao === 'autoavaliar' && $redacao && $redacao['status'] === 'enviada') {
        $notas = [];
        foreach (array_keys(COMPETENCIAS) as $n) {
            $nota = (int) ($_POST['c' . $n] ?? -1);
            if (!in_array($nota, NOTAS_COMPETENCIA, true)) {
                toast('Escolha uma nota para cada competência.', 'erro');
                redirecionar($voltar);
            }
            $notas[] = $nota;
        }
        $pdo->prepare('UPDATE redacoes SET autoavaliacao = ?, nota = ? WHERE id = ?')
            ->execute([implode(',', $notas), array_sum($notas), $redacao['id']]);
        toast('Autoavaliação salva: ' . array_sum($notas) . ' pontos.');
        redirecionar($voltar);
    }

    /* ---------- Correção por IA ---------- */
    if ($acao === 'corrigir_ia' && $redacao && $redacao['status'] === 'enviada') {
        $restantes = ia_geracoes_restantes($usuario);
        if (!ia_disponivel()) {
            toast('A correção por IA ainda não foi configurada neste servidor.', 'info');
        } elseif ($restantes !== null && $restantes <= 0) {
            toast('Suas gerações de IA deste mês acabaram. Com o Premium, as correções são ilimitadas.', 'aviso');
        } else {
            try {
                $correcao = ia_corrigir_redacao(tema_por_id((int) $redacao['tema_id']), (string) $redacao['titulo'], $redacao['texto']);
                $pdo->prepare('UPDATE redacoes SET correcao_ia = ? WHERE id = ?')
                    ->execute([json_encode($correcao, JSON_UNESCAPED_UNICODE), $redacao['id']]);
                ia_registrar_uso($uid);
                toast('Correção pronta: ' . $correcao['nota_total'] . ' pontos.');
            } catch (RuntimeException $erro) {
                toast($erro->getMessage(), 'erro');
            }
        }
        redirecionar($voltar);
    }

    redirecionar($voltar);
}

/* ===================== Dados para exibição ===================== */
$ver_id = (int) ($_GET['id'] ?? 0);
if ($ver_id) {
    $redacao = minha_redacao($ver_id, $uid);
    if (!$redacao) {
        toast('Redação não encontrada.', 'erro');
        redirecionar('aluno/redacao.php');
    }
    $tema = tema_por_id((int) $redacao['tema_id']);
    $data_tema = $redacao['data_tema'];
} else {
    $redacao = redacao_do_dia($uid, $hoje);
    // Se já começou a de hoje, mantém o tema dela (mesmo que a lista de temas mude no meio do dia)
    $tema = $redacao ? tema_por_id((int) $redacao['tema_id']) : tema_do_dia($hoje);
    $data_tema = $hoje;
}

$editavel = !$ver_id && (!$redacao || $redacao['status'] === 'rascunho');
$enviada  = $redacao && $redacao['status'] === 'enviada';
$autoavaliacao = $enviada && $redacao['autoavaliacao'] ? array_map('intval', explode(',', $redacao['autoavaliacao'])) : null;
$correcao = $enviada && $redacao['correcao_ia'] ? json_decode($redacao['correcao_ia'], true) : null;

// Indicadores e histórico
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS enviadas, ROUND(AVG(nota)) AS media, MAX(nota) AS melhor, COALESCE(SUM(palavras), 0) AS palavras
       FROM redacoes WHERE usuario_id = ? AND status = 'enviada'"
);
$stmt->execute([$uid]);
$resumo = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT r.id, r.data_tema, r.status, r.nota, r.correcao_ia IS NOT NULL AS tem_ia, t.titulo, t.eixo
       FROM redacoes r JOIN temas_redacao t ON t.id = r.tema_id
      WHERE r.usuario_id = ? ORDER BY r.data_tema DESC LIMIT 8"
);
$stmt->execute([$uid]);
$historico = $stmt->fetchAll();

$restantes_ia = ia_geracoes_restantes($usuario);

$titulo_pagina  = 'Redação';
$pagina_ativa   = 'redacao';
$scripts_extras = ['redacao.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<div class="pagina-cabecalho">
    <div>
        <h1>Redação</h1>
        <p>Um tema novo por dia, no modelo do ENEM. Escreva, envie e avalie seu texto pelas 5 competências.</p>
    </div>
    <?php if (!$ver_id): ?>
        <span class="contador-ia" title="O tema muda à meia-noite">
            <?= icone('relogio', 'icone--xs') ?> Novo tema em <b class="numero" id="contagem-tema" data-fim="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>T00:00:00">--</b>
        </span>
    <?php else: ?>
        <a class="btn btn--contorno" href="<?= e(url('aluno/redacao.php')) ?>"><?= icone('redacao', 'icone--sm') ?> Tema de hoje</a>
    <?php endif; ?>
</div>

<div class="kpis">
    <article class="kpi">
        <div class="kpi__topo"><span>Redações enviadas</span><?= icone('redacao') ?></div>
        <div class="kpi__valor"><?= (int) $resumo['enviadas'] ?></div>
        <div class="kpi__detalhe">Uma por dia, no máximo</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Média das notas</span><?= icone('historico') ?></div>
        <div class="kpi__valor"><?= $resumo['media'] !== null ? (int) $resumo['media'] : '-' ?><small> / 1000</small></div>
        <div class="kpi__detalhe">Pela sua autoavaliação</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Melhor nota</span><?= icone('coroa') ?></div>
        <div class="kpi__valor"><?= $resumo['melhor'] !== null ? (int) $resumo['melhor'] : '-' ?></div>
        <div class="kpi__detalhe"><?= $resumo['melhor'] !== null ? e(faixa_nota((int) $resumo['melhor'])[0]) : 'Envie a primeira' ?></div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Palavras escritas</span><?= icone('pena') ?></div>
        <div class="kpi__valor"><?= number_format((int) $resumo['palavras'], 0, ',', '.') ?></div>
        <div class="kpi__detalhe">Somando todas as redações</div>
    </article>
</div>

<?php if (!$tema): ?>
    <div class="card vazio"><?= icone('redacao') ?><p>Nenhum tema de redação cadastrado ainda.</p></div>
<?php else: ?>

<div class="grade-lateral">
    <!-- ===================== Coluna principal ===================== -->
    <div class="pilha">
        <!-- Proposta -->
        <section class="card proposta">
            <div class="proposta__topo">
                <span class="selo selo--accent"><?= icone('redacao', 'icone--xs') ?> <?= $ver_id ? 'Tema de ' . e(data_br($data_tema)) : 'Tema do dia' ?></span>
                <span class="selo"><?= e($tema['eixo']) ?></span>
            </div>
            <h2 class="proposta__titulo"><?= e($tema['titulo']) ?></h2>
            <p class="proposta__texto"><?= e(proposta_redacao($tema)) ?></p>

            <details class="motivadores" <?= $editavel ? 'open' : '' ?>>
                <summary>Textos motivadores</summary>
                <div class="motivadores__lista">
                    <?php foreach (textos_motivadores($tema) as $i => $motivador): ?>
                        <blockquote class="motivador">
                            <span class="motivador__rotulo">Texto <?= ['I', 'II', 'III', 'IV'][$i] ?? $i + 1 ?></span>
                            <p><?= e($motivador) ?></p>
                        </blockquote>
                    <?php endforeach; ?>
                </div>
            </details>
        </section>

        <?php if ($editavel): ?>
            <!-- Editor -->
            <section class="card editor-redacao">
                <form method="post" id="form-redacao"
                      data-confirmar="Enviar a redação de hoje?"
                      data-confirmar-texto="Depois de enviada, ela não pode mais ser editada. Você ainda poderá se autoavaliar e pedir a correção."
                      data-confirmar-botao="Enviar redação">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="enviar">

                    <div class="campo">
                        <label class="campo__rotulo" for="titulo-redacao">Título <span class="texto-secundario">(opcional)</span></label>
                        <input class="input" id="titulo-redacao" name="titulo" maxlength="150" autocomplete="off"
                               placeholder="Dê um título ao seu texto" value="<?= e($redacao['titulo'] ?? '') ?>">
                    </div>

                    <div class="campo mt-4">
                        <label class="campo__rotulo" for="texto-redacao">Sua redação</label>
                        <textarea class="textarea folha-redacao" id="texto-redacao" name="texto" maxlength="<?= REDACAO_MAX_CARACTERES ?>"
                                  spellcheck="true" placeholder="Comece pela introdução: apresente o tema e a sua tese."><?= e($redacao['texto'] ?? '') ?></textarea>
                    </div>

                    <div class="medidor-linhas" aria-live="polite">
                        <div class="medidor-linhas__barra"><span id="barra-linhas"></span>
                            <i style="left: <?= REDACAO_MIN_LINHAS / REDACAO_MAX_LINHAS * 100 ?>%;" title="Mínimo de <?= REDACAO_MIN_LINHAS ?> linhas"></i>
                        </div>
                        <div class="medidor-linhas__info">
                            <span><b class="numero" id="total-linhas">0</b> de <?= REDACAO_MAX_LINHAS ?> linhas (estimativa)</span>
                            <span><b class="numero" id="total-palavras">0</b> palavras</span>
                            <span class="texto-secundario" id="status-salvo"><?= $redacao ? 'Rascunho salvo' : 'Ainda não salvo' ?></span>
                        </div>
                    </div>

                    <div class="form-acoes">
                        <button class="btn btn--primario" type="submit" id="btn-enviar"><?= icone('check', 'icone--sm') ?> Enviar redação</button>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <!-- Texto enviado (somente leitura) -->
            <section class="card redacao-enviada">
                <div class="card__cabecalho">
                    <div>
                        <h2 class="card__titulo"><?= e($redacao['titulo'] ?: 'Sua redação') ?></h2>
                        <p class="card__subtitulo">
                            <?= $enviada ? 'Enviada em ' . e(data_br($redacao['enviada_em'], true)) : 'Rascunho: o prazo deste tema terminou sem envio' ?>,
                            <?= (int) $redacao['linhas'] ?> linhas, <?= (int) $redacao['palavras'] ?> palavras
                        </p>
                    </div>
                    <?php if ($enviada): ?>
                        <span class="selo selo--sucesso"><?= icone('check', 'icone--xs') ?> Enviada</span>
                    <?php else: ?>
                        <span class="selo">Não enviada</span>
                    <?php endif; ?>
                </div>
                <div class="redacao-enviada__texto"><?php foreach (preg_split('/\R/u', trim($redacao['texto'])) as $paragrafo): if (trim($paragrafo) === '') continue; ?><p><?= e($paragrafo) ?></p><?php endforeach; ?></div>
            </section>

            <!-- Correção por IA -->
            <?php if ($correcao): [$faixa, $classe_faixa] = faixa_nota((int) $correcao['nota_total']); ?>
                <section class="card correcao-ia">
                    <div class="card__cabecalho">
                        <div>
                            <h2 class="card__titulo"><?= icone('brilho', 'icone--sm') ?> Correção por IA</h2>
                            <p class="card__subtitulo">Estimativa automática. Use como orientação, não como nota oficial.</p>
                        </div>
                        <div class="nota-grande"><b class="numero"><?= (int) $correcao['nota_total'] ?></b><span class="selo <?= $classe_faixa ?>"><?= e($faixa) ?></span></div>
                    </div>
                    <p class="correcao-ia__geral"><?= e($correcao['comentario_geral'] ?? '') ?></p>
                    <div class="competencias-notas">
                        <?php foreach (COMPETENCIAS as $n => [$nome]): $c = $correcao['competencias'][$n - 1] ?? ['nota' => 0, 'comentario' => '']; ?>
                            <div class="competencia-nota">
                                <div class="competencia-nota__topo">
                                    <span><b>C<?= $n ?></b> <?= e($nome) ?></span>
                                    <b class="numero"><?= (int) $c['nota'] ?></b>
                                </div>
                                <div class="progresso"><div class="progresso__barra" style="width: <?= (int) $c['nota'] / 2 ?>%;"></div></div>
                                <p><?= e($c['comentario']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="grade-2 mt-5">
                        <div>
                            <h3 class="correcao-ia__subtitulo texto-sucesso">Pontos fortes</h3>
                            <ul class="correcao-ia__lista"><?php foreach ($correcao['pontos_fortes'] ?? [] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
                        </div>
                        <div>
                            <h3 class="correcao-ia__subtitulo texto-accent">Para melhorar</h3>
                            <ul class="correcao-ia__lista"><?php foreach ($correcao['melhorias'] ?? [] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ===================== Coluna lateral ===================== -->
    <aside class="pilha">
        <?php if ($enviada): ?>
            <!-- Autoavaliação -->
            <section class="card autoavaliacao">
                <div class="card__cabecalho">
                    <div>
                        <h2 class="card__titulo">Autoavaliação</h2>
                        <p class="card__subtitulo">Releia seu texto e dê uma nota de 0 a 200 em cada competência.</p>
                    </div>
                </div>
                <?php if ($autoavaliacao): [$faixa, $classe_faixa] = faixa_nota((int) $redacao['nota']); ?>
                    <div class="nota-grande nota-grande--bloco"><b class="numero"><?= (int) $redacao['nota'] ?></b><span>de 1000</span><span class="selo <?= $classe_faixa ?>"><?= e($faixa) ?></span></div>
                <?php endif; ?>
                <form method="post" class="pilha pilha--sm" id="form-autoavaliacao">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="autoavaliar">
                    <input type="hidden" name="id" value="<?= (int) $redacao['id'] ?>">
                    <?php foreach (COMPETENCIAS as $n => [$nome, $descricao]): ?>
                        <div class="campo competencia-campo">
                            <label class="campo__rotulo" for="c<?= $n ?>"><b>C<?= $n ?></b> <?= e($nome) ?></label>
                            <span class="campo__ajuda"><?= e($descricao) ?></span>
                            <select class="select" id="c<?= $n ?>" name="c<?= $n ?>" required data-competencia>
                                <option value="">Escolha</option>
                                <?php foreach (NOTAS_COMPETENCIA as $nota): ?>
                                    <option value="<?= $nota ?>" <?= $autoavaliacao && $autoavaliacao[$n - 1] === $nota ? 'selected' : '' ?>><?= $nota ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <div class="linha linha--entre mt-4">
                        <span class="texto-pequeno">Total: <b class="numero" id="total-autoavaliacao"><?= $autoavaliacao ? (int) $redacao['nota'] : 0 ?></b> / 1000</span>
                        <button class="btn btn--primario" type="submit"><?= $autoavaliacao ? 'Atualizar nota' : 'Salvar nota' ?></button>
                    </div>
                </form>
            </section>

            <?php if (!$correcao): ?>
                <section class="card">
                    <div class="card__cabecalho">
                        <h2 class="card__titulo"><?= icone('brilho', 'icone--sm') ?> Correção por IA</h2>
                    </div>
                    <?php if (!ia_disponivel()): ?>
                        <p class="texto-pequeno texto-secundario">A correção automática ainda não está ligada neste servidor. Enquanto isso, use a autoavaliação.</p>
                    <?php elseif ($restantes_ia !== null && $restantes_ia <= 0): ?>
                        <p class="texto-pequeno texto-secundario">Suas gerações de IA deste mês acabaram.</p>
                        <a class="btn btn--primario btn--bloco mt-4" href="<?= e(url('aluno/planos.php')) ?>"><?= icone('coroa', 'icone--sm') ?> Conhecer o Premium</a>
                    <?php else: ?>
                        <p class="texto-pequeno texto-secundario">Receba nota e comentários nas 5 competências.
                            <?= $restantes_ia === null ? 'Ilimitado no Premium.' : 'Usa 1 das suas ' . $restantes_ia . ' gerações deste mês.' ?></p>
                        <form method="post" class="mt-4" data-corrigir-ia>
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="corrigir_ia">
                            <input type="hidden" name="id" value="<?= (int) $redacao['id'] ?>">
                            <button class="btn btn--secundario btn--bloco" type="submit"><?= icone('brilho', 'icone--sm') ?> Corrigir com IA</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <!-- Guia de estrutura -->
            <section class="card">
                <div class="card__cabecalho">
                    <h2 class="card__titulo">Estrutura sugerida</h2>
                </div>
                <ol class="estrutura">
                    <li><b>Introdução</b><span>Contextualize o tema e apresente sua tese.</span></li>
                    <li><b>Desenvolvimento 1</b><span>Primeiro argumento, com repertório (lei, dado, autor, fato histórico).</span></li>
                    <li><b>Desenvolvimento 2</b><span>Segundo argumento, ligado ao primeiro por conectivos.</span></li>
                    <li><b>Conclusão</b><span>Proposta de intervenção: quem faz, o quê, como, para quê e um detalhe.</span></li>
                </ol>
                <p class="texto-xs texto-secundario mt-4">Entre <?= REDACAO_MIN_LINHAS ?> e <?= REDACAO_MAX_LINHAS ?> linhas. O rascunho é salvo sozinho enquanto você escreve.</p>
            </section>
        <?php endif; ?>

        <section class="card">
            <div class="card__cabecalho">
                <h2 class="card__titulo">Suas redações</h2>
            </div>
            <?php if (!$historico): ?>
                <p class="texto-pequeno texto-secundario">As redações que você enviar aparecem aqui.</p>
            <?php else: ?>
                <ul class="lista" style="gap: 8px;">
                    <?php foreach ($historico as $h): $atual = (int) $h['id'] === (int) ($redacao['id'] ?? 0); ?>
                        <li>
                            <a class="lista-item historico-redacao <?= $atual ? 'historico-redacao--atual' : '' ?>"
                               href="<?= e(url($h['data_tema'] === $hoje ? 'aluno/redacao.php' : 'aluno/redacao.php?id=' . $h['id'])) ?>" style="color: inherit; padding: 12px;">
                                <span class="lista-item__corpo">
                                    <span class="lista-item__titulo" style="display: block;"><?= e($h['titulo']) ?></span>
                                    <span class="lista-item__meta"><?= e(data_br($h['data_tema'])) ?>, <?= e($h['eixo']) ?></span>
                                </span>
                                <?php if ($h['status'] === 'rascunho'): ?>
                                    <span class="selo">Rascunho</span>
                                <?php elseif ($h['nota'] !== null): ?>
                                    <span class="selo <?= faixa_nota((int) $h['nota'])[1] ?> numero"><?= (int) $h['nota'] ?></span>
                                <?php else: ?>
                                    <span class="selo selo--accent">Avaliar</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
