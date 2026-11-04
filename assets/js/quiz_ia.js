/**
 * Quiz gerado pela IA: uma questão por vez, correção imediata e placar no fim.
 * (Material de treino pessoal, por isso a correção acontece no próprio navegador.)
 */
(function () {
    'use strict';

    const questoes = JSON.parse(document.getElementById('dados-quiz').textContent);
    const area = document.getElementById('quiz-area');
    const acoes = document.getElementById('quiz-acoes');
    const barra = document.getElementById('quiz-barra');
    const contador = document.getElementById('quiz-contador');

    let indice = 0;
    let acertos = 0;

    function el(tag, classe, texto) {
        const e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texto !== undefined) e.textContent = texto;
        return e;
    }

    function renderizar() {
        const q = questoes[indice];
        area.replaceChildren();
        acoes.replaceChildren();
        contador.textContent = (indice + 1) + ' de ' + questoes.length;
        barra.style.width = (indice / questoes.length) * 100 + '%';

        const wrapper = el('div', 'questao');
        wrapper.appendChild(el('p', 'questao__enunciado', q.pergunta));
        const lista = el('div', 'alternativas');

        ['A', 'B', 'C', 'D'].forEach((letra) => {
            const b = el('button', 'alternativa');
            b.type = 'button';
            b.append(el('span', 'alternativa__letra', letra), el('span', null, q['alternativa_' + letra.toLowerCase()]));
            b.addEventListener('click', () => {
                lista.querySelectorAll('button').forEach((x) => { x.disabled = true; });
                const certo = letra === q.resposta_correta;
                if (certo) acertos++;
                lista.children['ABCD'.indexOf(q.resposta_correta)].classList.add('alternativa--correta');
                if (!certo) b.classList.add('alternativa--errada');

                const fb = el('div', 'feedback ' + (certo ? 'feedback--certo' : 'feedback--errado'));
                fb.append(el('div', 'feedback__titulo', certo ? 'Resposta certa.' : 'A correta é a ' + q.resposta_correta + '.'),
                          el('p', null, q.explicacao || ''));
                area.appendChild(fb);

                const ultima = indice === questoes.length - 1;
                const proximo = el('button', 'btn btn--primario', ultima ? 'Ver resultado' : 'Próxima');
                proximo.type = 'button';
                proximo.addEventListener('click', () => {
                    if (ultima) terminar();
                    else { indice++; renderizar(); }
                });
                acoes.appendChild(proximo);
                proximo.focus({ preventScroll: true });
            });
            lista.appendChild(b);
        });

        wrapper.appendChild(lista);
        area.appendChild(wrapper);
    }

    function terminar() {
        barra.style.width = '100%';
        contador.textContent = 'Fim';
        const pct = Math.round((acertos / questoes.length) * 100);
        area.replaceChildren(
            el('h2', null, acertos + ' de ' + questoes.length + ' certas (' + pct + '%)'),
            el('p', 'texto-secundario mt-4', pct >= 70 ? 'Ótimo domínio do conteúdo.' : 'Vale reler o texto e tentar de novo.')
        );
        const deNovo = el('button', 'btn btn--secundario', 'Refazer quiz');
        deNovo.type = 'button';
        deNovo.addEventListener('click', () => { indice = 0; acertos = 0; renderizar(); });
        acoes.replaceChildren(deNovo);
    }

    renderizar();
})();
