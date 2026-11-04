/**
 * Formulário de geração com IA: contador de caracteres e envio com estado de carregamento.
 */
(function () {
    'use strict';

    const { api, Toast } = window.ReservaStudy;
    const form = document.getElementById('form-ia');
    if (!form) return;

    const texto = document.getElementById('texto');
    const contador = document.getElementById('contador-texto');
    const botao = document.getElementById('btn-gerar');
    const gerando = document.getElementById('gerando');

    const contar = () => { contador.textContent = texto.value.length.toLocaleString('pt-BR'); };
    texto.addEventListener('input', contar);
    contar();

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (botao.disabled) return;

        botao.disabled = true;
        gerando.classList.add('gerando--ativo');

        try {
            const r = await api('api/ia_gerar.php', {
                tipo: form.querySelector('input[name="tipo"]:checked').value,
                texto: texto.value,
                materia_id: document.getElementById('materia_id').value || null
            });
            window.location.href = r.redirecionar;
        } catch (erro) {
            Toast.mostrar(erro.message, 'erro', 7000);
            botao.disabled = false;
            gerando.classList.remove('gerando--ativo');
        }
    });
})();
