<?php
/**
 * Página de planos: Gratuito, Premium Anual (recomendado) e Premium Mensal.
 * A assinatura acontece no checkout (aluno/checkout.php?ciclo=...); aqui o
 * aluno também pode cancelar. Nada é cobrado: o Premium é demonstrativo.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login('aluno');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();

    if (($_POST['acao'] ?? '') === 'cancelar' && eh_premium($usuario)) {
        db()->prepare("UPDATE usuarios SET plano = 'gratuito' WHERE id = ? AND tipo = 'aluno'")->execute([$usuario['id']]);
        db()->prepare("UPDATE assinaturas SET status = 'cancelada', cancelada_em = NOW() WHERE usuario_id = ? AND status = 'ativa'")
            ->execute([$usuario['id']]);
        toast('Assinatura cancelada. Você voltou para o plano gratuito.', 'info');
    }

    redirecionar('aluno/planos.php');
}

$premium    = eh_premium($usuario);
$assinatura = $premium ? assinatura_ativa((int) $usuario['id']) : null;

$mensal   = PLANOS_PREMIUM['mensal']['valor'];
$anual    = PLANOS_PREMIUM['anual']['valor'];
$economia = (int) round((1 - $anual / ($mensal * 12)) * 100);

$itens_premium = [
    [true, 'Tudo do plano gratuito'],
    [true, 'Flashcards e quizzes com IA ilimitados'],
    [true, 'Flashcards do caderno de erros'],
    [true, 'Cancele quando quiser'],
];

// Ordem da referência: o recomendado fica no centro
$cards = [
    [
        'chave' => 'gratuito', 'icone_selo' => 'livro', 'selo' => 'Para começar', 'destaque' => false,
        'prefixo' => 'Plano', 'nome' => 'Gratuito',
        'itens' => [
            [true, 'Simulados com correção na hora'],
            [true, 'Cronograma, Pomodoro e revisões'],
            [true, 'Caderno de erros completo'],
            [true, 'IA: ' . IA_LIMITE_GRATUITO . ' gerações por mês'],
        ],
        'preco' => moeda(0), 'periodo' => 'para sempre', 'detalhe' => 'Sem cartão, sem prazo',
        'nota' => 'Ideal para criar sua rotina',
    ],
    [
        'chave' => 'anual', 'icone_selo' => 'brilho', 'selo' => 'Recomendado', 'destaque' => true,
        'prefixo' => 'Premium', 'nome' => 'Anual',
        'itens' => $itens_premium,
        'preco' => moeda($anual / 12), 'periodo' => 'por mês', 'detalhe' => moeda($anual) . ' por ano, economize ' . $economia . '%',
        'nota' => 'Melhor custo-benefício',
    ],
    [
        'chave' => 'mensal', 'icone_selo' => 'relogio', 'selo' => 'Flexível', 'destaque' => false,
        'prefixo' => 'Premium', 'nome' => 'Mensal',
        'itens' => $itens_premium,
        'preco' => moeda($mensal), 'periodo' => 'por mês', 'detalhe' => 'Renovação mensal',
        'nota' => 'Para testar sem compromisso',
    ],
];

$titulo_pagina = 'Planos';
$pagina_ativa  = 'planos';
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<section class="palco-planos" aria-labelledby="titulo-planos">
    <!-- Título gigante: os cards de vidro passam por cima e desfocam a parte de baixo -->
    <h1 class="palco-planos__titulo" id="titulo-planos">Planos</h1>

    <div class="cards-planos">
        <?php foreach ($cards as $i => $card):
            $eh_premium_card = $card['chave'] !== 'gratuito';
            $plano_atual = ($card['chave'] === 'gratuito' && !$premium)
                || ($eh_premium_card && $assinatura && $assinatura['ciclo'] === $card['chave']);
        ?>
            <article class="card-plano <?= $card['destaque'] ? 'card-plano--destaque' : '' ?> <?= $plano_atual ? 'card-plano--atual' : '' ?>"
                     style="--ordem: <?= $i ?>;">
                <span class="card-plano__selo <?= $card['destaque'] ? 'card-plano__selo--forte' : '' ?>">
                    <?= icone($card['icone_selo'], 'icone--xs') ?> <?= e($card['selo']) ?>
                </span>

                <h2 class="card-plano__nome"><span><?= e($card['prefixo']) ?></span> <?= e(mb_strtoupper($card['nome'])) ?></h2>

                <ul class="card-plano__itens">
                    <?php foreach ($card['itens'] as [$incluso, $texto]): ?>
                        <li class="<?= $incluso ? '' : 'indisponivel' ?>">
                            <span class="card-plano__check"><?= icone($incluso ? 'check' : 'fechar', 'icone--xs') ?></span>
                            <?= e($texto) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="card-plano__preco">
                    <strong class="numero"><?= e($card['preco']) ?></strong>
                    <span><?= e($card['periodo']) ?></span>
                    <small><?= e($card['detalhe']) ?></small>
                </div>

                <div class="card-plano__acao">
                    <?php if ($plano_atual): ?>
                        <span class="btn btn--secundario" aria-disabled="true"><?= icone('check', 'icone--sm') ?> Seu plano atual</span>
                    <?php elseif ($card['chave'] === 'gratuito'): ?>
                        <form method="post" data-confirmar="Cancelar o Premium?"
                              data-confirmar-texto="Você volta para o plano gratuito, com <?= IA_LIMITE_GRATUITO ?> gerações de IA por mês. Seus materiais salvos continuam disponíveis."
                              data-confirmar-botao="Cancelar assinatura">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="cancelar">
                            <button class="btn btn--contorno" type="submit">Voltar para o gratuito</button>
                        </form>
                    <?php elseif ($premium): ?>
                        <span class="btn btn--contorno" aria-disabled="true">Você já é Premium</span>
                    <?php else: ?>
                        <a class="btn <?= $card['destaque'] ? 'btn--primario' : 'btn--secundario' ?>"
                           href="<?= e(url('aluno/checkout.php?ciclo=' . $card['chave'])) ?>">
                            Escolher plano <?= icone('seta-direita', 'icone--sm') ?>
                        </a>
                    <?php endif; ?>
                </div>

                <p class="card-plano__nota"><?= e($card['nota']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($assinatura): ?>
        <p class="palco-planos__assinante">
            Assinante Premium desde <?= e(data_br($assinatura['criada_em'])) ?>, plano <?= e(mb_strtolower(PLANOS_PREMIUM[$assinatura['ciclo']]['nome'])) ?>.
        </p>
    <?php endif; ?>

    <!-- Manifesto (como o bloco final da referência) -->
    <div class="manifesto">
        <h2>Estudar não é sorte.<br><span>É método.</span></h2>
        <p>
            No Reserva Study, cada recurso tem um papel no seu estudo: o simulado mostra onde você está,
            o caderno de erros aponta o que corrigir e a revisão espaçada fixa o conteúdo. O Premium acelera
            esse ciclo com IA.
        </p>
        <ul class="manifesto__etapas">
            <li><?= icone('simulado') ?><b>Medir</b><span>Simulados com correção na hora</span></li>
            <li><?= icone('caderno') ?><b>Corrigir</b><span>Cada erro vira estudo dirigido</span></li>
            <li><?= icone('revisoes') ?><b>Fixar</b><span>Revisões em 1, 7 e 30 dias</span></li>
        </ul>
    </div>

    <p class="palco-planos__rodape">Projeto acadêmico: a assinatura é uma demonstração e nenhum valor é cobrado.</p>
</section>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
