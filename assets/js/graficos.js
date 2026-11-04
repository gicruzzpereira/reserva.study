/**
 * Gráficos com Chart.js. Cada <canvas data-grafico="..."> é configurado pelos atributos:
 *   data-grafico  "barras" | "linha" | "rosca"
 *   data-rotulos  JSON com os rótulos
 *   data-valores  JSON com os valores
 *   data-sufixo   texto após o valor no tooltip (padrão: "%")
 *
 * Paleta contida: um único accent. Nas barras, o maior valor ganha a cor cheia
 * e os demais ficam em tom mais suave, para o olho ir direto ao destaque.
 */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    // As cores vêm dos tokens CSS e são relidas a cada desenho (modo claro/escuro)
    let css = getComputedStyle(document.documentElement);
    const cor = (nome) => css.getPropertyValue(nome).trim();

    let ACCENT, ACCENT_SUAVE, TRILHA, TEXTO;
    const reduzirMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function lerCoresDoTema() {
        css = getComputedStyle(document.documentElement);
        ACCENT = cor('--accent');
        ACCENT_SUAVE = 'rgba(' + cor('--ciano-rgb') + ', 0.55)'; // barras secundárias em ciano suave
        TRILHA = cor('--bg-elevated');
        TEXTO = cor('--text-secondary');
        Chart.defaults.font.family = cor('--fonte');
        Chart.defaults.color = TEXTO;
    }

    const tooltipBase = (sufixo) => ({
        backgroundColor: cor('--bg-elevated'),
        titleColor: cor('--text-primary'),
        bodyColor: cor('--text-primary'),
        padding: 10,
        cornerRadius: 10,
        displayColors: false,
        callbacks: { label: (ctx) => ' ' + (ctx.parsed.y ?? ctx.parsed) + sufixo }
    });

    function ler(canvas, atributo) {
        try { return JSON.parse(canvas.dataset[atributo] || '[]'); } catch (_) { return []; }
    }

    // No modo claro, escurece um pouco as cores das matérias (tons claros somem no branco)
    function ajustarCor(hex) {
        if (document.documentElement.dataset.tema !== 'claro' || !/^#[0-9a-f]{6}$/i.test(hex)) return hex;
        const alvo = [12, 42, 61]; // azul-marinho do texto
        const canais = [1, 3, 5].map((i, n) => Math.round(parseInt(hex.slice(i, i + 2), 16) * 0.82 + alvo[n] * 0.18));
        return '#' + canais.map((v) => v.toString(16).padStart(2, '0')).join('');
    }

    function abreviar(nome) {
        return nome.length > 7 ? nome.slice(0, 5) + '.' : nome;
    }

    const construtores = {
        barras(canvas, rotulos, valores, sufixo, cores) {
            const maior = Math.max(...valores);
            // Com data-cores, cada barra usa a cor da sua matéria; sem, destaca só a maior
            const preenchimento = cores.length
                ? cores
                : valores.map((v) => (v === maior && v > 0 ? ACCENT : ACCENT_SUAVE));
            return {
                type: 'bar',
                data: {
                    labels: rotulos,
                    datasets: [
                        { // trilha de 100% ao fundo
                            data: valores.map(() => 100), backgroundColor: TRILHA,
                            borderRadius: 10, borderSkipped: false, barPercentage: 0.6, grouped: false, order: 2
                        },
                        {
                            data: valores,
                            backgroundColor: preenchimento,
                            borderRadius: 10, borderSkipped: false, barPercentage: 0.6, grouped: false, order: 1
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: { ...tooltipBase(sufixo), filter: (item) => item.datasetIndex === 1 }
                    },
                    scales: {
                        x: {
                            grid: { display: false }, border: { display: false },
                            ticks: { font: { size: 11 }, callback(v) { return abreviar(this.getLabelForValue(v)); } }
                        },
                        y: { display: false, min: 0, max: 100 }
                    }
                }
            };
        },

        linha(canvas, rotulos, valores, sufixo, cores) {
            // Cada ponto pode ter a cor da matéria do simulado (data-cores) e o nome dela no tooltip (data-legendas)
            const legendas = ler(canvas, 'legendas');
            const contexto = canvas.getContext('2d');
            const gradiente = contexto.createLinearGradient(0, 0, 0, canvas.clientHeight || 240);
            gradiente.addColorStop(0, 'rgba(' + cor('--accent-rgb') + ', 0.28)');
            gradiente.addColorStop(1, 'rgba(' + cor('--accent-rgb') + ', 0)');
            return {
                type: 'line',
                data: {
                    labels: rotulos,
                    datasets: [{
                        data: valores, borderColor: 'rgba(' + cor('--linha-rgb') + ', 0.45)', backgroundColor: gradiente, fill: true,
                        tension: 0.2, borderWidth: 2, pointRadius: 5, pointHoverRadius: 7,
                        pointBackgroundColor: cores.length ? cores : ACCENT, pointBorderColor: cor('--bg-surface'), pointBorderWidth: 2
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipBase(sufixo),
                            callbacks: {
                                title: (itens) => (legendas[itens[0].dataIndex] ? legendas[itens[0].dataIndex] + ', ' : '') + itens[0].label,
                                label: (ctx) => ' ' + ctx.parsed.y + sufixo + ' de acertos'
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: {
                            min: 0, max: 100, border: { display: false },
                            grid: { color: 'rgba(' + cor('--linha-rgb') + ', 0.08)' },
                            ticks: { stepSize: 25, callback: (v) => v + sufixo }
                        }
                    }
                }
            };
        },

        rosca(canvas, rotulos, valores, sufixo) {
            const tons = [ACCENT, cor('--ciano'), cor('--danger-claro'), cor('--amarelo')];
            return {
                type: 'doughnut',
                data: {
                    labels: rotulos,
                    datasets: [{ data: valores, backgroundColor: tons, borderColor: cor('--bg-surface'), borderWidth: 4, hoverOffset: 6 }]
                },
                options: {
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 16 } },
                        tooltip: { ...tooltipBase(sufixo), callbacks: { label: (ctx) => ' ' + ctx.label + ': ' + ctx.parsed + sufixo } }
                    }
                }
            };
        }
    };

    function desenharTodos(animar) {
        lerCoresDoTema();
        document.querySelectorAll('canvas[data-grafico]').forEach((canvas) => desenhar(canvas, animar));
    }

    function desenhar(canvas, animar) {
        const tipo = canvas.dataset.grafico;
        if (!construtores[tipo]) return;
        Chart.getChart(canvas)?.destroy();
        const sufixo = canvas.dataset.sufixo ?? '%';
        const config = construtores[tipo](canvas, ler(canvas, 'rotulos'), ler(canvas, 'valores'), sufixo, ler(canvas, 'cores').map(ajustarCor));
        config.options.responsive = true;
        config.options.maintainAspectRatio = false;
        config.options.animation = (reduzirMovimento || !animar) ? false : { duration: 700, easing: 'easeOutQuart' };
        new Chart(canvas, config);
    }

    desenharTodos(true);
    // Ao trocar o tema, redesenha sem animação (as cores mudam na hora)
    document.addEventListener('temaalterado', () => setTimeout(() => desenharTodos(false), 30));
})();
