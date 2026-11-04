/**
 * RESERVA STUDY — Scripts comuns a todas as páginas internas.
 * - Toasts (mensagens de sucesso/erro)
 * - Modal de confirmação para formulários com data-confirmar
 * - Validação simples de formulários com data-validar
 * - Transição suave entre páginas
 * - Helper de fetch() com token CSRF
 */
(function () {
    'use strict';

    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '';

    /* ------------------------------------------------------------------
     * Ícones usados pelos toasts (mesmo traço de includes/icones.php)
     * ---------------------------------------------------------------- */
    const ICONES_TOAST = {
        sucesso: '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.2 2.4 2.4 4.6-4.8"/>',
        erro: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4.5"/><path d="M12 17.2h.01"/>',
        aviso: '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4.5"/><path d="M12 17.2h.01"/>',
        info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5"/><path d="M12 7.8h.01"/>'
    };

    function svg(tracos, classe) {
        return '<svg class="icone ' + (classe || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + tracos + '</svg>';
    }

    /* ------------------------------------------------------------------
     * Toasts
     * ---------------------------------------------------------------- */
    const Toast = {
        /**
         * Exibe uma mensagem.
         * @param {string} mensagem
         * @param {'sucesso'|'erro'|'aviso'|'info'} tipo
         * @param {number} duracao em milissegundos
         */
        mostrar(mensagem, tipo = 'sucesso', duracao = 4500) {
            const area = document.getElementById('toasts');
            if (!area) return;

            const toast = document.createElement('div');
            toast.className = 'toast toast--' + tipo;
            toast.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');

            const texto = document.createElement('p');
            texto.className = 'toast__mensagem';
            texto.textContent = mensagem; // textContent evita injeção de HTML

            const fechar = document.createElement('button');
            fechar.type = 'button';
            fechar.className = 'toast__fechar';
            fechar.setAttribute('aria-label', 'Fechar mensagem');
            fechar.innerHTML = svg('<path d="M6 6l12 12M18 6 6 18"/>', 'icone--sm');

            toast.innerHTML = svg(ICONES_TOAST[tipo] || ICONES_TOAST.info);
            toast.append(texto, fechar);
            area.appendChild(toast);

            const remover = () => {
                toast.classList.add('toast--saindo');
                toast.addEventListener('animationend', () => toast.remove(), { once: true });
            };
            fechar.addEventListener('click', remover);
            setTimeout(remover, duracao);
        }
    };

    // Exibe as mensagens que o PHP deixou na sessão
    function exibirToastsPendentes() {
        const dados = document.getElementById('toasts-pendentes');
        if (!dados) return;
        try {
            JSON.parse(dados.textContent || '[]').forEach((t, i) => {
                setTimeout(() => Toast.mostrar(t.mensagem, t.tipo), i * 150);
            });
        } catch (erro) {
            console.error('Não foi possível ler os toasts pendentes.', erro);
        }
    }

    /* ------------------------------------------------------------------
     * Modal de confirmação
     * Uso: <form data-confirmar="Excluir esta matéria?" data-confirmar-texto="...">
     * ---------------------------------------------------------------- */
    function confirmar(titulo, texto, rotuloBotao = 'Confirmar') {
        const modal = document.getElementById('modal-confirmar');
        if (!modal || typeof modal.showModal !== 'function') {
            return Promise.resolve(window.confirm(titulo));
        }

        modal.querySelector('#modal-confirmar-titulo').textContent = titulo;
        modal.querySelector('#modal-confirmar-texto').textContent = texto || 'Esta ação não pode ser desfeita.';
        modal.querySelector('[data-modal-confirmar]').textContent = rotuloBotao;

        return new Promise((resolver) => {
            const btnOk = modal.querySelector('[data-modal-confirmar]');
            const btnCancelar = modal.querySelector('[data-modal-cancelar]');

            const finalizar = (resposta) => {
                btnOk.removeEventListener('click', aoConfirmar);
                btnCancelar.removeEventListener('click', aoCancelar);
                modal.removeEventListener('cancel', aoCancelar);
                modal.close();
                resolver(resposta);
            };
            const aoConfirmar = () => finalizar(true);
            const aoCancelar = (evento) => { evento.preventDefault(); finalizar(false); };

            btnOk.addEventListener('click', aoConfirmar);
            btnCancelar.addEventListener('click', aoCancelar);
            modal.addEventListener('cancel', aoCancelar); // tecla Esc

            modal.showModal();
            btnCancelar.focus();
        });
    }

    function ativarConfirmacoes() {
        document.addEventListener('submit', async (evento) => {
            const form = evento.target;
            if (!form.matches('[data-confirmar]') || form.dataset.confirmado === '1') return;

            evento.preventDefault();
            const ok = await confirmar(
                form.dataset.confirmar,
                form.dataset.confirmarTexto,
                form.dataset.confirmarBotao || 'Excluir'
            );
            if (ok) {
                form.dataset.confirmado = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    }

    /* ------------------------------------------------------------------
     * Validação de formulários (front-end)
     * Uso: <form data-validar> com campos dentro de .campo e um .campo__erro
     * ---------------------------------------------------------------- */
    function mensagemDeErro(campo) {
        const v = campo.validity;
        if (v.valueMissing) return 'Preencha este campo.';
        if (v.typeMismatch && campo.type === 'email') return 'Informe um e-mail válido.';
        if (v.tooShort) return 'Use pelo menos ' + campo.minLength + ' caracteres.';
        if (v.tooLong) return 'Use no máximo ' + campo.maxLength + ' caracteres.';
        if (v.rangeUnderflow) return 'O valor mínimo é ' + campo.min + '.';
        if (v.rangeOverflow) return 'O valor máximo é ' + campo.max + '.';
        if (v.patternMismatch) return campo.dataset.mensagemPadrao || 'Formato inválido.';
        if (v.customError) return campo.validationMessage;
        return 'Valor inválido.';
    }

    function validarCampo(campo) {
        // Confirmação de senha: data-igual-a="id-do-outro-campo"
        if (campo.dataset.igualA) {
            const outro = document.getElementById(campo.dataset.igualA);
            campo.setCustomValidity(outro && outro.value !== campo.value ? 'As senhas não conferem.' : '');
        }

        const grupo = campo.closest('.campo');
        if (!grupo) return campo.checkValidity();

        const valido = campo.checkValidity();
        grupo.classList.toggle('campo--erro', !valido);
        const areaErro = grupo.querySelector('.campo__erro');
        if (areaErro) areaErro.textContent = valido ? '' : mensagemDeErro(campo);
        campo.setAttribute('aria-invalid', valido ? 'false' : 'true');
        return valido;
    }

    function ativarValidacao() {
        document.querySelectorAll('form[data-validar]').forEach((form) => {
            form.noValidate = true;
            const campos = () => form.querySelectorAll('input, select, textarea');

            campos().forEach((campo) => {
                campo.addEventListener('blur', () => validarCampo(campo));
                campo.addEventListener('input', () => {
                    if (campo.closest('.campo--erro')) validarCampo(campo);
                });
            });

            form.addEventListener('submit', (evento) => {
                let primeiroInvalido = null;
                campos().forEach((campo) => {
                    if (campo.disabled || campo.closest('[hidden]')) return;
                    if (!validarCampo(campo) && !primeiroInvalido) primeiroInvalido = campo;
                });
                if (primeiroInvalido) {
                    evento.preventDefault();
                    evento.stopImmediatePropagation();
                    primeiroInvalido.focus();
                    Toast.mostrar('Confira os campos destacados.', 'erro');
                }
            }, true); // captura: roda antes da confirmação
        });
    }

    /* ------------------------------------------------------------------
     * Transição suave ao trocar de página
     * ---------------------------------------------------------------- */
    function ativarTransicoes() {
        document.addEventListener('click', (evento) => {
            const link = evento.target.closest('a[href]');
            if (!link || evento.defaultPrevented || evento.button !== 0 ||
                evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;

            const destino = new URL(link.href, location.href);
            if (destino.origin !== location.origin || destino.hash && destino.pathname === location.pathname) return;

            evento.preventDefault();
            document.body.classList.add('saindo');
            setTimeout(() => { location.href = destino.href; }, 150);
        });

        // Ao voltar pelo histórico do navegador, remove o estado de saída
        window.addEventListener('pageshow', () => document.body.classList.remove('saindo'));
    }

    /* ------------------------------------------------------------------
     * fetch() com JSON e token CSRF — usado pelos módulos (simulado, pomodoro…)
     * ---------------------------------------------------------------- */
    async function api(caminho, dados = null, metodo = null) {
        const opcoes = {
            method: metodo || (dados ? 'POST' : 'GET'),
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            credentials: 'same-origin'
        };
        if (dados) {
            opcoes.headers['Content-Type'] = 'application/json';
            opcoes.body = JSON.stringify(dados);
        }

        const resposta = await fetch(BASE_URL + '/' + caminho.replace(/^\//, ''), opcoes);
        let corpo = {};
        try { corpo = await resposta.json(); } catch (_) { /* resposta sem JSON */ }

        if (!resposta.ok || corpo.ok === false) {
            throw new Error(corpo.mensagem || 'Não foi possível concluir a operação.');
        }
        return corpo;
    }

    /* ------------------------------------------------------------------
     * Inicialização
     * ---------------------------------------------------------------- */
    // Botão de olho para mostrar/ocultar senha
    function ativarMostrarSenha() {
        document.querySelectorAll('[data-mostrar-senha]').forEach((botao) => {
            botao.addEventListener('click', () => {
                const campo = document.getElementById(botao.dataset.mostrarSenha);
                const visivel = campo.type === 'text';
                campo.type = visivel ? 'password' : 'text';
                botao.setAttribute('aria-label', visivel ? 'Mostrar senha' : 'Ocultar senha');
            });
        });
    }

    /* ------------------------------------------------------------------
     * Modo claro / escuro
     * O tema inicial é aplicado no <head> (sem piscar). Aqui só a troca.
     * ---------------------------------------------------------------- */
    const CORES_BARRA_NAVEGADOR = { escuro: '#01273A', claro: '#F2F7FA' };

    function temaAtual() {
        return document.documentElement.dataset.tema === 'claro' ? 'claro' : 'escuro';
    }

    function atualizarBotoesTema() {
        const proximo = temaAtual() === 'claro' ? 'Ativar modo escuro' : 'Ativar modo claro';
        document.querySelectorAll('[data-alternar-tema]').forEach((botao) => {
            botao.setAttribute('aria-label', proximo);
            botao.title = proximo;
        });
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', CORES_BARRA_NAVEGADOR[temaAtual()]);
    }

    function alternarTema() {
        definirTema(temaAtual() === 'claro' ? 'escuro' : 'claro');
    }

    function definirTema(novo) {
        const raiz = document.documentElement;
        if (novo !== 'claro' && novo !== 'escuro') return;
        if (novo === temaAtual()) return;

        // Transição suave só durante a troca (não pesa no resto do uso)
        raiz.classList.add('trocando-tema');
        raiz.dataset.tema = novo;
        try { localStorage.setItem('tema', novo); } catch (_) { /* sem armazenamento: vale só nesta página */ }
        atualizarBotoesTema();
        document.dispatchEvent(new CustomEvent('temaalterado', { detail: { tema: novo } }));
        setTimeout(() => raiz.classList.remove('trocando-tema'), 320);
    }

    function ativarTema() {
        document.querySelectorAll('[data-alternar-tema]').forEach((botao) => botao.addEventListener('click', alternarTema));
        atualizarBotoesTema();
    }

    // Filtros que se aplicam sozinhos: <select data-enviar-ao-mudar>
    function ativarFiltrosAutomaticos() {
        document.querySelectorAll('[data-enviar-ao-mudar]').forEach((campo) => {
            campo.addEventListener('change', () => campo.form?.requestSubmit());
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        ativarTema();
        ativarMostrarSenha();
        ativarFiltrosAutomaticos();
        exibirToastsPendentes();
        ativarValidacao();
        ativarConfirmacoes();
        ativarTransicoes();
    });

    // Disponibiliza para os scripts de cada módulo
    window.ReservaStudy = { Toast, confirmar, api, validarCampo, definirTema, CSRF_TOKEN, BASE_URL };
})();
