<?php
/**
 * Biblioteca de ícones SVG inline (estilo traço, 24×24, stroke = currentColor).
 * Uso: echo icone('painel');  ou  echo icone('sino', 'icone--sm');
 */

function icone(string $nome, string $classe = ''): string
{
    static $tracos = [
        // Navegação
        'painel'       => '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
        'simulado'     => '<rect x="5" y="4" width="14" height="17" rx="2.5"/><path d="M9 4V3h6v1"/><path d="m8.5 11 1.5 1.5 3-3"/><path d="M8.5 16.5h7"/>',
        'historico'    => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M21 20H3"/>',
        'cronograma'   => '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M8 14h2M14 14h2M8 17h2"/>',
        'pomodoro'     => '<circle cx="12" cy="13" r="7.5"/><path d="M12 9.5V13l2.5 2"/><path d="M9.5 2.5h5"/><path d="M12 2.5v3"/>',
        'revisoes'     => '<path d="M20 11.5A8 8 0 0 0 5.6 7"/><path d="M4 12.5A8 8 0 0 0 18.4 17"/><path d="M5 3v4.5h4.5"/><path d="M19 21v-4.5h-4.5"/>',
        'caderno'      => '<path d="M6 3.5h11.5A1.5 1.5 0 0 1 19 5v14.5a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2Z"/><path d="M8 3.5v17"/><path d="m11.5 9 4 4M15.5 9l-4 4"/>',
        'brilho'       => '<path d="M12 2.5c.8 5.3 3.7 8.2 9.5 9.5-5.8 1.3-8.7 4.2-9.5 9.5-.8-5.3-3.7-8.2-9.5-9.5 5.8-1.3 8.7-4.2 9.5-9.5Z"/>',
        'coroa'        => '<path d="M4 8.5 7.5 12 12 5.5l4.5 6.5L20 8.5 18.5 18h-13L4 8.5Z"/><path d="M6 21h12"/>',
        'materias'     => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15Z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/><path d="M9 8h6"/>',
        'questoes'     => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.3a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.1-2.4 3.6"/><path d="M12 17h.01"/>',
        'usuarios'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18.5 14.2a6.5 6.5 0 0 1 3 5.8"/>',
        'sair'         => '<path d="M14 4h3.5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5H14"/><path d="M10 16.5 5.5 12 10 7.5"/><path d="M5.5 12H15"/>',
        'configuracoes'=> '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',

        // Topbar e interface
        'busca'        => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
        'sino'         => '<path d="M6 9.5a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15.5 6 9.5Z"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>',
        'idioma'       => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.4 3.4 5.2 3.4 8.5s-1.1 6.1-3.4 8.5c-2.3-2.4-3.4-5.2-3.4-8.5S9.7 5.9 12 3.5Z"/>',
        'seta-baixo'   => '<path d="m6 9.5 6 6 6-6"/>',
        'seta-direita' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'mais'         => '<path d="M12 5v14M5 12h14"/>',
        'editar'       => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>',
        'lixeira'      => '<path d="M4 7h16"/><path d="M9.5 7V4.5h5V7"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v5.5M14 11v5.5"/>',
        'fechar'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        'check'        => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'check-circulo'=> '<circle cx="12" cy="12" r="8.5"/><path d="m8.5 12.2 2.4 2.4 4.6-4.8"/>',
        'alerta'       => '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4.5"/><path d="M12 17.2h.01"/>',
        'info'         => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5.5"/><path d="M12 7.8h.01"/>',
        'relogio'      => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'chama'        => '<path d="M12 21a6.5 6.5 0 0 0 6.5-6.5c0-4.5-4-6-4.5-10.5-2.5 1.5-4 4-3.5 7-1.2-.6-2-1.7-2.2-3C6.6 9.6 5.5 12 5.5 14.5A6.5 6.5 0 0 0 12 21Z"/>',
        'play'         => '<path d="M7.5 5.5v13l11-6.5-11-6.5Z"/>',
        'pause'        => '<path d="M8 5.5v13M16 5.5v13"/>',
        'filtro'       => '<path d="M4 5h16l-6.2 7.3V19l-3.6-1.8v-4.9L4 5Z"/>',
        'cadeado'      => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'email'        => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'olho'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'redacao'      => '<path d="M13.5 3.5h-7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-6.5"/><path d="M8 12.5h4.5M8 16h7"/><path d="m17.6 3.4 2.9 2.9-6.3 6.3-3.4.5.5-3.4 6.3-6.3Z"/>',
        'camera'       => '<path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h1.8l1.5-2h4.4l1.5 2h1.8A2.5 2.5 0 0 1 20 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-9Z"/><circle cx="12" cy="12.5" r="3.5"/>',
        'baixar'       => '<path d="M12 4v11"/><path d="m7 10.5 5 5 5-5"/><path d="M4.5 19.5h15"/>',
        'usuario'      => '<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'sol'          => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>',
        'lua'          => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z"/>',
        'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',

        // Ícones das matérias
        'calculadora'  => '<rect x="5" y="3" width="14" height="18" rx="2.5"/><rect x="8" y="6" width="8" height="3.5" rx="1"/><path d="M8.5 13h.01M12 13h.01M15.5 13h.01M8.5 16.5h.01M12 16.5h.01M15.5 16.5h.01"/>',
        'atomo'        => '<circle cx="12" cy="12" r="1.3"/><ellipse cx="12" cy="12" rx="9" ry="3.6"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(60 12 12)"/><ellipse cx="12" cy="12" rx="9" ry="3.6" transform="rotate(120 12 12)"/>',
        'frasco'       => '<path d="M9.5 3h5"/><path d="M10 3v6L4.8 18a2 2 0 0 0 1.7 3h11a2 2 0 0 0 1.7-3L14 9V3"/><path d="M7.5 14.5h9"/>',
        'folha'        => '<path d="M5 19C5 10 10 5 20 4c-.5 10-5.5 15-14 15"/><path d="M5 19c3-4 6-6.5 9.5-8.5"/>',
        'coluna'       => '<path d="M3.5 8.5 12 4l8.5 4.5"/><path d="M4.5 8.5h15"/><path d="M6.5 11v6.5M10 11v6.5M14 11v6.5M17.5 11v6.5"/><path d="M4 20.5h16"/>',
        'globo'        => '<circle cx="12" cy="12" r="8.5"/><path d="M4 9.5h5l1.5 2.5-1.5 3 1 3.5"/><path d="M14.5 4.2 13.5 7l2.5 1.5 3.5-.5"/><path d="M20.3 13.5 17 13l-1.5 2.5 1 3.2"/>',
        'pena'         => '<path d="M20 4c-7 0-12 4.5-13.5 11.5L5 20"/><path d="M20 4c-.5 6.5-4.5 11-11 12"/><path d="M9.5 14.5c2-.2 4.2-1 5.6-2.5"/>',
        'lampada'      => '<path d="M9 18.5h6"/><path d="M10 21.5h4"/><path d="M12 2.5a6.5 6.5 0 0 0-3.9 11.7c.6.5.9 1.2.9 2V16.5h6v-.3c0-.8.3-1.5.9-2A6.5 6.5 0 0 0 12 2.5Z"/>',
        'pessoas'      => '<circle cx="12" cy="7.5" r="3"/><path d="M6.5 19.5a5.5 5.5 0 0 1 11 0"/><circle cx="4.8" cy="10" r="2.2"/><path d="M1.5 18a3.5 3.5 0 0 1 4.6-3.3"/><circle cx="19.2" cy="10" r="2.2"/><path d="M22.5 18a3.5 3.5 0 0 0-4.6-3.3"/>',
        'livro'        => '<path d="M12 6.5C10 5 7 4.5 3.5 5v13.5c3.5-.5 6.5 0 8.5 1.5 2-1.5 5-2 8.5-1.5V5C17 4.5 14 5 12 6.5Z"/><path d="M12 6.5V20"/>',
    ];

    $conteudo = $tracos[$nome] ?? $tracos['livro'];

    return '<svg class="icone ' . e($classe) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . $conteudo . '</svg>';
}

/** Lista de ícones disponíveis para matérias (usada no CRUD do admin). */
function icones_materias(): array
{
    return [
        'calculadora' => 'Calculadora', 'atomo' => 'Átomo', 'frasco' => 'Frasco',
        'folha' => 'Folha', 'coluna' => 'Coluna', 'globo' => 'Globo',
        'pena' => 'Pena', 'lampada' => 'Lâmpada', 'pessoas' => 'Pessoas', 'livro' => 'Livro',
    ];
}
