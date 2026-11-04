/**
 * Timer Pomodoro.
 * O tempo é calculado pelo relógio (Date.now) e não por contagem de ticks,
 * então não atrasa se a aba ficar em segundo plano.
 * Ao terminar um ciclo de foco, a sessão é salva via fetch() em api/pomodoro_salvar.php.
 */
(function () {
    'use strict';

    const { api, Toast } = window.ReservaStudy;
    const CIRCUNFERENCIA = 2 * Math.PI * 54;

    const timer = document.getElementById('timer');
    const tempoEl = document.getElementById('timer-tempo');
    const faseEl = document.getElementById('timer-fase');
    const progressoEl = document.getElementById('timer-progresso');
    const ciclosEl = document.getElementById('timer-ciclos');
    const btnIniciar = document.getElementById('btn-iniciar');
    const btnReiniciar = document.getElementById('btn-reiniciar');
    const btnPular = document.getElementById('btn-pular');
    const selMateria = document.getElementById('materia');
    const campoFoco = document.getElementById('min-foco');
    const campoPausa = document.getElementById('min-pausa');
    const blocoId = parseInt(timer.dataset.bloco, 10) || null;

    // Tempos lembrados no navegador (conveniência; o servidor não precisa disso)
    try {
        campoFoco.value = localStorage.getItem('pomodoro_foco') || campoFoco.value;
        campoPausa.value = localStorage.getItem('pomodoro_pausa') || campoPausa.value;
    } catch (_) { /* armazenamento indisponível */ }

    const estado = {
        fase: 'foco',          // 'foco' | 'pausa'
        rodando: false,
        fim: 0,                // timestamp em ms do fim da fase
        restante: 0,           // segundos restantes quando pausado
        duracao: 0,            // segundos totais da fase
        ciclos: 0,
        intervalo: null
    };

    const minutos = (campo, min, max) => Math.min(max, Math.max(min, parseInt(campo.value, 10) || min));
    const duracaoDaFase = () => (estado.fase === 'foco' ? minutos(campoFoco, 5, 90) : minutos(campoPausa, 1, 30)) * 60;

    function formatar(segundos) {
        const m = Math.floor(segundos / 60);
        const s = segundos % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    function desenhar(segundos) {
        tempoEl.textContent = formatar(segundos);
        const fracao = estado.duracao ? 1 - segundos / estado.duracao : 0;
        progressoEl.style.strokeDashoffset = String(CIRCUNFERENCIA * fracao);
        const nomeMateria = selMateria.value ? selMateria.options[selMateria.selectedIndex].text : '';
        faseEl.textContent = estado.fase === 'foco' ? (nomeMateria ? 'Foco em ' + nomeMateria : 'Foco') : 'Pausa';
        timer.classList.toggle('timer--pausa', estado.fase === 'pausa');
        document.title = (estado.rodando ? formatar(segundos) + ' ' : '') + (estado.fase === 'foco' ? 'Foco' : 'Pausa') + ' | Reserva Study';
    }

    function desenharCiclos() {
        ciclosEl.replaceChildren();
        for (let i = 0; i < Math.max(4, estado.ciclos); i++) {
            const ponto = document.createElement('span');
            if (i < estado.ciclos) ponto.className = 'feito';
            ciclosEl.appendChild(ponto);
        }
    }

    function atualizarBotao() {
        btnIniciar.querySelector('span').textContent = estado.rodando ? 'Pausar' : (estado.restante < estado.duracao ? 'Continuar' : 'Começar');
        // Troca o ícone entre play e pause
        btnIniciar.querySelector('svg').innerHTML = estado.rodando
            ? '<path d="M8 5.5v13M16 5.5v13"/>'
            : '<path d="M7.5 5.5v13l11-6.5-11-6.5Z"/>';
        campoFoco.disabled = campoPausa.disabled = estado.rodando;
        selMateria.disabled = estado.rodando && estado.fase === 'foco';
    }

    function prepararFase(fase) {
        estado.fase = fase;
        estado.duracao = duracaoDaFase();
        estado.restante = estado.duracao;
        desenhar(estado.restante);
        atualizarBotao();
    }

    function tique() {
        const restante = Math.max(0, Math.round((estado.fim - Date.now()) / 1000));
        desenhar(restante);
        if (restante <= 0) concluirFase();
    }

    function iniciar() {
        if (estado.fase === 'foco' && !selMateria.value) {
            Toast.mostrar('Escolha a matéria antes de começar.', 'aviso');
            selMateria.focus();
            return;
        }
        estado.rodando = true;
        estado.fim = Date.now() + estado.restante * 1000;
        estado.intervalo = setInterval(tique, 250);
        atualizarBotao();
    }

    function pausar() {
        estado.rodando = false;
        clearInterval(estado.intervalo);
        estado.restante = Math.max(0, Math.round((estado.fim - Date.now()) / 1000));
        atualizarBotao();
        desenhar(estado.restante);
    }

    function aviso() {
        // Bipe curto com Web Audio (sem arquivo de som)
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [0, 0.25].forEach((atraso) => {
                const osc = ctx.createOscillator();
                const ganho = ctx.createGain();
                osc.frequency.value = 880;
                ganho.gain.setValueAtTime(0.15, ctx.currentTime + atraso);
                ganho.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + atraso + 0.2);
                osc.connect(ganho).connect(ctx.destination);
                osc.start(ctx.currentTime + atraso);
                osc.stop(ctx.currentTime + atraso + 0.2);
            });
        } catch (_) { /* sem áudio */ }
    }

    async function concluirFase(pulou = false) {
        pausar();
        if (!pulou) aviso();

        if (estado.fase === 'foco' && !pulou) {
            estado.ciclos++;
            desenharCiclos();
            await salvarSessao(Math.round(estado.duracao / 60));
            prepararFase('pausa');
            iniciar(); // a pausa começa sozinha
        } else {
            prepararFase(estado.fase === 'foco' ? 'pausa' : 'foco');
            if (!pulou) Toast.mostrar('Pausa encerrada. Pronto para mais um ciclo?', 'info');
        }
    }

    async function salvarSessao(min) {
        try {
            const r = await api('api/pomodoro_salvar.php', {
                materia_id: parseInt(selMateria.value, 10),
                minutos: min,
                bloco_id: blocoId
            });
            Toast.mostrar('Ciclo salvo: ' + min + ' min de ' + r.materia + '.', 'sucesso');
            adicionarNaLista(r.materia, r.cor, min);
            if (blocoId && timer.dataset.blocoPendente === '1') {
                document.getElementById('area-concluir-bloco').hidden = false;
            }
        } catch (erro) {
            Toast.mostrar(erro.message, 'erro');
        }
    }

    function adicionarNaLista(materia, cor, min) {
        document.getElementById('sem-sessoes')?.remove();
        const li = document.createElement('li');
        li.className = 'linha linha--entre texto-pequeno';
        const nome = document.createElement('span');
        nome.className = 'linha';
        nome.style.gap = '8px';
        const ponto = document.createElement('span');
        ponto.className = 'ponto-cor';
        ponto.style.setProperty('--cor', cor);
        nome.append(ponto, document.createTextNode(materia));
        const info = document.createElement('span');
        info.className = 'texto-secundario';
        const agora = new Date();
        info.textContent = min + ' min, ' + String(agora.getHours()).padStart(2, '0') + ':' + String(agora.getMinutes()).padStart(2, '0');
        li.append(nome, info);
        document.getElementById('lista-sessoes').prepend(li);

        const total = document.getElementById('minutos-hoje');
        const novo = parseInt(total.dataset.minutos, 10) + min;
        total.dataset.minutos = String(novo);
        total.textContent = novo >= 60 ? Math.floor(novo / 60) + 'h ' + String(novo % 60).padStart(2, '0') + 'min' : novo + 'min';
    }

    // O anel do timer assume a cor da matéria escolhida
    function aplicarCorMateria() {
        const opcao = selMateria.options[selMateria.selectedIndex];
        if (opcao && opcao.dataset.cor) timer.style.setProperty('--cor', opcao.dataset.cor);
        else timer.style.removeProperty('--cor');
        if (!estado.rodando) desenhar(estado.restante);
    }

    /* ---------- eventos ---------- */
    selMateria.addEventListener('change', aplicarCorMateria);
    btnIniciar.addEventListener('click', () => (estado.rodando ? pausar() : iniciar()));
    btnReiniciar.addEventListener('click', () => { pausar(); prepararFase(estado.fase); });
    btnPular.addEventListener('click', () => concluirFase(true));

    [campoFoco, campoPausa].forEach((campo) => campo.addEventListener('change', () => {
        try {
            localStorage.setItem('pomodoro_foco', String(minutos(campoFoco, 5, 90)));
            localStorage.setItem('pomodoro_pausa', String(minutos(campoPausa, 1, 30)));
        } catch (_) { /* ignora */ }
        if (!estado.rodando) prepararFase(estado.fase);
    }));

    // Barra de espaço inicia/pausa (fora de campos de texto)
    document.addEventListener('keydown', (evento) => {
        if (evento.code === 'Space' && !evento.target.closest('input, select, textarea, button')) {
            evento.preventDefault();
            btnIniciar.click();
        }
    });

    window.addEventListener('beforeunload', (evento) => {
        if (estado.rodando && estado.fase === 'foco') {
            evento.preventDefault();
            evento.returnValue = '';
        }
    });

    prepararFase('foco');
    aplicarCorMateria();
    desenharCiclos();
})();
