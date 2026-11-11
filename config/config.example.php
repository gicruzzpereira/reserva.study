<?php
/**
 * Reserva Study — arquivo de configuração de EXEMPLO.
 *
 * Copie este arquivo para config/config.php e ajuste os valores.
 * O config.php NÃO deve ir para o controle de versão (está no .gitignore),
 * pois guarda a senha do banco e a chave da API da Anthropic.
 */

// --- Banco de dados (padrão do XAMPP: usuário root sem senha) ---
define('DB_HOST', 'localhost');
define('DB_PORTA', '3306');
define('DB_NOME', 'reservastudy');
define('DB_USUARIO', 'root');
define('DB_SENHA', '');

// --- Aplicação ---
define('APP_NOME', 'Reserva Study');

// Caminho da aplicação a partir da raiz do servidor (ex.: http://localhost/study-reserva).
// Deixe null para detectar automaticamente.
define('BASE_URL', null);

// Exibir erros detalhados (use false em produção)
define('APP_DEBUG', true);

// --- Módulo premium de IA (API da Anthropic) ---
// Deixe vazio para desativar a geração; o sistema mostrará uma mensagem amigável.
define('ANTHROPIC_API_KEY', '');
define('ANTHROPIC_MODELO', 'claude-opus-5-5');

// Gerações de IA gratuitas por mês para alunos do plano gratuito
define('IA_LIMITE_GRATUITO', 2);
