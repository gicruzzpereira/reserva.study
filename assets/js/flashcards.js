/**
 * Flashcards: virar o card (clique, Enter ou espaço) e marcar "Sabia" / "Não sabia".
 * A marcação é salva via fetch(); "Não sabia" agenda uma revisão para o dia seguinte.
 */
(function () {
    'use strict';

    const { api, Toast } = window.ReservaStudy;
    const cards = JSON.parse(document.getElementById('dados-flashcards').textContent);

    const card = document.getElementById('fc-card');
    const frente = document.getElementById('fc-frente');
    const verso = document.getElementById('fc-verso');
    const posicao = document.getElementById('fc-posicao');
    const barra = document.getElementById('fc-barra');
    const acoes = document.getElementById('fc-acoes');
    const fim = document.getElementById('fc-fim');
    const contSabia = document.getElementById('fc-sabia');
    const contNao = document.getElementById('fc-nao');

    let indice = 0;
    let sabia = 0;
    let naoSabia = 0;
    let enviando = false;

    function mostrar() {
        const atual = cards[indice];
        const estavaVirado = card.classList.contains('flashcard--virado');
        card.classList.remove('flashcard--virado');
        // Troca o texto só depois que o card desvira, para não mostrar a próxima resposta
        setTimeout(() => {
            frente.textContent = atual.frente;
            verso.textContent = atual.verso;
        }, estavaVirado ? 220 : 0);
        posicao.textContent = (indice + 1) + ' de ' + cards.length;
        barra.style.width = (indice / cards.length) * 100 + '%';
    }

    function virar() {
        card.classList.toggle('flashcard--virado');
    }

    async function responder(resposta) {
        if (enviando) return;
        // Primeiro o aluno precisa ver a resposta
        if (!card.classList.contains('flashcard--virado')) {
            virar();
            return;
        }
        enviando = true;
        try {
            await api('api/flashcard_status.php', { id: cards[indice].id, status: resposta });
            if (resposta === 'sabia') contSabia.textContent = String(++sabia);
            else contNao.textContent = String(++naoSabia);

            indice++;
            if (indice >= cards.length) terminar();
            else mostrar();
        } catch (erro) {
            Toast.mostrar(erro.message, 'erro');
        } finally {
            enviando = false;
        }
    }

    function terminar() {
        barra.style.width = '100%';
        card.hidden = true;
        acoes.hidden = true;
        fim.hidden = false;
        document.getElementById('fc-resumo').textContent =
            'Você sabia ' + sabia + ' de ' + cards.length + '.' +
            (naoSabia ? ' Os ' + naoSabia + ' que faltaram entraram nas revisões de amanhã.' : ' Mandou bem.');
    }

    card.addEventListener('click', virar);
    card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); virar(); }
    });
    acoes.querySelectorAll('[data-resposta]').forEach((b) => b.addEventListener('click', () => responder(b.dataset.resposta)));

    // Atalhos: seta esquerda = não sabia, seta direita = sabia
    document.addEventListener('keydown', (e) => {
        if (card.hidden || e.target.closest('input, textarea, select')) return;
        if (e.key === 'ArrowLeft') responder('nao_sabia');
        if (e.key === 'ArrowRight') responder('sabia');
    });

    document.getElementById('fc-recomecar').addEventListener('click', () => {
        indice = sabia = naoSabia = 0;
        contSabia.textContent = contNao.textContent = '0';
        card.hidden = acoes.hidden = false;
        fim.hidden = true;
        mostrar();
    });

    mostrar();
})();
