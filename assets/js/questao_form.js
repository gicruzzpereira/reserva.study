/**
 * Formulário de questão: mostra/oculta as alternativas conforme o tipo
 * e destaca a letra marcada como correta.
 */
(function () {
    'use strict';

    const form = document.getElementById('form-questao');
    if (!form) return;

    const blocoAlternativas = document.getElementById('bloco-alternativas');
    const gabarito = document.getElementById('gabarito_comentario');
    const rotuloGabarito = document.getElementById('rotulo-gabarito');

    function atualizarTipo() {
        const dissertativa = form.querySelector('input[name="tipo"]:checked')?.value === 'dissertativa';

        blocoAlternativas.hidden = dissertativa;
        // Campos ocultos não devem bloquear o envio
        blocoAlternativas.querySelectorAll('[data-so-multipla]').forEach((campo) => {
            campo.required = !dissertativa;
            campo.closest('.campo')?.classList.remove('campo--erro');
        });

        gabarito.required = dissertativa;
        gabarito.minLength = dissertativa ? 5 : 0;
        rotuloGabarito.textContent = dissertativa ? 'Gabarito' : 'Comentário explicativo (opcional)';
    }

    function destacarCorreta() {
        form.querySelectorAll('[data-alternativa-correta]').forEach((radio) => {
            const letra = radio.closest('.alternativa__letra');
            letra.style.background = radio.checked ? 'var(--success)' : '';
            letra.style.color = radio.checked ? 'var(--bg-base)' : '';
        });
    }

    form.querySelectorAll('input[name="tipo"]').forEach((r) => r.addEventListener('change', atualizarTipo));
    form.querySelectorAll('[data-alternativa-correta]').forEach((r) => r.addEventListener('change', destacarCorreta));

    // Múltipla escolha exige marcar a correta
    form.addEventListener('submit', (evento) => {
        const multipla = form.querySelector('input[name="tipo"]:checked')?.value === 'multipla';
        if (multipla && !form.querySelector('[data-alternativa-correta]:checked')) {
            evento.preventDefault();
            window.ReservaStudy.Toast.mostrar('Clique na letra da alternativa correta.', 'erro');
        }
    });

    atualizarTipo();
    destacarCorreta();
})();
