<?php
/**
 * Roteador para o servidor embutido do PHP (desenvolvimento sem Apache):
 *   C:\xampp\php\php.exe -S localhost:8090 router.php
 * Serve arquivos existentes, bloqueia pastas internas e mostra a 404 própria.
 */

$caminho = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$arquivo = __DIR__ . $caminho;

// Pastas internas nunca são servidas diretamente
if (preg_match('#^/(config|includes|database)/#', $caminho)) {
    require __DIR__ . '/404.php';
    return true;
}

// Uploads: só imagens são servidas (nunca scripts)
if (str_starts_with($caminho, '/uploads/') && !preg_match('#^/uploads/avatars/[a-z0-9-]+\.(jpe?g|png|webp)$#', $caminho)) {
    require __DIR__ . '/404.php';
    return true;
}

if ($caminho === '/' || $caminho === '') {
    require __DIR__ . '/index.php';
    return true;
}

if (is_file($arquivo)) {
    return false; // o servidor entrega o arquivo (PHP, CSS, JS, imagem...)
}

if (is_dir($arquivo) && is_file(rtrim($arquivo, '/') . '/index.php')) {
    require rtrim($arquivo, '/') . '/index.php';
    return true;
}

require __DIR__ . '/404.php';
return true;
