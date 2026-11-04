/**
 * Redação: contagem de linhas/palavras, rascunho automático, contagem
 * regressiva para o próximo tema e soma da autoavaliação.
 * A estimativa de linhas é a mesma do servidor (includes/redacao.php).
 */
(function () {
    'use strict';

    const { api, Toast } = window.ReservaStudy;
    const CARACTERES_POR_LINHA = 72;
    const MIN_LINHAS = 7;
    const MAX_LINHAS = 30;

    /* ---------- Contagem regressiva até o próximo tema ---------- */
    const contagem = document.getElementById('contagem-tema');
    if (contagem) {
        const fim = new Date(contagem.dataset.fim).getTime();
        const atualizar = () => {
            const resto = Math.max(0, fim - Date.now());
            if (resto === 0) { location.reload(); return; }
            const h = Math.floor(resto / 3600000);
            const m = Math.floor((resto % 3600000) / 60000);
            contagem.textContent = String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'min';
        };
        atualizar();
        setInterval(atualizar, 30000);
    }

    /* ---------- Autoavaliação: soma ao vivo ---------- */
    const totalAuto = document.getElementById('total-autoavaliacao');
    if (totalAuto) {
        const selects = document.querySelectorAll('[data-competencia]');
        const somar = () => {
            totalAuto.textContent = [...selects].reduce((s, sel) => s + (parseInt(sel.value, 10) || 0), 0);
        };
        selects.forEach((sel) => sel.addEventListener('change', somar));
    }

    /* ---------- Correção por IA: estado de carregamento ---------- */
    document.querySelector('[data-corrigir-ia]')?.addEventListener('submit', (evento) => {
        const botao = evento.target.querySelector('button');
        botao.disabled = true;
        botao.textContent = 'Corrigindo... isso leva alguns segundos';
    });

    /* ---------- Editor ---------- */
    const form = document.getElementById('form-redacao');
    if (!form) return;

    const texto = document.getElementById('texto-redacao');
    const titulo = document.getElementById('titulo-redacao');
    const barra = document.getElementById('barra-linhas');
    const totalLinhas = document.getElementById('total-linhas');
    const totalPalavras = document.getElementById('total-palavras');
    const status = document.getElementById('status-salvo');
    const botaoEnviar = document.getElementById('btn-enviar');

    const contarPalavras = (t) => (t.trim() ? t.trim().split(/\s+/).length : 0);
    const estimarLinhas = (t) => t.split(/\r?\n/).reduce((soma, p) => {
        const tamanho = p.trim().length;
        return soma + (tamanho ? Math.ceil(tamanho / CARACTERES_POR_LINHA) : 0);
    }, 0);

    function atualizarMedidor() {
        const linhas = estimarLinhas(texto.value);
        totalLinhas.textContent = linhas;
        totalPalavras.textContent = contarPalavras(texto.value);
        barra.style.width = Math.min(100, (linhas / MAX_LINHAS) * 100) + '%';
        barra.dataset.estado = linhas < MIN_LINHAS ? 'curto' : linhas > MAX_LINHAS ? 'longo' : 'ok';
        botaoEnviar.disabled = linhas < MIN_LINHAS || linhas > MAX_LINHAS;
        botaoEnviar.title = linhas < MIN_LINHAS ? 'Escreva pelo menos ' + MIN_LINHAS + ' linhas'
            : linhas > MAX_LINHAS ? 'O texto passou de ' + MAX_LINHAS + ' linhas' : '';
    }

    // A folha cresce junto com o texto
    function ajustarAltura() {
        texto.style.height = 'auto';
        texto.style.height = Math.max(texto.scrollHeight, 420) + 'px';
    }

    /* Rascunho: salva 1,5 s depois que o aluno para de digitar */
    let temporizador = null;
    let pendente = false;

    async function salvar() {
        pendente = false;
        status.textContent = 'Salvando...';
        try {
            const r = await api('api/redacao_salvar.php', { titulo: titulo.value, texto: texto.value });
            status.textContent = 'Rascunho salvo às ' + r.salvo_em;
        } catch (erro) {
            pendente = true;
            status.textContent = 'Não foi possível salvar';
            Toast.mostrar(erro.message, 'erro');
        }
    }

    function agendarSalvamento() {
        pendente = true;
        status.textContent = 'Alterações não salvas';
        clearTimeout(temporizador);
        temporizador = setTimeout(salvar, 1500);
    }

    [texto, titulo].forEach((campo) => campo.addEventListener('input', () => {
        atualizarMedidor();
        ajustarAltura();
        agendarSalvamento();
    }));

    // Tab insere recuo de parágrafo em vez de sair do campo
    texto.addEventListener('keydown', (e) => {
        if (e.key === 'Tab' && !e.shiftKey) {
            e.preventDefault();
            const { selectionStart: ini, selectionEnd: fim } = texto;
            texto.setRangeText('    ', ini, fim, 'end');
            texto.dispatchEvent(new Event('input'));
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (pendente) { e.preventDefault(); e.returnValue = ''; }
    });
    form.addEventListener('submit', () => { pendente = false; clearTimeout(temporizador); });

    atualizarMedidor();
    ajustarAltura();
})();
