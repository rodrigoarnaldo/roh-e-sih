<?php
// ============================================================
// Conexão PDO (procedural) — Roh & Sih
// ============================================================

require_once __DIR__ . '/config.php';

/**
 * Retorna uma conexão PDO única (singleton simples via static).
 */
function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NOME . ';charset=' . DB_CHARSET;
    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, $opcoes);
    } catch (PDOException $e) {
        // Não vazar credenciais/detalhes em produção.
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        $msg = APP_DEBUG ? $e->getMessage() : 'Falha ao conectar ao banco de dados.';
        echo json_encode([
            'success' => false,
            'message' => 'Erro de conexão com o banco.',
            'data'    => null,
            'meta'    => null,
            'errors'  => [['field' => null, 'code' => 'DB_CONEXAO', 'message' => $msg]],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $pdo;
}
