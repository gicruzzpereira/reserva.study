/**
 * Perfil: medidor de força da nova senha e seletor de tema (claro/escuro).
 */
(function () {
    'use strict';

    /* ---------- Força da senha ---------- */
    const campo = document.getElementById('senha_nova');
    const barra = document.getElementById('forca-barra');
    const texto = document.getElementById('forca-texto');

    const niveis = [
        { rotulo: 'Mínimo de 6 caracteres.', largura: 0, cor: 'var(--bg-elevated)' },
        { rotulo: 'Senha fraca: misture letras, números e símbolos.', largura: 33, cor: 'var(--danger)' },
        { rotulo: 'Senha razoável.', largura: 66, cor: 'var(--aviso)' },
        { rotulo: 'Senha forte.', largura: 100, cor: 'var(--success)' }
    ];

    function forca(senha) {
        if (senha.length < 6) return 0;
        let pontos = 0;
        if (senha.length >= 10) pontos++;
        if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) pontos++;
        if (/\d/.test(senha)) pontos++;
        if (/[^A-Za-z0-9]/.test(senha)) pontos++;
        return pontos <= 1 ? 1 : pontos <= 2 ? 2 : 3;
    }

    if (campo) {
        campo.addEventListener('input', () => {
            const nivel = niveis[forca(campo.value)];
            barra.style.width = nivel.largura + '%';
            barra.style.background = nivel.cor;
            texto.textContent = nivel.rotulo;
        });
    }

    /* ---------- Foto de perfil ----------
     * Recorta o centro da imagem em um quadrado 400x400 e comprime em JPG
     * antes de enviar. Redesenhar no canvas também descarta metadados
     * (como a localização gravada por câmeras de celular).
     */
    const inputFoto = document.getElementById('input-foto');
    if (inputFoto) {
        const { Toast } = window.ReservaStudy;
        const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

        const carregarImagem = (arquivo) => new Promise((resolver, rejeitar) => {
            const url = URL.createObjectURL(arquivo);
            const img = new Image();
            img.onload = () => { URL.revokeObjectURL(url); resolver(img); };
            img.onerror = () => { URL.revokeObjectURL(url); rejeitar(new Error('imagem inválida')); };
            img.src = url;
        });

        async function recortar(arquivo) {
            const img = await carregarImagem(arquivo);
            const lado = Math.min(img.naturalWidth, img.naturalHeight);
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 400;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#FFFFFF'; // fundo para PNG transparente
            ctx.fillRect(0, 0, 400, 400);
            ctx.drawImage(img, (img.naturalWidth - lado) / 2, (img.naturalHeight - lado) / 2, lado, lado, 0, 0, 400, 400);
            const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.88));
            return new File([blob], 'foto.jpg', { type: 'image/jpeg' });
        }

        inputFoto.addEventListener('change', async () => {
            const arquivo = inputFoto.files[0];
            if (!arquivo) return;
            if (!TIPOS.includes(arquivo.type)) {
                Toast.mostrar('Envie uma imagem JPG, PNG ou WebP.', 'erro');
                inputFoto.value = '';
                return;
            }
            if (arquivo.size > 15 * 1024 * 1024) {
                Toast.mostrar('Imagem muito grande. Escolha uma de até 15 MB.', 'erro');
                inputFoto.value = '';
                return;
            }

            document.querySelector('.perfil-foto').classList.add('perfil-foto--enviando');
            try {
                const pronta = await recortar(arquivo);
                const lista = new DataTransfer();
                lista.items.add(pronta);
                inputFoto.files = lista.files;
            } catch (_) {
                // Se o recorte falhar, envia o original (o servidor valida)
            }
            document.getElementById('form-foto').submit();
        });
    }

    /* ---------- Tema ---------- */
    const opcoes = document.querySelectorAll('[data-escolher-tema]');
    function marcarAtual() {
        const atual = document.documentElement.dataset.tema === 'claro' ? 'claro' : 'escuro';
        opcoes.forEach((radio) => { radio.checked = radio.value === atual; });
    }
    opcoes.forEach((radio) => radio.addEventListener('change', () => window.ReservaStudy.definirTema(radio.value)));
    // Se o tema mudar pelo botão da barra superior, o seletor acompanha
    document.addEventListener('temaalterado', marcarAtual);
    marcarAtual();
})();
