<?php
/** Bot?o que alterna entre modo escuro e modo claro (l?gica em app.js). */
?>
<button type="button" class="botao-icone botao-tema <?= e($classe_botao_tema ?? '') ?>" data-alternar-tema
        aria-label="Ativar modo claro" title="Ativar modo claro">
    <?= icone('sol', 'icone-sol') ?>
    <?= icone('lua', 'icone-lua') ?>
</button>
