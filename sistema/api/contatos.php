<?php
// ============================================================
// Contatos — núcleo do CRM. CRUD completo + follow-up.
// Todas as regras críticas validadas aqui no backend.
//   GET  ?acao=listar    (filtros: q, tipo_contato, pagina)
//   GET  ?acao=obter&id=
//   GET  ?acao=followup   -> contatos com próximo contato vencido/hoje
//   GET  ?acao=resumo     -> contadores para o dashboard
//   POST ?acao=criar
//   POST ?acao=atualizar&id=
//   POST ?acao=excluir&id=
// ============================================================
require_once __DIR__ . '/_bootstrap.php';
exigir_login();

// Conjuntos permitidos (espelham os ENUMs do schema)
const ENUM_PAR        = ['com_par', 'sem_par'];
const ENUM_PAPEL      = ['lider', 'seguidora'];
const ENUM_TIPO       = ['nao_aluno', 'aluno', 'ex_aluno', 'nao_contatar'];
const ENUM_PLANO      = ['mensalista', 'trimestral', 'anual'];
const ENUM_ST_ALUNO   = ['novo', 'fidelizado'];
const ENUM_ST_NAO     = ['nao_conhece', 'conhece_nao_quer', 'conhece_nao_quer_agora', 'conhece_quer'];
const ENUM_ST_EX      = ['quer_voltar', 'nao_quer_voltar', 'quer_voltar_nao_agora'];
const ENUM_ST_NC      = ['bloqueou', 'mal_educado', 'concorrencia', 'pediu_para_sair'];
const ENUM_ESTILOS    = ['vaneira', 'forro', 'samba', 'sertanejo'];
const ENUM_DIAS       = ['segunda', 'terca', 'quarta', 'quinta'];

$acao = query('acao', 'listar');

switch ($acao) {
    case 'listar':   listar(); break;
    case 'obter':    obter(); break;
    case 'followup': followup(); break;
    case 'resumo':   resumo(); break;
    case 'criar':    salvar(null); break;
    case 'atualizar': salvar((int) query('id')); break;
    case 'excluir':  excluir(); break;
    default:         erro('Ação inválida.', [], 400);
}

// ------------------------------------------------------------
function listar() {
    $q    = query('q');
    $tipo = query('tipo_contato');
    $pagina = max(1, (int) query('pagina', 1));
    $porPagina = 20;
    $offset = ($pagina - 1) * $porPagina;

    $where = [];
    $bind  = [];
    if ($q !== null) {
        $where[] = '(c.nome LIKE :q OR c.sobrenome LIKE :q OR c.whatsapp LIKE :q OR c.cidade LIKE :q)';
        $bind[':q'] = '%' . $q . '%';
    }
    if ($tipo !== null && in_array($tipo, ENUM_TIPO, true)) {
        $where[] = 'c.tipo_contato = :tipo';
        $bind[':tipo'] = $tipo;
    }
    $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $total = db()->prepare("SELECT COUNT(*) FROM contatos c $sqlWhere");
    $total->execute($bind);
    $totalReg = (int) $total->fetchColumn();

    $sql = "SELECT c.id, c.nome, c.sobrenome, c.whatsapp, c.cidade, c.uf,
                   c.tipo_contato, c.papel, c.plano, c.status_aluno,
                   c.data_proximo_contato, c.data_ultimo_contato
            FROM contatos c
            $sqlWhere
            ORDER BY c.nome ASC
            LIMIT $porPagina OFFSET $offset";
    $stmt = db()->prepare($sql);
    $stmt->execute($bind);
    $itens = $stmt->fetchAll();

    sucesso($itens, 'Lista de contatos.', [
        'pagina'      => $pagina,
        'por_pagina'  => $porPagina,
        'total'       => $totalReg,
        'total_paginas' => (int) ceil($totalReg / $porPagina),
    ]);
}

// ------------------------------------------------------------
function obter() {
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }

    $stmt = db()->prepare('SELECT * FROM contatos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $c = $stmt->fetch();
    if (!$c) { erro('Contato não encontrado.', [], 404); }

    $est = db()->prepare('SELECT estilo FROM contato_estilos WHERE contato_id = :id');
    $est->execute([':id' => $id]);
    $c['estilos'] = array_column($est->fetchAll(), 'estilo');

    $dis = db()->prepare('SELECT dia FROM contato_disponibilidade WHERE contato_id = :id');
    $dis->execute([':id' => $id]);
    $c['disponibilidade'] = array_column($dis->fetchAll(), 'dia');

    sucesso($c);
}

// ------------------------------------------------------------
function followup() {
    $stmt = db()->query(
        "SELECT id, nome, sobrenome, whatsapp, tipo_contato, data_proximo_contato
         FROM contatos
         WHERE data_proximo_contato IS NOT NULL
           AND data_proximo_contato <= CURDATE()
           AND tipo_contato <> 'nao_contatar'
         ORDER BY data_proximo_contato ASC"
    );
    sucesso($stmt->fetchAll(), 'Contatos para follow-up.');
}

// ------------------------------------------------------------
function resumo() {
    $porTipo = db()->query(
        'SELECT tipo_contato, COUNT(*) AS total FROM contatos GROUP BY tipo_contato'
    )->fetchAll();
    $mapa = ['nao_aluno' => 0, 'aluno' => 0, 'ex_aluno' => 0, 'nao_contatar' => 0];
    foreach ($porTipo as $r) { $mapa[$r['tipo_contato']] = (int) $r['total']; }

    $followup = (int) db()->query(
        "SELECT COUNT(*) FROM contatos
         WHERE data_proximo_contato IS NOT NULL AND data_proximo_contato <= CURDATE()
           AND tipo_contato <> 'nao_contatar'"
    )->fetchColumn();

    sucesso([
        'por_tipo'   => $mapa,
        'total'      => array_sum($mapa),
        'followup'   => $followup,
    ]);
}

// ------------------------------------------------------------
function salvar($id) {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $editar = $id !== null;
    if ($editar && $id <= 0) { erro('ID inválido.', [], 400); }

    // --- Campos base ---
    $nome     = in('nome');
    $whatsapp = so_digitos(in('whatsapp'));
    $tipo     = validar_enum(in('tipo_contato'), ENUM_TIPO, 'tipo_contato', true);

    $errs = [];
    if (!$nome)           { $errs[] = campo_erro('nome', 'OBRIGATORIO', 'Informe o nome.'); }
    if (!$whatsapp)       { $errs[] = campo_erro('whatsapp', 'OBRIGATORIO', 'Informe o WhatsApp.'); }
    elseif (strlen($whatsapp) < 10) { $errs[] = campo_erro('whatsapp', 'WHATSAPP_INVALIDO', 'WhatsApp incompleto.'); }
    if ($errs) { erro_validacao($errs); }

    $cpf = so_digitos(in('cpf'));
    if ($cpf !== null && strlen($cpf) !== 11) {
        erro_validacao([campo_erro('cpf', 'CPF_INVALIDO', 'CPF deve ter 11 dígitos.')]);
    }

    $uf = in('uf');
    if ($uf !== null) { $uf = strtoupper(substr($uf, 0, 2)); }

    // --- Status por tipo: só aceita o status coerente com o tipo_contato ---
    $status_nao_aluno = $status_ex_aluno = $status_nao_contatar = null;
    $plano = $status_aluno = $data_matricula = null;

    if ($tipo === 'nao_aluno') {
        $status_nao_aluno = validar_enum(in('status_nao_aluno'), ENUM_ST_NAO, 'status_nao_aluno');
    } elseif ($tipo === 'ex_aluno') {
        $status_ex_aluno = validar_enum(in('status_ex_aluno'), ENUM_ST_EX, 'status_ex_aluno');
    } elseif ($tipo === 'nao_contatar') {
        $status_nao_contatar = validar_enum(in('status_nao_contatar'), ENUM_ST_NC, 'status_nao_contatar');
    } elseif ($tipo === 'aluno') {
        $plano        = validar_enum(in('plano'), ENUM_PLANO, 'plano');
        $status_aluno = validar_enum(in('status_aluno'), ENUM_ST_ALUNO, 'status_aluno');
        $data_matricula = validar_data(in('data_matricula'), 'data_matricula');
    }

    $par_situacao = validar_enum(in('par_situacao'), ENUM_PAR, 'par_situacao');
    $papel        = validar_enum(in('papel'), ENUM_PAPEL, 'papel');

    // Vínculo com par (outro cadastro)
    $par_contato_id = in('par_contato_id');
    $par_contato_id = ($par_contato_id !== null && (int) $par_contato_id > 0) ? (int) $par_contato_id : null;
    if ($par_contato_id !== null) {
        if ($editar && $par_contato_id === $id) {
            erro_validacao([campo_erro('par_contato_id', 'PAR_INVALIDO', 'O par não pode ser o próprio contato.')]);
        }
        $existe = db()->prepare('SELECT id FROM contatos WHERE id = :id');
        $existe->execute([':id' => $par_contato_id]);
        if (!$existe->fetch()) {
            erro_validacao([campo_erro('par_contato_id', 'PAR_INEXISTENTE', 'Contato do par não encontrado.')]);
        }
    }

    // Datas de relacionamento
    $campos = [
        'nome'                  => $nome,
        'sobrenome'             => in('sobrenome'),
        'whatsapp'              => $whatsapp,
        'cidade'                => in('cidade'),
        'uf'                    => $uf,
        'cpf'                   => $cpf,
        'data_nascimento'       => validar_data(in('data_nascimento'), 'data_nascimento'),
        'par_situacao'          => $par_situacao,
        'par_contato_id'        => $par_contato_id,
        'papel'                 => $papel,
        'origem'                => in('origem'),
        'tipo_contato'          => $tipo,
        'plano'                 => $plano,
        'status_aluno'          => $status_aluno,
        'status_nao_aluno'      => $status_nao_aluno,
        'status_ex_aluno'       => $status_ex_aluno,
        'status_nao_contatar'   => $status_nao_contatar,
        'data_matricula'        => $data_matricula,
        'data_primeiro_contato' => validar_data(in('data_primeiro_contato'), 'data_primeiro_contato'),
        'data_ultimo_contato'   => validar_data(in('data_ultimo_contato'), 'data_ultimo_contato'),
        'data_proximo_contato'  => validar_data(in('data_proximo_contato'), 'data_proximo_contato'),
        'data_pausa'            => validar_data(in('data_pausa'), 'data_pausa'),
        'observacoes'           => in('observacoes'),
    ];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($editar) {
            $sets = [];
            foreach ($campos as $k => $v) { $sets[] = "$k = :$k"; }
            $sql = 'UPDATE contatos SET ' . implode(', ', $sets) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            foreach ($campos as $k => $v) { $stmt->bindValue(":$k", $v); }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() === 0) {
                $chk = $pdo->prepare('SELECT id FROM contatos WHERE id = :id');
                $chk->execute([':id' => $id]);
                if (!$chk->fetch()) { $pdo->rollBack(); erro('Contato não encontrado.', [], 404); }
            }
            $contatoId = $id;
        } else {
            $u = usuario_logado();
            $campos['criado_por'] = $u['id'] ?? null;
            $cols = implode(', ', array_keys($campos));
            $ph   = ':' . implode(', :', array_keys($campos));
            $stmt = $pdo->prepare("INSERT INTO contatos ($cols) VALUES ($ph)");
            foreach ($campos as $k => $v) { $stmt->bindValue(":$k", $v); }
            $stmt->execute();
            $contatoId = (int) $pdo->lastInsertId();
        }

        // Estilos (N:N) — regravar
        salvar_multi($pdo, 'contato_estilos', 'estilo', $contatoId, in('estilos'), ENUM_ESTILOS);
        // Disponibilidade (N:N)
        salvar_multi($pdo, 'contato_disponibilidade', 'dia', $contatoId, in('disponibilidade'), ENUM_DIAS);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        erro('Não foi possível salvar o contato.', [campo_erro(null, 'ERRO_SALVAR', APP_DEBUG ? $e->getMessage() : 'Erro interno.')], 500);
    }

    registrar_log($editar ? 'atualizar' : 'criar', 'contato', $contatoId);
    sucesso(['id' => $contatoId], $editar ? 'Contato atualizado.' : 'Contato cadastrado.');
}

/** Regrava uma tabela N:N (estilos/disponibilidade) validando o conjunto. */
function salvar_multi($pdo, $tabela, $coluna, $contatoId, $valores, array $permitidos) {
    if (!is_array($valores)) { $valores = []; }
    $limpos = [];
    foreach ($valores as $v) {
        if (in_array($v, $permitidos, true) && !in_array($v, $limpos, true)) {
            $limpos[] = $v;
        }
    }
    $pdo->prepare("DELETE FROM $tabela WHERE contato_id = :id")->execute([':id' => $contatoId]);
    if ($limpos) {
        $ins = $pdo->prepare("INSERT INTO $tabela (contato_id, $coluna) VALUES (:id, :v)");
        foreach ($limpos as $v) { $ins->execute([':id' => $contatoId, ':v' => $v]); }
    }
}

// ------------------------------------------------------------
function excluir() {
    if (metodo() !== 'POST') { erro('Método não permitido.', [], 405); }
    $id = (int) query('id');
    if ($id <= 0) { erro('ID inválido.', [], 400); }

    $stmt = db()->prepare('DELETE FROM contatos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) { erro('Contato não encontrado.', [], 404); }

    registrar_log('excluir', 'contato', $id);
    sucesso(null, 'Contato excluído.');
}
