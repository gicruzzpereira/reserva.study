<?php
/** Toasts + app.js para páginas fora do layout interno (login, cadastro, erro). */
?>
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
<script type="application/json" id="toasts-pendentes"><?= json_encode(toasts_pendentes(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
