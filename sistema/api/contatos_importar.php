<?php
// ============================================================
// Importação em lote de CONTATOS a partir de CSV.
// O tipo de cada contato é lido de uma coluna do arquivo (normalizado);
// linhas sem tipo reconhecido usam o "tipo_padrao".
// POST JSON:
//   {
//     "registros": [ {nome, sobrenome, whatsapp, cidade, uf, cpf, origem, tipo}, ... ],
//     "tipo_padrao": "nao_aluno" | "aluno" | "ex_aluno" | "nao_contatar",   (opcional)
//     "origem_padrao": "importação planilha",                                 (opcional)
//     "status_nao_aluno_padrao": "conhece_quer" | null                        (opcional; só p/ nao_aluno)
//   }
// Regras: nome e whatsapp obrigatórios; duplicados (mesmo whatsapp) ignorados.
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

if (metodo() !== 'POST') {
    erro('Método não permitido.', [], 405);
}

const ENUM_TIPO_CONTATO   = ['nao_aluno', 'aluno', 'ex_aluno', 'nao_contatar'];
const ENUM_ST_NAO_ALUNO   = ['nao_conhece', 'conhece_nao_quer', 'conhece_nao_quer_agora', 'conhece_quer'];
const MAX_REGISTROS = 5000;

/**
 * Interpreta o texto da coluna "tipo" e devolve um valor do ENUM (ou null).
 * Aceita variações: "Aluno", "ex-aluno", "Ex aluno", "Não aluno", "não contatar", etc.
 */
function normalizar_tipo($valor) {
    if ($valor === null) { return null; }
    $s = mb_strtolower(trim((string) $valor));
    if ($s === '') { return null; }
    // remove acentos e reduz a letras + espaço
    $s = strtr($s, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e',
        'í'=>'i','ì'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ù'=>'u','ç'=>'c',
    ]);
    $s = trim(preg_replace('/[^a-z]+/', ' ', $s));

    // valores já no formato do ENUM (underscore virou espaço acima)
    if (in_array(str_replace(' ', '_', $s), ENUM_TIPO_CONTATO, true)) {
        return str_replace(' ', '_', $s);
    }
    // ordem importa: "ex aluno" e "nao aluno" contêm "aluno"
    if (strpos($s, 'ex') === 0 || strpos($s, 'ex aluno') !== false) { return 'ex_aluno'; }
    if (strpos($s, 'nao contatar') !== false || strpos($s, 'bloq') !== false || strpos($s, 'nao contat') !== false) { return 'nao_contatar'; }
    if (strpos($s, 'nao aluno') !== false || strpos($s, 'lead') !== false || strpos($s, 'prospect') !== false) { return 'nao_aluno'; }
    if (strpos($s, 'aluno') !== false || strpos($s, 'ativo') !== false || strpos($s, 'matricul') !== false) { return 'aluno'; }
    return null;
}

function remover_acentos($s) {
    return strtr($s, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e',
        'í'=>'i','ì'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ú'=>'u','ù'=>'u','ç'=>'c',
    ]);
}

/** com par / sem par */
function normalizar_par($v) {
    if ($v === null) { return null; }
    $s = remover_acentos(mb_strtolower(trim((string) $v)));
    if ($s === '') { return null; }
    if (in_array(str_replace(' ', '_', $s), ['com_par', 'sem_par'], true)) { return str_replace(' ', '_', $s); }
    if (strpos($s, 'sem') !== false || strpos($s, 'solo') !== false || strpos($s, 'sozinh') !== false || $s === 'nao') { return 'sem_par'; }
    if (strpos($s, 'com') !== false || strpos($s, 'dupla') !== false || strpos($s, 'casal') !== false || $s === 'sim') { return 'com_par'; }
    return null;
}

/** lider / seguidora */
function normalizar_papel($v) {
    if ($v === null) { return null; }
    $s = remover_acentos(mb_strtolower(trim((string) $v)));
    if ($s === '') { return null; }
    if (strpos($s, 'lid') !== false || strpos($s, 'cond') !== false || strpos($s, 'homem') !== false || $s === 'l') { return 'lider'; }
    if (strpos($s, 'seg') !== false || strpos($s, 'dama') !== false || strpos($s, 'follow') !== false || strpos($s, 'mulher') !== false || $s === 's') { return 'seguidora'; }
    return null;
}

/** vaneira, forro, samba, sertanejo (aceita vários separados por , ; / | ou espaço) */
function normalizar_estilos($v) {
    if ($v === null) { return []; }
    $out = [];
    foreach (preg_split('/[\s,;\/|]+/', remover_acentos(mb_strtolower((string) $v))) as $t) {
        $t = trim($t);
        if ($t === '') { continue; }
        if (strpos($t, 'van') !== false)                                   { $out[] = 'vaneira'; }
        elseif (strpos($t, 'forr') !== false)                              { $out[] = 'forro'; }
        elseif (strpos($t, 'samba') !== false)                             { $out[] = 'samba'; }
        elseif (strpos($t, 'sertan') !== false)                            { $out[] = 'sertanejo'; }
    }
    return array_values(array_unique($out));
}

/** segunda, terca, quarta, quinta (aceita vários) */
function normalizar_dias($v) {
    if ($v === null) { return []; }
    $out = [];
    foreach (preg_split('/[\s,;\/|]+/', remover_acentos(mb_strtolower((string) $v))) as $t) {
        $t = trim($t);
        if ($t === '') { continue; }
        if (strpos($t, 'seg') === 0)      { $out[] = 'segunda'; }
        elseif (strpos($t, 'ter') === 0)  { $out[] = 'terca'; }
        elseif (strpos($t, 'qua') === 0)  { $out[] = 'quarta'; }
        elseif (strpos($t, 'qui') === 0)  { $out[] = 'quinta'; }
    }
    return array_values(array_unique($out));
}

/** Aceita AAAA-MM-DD ou DD/MM/AAAA (e DD-MM-AAAA, ano com 2 dígitos). Inválida => null. */
function parse_data_br($v) {
    if ($v === null) { return null; }
    $s = trim((string) $v);
    if ($s === '') { return null; }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if ($d && $d->format('Y-m-d') === $s) { return $s; }
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $s, $m)) {
        $yy = strlen($m[3]) === 2 ? ('20' . $m[3]) : $m[3];
        $iso = sprintf('%04d-%02d-%02d', (int) $yy, (int) $m[2], (int) $m[1]);
        $dt = DateTime::createFromFormat('Y-m-d', $iso);
        if ($dt && $dt->format('Y-m-d') === $iso) { return $iso; }
    }
    return null;
}

$registros = corpo_json()['registros'] ?? null;
if (!is_array($registros) || count($registros) === 0) {
    erro_validacao([campo_erro('registros', 'VAZIO', 'Nenhuma linha para importar.')]);
}
if (count($registros) > MAX_REGISTROS) {
    erro('Muitas linhas de uma vez. Divida em partes de até ' . MAX_REGISTROS . '.', [], 413);
}

$tipo_padrao = in('tipo_padrao');
if (!in_array($tipo_padrao, ENUM_TIPO_CONTATO, true)) { $tipo_padrao = 'nao_aluno'; }

$origem_padrao = in('origem_padrao');

$status_na_padrao = in('status_nao_aluno_padrao');
if (!in_array($status_na_padrao, ENUM_ST_NAO_ALUNO, true)) { $status_na_padrao = null; }

$pdo = db();
$u = usuario_logado();

$verificaDup = $pdo->prepare('SELECT id FROM contatos WHERE whatsapp = :w LIMIT 1');
$insere = $pdo->prepare(
    'INSERT INTO contatos
        (nome, sobrenome, whatsapp, cidade, uf, cpf, origem,
         par_situacao, papel, data_primeiro_contato,
         tipo_contato, status_nao_aluno, criado_por)
     VALUES
        (:nome, :sobrenome, :whatsapp, :cidade, :uf, :cpf, :origem,
         :par, :papel, :data_pc,
         :tipo, :status_na, :criado_por)'
);
$insEstilo = $pdo->prepare('INSERT IGNORE INTO contato_estilos (contato_id, estilo) VALUES (:c, :e)');
$insDia    = $pdo->prepare('INSERT IGNORE INTO contato_disponibilidade (contato_id, dia) VALUES (:c, :d)');

$inseridos = 0;
$ignorados = 0;
$erros = [];
$porTipo = ['nao_aluno' => 0, 'aluno' => 0, 'ex_aluno' => 0, 'nao_contatar' => 0];
$vistosNoArquivo = [];

foreach ($registros as $i => $r) {
    $linha = $i + 1;
    if (!is_array($r)) { $erros[] = ['linha' => $linha, 'motivo' => 'Linha inválida.']; continue; }

    $nome     = isset($r['nome']) ? trim((string) $r['nome']) : '';
    $whatsapp = isset($r['whatsapp']) ? preg_replace('/\D+/', '', (string) $r['whatsapp']) : '';

    if ($nome === '' && $whatsapp === '') { continue; } // linha vazia
    if ($nome === '') { $erros[] = ['linha' => $linha, 'motivo' => 'Nome vazio.']; continue; }
    if (strlen($whatsapp) < 10) { $erros[] = ['linha' => $linha, 'motivo' => 'WhatsApp ausente ou incompleto.']; continue; }

    if (isset($vistosNoArquivo[$whatsapp])) { $ignorados++; continue; }
    $vistosNoArquivo[$whatsapp] = true;

    $verificaDup->execute([':w' => $whatsapp]);
    if ($verificaDup->fetch()) { $ignorados++; continue; }

    // Tipo: da coluna (normalizado) ou padrão
    $tipo = normalizar_tipo($r['tipo'] ?? null) ?? $tipo_padrao;
    // status_nao_aluno só faz sentido para nao_aluno
    $status_na = ($tipo === 'nao_aluno') ? $status_na_padrao : null;

    $cpf = isset($r['cpf']) ? preg_replace('/\D+/', '', (string) $r['cpf']) : '';
    $cpf = strlen($cpf) === 11 ? $cpf : null;

    $uf = isset($r['uf']) ? strtoupper(substr(trim((string) $r['uf']), 0, 2)) : null;
    $uf = ($uf === '') ? null : $uf;

    $origemLinha = isset($r['origem']) ? trim((string) $r['origem']) : '';
    $origem = $origemLinha !== '' ? $origemLinha : $origem_padrao;

    // Campos opcionais adicionais
    $par     = normalizar_par($r['par'] ?? null);
    $papel   = normalizar_papel($r['papel'] ?? null);
    $dataPc  = parse_data_br($r['data_contato'] ?? null);
    $estilos = normalizar_estilos($r['estilos'] ?? null);
    $dias    = normalizar_dias($r['disponibilidade'] ?? null);

    try {
        $insere->execute([
            ':nome'       => $nome,
            ':sobrenome'  => isset($r['sobrenome']) && trim((string) $r['sobrenome']) !== '' ? trim((string) $r['sobrenome']) : null,
            ':whatsapp'   => $whatsapp,
            ':cidade'     => isset($r['cidade']) && trim((string) $r['cidade']) !== '' ? trim((string) $r['cidade']) : null,
            ':uf'         => $uf,
            ':cpf'        => $cpf,
            ':origem'     => $origem,
            ':par'        => $par,
            ':papel'      => $papel,
            ':data_pc'    => $dataPc,
            ':tipo'       => $tipo,
            ':status_na'  => $status_na,
            ':criado_por' => $u['id'] ?? null,
        ]);
        $cid = (int) $pdo->lastInsertId();
        foreach ($estilos as $e) { $insEstilo->execute([':c' => $cid, ':e' => $e]); }
        foreach ($dias as $dia)  { $insDia->execute([':c' => $cid, ':d' => $dia]); }
        $inseridos++;
        $porTipo[$tipo]++;
    } catch (Throwable $e) {
        $erros[] = ['linha' => $linha, 'motivo' => APP_DEBUG ? $e->getMessage() : 'Falha ao inserir.'];
    }
}

registrar_log('importar', 'contato', null, [
    'inseridos' => $inseridos, 'ignorados' => $ignorados, 'erros' => count($erros), 'por_tipo' => $porTipo,
]);

sucesso([
    'inseridos'            => $inseridos,
    'ignorados_duplicados' => $ignorados,
    'erros'                => $erros,
    'por_tipo'            => $porTipo,
    'total_recebido'       => count($registros),
], "Importação concluída: $inseridos inserido(s), $ignorados ignorado(s), " . count($erros) . ' com erro.');
