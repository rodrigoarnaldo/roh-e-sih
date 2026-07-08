<?php
// ============================================================
// Bootstrap das APIs — Roh & Sih
// Envelope JSON oficial, sessão, helpers de entrada e validação.
// Incluir no topo de todo endpoint em api/.
// ============================================================

require_once dirname(__DIR__) . '/config/db.php';

// Sessão para autenticação do painel
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ------------------------------------------------------------
// Respostas no padrão oficial da biblioteca
// ------------------------------------------------------------
function responder($success, $message, $data = null, $meta = null, $errors = [], $http = 200) {
    http_response_code($http);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
        'meta'    => $meta,
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sucesso($data = null, $message = 'Operação realizada com sucesso.', $meta = null) {
    responder(true, $message, $data, $meta, [], 200);
}

function erro($message, $errors = [], $http = 400) {
    responder(false, $message, null, null, $errors, $http);
}

function erro_validacao($errors, $message = 'Verifique os campos destacados.') {
    responder(false, $message, null, null, $errors, 422);
}

function campo_erro($field, $code, $message) {
    return ['field' => $field, 'code' => $code, 'message' => $message];
}

// ------------------------------------------------------------
// Entrada
// ------------------------------------------------------------
function metodo() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Corpo JSON da requisição como array associativo.
 */
function corpo_json() {
    static $dados = null;
    if ($dados !== null) {
        return $dados;
    }
    $raw = file_get_contents('php://input');
    $dados = json_decode($raw, true);
    if (!is_array($dados)) {
        $dados = [];
    }
    return $dados;
}

/**
 * Lê um valor do corpo JSON com trim e fallback.
 */
function in($chave, $default = null) {
    $dados = corpo_json();
    if (!array_key_exists($chave, $dados)) {
        return $default;
    }
    $v = $dados[$chave];
    if (is_string($v)) {
        $v = trim($v);
        if ($v === '') {
            return $default;
        }
    }
    return $v;
}

function query($chave, $default = null) {
    if (!isset($_GET[$chave])) {
        return $default;
    }
    $v = trim((string) $_GET[$chave]);
    return $v === '' ? $default : $v;
}

// ------------------------------------------------------------
// Validação
// ------------------------------------------------------------

/**
 * Confere se um valor está dentro de um conjunto permitido (ENUM).
 * Retorna o valor se válido; null caso vazio; lança erro de validação se inválido.
 */
function validar_enum($valor, array $permitidos, $campo, $obrigatorio = false) {
    if ($valor === null || $valor === '') {
        if ($obrigatorio) {
            erro_validacao([campo_erro($campo, 'OBRIGATORIO', 'Campo obrigatório.')]);
        }
        return null;
    }
    if (!in_array($valor, $permitidos, true)) {
        erro_validacao([campo_erro($campo, 'VALOR_INVALIDO', 'Valor não permitido para ' . $campo . '.')]);
    }
    return $valor;
}

/** Mantém só dígitos (whatsapp, cpf). */
function so_digitos($v) {
    return $v === null ? null : preg_replace('/\D+/', '', (string) $v);
}

/** Valida data no formato YYYY-MM-DD (ou null). */
function validar_data($valor, $campo) {
    if ($valor === null || $valor === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $valor);
    if (!$d || $d->format('Y-m-d') !== $valor) {
        erro_validacao([campo_erro($campo, 'DATA_INVALIDA', 'Data inválida (use AAAA-MM-DD).')]);
    }
    return $valor;
}

// ------------------------------------------------------------
// Autenticação / sessão
// ------------------------------------------------------------
function usuario_logado() {
    return $_SESSION['usuario'] ?? null;
}

/** Bloqueia o endpoint se não houver sessão válida. */
function exigir_login() {
    if (!usuario_logado()) {
        erro('Sessão expirada ou não autenticada.', [campo_erro(null, 'NAO_AUTENTICADO', 'Faça login para continuar.')], 401);
    }
}

// ------------------------------------------------------------
// Auditoria
// ------------------------------------------------------------
function registrar_log($acao, $entidade_tipo, $entidade_id = null, $contexto = null) {
    try {
        $u = usuario_logado();
        $stmt = db()->prepare(
            'INSERT INTO logs_auditoria (usuario_id, acao, entidade_tipo, entidade_id, contexto)
             VALUES (:uid, :acao, :et, :eid, :ctx)'
        );
        $stmt->execute([
            ':uid'  => $u['id'] ?? null,
            ':acao' => $acao,
            ':et'   => $entidade_tipo,
            ':eid'  => $entidade_id,
            ':ctx'  => $contexto !== null ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        // Log de auditoria nunca deve quebrar a operação principal.
    }
}
