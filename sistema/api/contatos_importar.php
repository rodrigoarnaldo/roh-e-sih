<?php
// ============================================================
// Importação em lote de contatos (não alunos) a partir de CSV.
// O frontend lê o CSV, mapeia as colunas e envia as linhas já organizadas.
// POST JSON:
//   {
//     "registros": [ {nome, sobrenome, whatsapp, cidade, uf, cpf, origem}, ... ],
//     "origem_padrao": "importação planilha",   (opcional)
//     "status_padrao": "conhece_quer" | null      (opcional; status de não-aluno)
//   }
// Regras: nome e whatsapp obrigatórios; duplicados (mesmo whatsapp) são ignorados;
//         tudo entra como tipo_contato = 'nao_aluno'.
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

if (metodo() !== 'POST') {
    erro('Método não permitido.', [], 405);
}

const ENUM_ST_NAO_ALUNO = ['nao_conhece', 'conhece_nao_quer', 'conhece_nao_quer_agora', 'conhece_quer'];
const MAX_REGISTROS = 5000;

$registros = corpo_json()['registros'] ?? null;
if (!is_array($registros) || count($registros) === 0) {
    erro_validacao([campo_erro('registros', 'VAZIO', 'Nenhuma linha para importar.')]);
}
if (count($registros) > MAX_REGISTROS) {
    erro('Muitas linhas de uma vez. Divida em partes de até ' . MAX_REGISTROS . '.', [], 413);
}

$origem_padrao = in('origem_padrao');
$status_padrao = in('status_padrao');
if ($status_padrao !== null && !in_array($status_padrao, ENUM_ST_NAO_ALUNO, true)) {
    $status_padrao = null;
}

$pdo = db();
$u = usuario_logado();

$verificaDup = $pdo->prepare('SELECT id FROM contatos WHERE whatsapp = :w LIMIT 1');
$insere = $pdo->prepare(
    'INSERT INTO contatos
        (nome, sobrenome, whatsapp, cidade, uf, cpf, origem,
         tipo_contato, status_nao_aluno, criado_por)
     VALUES
        (:nome, :sobrenome, :whatsapp, :cidade, :uf, :cpf, :origem,
         "nao_aluno", :status, :criado_por)'
);

$inseridos = 0;
$ignorados = 0;
$erros = [];
$vistosNoArquivo = [];

foreach ($registros as $i => $r) {
    $linha = $i + 1; // referência para o usuário (1 = primeira linha de dados)
    if (!is_array($r)) {
        $erros[] = ['linha' => $linha, 'motivo' => 'Linha inválida.'];
        continue;
    }

    $nome     = isset($r['nome']) ? trim((string) $r['nome']) : '';
    $whatsapp = isset($r['whatsapp']) ? preg_replace('/\D+/', '', (string) $r['whatsapp']) : '';

    if ($nome === '' && $whatsapp === '') {
        continue; // linha totalmente vazia: ignora em silêncio
    }
    if ($nome === '') {
        $erros[] = ['linha' => $linha, 'motivo' => 'Nome vazio.'];
        continue;
    }
    if (strlen($whatsapp) < 10) {
        $erros[] = ['linha' => $linha, 'motivo' => 'WhatsApp ausente ou incompleto.'];
        continue;
    }

    // Duplicado dentro do próprio arquivo
    if (isset($vistosNoArquivo[$whatsapp])) {
        $ignorados++;
        continue;
    }
    $vistosNoArquivo[$whatsapp] = true;

    // Duplicado já existente no banco
    $verificaDup->execute([':w' => $whatsapp]);
    if ($verificaDup->fetch()) {
        $ignorados++;
        continue;
    }

    $cpf = isset($r['cpf']) ? preg_replace('/\D+/', '', (string) $r['cpf']) : '';
    $cpf = strlen($cpf) === 11 ? $cpf : null;

    $uf = isset($r['uf']) ? strtoupper(substr(trim((string) $r['uf']), 0, 2)) : null;
    $uf = ($uf === '' ) ? null : $uf;

    $origemLinha = isset($r['origem']) ? trim((string) $r['origem']) : '';
    $origem = $origemLinha !== '' ? $origemLinha : $origem_padrao;

    try {
        $insere->execute([
            ':nome'       => $nome,
            ':sobrenome'  => isset($r['sobrenome']) && trim((string) $r['sobrenome']) !== '' ? trim((string) $r['sobrenome']) : null,
            ':whatsapp'   => $whatsapp,
            ':cidade'     => isset($r['cidade']) && trim((string) $r['cidade']) !== '' ? trim((string) $r['cidade']) : null,
            ':uf'         => $uf,
            ':cpf'        => $cpf,
            ':origem'     => $origem,
            ':status'     => $status_padrao,
            ':criado_por' => $u['id'] ?? null,
        ]);
        $inseridos++;
    } catch (Throwable $e) {
        $erros[] = ['linha' => $linha, 'motivo' => APP_DEBUG ? $e->getMessage() : 'Falha ao inserir.'];
    }
}

registrar_log('importar', 'contato', null, [
    'inseridos' => $inseridos, 'ignorados' => $ignorados, 'erros' => count($erros),
]);

sucesso([
    'inseridos'            => $inseridos,
    'ignorados_duplicados' => $ignorados,
    'erros'                => $erros,
    'total_recebido'       => count($registros),
], "Importação concluída: $inseridos inserido(s), $ignorados ignorado(s), " . count($erros) . ' com erro.');
