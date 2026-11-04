<?php
/**
 * Checkout do Premium (demonstrativo).
 *
 * A tela simula um checkout real, mas nada é cobrado e nenhum dado de cartão
 * sai do navegador: os campos do cartão não têm "name", servem só para a
 * prévia visual e para a validação no front. O servidor recebe apenas o ciclo
 * (mensal/anual) e a forma de pagamento escolhida.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');
$uid = (int) $usuario['id'];
$pdo = db();

/* ===================== Confirmação (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    $ciclo  = $_POST['ciclo'] ?? '';
    $metodo = $_POST['metodo'] ?? '';

    if (eh_premium($usuario)) {
        toast('Você já tem o Premium ativo.', 'info');
        redirecionar('aluno/planos.php');
    }
    if (!isset(PLANOS_PREMIUM[$ciclo]) || !in_array($metodo, ['cartao', 'pix'], true)) {
        toast('Escolha o plano e a forma de pagamento.', 'erro');
        redirecionar('aluno/checkout.php');
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO assinaturas (usuario_id, ciclo, metodo, valor) VALUES (?, ?, ?, ?)')
        ->execute([$uid, $ciclo, $metodo, PLANOS_PREMIUM[$ciclo]['valor']]);
    $assinaturaId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE usuarios SET plano = 'premium' WHERE id = ? AND tipo = 'aluno'")->execute([$uid]);
    $pdo->commit();

    redirecionar('aluno/checkout.php?sucesso=' . $assinaturaId);
}

/* ===================== Tela de sucesso ===================== */
$assinatura = null;
if (isset($_GET['sucesso'])) {
    $stmt = $pdo->prepare('SELECT * FROM assinaturas WHERE id = ? AND usuario_id = ?');
    $stmt->execute([(int) $_GET['sucesso'], $uid]);
    $assinatura = $stmt->fetch() ?: null;
    if (!$assinatura) {
        redirecionar('aluno/planos.php');
    }
} elseif (eh_premium($usuario)) {
    toast('Você já tem o Premium ativo.', 'info');
    redirecionar('aluno/planos.php');
}

$ciclo_inicial = ($_GET['ciclo'] ?? '') === 'mensal' ? 'mensal' : 'anual';
$mensal = PLANOS_PREMIUM['mensal']['valor'];
$anual  = PLANOS_PREMIUM['anual']['valor'];
$economia_pct = (int) round((1 - $anual / ($mensal * 12)) * 100);

/**
 * QR Code ilustrativo do Pix (não é um QR válido): padrão determinístico com
 * os três quadrados de posição, desenhado em SVG.
 */
function qr_demonstrativo(int $semente, int $tamanho = 25): string
{
    mt_srand($semente);
    $modulos = '';
    $ehLocalizador = fn($x, $y) => ($x < 7 && $y < 7) || ($x >= $tamanho - 7 && $y < 7) || ($x < 7 && $y >= $tamanho - 7);
    for ($y = 0; $y < $tamanho; $y++) {
        for ($x = 0; $x < $tamanho; $x++) {
            if (!$ehLocalizador($x, $y) && mt_rand(0, 100) < 48) {
                $modulos .= "<rect x=\"$x\" y=\"$y\" width=\"1\" height=\"1\"/>";
            }
        }
    }
    $localizador = fn($x, $y) => "<rect x=\"" . ($x + .5) . "\" y=\"" . ($y + .5) . "\" width=\"6\" height=\"6\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1\"/>"
                               . "<rect x=\"" . ($x + 2) . "\" y=\"" . ($y + 2) . "\" width=\"3\" height=\"3\"/>";
    mt_srand();
    return '<svg viewBox="-1 -1 ' . ($tamanho + 2) . ' ' . ($tamanho + 2) . '" fill="currentColor" shape-rendering="crispEdges" role="img" aria-label="QR Code ilustrativo do Pix">'
         . $modulos . $localizador(0, 0) . $localizador($tamanho - 7, 0) . $localizador(0, $tamanho - 7) . '</svg>';
}

$titulo_pagina  = $assinatura ? 'Premium ativado' : 'Assinar Premium';
$pagina_ativa   = 'planos';
$scripts_extras = $assinatura ? [] : ['checkout.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<?php if ($assinatura):
    $plano = PLANOS_PREMIUM[$assinatura['ciclo']];
    $renovacao = date('Y-m-d', strtotime($assinatura['criada_em'] . ' +' . $plano['meses'] . ' month'));
?>
<!-- ===================== SUCESSO ===================== -->
<section class="confirmacao">
    <div class="confirmacao__selo" aria-hidden="true"><?= icone('check', 'icone--lg') ?></div>
    <h1>Premium ativado</h1>
    <p>Tudo certo, <?= e(primeiro_nome($usuario['nome'])) ?>. Seus flashcards e quizzes com IA agora são ilimitados.</p>

    <dl class="confirmacao__detalhes">
        <div><dt>Plano</dt><dd>Premium <?= e(mb_strtolower($plano['nome'])) ?></dd></div>
        <div><dt>Pagamento</dt><dd><?= $assinatura['metodo'] === 'pix' ? 'Pix' : 'Cartão de crédito' ?></dd></div>
        <div><dt>Valor</dt><dd class="numero"><?= e(moeda((float) $assinatura['valor'])) ?> <span class="selo">demonstração</span></dd></div>
        <div><dt>Renova em</dt><dd><?= e(data_br($renovacao)) ?></dd></div>
        <div><dt>Pedido</dt><dd class="numero">#RS<?= str_pad((string) $assinatura['id'], 6, '0', STR_PAD_LEFT) ?></dd></div>
    </dl>

    <div class="linha" style="justify-content: center;">
        <a class="btn btn--primario" href="<?= e(url('aluno/ia.php')) ?>"><?= icone('brilho', 'icone--sm') ?> Começar a usar a IA</a>
        <a class="btn btn--contorno" href="<?= e(url('aluno/painel.php')) ?>">Ir para o painel</a>
    </div>
    <p class="texto-xs texto-secundario">Nenhum valor foi cobrado. Você pode cancelar a qualquer momento em Planos.</p>
</section>

<?php else: ?>
<!-- ===================== CHECKOUT ===================== -->
<div class="pagina-cabecalho">
    <div>
        <p class="texto-pequeno"><a href="<?= e(url('aluno/planos.php')) ?>">Planos</a></p>
        <h1>Assinar o Premium</h1>
        <p>Flashcards e quizzes com IA sem limite, inclusive a partir do seu caderno de erros.</p>
    </div>
</div>

<div class="aviso checkout__aviso-demo" role="note">
    <?= icone('cadeado', 'icone--sm') ?>
    <div><strong>Ambiente de demonstração.</strong> Nenhum valor é cobrado e os dados do cartão não saem do seu navegador. Para testar, use o cartão de teste.</div>
</div>

<form method="post" class="checkout" id="form-checkout" novalidate>
    <?= csrf_campo() ?>

    <div class="checkout__principal">
        <!-- Ciclo -->
        <section class="card checkout__secao" aria-labelledby="titulo-ciclo">
            <h2 id="titulo-ciclo" class="card__titulo">Escolha o ciclo</h2>
            <div class="ciclos">
                <label class="ciclo">
                    <input type="radio" name="ciclo" value="anual" <?= $ciclo_inicial === 'anual' ? 'checked' : '' ?>
                           data-valor="<?= $anual ?>" data-nome="Premium anual" data-meses="12" data-desconto="<?= round($mensal * 12 - $anual, 2) ?>">
                    <span class="ciclo__card">
                        <span class="ciclo__topo">
                            <span class="ciclo__nome">Anual</span>
                            <span class="selo selo--premium">Economize <?= $economia_pct ?>%</span>
                        </span>
                        <span class="ciclo__preco numero"><?= e(moeda($anual / 12)) ?><small>/mês</small></span>
                        <span class="ciclo__detalhe"><?= e(moeda($anual)) ?> por ano</span>
                    </span>
                </label>
                <label class="ciclo">
                    <input type="radio" name="ciclo" value="mensal" <?= $ciclo_inicial === 'mensal' ? 'checked' : '' ?>
                           data-valor="<?= $mensal ?>" data-nome="Premium mensal" data-meses="1" data-desconto="0">
                    <span class="ciclo__card">
                        <span class="ciclo__topo"><span class="ciclo__nome">Mensal</span></span>
                        <span class="ciclo__preco numero"><?= e(moeda($mensal)) ?><small>/mês</small></span>
                        <span class="ciclo__detalhe">Cancele quando quiser</span>
                    </span>
                </label>
            </div>
        </section>

        <!-- Pagamento -->
        <section class="card checkout__secao" aria-labelledby="titulo-pagamento">
            <div class="card__cabecalho" style="margin-bottom: 0;">
                <h2 id="titulo-pagamento" class="card__titulo">Forma de pagamento</h2>
                <div class="segmento" role="radiogroup" aria-label="Forma de pagamento">
                    <input type="radio" id="metodo-cartao" name="metodo" value="cartao" checked>
                    <label for="metodo-cartao">Cartão de crédito</label>
                    <input type="radio" id="metodo-pix" name="metodo" value="pix">
                    <label for="metodo-pix">Pix</label>
                </div>
            </div>

            <!-- Cartão: campos SEM name (não são enviados ao servidor) -->
            <div class="pagamento-cartao" id="painel-cartao">
                <div class="cartao-preview" id="cartao-preview" aria-hidden="true">
                    <div class="cartao-preview__frente">
                        <div class="cartao-preview__topo">
                            <?= logo_html('simbolo', 'escuro', 34) ?>
                            <span class="cartao-preview__bandeira" id="cartao-bandeira">Cartão</span>
                        </div>
                        <div class="cartao-preview__chip"></div>
                        <div class="cartao-preview__numero numero" id="cartao-numero-preview">•••• •••• •••• ••••</div>
                        <div class="cartao-preview__rodape">
                            <span><small>Titular</small><b id="cartao-nome-preview">SEU NOME</b></span>
                            <span><small>Validade</small><b class="numero" id="cartao-validade-preview">MM/AA</b></span>
                        </div>
                    </div>
                    <div class="cartao-preview__verso">
                        <div class="cartao-preview__tarja"></div>
                        <div class="cartao-preview__cvv"><small>CVV</small><b class="numero" id="cartao-cvv-preview">•••</b></div>
                    </div>
                </div>

                <div class="pilha pilha--sm">
                    <div class="campo">
                        <label class="campo__rotulo" for="cartao-numero">Número do cartão</label>
                        <input class="input numero" id="cartao-numero" inputmode="numeric" autocomplete="off" placeholder="0000 0000 0000 0000" maxlength="19">
                        <span class="campo__erro"></span>
                    </div>
                    <div class="campo">
                        <label class="campo__rotulo" for="cartao-nome">Nome impresso no cartão</label>
                        <input class="input" id="cartao-nome" autocomplete="off" placeholder="Como está no cartão" maxlength="26">
                        <span class="campo__erro"></span>
                    </div>
                    <div class="form-grid">
                        <div class="campo">
                            <label class="campo__rotulo" for="cartao-validade">Validade</label>
                            <input class="input numero" id="cartao-validade" inputmode="numeric" autocomplete="off" placeholder="MM/AA" maxlength="5">
                            <span class="campo__erro"></span>
                        </div>
                        <div class="campo">
                            <label class="campo__rotulo" for="cartao-cvv">CVV</label>
                            <input class="input numero" id="cartao-cvv" inputmode="numeric" autocomplete="off" placeholder="123" maxlength="4">
                            <span class="campo__erro"></span>
                        </div>
                    </div>
                    <button type="button" class="btn btn--contorno btn--sm" id="btn-cartao-teste" style="align-self: flex-start;">
                        Preencher com cartão de teste
                    </button>
                </div>
            </div>

            <!-- Pix -->
            <div class="pagamento-pix" id="painel-pix" hidden>
                <div class="pix-qr"><?= qr_demonstrativo($uid * 7919) ?></div>
                <div class="pilha pilha--sm">
                    <p><strong>Pague com Pix em segundos.</strong></p>
                    <ol class="pix-passos">
                        <li>Abra o app do seu banco e escolha pagar com Pix.</li>
                        <li>Leia o QR Code ou cole o código abaixo.</li>
                        <li>Volte aqui e confirme a assinatura.</li>
                    </ol>
                    <div class="campo">
                        <label class="campo__rotulo" for="pix-codigo">Pix copia e cola</label>
                        <div class="pix-codigo">
                            <input class="input numero" id="pix-codigo" readonly
                                   value="DEMO-RESERVASTUDY-<?= strtoupper(substr(hash('crc32b', 'pix' . $uid), 0, 8)) ?>-SEM-COBRANCA">
                            <button type="button" class="btn btn--secundario" id="btn-copiar-pix"><?= icone('check', 'icone--sm') ?> Copiar</button>
                        </div>
                    </div>
                    <p class="texto-xs texto-secundario">Código ilustrativo: não funciona em apps de banco.</p>
                </div>
            </div>
        </section>
    </div>

    <!-- Resumo do pedido -->
    <aside class="card checkout__resumo" aria-labelledby="titulo-resumo">
        <div class="checkout__resumo-topo">
            <span class="checkout__resumo-icone"><?= icone('coroa') ?></span>
            <div>
                <h2 id="titulo-resumo" class="card__titulo">Resumo</h2>
                <p class="card__subtitulo" id="resumo-nome">Premium anual</p>
            </div>
        </div>

        <dl class="resumo-linhas">
            <div><dt>Assinatura</dt><dd class="numero" id="resumo-bruto"></dd></div>
            <div id="resumo-linha-desconto"><dt>Desconto do plano anual</dt><dd class="numero texto-sucesso" id="resumo-desconto"></dd></div>
            <div class="resumo-linhas__total"><dt>Total hoje</dt><dd class="numero" id="resumo-total"></dd></div>
        </dl>
        <p class="texto-xs texto-secundario" id="resumo-renovacao"></p>

        <ul class="recursos">
            <li><?= icone('check', 'icone--sm') ?><span>Flashcards e quizzes com IA ilimitados</span></li>
            <li><?= icone('check', 'icone--sm') ?><span>Flashcards gerados do caderno de erros</span></li>
            <li><?= icone('check', 'icone--sm') ?><span>Tudo do plano gratuito</span></li>
        </ul>

        <button type="submit" class="btn btn--primario btn--bloco checkout__botao" id="btn-confirmar">
            <span class="checkout__botao-texto">Confirmar assinatura</span>
        </button>

        <ul class="checkout__garantias">
            <li><?= icone('cadeado', 'icone--xs') ?> Dados do cartão não são enviados</li>
            <li><?= icone('revisoes', 'icone--xs') ?> Cancele quando quiser, sem multa</li>
        </ul>
    </aside>
</form>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
