<?php
/**
 * Fechamento do layout: toasts, modal de confirmação e scripts.
 */
$scripts_extras = $scripts_extras ?? [];
$usa_chartjs    = $usa_chartjs ?? false;
?>
        </div><!-- /.conteudo -->
    </main>
</div><!-- /.app -->

<!-- Área dos toasts -->
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
<script type="application/json" id="toasts-pendentes"><?= json_encode(toasts_pendentes(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<!-- Modal de confirmação reutilizável (usado por formulários com data-confirmar) -->
<dialog class="modal" id="modal-confirmar" aria-labelledby="modal-confirmar-titulo">
    <div class="modal__icone"><?= icone('alerta') ?></div>
    <h2 id="modal-confirmar-titulo">Tem certeza?</h2>
    <p id="modal-confirmar-texto">Esta ação não pode ser desfeita.</p>
    <div class="form-acoes">
        <button type="button" class="btn btn--secundario" data-modal-cancelar>Cancelar</button>
        <button type="button" class="btn btn--perigo" data-modal-confirmar>Confirmar</button>
    </div>
</dialog>

<?php if ($usa_chartjs): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts_extras as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
