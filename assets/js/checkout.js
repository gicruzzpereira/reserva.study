/**
 * Checkout do Premium (demonstrativo).
 * - Atualiza o resumo conforme o ciclo (mensal/anual)
 * - Alterna entre cartão e Pix
 * - Prévia do cartão em tempo real (o cartão vira ao digitar o CVV)
 * - Valida o cartão no navegador (Luhn, validade, CVV) — nada disso é enviado
 * - Mostra o estado "processando" antes de enviar
 */
(function () {
    'use strict';

    const { Toast } = window.ReservaStudy;
    const form = document.getElementById('form-checkout');
    if (!form) return;

    const $ = (id) => document.getElementById(id);
    const moeda = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    /* ---------------- Resumo do pedido ---------------- */
    function atualizarResumo() {
        const ciclo = form.querySelector('input[name="ciclo"]:checked');
        const valor = parseFloat(ciclo.dataset.valor);
        const desconto = parseFloat(ciclo.dataset.desconto);
        const meses = parseInt(ciclo.dataset.meses, 10);

        $('resumo-nome').textContent = ciclo.dataset.nome;
        $('resumo-bruto').textContent = moeda(valor + desconto);
        $('resumo-desconto').textContent = '- ' + moeda(desconto);
        $('resumo-linha-desconto').hidden = desconto <= 0;
        $('resumo-total').textContent = moeda(valor);

        const renova = new Date();
        renova.setMonth(renova.getMonth() + meses);
        $('resumo-renovacao').textContent = 'Renova em ' + renova.toLocaleDateString('pt-BR') +
            ' por ' + moeda(valor) + '. Nesta demonstração, nada é cobrado.';
    }

    /* ---------------- Cartão ou Pix ---------------- */
    function metodoAtual() {
        return form.querySelector('input[name="metodo"]:checked').value;
    }

    function atualizarMetodo() {
        const pix = metodoAtual() === 'pix';
        $('painel-cartao').hidden = pix;
        $('painel-pix').hidden = !pix;
        form.querySelector('.checkout__botao-texto').textContent = pix ? 'Já paguei, confirmar' : 'Confirmar assinatura';
    }

    /* ---------------- Prévia do cartão ---------------- */
    const campos = {
        numero: $('cartao-numero'),
        nome: $('cartao-nome'),
        validade: $('cartao-validade'),
        cvv: $('cartao-cvv')
    };
    const preview = $('cartao-preview');

    function bandeira(numero) {
        if (/^4/.test(numero)) return 'Visa';
        if (/^(5[1-5]|2[2-7])/.test(numero)) return 'Mastercard';
        if (/^3[47]/.test(numero)) return 'Amex';
        if (/^(636368|438935|504175|451416|636297|5067|4576|4011)/.test(numero)) return 'Elo';
        return 'Cartão';
    }

    campos.numero.addEventListener('input', () => {
        const digitos = campos.numero.value.replace(/\D/g, '').slice(0, 16);
        campos.numero.value = digitos.replace(/(.{4})/g, '$1 ').trim();
        const mascara = (digitos + '•'.repeat(16 - digitos.length)).replace(/(.{4})/g, '$1 ').trim();
        $('cartao-numero-preview').textContent = mascara;
        $('cartao-bandeira').textContent = bandeira(digitos);
    });

    campos.nome.addEventListener('input', () => {
        campos.nome.value = campos.nome.value.replace(/[^A-Za-zÀ-ÿ\s]/g, '');
        $('cartao-nome-preview').textContent = campos.nome.value.trim().toUpperCase() || 'SEU NOME';
    });

    campos.validade.addEventListener('input', () => {
        const d = campos.validade.value.replace(/\D/g, '').slice(0, 4);
        campos.validade.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
        $('cartao-validade-preview').textContent = campos.validade.value || 'MM/AA';
    });

    campos.cvv.addEventListener('input', () => {
        campos.cvv.value = campos.cvv.value.replace(/\D/g, '').slice(0, 4);
        $('cartao-cvv-preview').textContent = campos.cvv.value.replace(/./g, '•') || '•••';
    });

    // O cartão vira para mostrar o verso enquanto o CVV está em foco
    campos.cvv.addEventListener('focus', () => preview.classList.add('cartao-preview--verso'));
    campos.cvv.addEventListener('blur', () => preview.classList.remove('cartao-preview--verso'));

    $('btn-cartao-teste').addEventListener('click', () => {
        const teste = { numero: '4242424242424242', nome: 'Estudante Teste', validade: '1230', cvv: '123' };
        Object.entries(teste).forEach(([chave, valor]) => {
            campos[chave].value = valor;
            campos[chave].dispatchEvent(new Event('input'));
            marcarErro(campos[chave], '');
        });
    });

    /* ---------------- Validação (só no navegador) ---------------- */
    function luhn(numero) {
        let soma = 0;
        let dobrar = false;
        for (let i = numero.length - 1; i >= 0; i--) {
            let d = parseInt(numero[i], 10);
            if (dobrar) { d *= 2; if (d > 9) d -= 9; }
            soma += d;
            dobrar = !dobrar;
        }
        return soma % 10 === 0;
    }

    function marcarErro(campo, mensagem) {
        const grupo = campo.closest('.campo');
        grupo.classList.toggle('campo--erro', Boolean(mensagem));
        grupo.querySelector('.campo__erro').textContent = mensagem;
        campo.setAttribute('aria-invalid', mensagem ? 'true' : 'false');
    }

    function validarCartao() {
        const numero = campos.numero.value.replace(/\D/g, '');
        const [mes, ano] = campos.validade.value.split('/').map((v) => parseInt(v, 10));
        const hoje = new Date();
        const fimValidade = new Date(2000 + (ano || 0), mes || 0, 1); // primeiro dia do mês seguinte

        const regras = [
            [campos.numero, numero.length < 13 ? 'Digite o número completo do cartão.' : (!luhn(numero) ? 'Número de cartão inválido.' : '')],
            [campos.nome, campos.nome.value.trim().split(/\s+/).length < 2 ? 'Digite nome e sobrenome.' : ''],
            [campos.validade, !(mes >= 1 && mes <= 12) || !ano ? 'Use o formato MM/AA.' : (fimValidade <= hoje ? 'Cartão vencido.' : '')],
            [campos.cvv, campos.cvv.value.length < 3 ? 'CVV com 3 ou 4 dígitos.' : '']
        ];

        let primeiroInvalido = null;
        regras.forEach(([campo, erro]) => {
            marcarErro(campo, erro);
            if (erro && !primeiroInvalido) primeiroInvalido = campo;
        });
        if (primeiroInvalido) primeiroInvalido.focus();
        return !primeiroInvalido;
    }

    // Revalida o campo ao sair dele, só se já estava com erro
    Object.values(campos).forEach((campo) => {
        campo.addEventListener('input', () => {
            if (campo.closest('.campo--erro')) marcarErro(campo, '');
        });
    });

    /* ---------------- Pix: copiar código ---------------- */
    $('btn-copiar-pix').addEventListener('click', async () => {
        const codigo = $('pix-codigo');
        try {
            await navigator.clipboard.writeText(codigo.value);
        } catch (_) {
            codigo.select();
            document.execCommand('copy');
        }
        Toast.mostrar('Código Pix copiado.', 'info', 2500);
    });

    /* ---------------- Envio ---------------- */
    let enviando = false;
    form.addEventListener('submit', (evento) => {
        evento.preventDefault();
        if (enviando) return;
        if (metodoAtual() === 'cartao' && !validarCartao()) {
            Toast.mostrar('Confira os dados do cartão.', 'erro');
            return;
        }

        enviando = true;
        const botao = $('btn-confirmar');
        botao.disabled = true;
        botao.classList.add('checkout__botao--processando');
        botao.querySelector('.checkout__botao-texto').textContent =
            metodoAtual() === 'pix' ? 'Confirmando o Pix...' : 'Processando pagamento...';

        // Pausa curta para o retorno visual; depois envia só ciclo + método + CSRF
        setTimeout(() => form.submit(), 1200);
    });

    form.querySelectorAll('input[name="ciclo"]').forEach((r) => r.addEventListener('change', atualizarResumo));
    form.querySelectorAll('input[name="metodo"]').forEach((r) => r.addEventListener('change', atualizarMetodo));

    atualizarResumo();
    atualizarMetodo();
})();
