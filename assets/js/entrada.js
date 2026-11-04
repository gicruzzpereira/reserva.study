/**
 * Telas de entrada: carrossel de recursos e botão "Entrar com a conta de teste".
 */
(function () {
    'use strict';

    /* ---------- Imagens do painel: 5 s cada, em loop (da última volta para a primeira) ---------- */
    const imagens = [...document.querySelectorAll('[data-imagem]')];
    if (imagens.length > 1) {
        let imagemAtual = 0;
        setInterval(() => {
            imagens[imagemAtual].classList.remove('ativo');
            imagemAtual = (imagemAtual + 1) % imagens.length;
            imagens[imagemAtual].classList.add('ativo');
        }, 5000);
    }

    /* ---------- Carrossel ---------- */
    const carrossel = document.querySelector('[data-carrossel]');
    if (carrossel) {
        const slides = [...carrossel.querySelectorAll('[data-slide]')];
        const reduzir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let atual = 0;
        let timer = null;

        const mostrar = (indice) => {
            atual = (indice + slides.length) % slides.length;
            slides.forEach((s, i) => {
                s.classList.toggle('ativo', i === atual);
                s.setAttribute('aria-hidden', i === atual ? 'false' : 'true');
            });
        };
        const iniciar = () => { if (!reduzir) { clearInterval(timer); timer = setInterval(() => mostrar(atual + 1), 5000); } };
        const parar = () => clearInterval(timer);

        carrossel.querySelector('[data-anterior]').addEventListener('click', () => { mostrar(atual - 1); iniciar(); });
        carrossel.querySelector('[data-proximo]').addEventListener('click', () => { mostrar(atual + 1); iniciar(); });
        carrossel.addEventListener('mouseenter', parar);
        carrossel.addEventListener('mouseleave', iniciar);
        iniciar();
    }

    /* ---------- Conta de teste ---------- */
    document.querySelector('[data-conta-teste]')?.addEventListener('click', (evento) => {
        const botao = evento.currentTarget;
        const form = document.getElementById('form-login');
        form.querySelector('#email').value = botao.dataset.email;
        form.querySelector('#senha').value = botao.dataset.senha;
        botao.disabled = true;
        form.requestSubmit();
    });
})();
