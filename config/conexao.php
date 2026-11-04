<?php
/**
 * Conexão única com o MySQL via PDO.
 * Uso: $pdo = db();
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORTA,
            DB_NOME
        );

        try {
            $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erros viram exceções
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // arrays associativos
                PDO::ATTR_EMULATE_PREPARES   => false,                 // prepared statements reais
            ]);
            // Mantém o fuso de Brasília também no MySQL (NOW(), CURDATE())
            $pdo->exec("SET time_zone = '-03:00'");
        } catch (PDOException $erro) {
            http_response_code(500);
            if (APP_DEBUG) {
                exit('Erro ao conectar ao banco de dados: ' . htmlspecialchars($erro->getMessage()));
            }
            exit('Não foi possível conectar ao banco de dados. Tente novamente mais tarde.');
        }
    }

    return $pdo;
}
