/**
 * Simulado: mostra uma questão por vez, envia cada resposta ao servidor para
 * conferência e exibe o feedback (verde para certa, vermelho para errada).
 * Dissertativas: o aluno escreve, vê o gabarito e se autoavalia.
 */
(function () {
    'use strict';

    const { api, Toast } = window.ReservaStudy;
    const questoes = JSON.parse(document.getElementById('dados-simulado').textContent);
    const area = document.getElementById('area-questao');
    const acoes = document.getElementById('acoes');
    const barra = document.getElementById('progresso-barra');
    const progresso = document.getElementById('progresso');
    const contador = document.getElementById('contador');

    let indice = 0;
    let respondidas = 0;
    let saindoConfirmado = false;

    /* ---------- utilitários de DOM (sempre textContent: nada de HTML vindo do banco) ---------- */
    function el(tag, classe, texto) {
        const elemento = document.createElement(tag);
        if (classe) elemento.className = classe;
        if (texto !== undefined && texto !== null) elemento.textContent = texto;
        return elemento;
    }

    function botao(texto, classe, aoClicar) {
        const b = el('button', 'btn ' + classe, texto);
        b.type = 'button';
        b.addEventListener('click', aoClicar);
        return b;
    }

    function atualizarProgresso() {
        const pct = (respondidas / questoes.length) * 100;
        barra.style.width = pct + '%';
        progresso.setAttribute('aria-valuenow', String(respondidas));
        contador.textContent = (indice + 1) + ' de ' + questoes.length;
    }

    function mostrarFeedback(certo, titulo, texto) {
        const caixa = el('div', 'feedback ' + (certo === null ? '' : certo ? 'feedback--certo' : 'feedback--errado'));
        const cabecalho = el('div', 'feedback__titulo', titulo);
        caixa.appendChild(cabecalho);
        if (texto) caixa.appendChild(el('p', null, texto));
        area.appendChild(caixa);
        return caixa;
    }

    function botaoAvancar() {
        acoes.replaceChildren();
        const ultima = indice === questoes.length - 1;
        const b = botao(ultima ? 'Ver resultado' : 'Próxima questão', 'btn--primario', ultima ? finalizar : proxima);
        acoes.appendChild(b);
        b.focus({ preventScroll: true });
    }

    /* ---------- renderização ---------- */
    function renderizar() {
        const q = questoes[indice];
        area.replaceChildren();
        acoes.replaceChildren();

        const wrapper = el('div', 'questao');
        const meta = el('div', 'questao__meta');
        // Identidade de cor da matéria: chip, borda do card e barra de progresso
        document.getElementById('simulado').style.setProperty('--cor', q.cor);
        const chipMateria = el('span', 'chip-materia');
        chipMateria.append(el('span', 'chip-materia__ponto'), document.createTextNode(q.materia));
        meta.append(chipMateria, el('span', 'selo', q.dificuldade),
            el('span', 'selo', q.tipo === 'multipla' ? 'Múltipla escolha' : 'Dissertativa'));

        wrapper.append(meta, el('p', 'questao__enunciado', q.enunciado));
        area.appendChild(wrapper);

        if (q.tipo === 'multipla') renderizarMultipla(q, wrapper);
        else renderizarDissertativa(q, wrapper);

        atualizarProgresso();
    }

    function renderizarMultipla(q, wrapper) {
        const lista = el('div', 'alternativas');
        lista.setAttribute('role', 'group');
        lista.setAttribute('aria-label', 'Alternativas');

        Object.entries(q.alternativas).forEach(([letra, texto]) => {
            const b = el('button', 'alternativa');
            b.type = 'button';
            b.dataset.letra = letra;
            b.append(el('span', 'alternativa__letra', letra), el('span', null, texto));
            b.addEventListener('click', () => responderMultipla(q, letra, lista));
            lista.appendChild(b);
        });
        wrapper.appendChild(lista);
        acoes.appendChild(el('p', 'texto-secundario texto-pequeno', 'Escolha uma alternativa. A correção aparece na hora.'));
    }

    async function responderMultipla(q, letra, lista) {
        const botoes = lista.querySelectorAll('.alternativa');
        botoes.forEach((b) => { b.disabled = true; });

        try {
            const r = await api('api/simulado_responder.php', { questao_id: q.id, resposta: letra });
            botoes.forEach((b) => {
                if (b.dataset.letra === r.resposta_correta) b.classList.add('alternativa--correta');
                else if (b.dataset.letra === letra) b.classList.add('alternativa--errada');
            });
            respondidas++;
            mostrarFeedback(
                r.correta,
                r.correta ? 'Resposta certa.' : 'Resposta errada. A correta é a ' + r.resposta_correta + '.',
                r.comentario || ''
            );
            botaoAvancar();
            atualizarProgresso();
        } catch (erro) {
            botoes.forEach((b) => { b.disabled = false; });
            Toast.mostrar(erro.message, 'erro');
        }
    }

    function renderizarDissertativa(q, wrapper) {
        const campo = el('div', 'campo');
        const rotulo = el('label', 'campo__rotulo', 'Sua resposta');
        rotulo.htmlFor = 'resposta-dissertativa';
        const texto = el('textarea', 'textarea');
        texto.id = 'resposta-dissertativa';
        texto.placeholder = 'Escreva com suas palavras. Depois você compara com o gabarito.';
        texto.maxLength = 5000;
        campo.append(rotulo, texto);
        wrapper.appendChild(campo);
        texto.focus({ preventScroll: true });

        acoes.appendChild(botao('Ver gabarito', 'btn--primario', async () => {
            if (texto.value.trim().length < 3) {
                Toast.mostrar('Escreva sua resposta antes de ver o gabarito.', 'aviso');
                texto.focus();
                return;
            }
            try {
                const r = await api('api/simulado_responder.php', { questao_id: q.id, resposta: texto.value });
                texto.readOnly = true;
                mostrarFeedback(null, 'Gabarito', r.comentario || 'Sem gabarito cadastrado.');
                pedirAutoavaliacao(q);
            } catch (erro) {
                Toast.mostrar(erro.message, 'erro');
            }
        }));
    }

    function pedirAutoavaliacao(q) {
        acoes.replaceChildren(
            el('span', 'texto-secundario texto-pequeno', 'Sua resposta bate com o gabarito?'),
            botao('Errei', 'btn--nao-sabia', () => autoavaliar(q, false)),
            botao('Acertei', 'btn--sabia', () => autoavaliar(q, true))
        );
        acoes.style.alignItems = 'center';
    }

    async function autoavaliar(q, acertou) {
        try {
            await api('api/simulado_responder.php', { questao_id: q.id, autoavaliacao: acertou ? 'acertei' : 'errei' });
            respondidas++;
            atualizarProgresso();
            botaoAvancar();
        } catch (erro) {
            Toast.mostrar(erro.message, 'erro');
        }
    }

    function proxima() {
        indice++;
        renderizar();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function finalizar() {
        acoes.replaceChildren(el('p', 'texto-secundario', 'Calculando sua pontuação...'));
        try {
            const r = await api('api/simulado_finalizar.php', {});
            saindoConfirmado = true;
            window.location.href = r.redirecionar;
        } catch (erro) {
            Toast.mostrar(erro.message, 'erro');
            botaoAvancar();
        }
    }

    // Avisa antes de fechar a aba no meio do simulado
    window.addEventListener('beforeunload', (evento) => {
        if (!saindoConfirmado && respondidas > 0 && respondidas < questoes.length) {
            evento.preventDefault();
            evento.returnValue = '';
        }
    });

    renderizar();
})();
