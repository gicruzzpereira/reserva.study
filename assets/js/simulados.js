/**
 * Escolha do simulado por matéria: mostra a matéria escolhida, desabilita as
 * quantidades maiores que o banco da matéria e estima o tempo de prova.
 */
(function () {
    'use strict';

    const form = document.getElementById('form-simulado');
    if (!form) return;

    const { Toast } = window.ReservaStudy;
    const escolha = document.getElementById('escolha-materia');
    const tempo = document.getElementById('tempo-estimado');
    const opcoesQtd = [...form.querySelectorAll('input[name="qtd"]')];

    function atualizar() {
        const materia = form.querySelector('input[name="materia"]:checked');
        const total = materia ? parseInt(materia.dataset.total, 10) : Infinity;

        // Quantidades acima do que a matéria tem ficam desabilitadas
        opcoesQtd.forEach((op) => {
            op.disabled = parseInt(op.value, 10) > total;
            op.nextElementSibling.title = op.disabled ? 'Esta matéria tem só ' + total + ' questões' : '';
        });
        if (form.querySelector('input[name="qtd"]:checked')?.disabled) {
            const maior = opcoesQtd.filter((op) => !op.disabled).pop();
            if (maior) maior.checked = true;
        }

        escolha.textContent = materia ? materia.dataset.nome : 'Escolha uma matéria';
        escolha.classList.toggle('barra-iniciar__escolha--vazia', !materia);
        const qtd = parseInt(form.querySelector('input[name="qtd"]:checked')?.value || '0', 10);
        tempo.textContent = qtd ? 'cerca de ' + qtd * 3 + ' min' : '';
    }

    form.addEventListener('change', atualizar);
    form.addEventListener('submit', (evento) => {
        if (!form.querySelector('input[name="materia"]:checked')) {
            evento.preventDefault();
            Toast.mostrar('Escolha uma matéria para começar.', 'aviso');
            form.querySelector('.materia-opcao input:not(:disabled)')?.focus();
        }
    });

    atualizar();

    const lingua = document.getElementById('lingua-enem');
    const linkLinguagens = document.getElementById('btn-simulado-linguagens');
    if (lingua && linkLinguagens) {
        lingua.addEventListener('change', () => {
            const url = new URL(linkLinguagens.href, window.location.origin);
            url.searchParams.set('lingua', lingua.value);
            linkLinguagens.href = url.pathname + url.search;
        });
    }
})();
