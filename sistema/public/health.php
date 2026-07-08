<?php
// Health check — não expõe credenciais nem stack trace.
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

$saude = [
    'app'       => 'roh-e-sih',
    'ambiente'  => APP_AMBIENTE,
    'status'    => 'ok',
    'banco'     => 'ok',
    'data_hora' => date('c'),
];

try {
    require_once dirname(__DIR__) . '/config/db.php';
    // Se o banco não estiver instalado ainda, mesmo assim a conexão deve responder.
    db()->query('SELECT 1');
} catch (Throwable $e) {
    http_response_code(503);
    $saude['status'] = 'degradado';
    $saude['banco']  = 'falha';
    echo json_encode($saude, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($saude, JSON_UNESCAPED_UNICODE);
