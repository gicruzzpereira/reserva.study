/**
 * Ajustar rotina: interruptores das matérias, botões de horas por dia e
 * prévia ao vivo da semana. A divisão dos blocos usa a mesma regra do
 * servidor (includes/estudos.php): proporcional à prioridade, pelos maiores restos.
 */
(function () {
    'use strict';

    const form = document.getElementById('form-cronograma');
    if (!form) return;

    const BLOCO = 50;
    const PAUSA = 10;

    /** Mesma regra de slots_do_dia(): blocos de 50 min + 10 de pausa; sobra de 30+ vira bloco curto. */
    function blocosPorHoras(horas) {
        let minutos = Math.round(horas * 60);
        let blocos = 0;
        while (minutos >= BLOCO) { blocos++; minutos -= BLOCO + PAUSA; }
        if (minutos >= 30) blocos++;
        return blocos;
    }

    const formatarHoras = (h) => String(h).replace('.', ',') + 'h';

    /* ---------- Matérias: interruptor liga/desliga a prioridade ---------- */
    const linhas = [...form.querySelectorAll('.prioridade-linha')];
    linhas.forEach((linha) => {
        const check = linha.querySelector('[data-materia-check]');
        const radios = linha.querySelectorAll('[data-peso]');
        const atualizar = () => {
            linha.classList.toggle('prioridade-linha--off', !check.checked);
            radios.forEach((r) => { r.disabled = !check.checked; });
            atualizarPrevia();
        };
        check.addEventListener('change', atualizar);
        radios.forEach((r) => r.addEventListener('change', atualizarPrevia));
        radios.forEach((r) => { r.disabled = !check.checked; });
    });

    /* ---------- Horas por dia: − e + de meia em meia hora ---------- */
    const dias = [...form.querySelectorAll('[data-horas-dia]')];
    dias.forEach((dia) => {
        const campo = dia.querySelector('[data-horas]');
        const mostrar = () => {
            const h = parseFloat(campo.value) || 0;
            dia.querySelector('[data-valor]').textContent = formatarHoras(h);
            const b = blocosPorHoras(h);
            dia.querySelector('[data-blocos]').textContent = h === 0 ? 'Folga' : b + (b === 1 ? ' bloco' : ' blocos');
            dia.classList.toggle('horas-dia--folga', h === 0);
            dia.querySelector('[data-menos]').disabled = h <= 0;
            dia.querySelector('[data-mais]').disabled = h >= 12;
        };
        const mudar = (delta) => {
            campo.value = Math.max(0, Math.min(12, (parseFloat(campo.value) || 0) + delta));
            mostrar();
            atualizarPrevia();
        };
        dia.querySelector('[data-menos]').addEventListener('click', () => mudar(-0.5));
        dia.querySelector('[data-mais]').addEventListener('click', () => mudar(0.5));
        mostrar();
    });

    /* ---------- Prévia da semana ---------- */
    const previaMaterias = document.getElementById('previa-materias');

    function atualizarPrevia() {
        const totalBlocos = dias.reduce((s, d) => s + blocosPorHoras(parseFloat(d.querySelector('[data-horas]').value) || 0), 0);
        const totalHoras = dias.reduce((s, d) => s + (parseFloat(d.querySelector('[data-horas]').value) || 0), 0);
        document.getElementById('previa-blocos').textContent = totalBlocos;
        document.getElementById('previa-horas').textContent = formatarHoras(totalHoras);

        const escolhidas = linhas
            .filter((l) => l.querySelector('[data-materia-check]').checked)
            .map((l) => ({
                nome: l.dataset.nome,
                cor: getComputedStyle(l).getPropertyValue('--cor').trim(),
                peso: parseInt(l.querySelector('[data-peso]:checked')?.value || '3', 10)
            }));

        // Divisão proporcional pelos maiores restos (igual ao servidor)
        const somaPesos = escolhidas.reduce((s, m) => s + m.peso, 0);
        escolhidas.forEach((m) => {
            const exato = somaPesos ? (totalBlocos * m.peso) / somaPesos : 0;
            m.blocos = Math.floor(exato);
            m.resto = exato - m.blocos;
        });
        let faltam = totalBlocos - escolhidas.reduce((s, m) => s + m.blocos, 0);
        [...escolhidas].sort((a, b) => b.resto - a.resto).forEach((m) => { if (faltam-- > 0) m.blocos++; });

        const maior = Math.max(1, ...escolhidas.map((m) => m.blocos));
        previaMaterias.replaceChildren(...escolhidas.sort((a, b) => b.blocos - a.blocos).map((m) => {
            const linha = document.createElement('div');
            linha.className = 'previa-materia';
            linha.style.setProperty('--cor', m.cor);
            const nome = document.createElement('span');
            nome.className = 'previa-materia__nome';
            nome.textContent = m.nome;
            const trilha = document.createElement('span');
            trilha.className = 'barra-materia__trilha';
            const valor = document.createElement('span');
            valor.className = 'barra-materia__valor';
            valor.style.display = 'block';
            valor.style.width = (m.blocos / maior) * 100 + '%';
            trilha.appendChild(valor);
            const numero = document.createElement('span');
            numero.className = 'numero';
            numero.textContent = m.blocos;
            linha.append(nome, trilha, numero);
            return linha;
        }));

        const aviso = document.getElementById('previa-aviso');
        const semBloco = escolhidas.filter((m) => m.blocos === 0).length;
        aviso.textContent = !escolhidas.length ? 'Ligue pelo menos uma matéria.'
            : !totalBlocos ? 'Reserve tempo em pelo menos um dia.'
            : semBloco ? semBloco + (semBloco === 1 ? ' matéria ficou' : ' matérias ficaram') + ' sem bloco: aumente as horas ou a prioridade dela.'
            : 'Os blocos são intercalados para você não estudar a mesma matéria duas vezes seguidas.';
    }

    form.addEventListener('submit', (evento) => {
        const { Toast } = window.ReservaStudy;
        if (!form.querySelector('[data-materia-check]:checked')) {
            evento.preventDefault();
            Toast.mostrar('Ligue pelo menos uma matéria.', 'erro');
        } else if (!dias.some((d) => parseFloat(d.querySelector('[data-horas]').value) > 0)) {
            evento.preventDefault();
            Toast.mostrar('Reserve tempo em pelo menos um dia.', 'erro');
        }
    });

    atualizarPrevia();
})();
