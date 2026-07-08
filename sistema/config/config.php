<?php
// ============================================================
// Configuração central — Roh & Sih
// Lê credenciais do ambiente (getenv). NÃO versionar credenciais reais.
// Em dev, ajuste os fallbacks abaixo. Em produção, use variáveis de ambiente.
// ============================================================

if (!defined('APP_INICIADO')) {
    define('APP_INICIADO', true);
}

// Carrega um arquivo .env (se existir), sem sobrescrever variáveis já presentes
// no ambiente. Torna a config robusta tanto para variáveis de ambiente do
// EasyPanel quanto para a opção "Create env file".
(function () {
    $candidatos = [dirname(__DIR__) . '/.env', __DIR__ . '/.env'];
    foreach ($candidatos as $arquivo) {
        if (!is_readable($arquivo)) {
            continue;
        }
        foreach (file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#') {
                continue;
            }
            $pos = strpos($linha, '=');
            if ($pos === false) {
                continue;
            }
            $chave = trim(substr($linha, 0, $pos));
            $valor = preg_replace('/\s+#.*$/', '', trim(substr($linha, $pos + 1)));
            $valor = trim($valor, "\"'");
            if ($chave !== '' && getenv($chave) === false) {
                putenv("$chave=$valor");
            }
        }
        break;
    }
})();

// --- Banco de dados ---
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NOME', getenv('DB_NOME') ?: 'roh_e_sih');
define('DB_USUARIO', getenv('DB_USUARIO') ?: 'root');
define('DB_SENHA', getenv('DB_SENHA') ?: '');
define('DB_CHARSET', 'utf8mb4');

// --- Aplicação ---
define('APP_NOME', 'Roh & Sih — CRM & Secretaria');
define('APP_AMBIENTE', getenv('APP_AMBIENTE') ?: 'desenvolvimento'); // desenvolvimento | producao
define('APP_DEBUG', APP_AMBIENTE !== 'producao');

// Diretório de armazenamento (vídeos de prova, uploads)
define('STORAGE_DIR', dirname(__DIR__) . '/storage');

// Fuso horário oficial da escola
date_default_timezone_set('America/Sao_Paulo');
